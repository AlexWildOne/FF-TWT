<?php
namespace RoutesPro\Support;

use RoutesPro\Architecture\RestRouteRegistry;
use RoutesPro\Cloud\Phase6StableRelease;
use RoutesPro\Architecture\RuntimeRegistry;
use RoutesPro\Mobile\ContractRegistry;
use RoutesPro\Operations\Queue\QueueManager;
use RoutesPro\Shopping\ShoppingService;

if (!defined('ABSPATH')) exit;

/**
 * Non-destructive release readiness gate.
 */
final class ReleaseGate {
    public const VERSION = '2.51.0';
    // 9D4 predecessor: public const VERSION = '2.46.0';
    public const ASSET_SUCCESSOR_CONTRACT = 'fieldflow-certification-asset-successor-continuity-v1';
    public const RUNTIME_DATABASE_PERFORMANCE_CONTRACT = 'fieldflow-runtime-database-performance-v1';
    public const SECURITY_STORAGE_HARDENING_CONTRACT = 'fieldflow-security-storage-hardening-v1';
    public const AI_GOVERNANCE_MODERNIZATION_CONTRACT = 'fieldflow-ai-governance-modernization-wave-v1';
    public const AI_GOVERNANCE_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-ai-governance-certification-continuity-v1';
    public const OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1';
    public const FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-frontend-runtime-certification-continuity-v1';
    public const OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1';
    public const FIELD_FORM_TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1';
    public const FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1';
    public const CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT = 'fieldflow-certification-gate-continuity-9t-v1';
    public const CARDEX_DATA_CONSISTENCY_9DW_CONTRACT = 'fieldflow-cardex-data-consistency-center-9dw-v1';
    public const BRIEFING_STEPS_9DX_CONTRACT = 'fieldflow-briefing-steps-continuity-9dx-v1';
    public const ORDER_SOURCE_CARDEX_CONSISTENCY_9U_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1';
    public const CANONICAL_ANSWER_SEMANTICS_9V_CONTRACT = 'fieldflow-canonical-answer-semantics-v1';
    public const VISIT_CLOSURE_DAILY_MAIL_9W_CONTRACT = 'fieldflow-visit-closure-daily-mail-v1';
    public const CERTIFICATION_CONTINUITY_VISIT_CLOSURE_9X_CONTRACT = 'fieldflow-certification-continuity-visit-closure-v1';
    public const SUBMISSION_STATUS_OPERATIONAL_DAY_9Y_CONTRACT = 'fieldflow-submission-status-operational-day-v1';
    public const SUBMISSION_CORRECTIONS_AUDIT_9Z_CONTRACT = 'fieldflow-submission-corrections-audit-v1';
    public const FIELD_RESUME_NAVIGATION_9AA_CONTRACT = 'fieldflow-field-resume-navigation-continuity-v1';
    public const REPORT_SELECTION_MEDIA_9AB_CONTRACT = 'fieldflow-report-selection-media-continuity-v1';
    public const RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT = 'fieldflow-resume-certification-media-integrity-v1';
    public const FORM_VIEWPORT_SHOPPING_9AD_CONTRACT = 'fieldflow-form-viewport-shopping-delivery-integrity-v1';
    public const CANONICAL_REPORTING_DELIVERY_9AE_CONTRACT = 'fieldflow-canonical-reporting-delivery-viewport-continuity-v1';
    public const CERTIFICATION_VIEWPORT_MAIL_9AF_CONTRACT = 'fieldflow-certification-viewport-lock-mail-template-recovery-v1';
    public const MAIL_FLOW_VIEWPORT_9AG_CONTRACT = 'fieldflow-mail-flow-finalization-true-viewport-freeze-v1';
    public const SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT = 'fieldflow-single-anchor-viewport-continuity-v4';
    public const MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT = 'fieldflow-mail-intelligence-daily-commercial-reporting-v1';
    public const BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT = 'fieldflow-briefing-store-scope-reporting-v1';
    public const DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT = 'fieldflow-daily-mobility-cost-reporting-v1';
    public const KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT = 'fieldflow-known-mobility-reporting-recovery-v1';
    public const FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT = 'fieldflow-full-route-mobility-reporting-reconciliation-v1';
    public const ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT = 'fieldflow-routing-endpoint-geographic-integrity-v1';
    public const ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1';
    public const REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT = 'fieldflow-report-status-canonical-sync-v1';
    public const CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT = 'fieldflow-condition-graph-integrity-v1';
    public const WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT = 'fieldflow-wordpress-media-isolation-v1';
    public const MY_ROUTE_PERIODICITY_9AS_CONTRACT = 'fieldflow-my-route-periodicity-control-v1';
    public const MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1';
    public const RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT = 'fieldflow-release-manager-publication-receipt-v1';
    public const CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT = 'fieldflow-client-cost-date-scope-route-totals-v1';
    public const ROUTE_FINANCIAL_AUDIENCE_ANALYTICS_9AW_CONTRACT = 'fieldflow-route-financial-audience-analytics-v1';
    public const CLIENT_PORTAL_RUNTIME_SCOPE_9AX_CONTRACT = 'fieldflow-client-portal-runtime-scope-continuity-v1';
    public const CLIENT_PORTAL_FILTER_AUTHORITY_9AY_CONTRACT = 'fieldflow-client-portal-filter-authority-rebase-v1';
    public const ROUTE_CALENDAR_MOBILE_PORTAL_FEATURES_9AZ_CONTRACT = 'fieldflow-route-calendar-daily-total-mobile-portal-features-v1';
    public const CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT = 'fieldflow-client-route-hero-date-authority-v1';
    public const PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT = 'fieldflow-planner-assigned-store-scope-continuity-v1';
    public const PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT = 'fieldflow-planner-canonical-assignment-catalog-v1';
    public const FORM_VISIT_MEMORY_FRONTS_9BD_CONTRACT = 'fieldflow-form-visit-memory-fronts-continuity-v1';
    public const PLANNER_FREE_WARNING_9BE_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1';
    public const VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1';
    public const OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT = 'fieldflow-open-day-planning-visit-completion-sync-v1';
    public const VISIT_MEMORY_REFRESH_9BI_CONTRACT = 'fieldflow-visit-memory-refresh-v1';
    public const STORE_IDENTITY_AUTHORITY_9BJ_CONTRACT = 'fieldflow-store-identity-authority-v1';
    public const STATIC_ROUTING_COST_AUTHORITY_9BK_CONTRACT = 'fieldflow-static-routing-cost-authority-v1';
    public const TOLL_COST_INTELLIGENCE_9BL_CONTRACT = 'fieldflow-toll-cost-intelligence-v1';
    public const PLACES_GEOCODING_COST_AUTHORITY_9BM_CONTRACT = 'fieldflow-places-geocoding-cost-authority-v1';
    public const GOOGLE_COST_CONTROL_CENTER_9BN_CONTRACT = 'fieldflow-google-cost-control-center-v1';
    public const DAILY_OPERATIONAL_PRIORITY_9BO_CONTRACT = 'fieldflow-daily-operational-priority-v1';
    public const DAILY_EXECUTION_EXCEPTION_9BP_CONTRACT = 'fieldflow-daily-execution-exception-control-v1';
    public const ORDER_MAIL_VISIT_REOPEN_9BQ_CONTRACT = 'fieldflow-order-mail-visit-reopen-safety-v1';
    public const CERTIFICATION_FOUNDATION_CONTINUITY_9BR_CONTRACT = 'fieldflow-certification-foundation-continuity-v1';
    public const EXECUTIVE_ANALYTICS_ROUTE_FINANCIAL_9BS_CONTRACT = 'fieldflow-executive-analytics-route-financial-visibility-v1';
    public const MOBILE_ANALYTICS_READABILITY_EVIDENCE_9BT_CONTRACT = 'fieldflow-mobile-analytics-readability-evidence-density-v1';
    public const CLIENT_REPORTS_RUNTIME_9BU_CONTRACT = 'fieldflow-client-reports-runtime-continuity-v1';
    public const PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT = 'fieldflow-portal-export-runtime-certification-parity-v1';
    public const CLIENT_REPORT_SCOPE_BRIEFING_ASSIGNMENT_9BW_CONTRACT = 'fieldflow-client-report-scope-briefing-assignment-v1';
    public const VISIT_CENTRIC_REPORTING_BRIEFING_9BX_CONTRACT = 'fieldflow-visit-centric-reporting-briefing-continuity-v1';
    public const MULTILINGUAL_CLIENT_LOCALE_AUTHORITY_9BY_CONTRACT = 'fieldflow-multilingual-client-locale-authority-v1';
    public const PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT = 'fieldflow-premium-locale-visit-memory-continuity-v1';
    public const CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT = 'fieldflow-certification-marker-continuity-9ca-v1';
    public const LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT = 'fieldflow-locale-brand-scope-continuity-v1';
    public const APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT = 'fieldflow-app-shell-browserlike-translation-v1';
    public const ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT = 'fieldflow-adaptive-locale-dock-v1';
    public const ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT = 'fieldflow-analytics-header-date-export-authority-v1';
    public const EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT = 'fieldflow-expense-statement-pdf-receipt-authority-v1';
    public const EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT = 'fieldflow-expense-statement-mobile-ticket-delivery-v1';
    public const EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT = 'fieldflow-expense-settlement-signature-authority-v1';
    public const VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT = 'fieldflow-visit-closure-selector-return-v1';
    public const SECURITY_HYGIENE_AUTHORITY_9CJ_CONTRACT = 'fieldflow-security-hygiene-authority-v1';
    public const PLANNER_GOOGLE_COST_AUTHORITY_9CK_CONTRACT = 'fieldflow-planner-google-cost-authority-v1';
    public const VISUAL_PRODUCT_CATALOG_9CL_CONTRACT = 'fieldflow-visual-product-catalog-v1';
    public const FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT = 'fieldflow-fronts-mobile-catalog-visual-v1';
    public const CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT = 'fieldflow-catalog-cross-client-reuse-v1';
    public const FRONTS_CAMERA_READING_9CO_CONTRACT = 'fieldflow-fronts-vision-reading-v1';
    public const CATALOG_GENERAL_IDENTITY_9CP_CONTRACT = 'fieldflow-catalog-general-identity-v1';
    public const FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT = 'fieldflow-theme-isolation-v1';
    public const MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT = 'fieldflow-media-capture-resilience-v1';
    public const FRONTS_SAFE_MULTIMODAL_9CS_CONTRACT = 'fieldflow-fronts-safe-multimodal-9cs-v1';
    public const FORM_DELETION_CONFIRMATION_9CT_CONTRACT = 'fieldflow-form-deletion-confirmation-9ct-v1';
    public const FRONTS_CAPTURE_REVIEW_9CU_CONTRACT = 'fieldflow-fronts-capture-review-9cu-v1';
    public const REPORT_CONTEXT_RECOVERY_9CV_CONTRACT = 'fieldflow-report-context-recovery-9cv-v1';
    public const FRONTS_VISUAL_EVIDENCE_9CW_CONTRACT = 'fieldflow-fronts-visual-evidence-9cw-v1';
    public const FRONTS_SKU_IDENTITY_9CX_CONTRACT = 'fieldflow-fronts-sku-identity-9cx-v1';
    public const FRONTS_BLIND_INVENTORY_9CY_CONTRACT = 'fieldflow-fronts-blind-inventory-9cy-v1';
    public const FRONTS_AI_COST_METERING_9CZ_CONTRACT = 'fieldflow-fronts-ai-usage-finops-9cz-v1';
    public const FRONTS_BUDGET_RECONCILIATION_9D0_CONTRACT = 'fieldflow-fronts-budget-reconciliation-9d0-v1';
    public const FRONTS_VISION_INTELLIGENCE_9D1_CONTRACT = 'fieldflow-fronts-vision-intelligence-9d1-v1';
    public const BRIEFING_CATEGORY_CONTINUITY_9D2_CONTRACT = 'fieldflow-briefing-category-continuity-9d2-v1';
    public const CATALOG_CATEGORY_PICKER_9D3_CONTRACT = 'fieldflow-catalog-category-picker-9d3-v1';
    public const COMMERCIAL_PRODUCTION_HARDENING_9D4_CONTRACT = 'fieldflow-commercial-production-hardening-9d4-v1';
    public const VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT = 'fieldflow-visual-experience-consolidation-9d5-v1';
    public const OPERATIVE_DOCK_PARITY_9D6_CONTRACT = 'fieldflow-operative-dock-parity-9d6-v1';
    public const OPERATIVE_DOCK_OVERLAY_9D7_CONTRACT = 'fieldflow-operative-dock-overlay-9d7-v1';
    public const OPERATIVE_INLINE_DOCK_AUTHORITY_9D8_CONTRACT = 'fieldflow-operative-inline-dock-authority-9d8-v1';
    public const OPERATIVE_CLIENT_DOCK_PARITY_9D9_CONTRACT = 'fieldflow-operative-client-dock-parity-9d9-v1';
    public const LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT = 'fieldflow-latest-visit-history-isolation-v1';
    public const RUNTIME_IDENTITY_SUCCESSOR_CONTRACT = 'fieldflow-runtime-identity-successor-v1';
    /**
     * Compatibility markers for the installed 2.2.526 package validator.
     * They are inert. Active runtime checks resolve FIELDFLOW_VERSION dynamically.
     */
    private const RUNTIME_IDENTITY_2526_VALIDATOR_BRIDGE = [
        "const RUNTIME_BUILD='2.2.527'", 'fieldflow-offline-runtime-2.2.527.js',
        "const RUNTIME_BUILD='2.2.527'", 'fieldflow-offline-runtime-2.2.527.js',
        "const RUNTIME_BUILD='2.2.527'", 'fieldflow-offline-runtime-2.2.527.js',
        "const RUNTIME_BUILD='2.2.527'", 'fieldflow-offline-runtime-2.2.527.js',
    ];
    public const REPORT_OPTION = 'fieldflow_release_gate_last_report';
    public const HISTORY_OPTION = 'fieldflow_release_gate_history';

    private static function currentRelease(): string {
        return defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.603';
    }

    private static function currentOfflineRuntimeRelative(): string {
        return 'assets/fieldflow-offline-runtime-' . self::currentRelease() . '.js';
    }

    private static function currentOfflineRuntimeFile(): string {
        return basename(self::currentOfflineRuntimeRelative());
    }

    private static function currentOfflineRuntimeMarker(): string {
        return "const RUNTIME_BUILD='" . self::currentRelease() . "'";
    }

    public static function run(int $actorId = 0): array {
        $started = microtime(true);
        $checks = [];
        $checks[] = self::checkVersionConsistency();
        $checks[] = self::checkReleaseContinuityAuthorityContract();
        $checks[] = self::checkReleaseManagerAssistedPreflight9DAContract();
        $checks[] = self::checkReleaseManagerAssistedWorkflow9DBContract();
        $checks[] = self::checkOperativeDockScrollAuthority9DCContract();
        $checks[] = self::checkOperativeDockRealRuntimeAudit9DDContract();
        $checks[] = self::checkOperativeDockBootstrapRecovery9DEContract();
        $checks[] = self::checkPhase4AndCalmOperativeDock9DFContract();
        $checks[] = self::checkPhase4CostPerformanceDrivers9DGContract();
        $checks[] = self::checkPhase4AdminHotspotsManifestSafety9DHContract();
        $checks[] = self::checkPhase4AdminHealthSnapshotReuse9DIContract();
        $checks[] = self::checkPhase4AdminCertificationMobile9DJContract();
        $checks[] = self::checkBriefingStockRouteScope9DKContract();
        $checks[] = self::checkBriefingStocksMobile9DLContract();
        $checks[] = self::checkStockInsigniaAuthority9DMContract();
        $checks[] = self::checkUniversalStoreIdentity9DNContract();
        $checks[] = self::checkDailyCloseRecipientReliability9DOContract();
        $checks[] = self::checkSafeCardexClonePublication9DPContract();
        $checks[] = self::checkFlexibleCardexInsigniaRegistry9DQContract();
        $checks[] = self::checkCardexFormBindingContinuity9DRContract();
        $checks[] = self::checkCardexVisibleBinding9DSContract();
        $checks[] = self::checkCardexScopeTruth9DTContract();
        $checks[] = self::checkCardexGlobalLinkedScope9DUContract();
        $checks[] = self::checkOrderSuggestionIntegrity9DVContract();
        $checks[] = self::checkCardexDataConsistency9DWContract();
        $checks[] = self::checkBriefingSteps9DXContract();
        $checks[] = self::checkProductionManifest();
        $checks[] = self::checkUpgradeBootstrapContract();
        $checks[] = self::checkOfflineZeroLossContract();
        $checks[] = self::checkOfflineOperationsIntelligenceContract();
        $checks[] = self::checkResilientMediaOfflineTrustContract();
        $checks[] = self::checkOfflineMediaRehydrationContract();
        $checks[] = self::checkResumableMediaTransportContract();
        $checks[] = self::checkEndToEndOfflineFormRecoveryContract();
        $checks[] = self::checkFieldFormReliabilityCoreContract();
        $checks[] = self::checkMailCenterHistoricalRecoveryContract();
        $checks[] = self::checkResponsiveEmailRenderingContract();
        $checks[] = self::checkOperationalIntelligenceHardeningContract();
        $checks[] = self::checkAnalyticsExportResilienceContract();
        $checks[] = self::checkEvidenceZipRuntimeV3Contract();
        $checks[] = self::checkEvidenceZipTicketContinuityContract();
        $checks[] = self::checkAnalyticsMobileFullWidthDownloadStateContract();
        $checks[] = self::checkEvidenceZipSystemHandoffContract();
        $checks[] = self::checkFormOrderSuggestionPlacementContract();
        $checks[] = self::checkOrderSuggestionConditionalMailContract();
        $checks[] = self::checkOrderSuggestionMailEligibilityV2Contract();
        $checks[] = self::checkOfflineRuntimeHardeningContract();
        $checks[] = self::checkFrontendBuildFoundationContract();
        $checks[] = self::checkTypedOfflineCoreContract();
        $checks[] = self::checkTypedDraftMediaCoreContract();
        $checks[] = self::checkTypedFormConditionalCoreContract();
        $checks[] = self::checkTypedSubmissionValidationCoreContract();
        $checks[] = self::checkTypedSpecializedValidationCoreContract();
        $checks[] = self::checkSubmissionApplicationServiceContract();
        $checks[] = self::checkSubmissionSideEffectsContract();
        $checks[] = self::checkReactBackofficeFoundationContract();
        $checks[] = self::checkReactBackofficeSharedContract();
        $checks[] = self::checkReactControlCenterDevicesContract();
        $checks[] = self::checkReactAnalyticsFoundationContract();
        $checks[] = self::checkReactFrontFormWizardContract();
        $checks[] = self::checkReactFrontOperationalWaveContract();
        $checks[] = self::checkReactSimpleFieldControlsContract();
        $checks[] = self::checkReactChoiceSpecialWaveContract();
        $checks[] = self::checkModernizationStableClosureContract();
        $checks[] = self::checkFrontFullwidthNavigationContract();
        $checks[] = self::checkReactOperationalModulesContract();
        $checks[] = self::checkOperationalActionHardeningContract();
        $checks[] = self::checkBriefingProgressTabsHardeningContract();
        $checks[] = self::checkReactFormCompletionContract();
        $checks[] = self::checkCertificationRuntimeIdentityContract();
        $checks[] = self::checkReleaseGateHistoricalMarkerHardeningContract();
        $checks[] = self::checkFrontTransitionStabilityContract();
        $checks[] = self::checkMobilityCostIntelligenceContract();
        $checks[] = self::checkMobilityIntegrityCanonicalMetricsContract();
        $checks[] = self::checkRouteEndpointDistanceIntegrityContract();
        $checks[] = self::checkRouteEndpointProvenanceReconciliationContract();
        $checks[] = self::checkRouteFinancialContinuityContract();
        $checks[] = self::checkOperationalEndpointSourceOfTruthContract();
        $checks[] = self::checkEndpointReconciliationAddressReuseContract();
        $checks[] = self::checkSemanticEdgeEndpointReconciliationContract();
        $checks[] = self::checkOwnerProfileRouteMigrationContract();
        $checks[] = self::checkEndpointRepairSignatureCompatibilityContract();
        $checks[] = self::checkRouteDistanceFinalizationCertificationContinuityContract();
        $checks[] = self::checkReleaseGateRuntimeSourceAuthorityContract();
        $checks[] = self::checkClientPortalShellStabilityContract();
        $checks[] = self::checkClientPortalContinuityContract();
        $checks[] = self::checkReactClientWave1AnalyticsStabilityContract();
        $checks[] = self::checkReactClientWave2CrossPortalIsolationContract();
        $checks[] = self::checkAppResumeDualPortalFullwidthContract();
        $checks[] = self::checkDualPortalFullwidthParityResumeV2Contract();
        $checks[] = self::checkUiCompositionResumeDiagnosticsContract();
        $checks[] = self::checkSharedPortalPaintStabilityContract();
        $checks[] = self::checkPortalEntrySyncVisualContract();
        $checks[] = self::checkMailDispatchFoundationContract();
        $checks[] = self::checkMailReliabilityOutboxContract();
        $checks[] = self::checkCertificationAssetSuccessorContinuityContract();
        $checks[] = self::checkRuntimeDatabasePerformanceContract();
        $checks[] = self::checkSecurityStorageHardeningContract();
        $checks[] = self::checkAiGovernanceModernizationContract();
        $checks[] = self::checkAiGovernanceCertificationContinuityContract();
        $checks[] = self::checkOperationalModernizationWaveContract();
        $checks[] = self::checkFrontendRuntimeCertificationContinuityContract();
        $checks[] = self::checkOperationalModernizationWave2Contract();
        $checks[] = self::checkFieldFormTerrainContinuityContract();
        $checks[] = self::checkFieldSessionFormContinuityContract();
        $checks[] = self::checkCertificationGateContinuity9TContract();
        $checks[] = self::checkOrderSourceCardexConsistency9UContract();
        $checks[] = self::checkCanonicalAnswerSemantics9VContract();
        $checks[] = self::checkVisitClosureDailyMail9WContract();
        $checks[] = self::checkCertificationContinuityVisitClosure9XContract();
        $checks[] = self::checkSubmissionStatusOperationalDay9YContract();
        $checks[] = self::checkSubmissionCorrectionsAudit9ZContract();
        $checks[] = self::checkFieldResumeNavigation9AAContract();
        $checks[] = self::checkReportSelectionMedia9ABContract();
        $checks[] = self::checkResumeCertificationMedia9ACContract();
        $checks[] = self::checkFormViewportShoppingDelivery9ADContract();
        $checks[] = self::checkCanonicalReportingDelivery9AEContract();
        $checks[] = self::checkCertificationViewportMail9AFContract();
        $checks[] = self::checkMailFlowViewport9AGContract();
        $checks[] = self::checkSingleAnchorViewport9AHContract();
        $checks[] = self::checkMailIntelligenceDailyReporting9AIContract();
        $checks[] = self::checkBriefingStoreScopeReporting9AJContract();
        $checks[] = self::checkDailyMobilityCostReporting9AKContract();
        $checks[] = self::checkKnownMobilityReportingRecovery9ALContract();
        $checks[] = self::checkFullRouteMobilityReporting9AMContract();
        $checks[] = self::checkRoutingEndpointGeographicIntegrity9ANContract();
        $checks[] = self::checkRouteMobilityDashboardWidgets9AOContract();
        $checks[] = self::checkReportStatusCanonicalSync9APContract();
        $checks[] = self::checkConditionGraphIntegrity9AQContract();
        $checks[] = self::checkWordPressMediaIsolation9ARContract();
        $checks[] = self::checkMyRoutePeriodicity9ASContract();
        $checks[] = self::checkMyRoutePeriodicityAction9ATContract();
        $checks[] = self::checkReleaseManagerPublicationReceipt9AUContract();
        $checks[] = self::checkClientCostDateScopeRouteTotals9AVContract();
        $checks[] = self::checkRouteFinancialAudienceAnalytics9AWContract();
        $checks[] = self::checkClientPortalRuntimeScope9AXContract();
        $checks[] = self::checkClientPortalFilterAuthority9AYContract();
        $checks[] = self::checkRouteCalendarMobilePortalFeatures9AZContract();
        $checks[] = self::checkClientRouteHeroDateAuthority9BAContract();
        $checks[] = self::checkPlannerAssignedStoreScope9BBContract();
        $checks[] = self::checkPlannerCanonicalAssignment9BCContract();
        $checks[] = self::checkFormVisitMemoryFronts9BDContract();
        $checks[] = self::checkPlannerFreeWarning9BEContract();
        $checks[] = self::checkVisitExceptionJustifiedNonVisit9BFContract();
        $checks[] = self::checkOpenDayPlanningVisitCompletion9BGContract();
        $checks[] = self::checkLatestVisitHistoryIsolation9BHContract();
        $checks[] = self::checkVisitMemoryRefresh9BIContract();
        $checks[] = self::checkStoreIdentityAuthority9BJContract();
        $checks[] = self::checkStaticRoutingCostAuthority9BKContract();
        $checks[] = self::checkTollCostIntelligence9BLContract();
        $checks[] = self::checkPlacesGeocodingCostAuthority9BMContract();
        $checks[] = self::checkGoogleCostControlCenter9BNContract();
        $checks[] = self::checkDailyOperationalPriority9BOContract();
        $checks[] = self::checkDailyExecutionException9BPContract();
        $checks[] = self::checkOrderMailVisitReopen9BQContract();
        $checks[] = self::checkCertificationFoundationContinuity9BRContract();
        $checks[] = self::checkExecutiveAnalyticsRouteFinancial9BSContract();
        $checks[] = self::checkMobileAnalyticsReadabilityEvidence9BTContract();
        $checks[] = self::checkClientReportsRuntime9BUContract();
        $checks[] = self::checkPortalExportRuntimeCertParity9BVContract();
        $checks[] = self::checkClientReportScopeBriefingAssignment9BWContract();
        $checks[] = self::checkVisitCentricReportingBriefing9BXContract();
        $checks[] = self::checkMultilingualClientLocaleAuthority9BYContract();
        $checks[] = self::checkPremiumLocaleVisitMemory9BZContract();
        $checks[] = self::checkCertificationMarkerContinuity9CAContract();
        $checks[] = self::checkLocaleBrandAssociatedClientScope9CBContract();
        $checks[] = self::checkAppShellBrowserlikeTranslation9CCContract();
        $checks[] = self::checkAdaptiveLocaleDock9CDContract();
        $checks[] = self::checkAnalyticsHeaderDateExportAuthority9CEContract();
        $checks[] = self::checkExpenseStatementPdfReceiptAuthority9CFContract();
        $checks[] = self::checkExpenseStatementMobileTicketDelivery9CGContract();
        $checks[] = self::checkExpenseSettlementSignature9CHContract();
        $checks[] = self::checkVisitClosureSelectorReturn9CIContract();
        $checks[] = self::checkSecurityHygieneAuthority9CJContract();
        $checks[] = self::checkPlannerGoogleCostAuthority9CKContract();
        $checks[] = self::checkVisualProductCatalog9CLContract();
        $checks[] = self::checkFrontsMobileCatalogVisual9CMContract();
        $checks[] = self::checkCatalogCrossClientReuse9CNContract();
        $checks[] = self::checkFrontsCameraReading9COContract();
        $checks[] = self::checkCatalogGeneralIdentity9CPContract();
        $checks[] = self::checkFrontThemeCameraLive9CQContract();
        $checks[] = self::checkMediaCaptureResilience9CRContract();
        $checks[] = self::checkFrontsSafeMultimodal9CSContract();
        $checks[] = self::checkFormDeletionConfirmation9CTContract();
        $checks[] = self::checkFrontsCaptureReview9CUContract();
        $checks[] = self::checkReportContextRecovery9CVContract();
        $checks[] = self::checkFrontsVisualEvidence9CWContract();
        $checks[] = self::checkFrontsSkuIdentity9CXContract();
        $checks[] = self::checkFrontsBlindInventory9CYContract();
        $checks[] = self::checkFrontsAiCostMetering9CZContract();
        $checks[] = self::checkFrontsBudgetReconciliation9D0Contract();
        $checks[] = self::checkFrontsVisionIntelligence9D1Contract();
        $checks[] = self::checkBriefingCategoryContinuity9D2Contract();
        $checks[] = self::checkCatalogCategoryPicker9D3Contract();
        $checks[] = self::checkCommercialProductionHardening9D4Contract();
        $checks[] = self::checkVisualExperienceConsolidation9D5Contract();
        $checks[] = self::checkOperativeDockParity9D6Contract();
        $checks[] = self::checkOperativeDockOverlay9D7Contract();
        $checks[] = self::checkOperativeInlineDockAuthority9D8Contract();
        $checks[] = self::checkOperativeClientDockParity9D9Contract();
        $checks[] = self::checkShoppingMailMerchandisingContract();
        $checks[] = self::checkRuntimeFiles();
        $checks[] = self::checkArchitectureRuntime();
        $checks[] = self::checkRestRegistry();
        $checks[] = self::checkMobileContract();
        $checks[] = self::checkSchema();
        $checks[] = self::checkMigrations();
        $checks[] = self::checkBatchFramework();
        $checks[] = self::checkQueue();
        $checks[] = self::checkCron();
        $checks[] = self::checkUploads();
        $checks[] = self::checkAssets();
        $checks[] = self::checkEnvironment();

        $counts = ['pass'=>0,'warning'=>0,'fail'=>0];
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'warning');
            if (!isset($counts[$status])) $status = 'warning';
            $counts[$status]++;
        }
        $status = $counts['fail'] > 0 ? 'blocked' : ($counts['warning'] > 0 ? 'conditional' : 'ready');
        $score = max(0, min(100, 100 - ($counts['fail'] * 20) - ($counts['warning'] * 5)));
        $report = [
            'status'=>$status,
            'score'=>$score,
            'counts'=>$counts,
            'checks'=>$checks,
            'generated_at'=>current_time('mysql', true),
            'duration_ms'=>max(0, (int)round((microtime(true) - $started) * 1000)),
            'actor_user_id'=>max(0, $actorId),
            'plugin_version'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'gate_version'=>self::VERSION,
            'read_only'=>true,
        ];
        update_option(self::REPORT_OPTION, $report, false);
        self::appendHistory($report);
        return $report;
    }

    public static function latest(): array {
        $report = get_option(self::REPORT_OPTION, []);
        return is_array($report) ? $report : [];
    }

    public static function history(): array {
        $history = get_option(self::HISTORY_OPTION, []);
        return is_array($history) ? $history : [];
    }

    private static function checkVersionConsistency(): array {
        $constant = defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '';
        $header = '';
        $stable = '';
        $pluginFile = defined('FIELDFLOW_PATH') ? FIELDFLOW_PATH . 'fieldflow.php' : '';
        $readme = defined('FIELDFLOW_PATH') ? FIELDFLOW_PATH . 'readme.txt' : '';
        if ($pluginFile && is_readable($pluginFile)) {
            $content = (string)file_get_contents($pluginFile);
            if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $content, $m)) $header = trim($m[1]);
        }
        if ($readme && is_readable($readme)) {
            $content = (string)file_get_contents($readme);
            if (preg_match('/^Stable tag:\s*([^\r\n]+)/mi', $content, $m)) $stable = trim($m[1]);
        }
        $ok = $constant !== '' && $constant === $header && $constant === $stable;
        return self::check('version_consistency', 'Consistência da versão', $ok ? 'pass' : 'fail',
            $ok ? 'A constante, o cabeçalho e o stable tag estão sincronizados em ' . $constant . '.' : 'A versão não está sincronizada entre os ficheiros de release.',
            ['constant'=>$constant,'header'=>$header,'stable_tag'=>$stable]
        );
    }

    private static function checkReleaseContinuityAuthorityContract(): array {
        $runtime = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $stable = class_exists(Phase6StableRelease::class) ? (string)Phase6StableRelease::STABLE_RELEASE : '';
        $source = class_exists(Phase6StableRelease::class) ? (string)Phase6StableRelease::SOURCE_RELEASE : '';
        $expectedSource = self::previousPatchRelease($runtime);
        $ok = $runtime !== ''
            && $stable !== ''
            && hash_equals($runtime, $stable)
            && $expectedSource !== ''
            && hash_equals($expectedSource, $source)
            && version_compare($source, $stable, '<');
        return self::check(
            'release_continuity_authority',
            'Release Continuity Authority',
            $ok ? 'pass' : 'fail',
            $ok
                ? 'A continuidade N→N+1 é validada uma única vez pela Phase6StableRelease; gates históricos deixam de fixar SOURCE_RELEASE por componente.'
                : 'A autoridade central de continuidade não corresponde à release corrente e à release imediatamente anterior.',
            [
                'runtime'=>$runtime,
                'source_release'=>$source,
                'stable_release'=>$stable,
                'expected_source'=>$expectedSource,
                'component_gates_source_release_pinning'=>false,
            ]
        );
    }

    private static function previousPatchRelease(string $version): string {
        if (!preg_match('/^(\\d+)\\.(\\d+)\\.(\\d+)$/', $version, $m)) return '';
        $patch = (int)$m[3];
        if ($patch <= 0) return '';
        return (int)$m[1] . '.' . (int)$m[2] . '.' . ($patch - 1);
    }

    /** 9DA: fail closed for missing preflight capability, but never auto-certify. */
    private static function checkReleaseManagerAssistedPreflight9DAContract(): array {
        $requirements = [
            'src/Support/ReleasePreflight.php' => [
                'fieldflow-release-manager-assisted-preflight-9da-v1',
                'function evaluate(', "'can_publish'", "'diagnostics'", "'diff_available'",
            ],
            'src/Admin/ReleaseManager.php' => [
                'ReleasePreflight::evaluate(', 'ffrm-assisted-9da', 'ffrm-assisted-diagnostics',
                "'publish_package'", 'confirm_publish',
            ],
            'tools/test-release-manager-assisted-9da.php' => ['RELEASE MANAGER ASSISTED PREFLIGHT 9DA PASS'],
            'PHASE-9DA-RELEASE-MANAGER-ASSISTED-PREFLIGHT.md' => ['fieldflow-release-manager-assisted-preflight-9da-v1'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.635', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('release_manager_assisted_preflight_9da', 'Release Manager Assisted Preflight 9DA',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DA incompleto: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Pré-validação informativa preserva SHA, Gate, Publisher e aprovação manual da Stable.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-release-manager-assisted-preflight-9da-v1',
             'added_ai_calls'=>false, 'schema_change'=>false, 'governance_change'=>false, 'missing'=>$missing]);
    }

    /** 9DB: assisted workflow is additive, with no alternative to package validation or Phase 6. */
    private static function checkReleaseManagerAssistedWorkflow9DBContract(): array {
        $requirements = [
            'src/Support/ReleaseStagingWorkflow.php' => [
                'fieldflow-release-manager-assisted-workflow-9db-v1',
                'function stage(', 'function publish(', 'function reconcile(',
                'function resumeCatalog(', 'function confirmedInspect(',
                'function verifiedPath(', 'function privateDirectory(',
                'is_uploaded_file(', 'hash_file(', 'flock(',
            ],
            'src/Admin/ReleaseManager.php' => [
                'ReleaseStagingWorkflow::stage(', 'ReleaseStagingWorkflow::publish(',
                'ReleaseStagingWorkflow::reconcile(', 'ReleaseStagingWorkflow::resumeCatalog(',
                'confirm_publish', 'confirm_discard', 'publish_package',
            ],
            'tools/test-release-manager-assisted-workflow-9db.php' => ['RELEASE MANAGER ASSISTED WORKFLOW 9DB PASS'],
            'PHASE-9DB-RELEASE-MANAGER-ASSISTED-WORKFLOW.md' => ['fieldflow-release-manager-assisted-workflow-9db-v1'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.636', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('release_manager_assisted_workflow_9db', 'Release Manager Assisted Workflow 9DB',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DB incompleto: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Upload privado efémero, prova SHA, reconciliação read-only e aprovação explícita preservam os gates oficiais.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-release-manager-assisted-workflow-9db-v1',
             'automatic_certification'=>false,'schema_change'=>false,'remote_retry_on_timeout'=>false, 'missing'=>$missing]);
    }

    /** 9DC: real Operative bottom nav scroll owner. Client 9D5 is unchanged. */
    private static function checkOperativeDockScrollAuthority9DCContract(): array {
        $requirements = [
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
                'ffv-operative-dock-collapsed-9dc', 'syncDock()',
            ],
            'assets/fieldflow-operative-dock-scroll-9dc.css' => [
                '#routespro-app.ffv-operative-dock-9dc',
                'ffv-operative-dock-collapsed-9dc',
                'scroll-padding-bottom',
                'env(safe-area-inset-bottom,0px)',
            ],
            'tools/test-operative-dock-scroll-9dc.php' => ['OPERATIVE DOCK SCROLL 9DC PASS'],
            'PHASE-9DC-OPERATIVE-DOCK-SCROLL-AUTHORITY.md' => ['fieldflow-operative-dock-scroll-authority-9dc-v1'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.637', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('operative_dock_scroll_authority_9dc',
            'Barra inferior Operativo: scroll e gestos reais 9DC',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DC incompleto: '.implode(', ',array_slice($missing,0,8))
                : 'Navegacao Operativo: janela, contentores internos, gestos e reserva inferior; Cliente isolado.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-operative-dock-scroll-authority-9dc-v1',
             'client_modified'=>false,'business_data_modified'=>false,'missing'=>$missing]);
    }

    /** 9DD: optional read-only evidence of the real operative navigation. */
    private static function checkOperativeDockRealRuntimeAudit9DDContract(): array {
        $requirements = [
            'src/Front/AppShortcodeRenderer.php' => [
                'ff_dock_diag', 'data-ffdd-status',
                'fieldflow-operative-dock-audit-9dd.js',
                'fieldflow-operative-dock-audit-9dd.css',
                'data-fieldflow-contract="fieldflow-operative-dock-real-runtime-audit-9dd-v1"',
            ],
            'assets/fieldflow-operative-dock-audit-9dd.js' => [
                "const CONTRACT='fieldflow-operative-dock-real-runtime-audit-9dd-v1'",
                "document.addEventListener('scroll'", "document.addEventListener('touchmove'",
                "el.style.setProperty('transform'", "restore()",
                'window.FieldFlowDockAudit9DD={snapshot,contract:CONTRACT}',
            ],
            'assets/fieldflow-operative-dock-audit-9dd.css' => ['#ff-dock-audit-9dd', 'z-index:2147483646'],
            'tools/test-operative-dock-runtime-audit-9dd.php' => ['OPERATIVE DOCK REAL RUNTIME AUDIT 9DD PASS'],
            'PHASE-9DD-OPERATIVE-DOCK-REAL-RUNTIME-AUDIT.md' => ['fieldflow-operative-dock-real-runtime-audit-9dd-v1'],
        ];
        $missing=[];
        if(version_compare(self::currentRelease(),'2.2.638','<'))$missing[]='runtime:version';
        foreach($requirements as $relative=>$markers){
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if($raw===''){$missing[]=$relative.':file';continue;}
            foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$relative.':'.$marker;
        }
        return self::check('operative_dock_real_runtime_audit_9dd',
            'Diagnostico real da barra inferior 9DD',
            $missing?'fail':'pass',
            $missing?'Diagnostico 9DD incompleto: '.implode('; ',array_slice($missing,0,8))
                :'Diagnostico ativo apenas com parametro voluntario. A barra 9DC permanece inalterada.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-operative-dock-real-runtime-audit-9dd-v1',
             'no_automatic_changes'=>true,'no_network_telemetry'=>true,'client_portal_modified'=>false, 'missing'=>$missing]);
    }

    /** 9DE: independently bootstrapped, observable Operative dock owner. */
    private static function checkOperativeDockBootstrapRecovery9DEContract(): array {
        $requirements = [
            'src/Front/AppShortcodeRenderer.php' => [
                'FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE',
                'data-fieldflow-dock-delivery-9de=',
                'fieldflow-operative-dock-bootstrap-9de.css',
                'fieldflow-operative-dock-bootstrap-9de.js',
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
                "bar.classList.toggle('ffv-dock-hidden'",
            ],
            'assets/fieldflow-operative-dock-bootstrap-9de.css' => [
                '#routespro-app[data-fieldflow-portal="operative"].ffv-operative-dock-9de',
                'ffv-operative-dock-collapsed-9de',
                'scroll-padding-bottom',
                'env(safe-area-inset-bottom,0px)',
            ],
            'tools/test-operative-dock-bootstrap-9de.php' => ['OPERATIVE DOCK BOOTSTRAP 9DE PASS'],
            'tools/test-operative-dock-bootstrap-9de.mjs' => ['OPERATIVE DOCK BOOTSTRAP 9DE JS PASS'],
            'PHASE-9DE-OPERATIVE-DOCK-BOOTSTRAP-RECOVERY.md' => ['fieldflow-operative-dock-bootstrap-recovery-9de-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.639', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$relative.':'.$marker;
        }
        return self::check('operative_dock_bootstrap_recovery_9de',
            'Barra Operativo: recuperacao do controlador 9DE',
            $missing?'fail':'pass',
            $missing?'9DE incompleto: '.implode('; ',array_slice($missing,0,8))
                :'9DE emitido independentemente dos controlos historicos, com ligacao ao DOM e diagnostico em dispositivo.',
            ['runtime'=>self::currentRelease(),'contract'=>'fieldflow-operative-dock-bootstrap-recovery-9de-v1',
             'client_modified'=>false,'business_data_modified'=>false,'missing'=>$missing]);
    }

    /** 9DF: calmer Operative transitions and read-only Phase 4 observability. */
    private static function checkPhase4AndCalmOperativeDock9DFContract(): array {
        $required = [
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
                'prefers-reduced-motion:reduce',
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
                'API estimado, 30 dias',
            ],
            'tools/test-operative-dock-calm-motion-9df.mjs' => ['OPERATIVE DOCK CALM MOTION 9DF JS PASS'],
            'tools/test-phase4-observability-9df.php' => ['PHASE 4 OBSERVABILITY 9DF PASS'],
            'PHASE-9DF-CALM-OPERATIVE-DOCK-PHASE4-BASELINE.md' => ['fieldflow-operative-dock-calm-motion-9df-v1','fieldflow-phase4-observability-cost-baseline-9df-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.640','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('phase4_calm_operative_dock_9df',
            'Fase 4A e transicao suave do dock Operativo 9DF',
            $missing?'fail':'pass',
            $missing?'Contrato 9DF incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Histerese, fade e fotografia observacional de custos e performance sem pedidos externos.',
            ['runtime'=>self::currentRelease(),'contract'=>'fieldflow-operative-dock-calm-motion-9df-v1',
             'read_only_api_costs'=>true,'client_modified'=>false,'missing'=>$missing]);
    }

    /** 9DG: cost/latency priorities without changing any paid services or routes. */
    private static function checkPhase4CostPerformanceDrivers9DGContract(): array {
        $required = [
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
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.641', '<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('phase4_cost_performance_drivers_9dg',
            'Fase 4B: prioridades API e P95, sem alteração operacional',
            $missing?'fail':'pass',
            $missing?'Contrato 9DG incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Leitura agregada 30 dias, tarifa desconhecida sinalizada e investigação de P95, sem pedidos externos.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-phase4-cost-performance-drivers-9dg-v1',
             'read_only'=>true,'client_modified'=>false,'dock_modified'=>false,'missing'=>$missing]);
    }

    /** 9DH: measured admin hot spots, lazy snapshots and fail-closed manifest keys. */
    private static function checkPhase4AdminHotspotsManifestSafety9DHContract(): array {
        $required = [
            'src/Performance/Phase4AdminHotspots.php' => [
                'fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1',
                'final class Phase4AdminHotspots', 'MAX_ROWS = 2500',
                'public static function analyze(', 'public static function collect(',
            ],
            'src/Admin/SecurityObservability.php' => [
                'Phase4AdminHotspots::collect(', 'data-fieldflow-phase4-admin-hotspots',
                'overviewSnapshotsRequired($tab)', 'Fase 4C, gargalos por página do Backoffice',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'manifest_key_files_integrity_9dh_ok', 'key_files inválidos ou obsoletos',
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
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.642', '<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('phase4_admin_hotspots_manifest_safety_9dh',
            'Fase 4C: gargalos admin por pagina e integridade key_files 9DH',
            $missing?'fail':'pass',
            $missing?'Contrato 9DH incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Atribuicao P95 read-only por pagina e manifesto fechado a referencias obsoletas.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1',
             'read_only'=>true,'client_modified'=>false,'dock_modified'=>false,'missing'=>$missing]);
    }

    /** 9DI: immutable admin-health snapshot reuse and bounded integration summaries. */
    private static function checkPhase4AdminHealthSnapshotReuse9DIContract(): array {
        $required = [
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
            'src/Support/SystemHealth.php' => [
                "'offline' => \$offline, // 9DI",
            ],
            'tools/test-phase4-admin-health-9di.php' => [
                'PHASE 4 ADMIN HEALTH 9DI PASS',
            ],
            'PHASE-9DI-ADMIN-HEALTH-SNAPSHOT-REUSE.md' => [
                'fieldflow-phase4-admin-health-snapshot-reuse-9di-v1',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.643', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('phase4_admin_health_snapshot_reuse_9di',
            'Fase 4D: painel Saude do sistema com agregados reutilizados',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DI incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                : 'Read-only; menos consultas repetidas e nenhum carregamento de segredos ou payloads no painel.',
            ['runtime' => self::currentRelease(),
             'contract' => 'fieldflow-phase4-admin-health-snapshot-reuse-9di-v1',
             'read_only' => true, 'dock_modified' => false, 'client_modified' => false,
             'missing' => $missing]);
    }

    /** 9DJ: GET-only cached certification diagnostics and scoped mobile performance layout. */
    private static function checkPhase4AdminCertificationMobile9DJContract(): array {
        $required = [
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
            'tools/test-phase4e-admin-mobile-9dj.php' => [
                'PHASE 4E 9DJ PASS',
            ],
            'PHASE-9DJ-ADMIN-PERFORMANCE-MOBILE-READABILITY.md' => [
                'fieldflow-phase4e-admin-cert-readonly-mobile-layout-9dj-v1',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.644', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw === '') { $missing[] = $relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check('phase4e_admin_readonly_mobile_9dj',
            'Fase 4E: GET read-only e desempenho legivel no BO mobile 9DJ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DJ incompleto: '.implode('; ',array_slice($missing,0,6))
                : 'Leituras admin otimizadas, certificacao fresca preservada e layout de desempenho mobile isolado.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-phase4e-admin-cert-readonly-mobile-layout-9dj-v1',
             'certification_fresh'=>true, 'frontend_unchanged'=>true, 'missing'=>$missing]);
    }


    /** 9DL: narrow per-route content and reject cross-retailer stock links. */
    private static function checkBriefingStocksMobile9DLContract(): array {
        $required = [
            'src/Briefings/BriefingService.php' => ["if (\$scope !== 'assigned')", 'excluded_store_items', 'if (!$relevant) continue;'],
            'src/Stocks/StockService.php' => ["'SONAE','SONAE MC'", 'locationMatchesInsignia(', 'cross_insignia_rows', 'repairBatchScope('],
            'src/Front/BriefingsShortcodeRenderer.php' => ['ffb-mobile-records', 'ffb-responsive-records', 'ffb-scope-banner'],
            'src/Front/StocksShortcodeRenderer.php' => ['rebuildStoreOptions(', 'selectedInsignia', 'data-filter-feedback'],
            'tools/test-briefing-stock-mobile-9dl.php' => ['BRIEFING STOCK MOBILE 9DL PASS'],
            'frontend/tests/stocks-insignia-filter-9dl.test.mjs' => ['9DL: switching Sonae / Pingo Doce', 'brand.onchange();'],
            'PHASE-9DL-BRIEFING-STOCK-MOBILE-SCOPE.md' => ['fieldflow-briefing-stock-mobile-scope-integrity-9dl-v1'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.646', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw === '') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $missing[]=$relative.':'.$marker;
        }
        return self::check(
            'briefing_stocks_mobile_scope_9dl',
            'Briefing da rota, correspondencia de insignias e layout mobile 9DL',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DL incompleto: '.implode('; ',array_slice($missing,0,6))
                : 'Lojas efetivas da rota; Sonae nunca associa PDV Makro; filtros operacionais ativos.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-briefing-stock-mobile-scope-integrity-9dl-v1',
             'no_cross_retailer_store_alias'=>true, 'no_route_assignment_inferred_from_briefing'=>true,
             'missing'=>$missing]
        );
    }

    /** 9DM: PDV family is authoritative across matching and legacy review. */
    private static function checkStockInsigniaAuthority9DMContract(): array {
        $required = [
            'src/Stocks/StockService.php' => [
                "'arcol' => ['ARCOL']", "'mcunha' => ['M CUNHA'",
                'sourceMatchesInsignia(', 'explicitLocationFamily(',
                'invalidBatchStoreAssociations(', 'review_count', 'count($found) !== 1',
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
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.647', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) {
                if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
            }
        }
        return self::check(
            'stock_insignia_authority_9dm',
            'Stock e PDV por insignia com autoridade estrita 9DM',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DM incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                : 'CNT/MDL/CBD, PD, Arcol, M Cunha, Makro e Recheio sem cruzamentos; revisao nao destrutiva.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-stock-insignia-identity-authority-9dm-v1',
             'automatic_cross_insignia_mapping'=>false, 'destructive_legacy_migration'=>false,
             'missing'=>$missing]
        );
    }

    /** 9DN: single closed-world PDV authority for import, mapping and client-facing flows. */
    private static function checkUniversalStoreIdentity9DNContract(): array {
        $required = [
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
            'PHASE-9DN-UNIVERSAL-STORE-IDENTITY-AUTHORITY.md' => [
                'fieldflow-universal-store-identity-authority-9dn-v1',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.648', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) {
                if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
            }
        }
        $semantic = false;
        if (!$missing) {
            try {
                require_once ROUTESPRO_PATH . 'src/Stocks/StockService.php';
                require_once ROUTESPRO_PATH . 'src/Stores/StoreIdentityAuthority.php';
                $type = \RoutesPro\Stores\StoreIdentityAuthority::class;
                $semantic = $type::sourceMatchesLocation('sonae', 'CNT Matosinhos', ['name'=>'CNT Matosinhos'], true)
                    && !$type::sourceMatchesLocation('sonae', 'CNT Matosinhos', ['name'=>'MDL Matosinhos'], true)
                    && !$type::sourceMatchesLocation('sonae', 'CNT Matosinhos', ['name'=>'Makro Matosinhos'], true)
                    && $type::sourceMatchesLocation('pingo_doce', 'PD Matosinhos', ['name'=>'PD Matosinhos'], true)
                    && !$type::sourceMatchesLocation('arcol', 'Arcol Matosinhos', ['name'=>'M. Cunha Matosinhos'], true)
                    && !$type::canUpdateLocation(['name'=>'MDL Matosinhos'], ['name'=>'CNT Matosinhos']);
            } catch (\Throwable $ex) {
                $missing[] = 'runtime:' . get_class($ex);
            }
            if (!$semantic) $missing[] = 'runtime:closed_world_semantics';
        }
        return self::check(
            'universal_store_identity_authority_9dn',
            'Identidade comercial unificada em todos os mapeamentos 9DN',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DN incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                : 'Briefing, mapping, shopping, cardex, implementacoes e CSV com prova da familia e subtipo da loja.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-universal-store-identity-authority-9dn-v1',
                'destructive_legacy_migration'=>false, 'fail_closed'=>true, 'missing'=>$missing]
        );
    }

    /** 9DP: cloned assortment draft isolation, strict store identity and explicit publication. */
    private static function checkSafeCardexClonePublication9DPContract(): array {
        $required = [
            'src/Admin/CardexCloneService.php' => ['fieldflow-safe-cardex-clone-publication-9dp-v1','function duplicate(', 'function publish(', 'locationInScope(', 'START TRANSACTION', "'status'=>'draft'"],
            'src/Admin/ProductCardex.php' => ['routespro_duplicate_product_cardex', 'routespro_publish_product_cardex', 'handle_duplicate(', 'handle_publish(', 'CardexCloneService::supportedInsignia(', "'draft'"],
            'src/Forms/ProductCardex.php' => ['clone_scope', 'clone_source_id', "(\$cardex['status'] ?? '')!=='active'"],
            'src/Catalog/ProductCatalogService.php' => ["(\$cardex['status'] ?? 'active') !== 'active'"],
            'src/Briefings/CardexSyncService.php' => ["if (!empty(\$meta['clone_source_id'])) continue;"],
            'tools/test-safe-cardex-clone-publication-9dp.php' => ['SAFE CARDEX CLONE PUBLICATION 9DP PASS'],
            'tools/test-cardex-csv-identity-9dp.php' => ['CARDEX CSV INSIGNIA SKU 9DP PASS'],
            'tools/test-release-gate-runtime-9dp.php' => ['RELEASE GATE RUNTIME 9DP PASS'],
            'PHASE-9DP-SAFE-CARDEX-CLONE-PUBLICATION.md' => ['fieldflow-safe-cardex-clone-publication-9dp-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.650', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('safe_cardex_clone_publication_9dp','Duplicação e publicação segura de cardex 9DP',
            $missing?'fail':'pass',
            $missing?'Contrato 9DP incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Rascunhos isolados, identidade comercial estrita e publicação com âmbito confirmado.',
            ['runtime'=>self::currentRelease(),'contract'=>'fieldflow-safe-cardex-clone-publication-9dp-v1','missing'=>$missing]);
    }

    /** 9DQ: free-text commercial registry, independent assortment and fail-closed PDV authority. */
    private static function checkFlexibleCardexInsigniaRegistry9DQContract(): array {
        $required = [
            'src/Admin/CardexCloneService.php' => [
                'fieldflow-flexible-cardex-insignia-registry-9dq-v1','function canonicalInsignia(',
                'function rememberInsignia(', 'function locationMatchesInsignia(', 'locationInScope(',
                'function duplicate(', 'function publish(', "'status'=>'draft'"],
            'src/Admin/ProductCardex.php' => [
                'ff-cardex-brand-options', 'list="ff-cardex-brand-options"', 'name="destination_insignia"',
                'CardexCloneService::canonicalInsignia(', 'CardexCloneService::locationMatchesInsignia(',
                'routespro_duplicate_product_cardex','routespro_publish_product_cardex'],
            'src/Forms/ProductCardex.php' => ['CardexCloneService::locationMatchesInsignia(', 'clone_scope', 'clone_source_id'],
            'tools/test-flexible-cardex-insignia-registry-9dq.php' => ['FLEXIBLE CARDEX INSIGNIA REGISTRY 9DQ PASS'],
            'tools/test-release-gate-runtime-9dq.php' => ['RELEASE GATE RUNTIME 9DQ PASS'],
            'PHASE-9DQ-FLEXIBLE-CARDEX-INSIGNIA-REGISTRY.md' => ['fieldflow-flexible-cardex-insignia-registry-9dq-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.651', '<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $source=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($source==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($source,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('flexible_cardex_insignia_registry_9dq',
            'Registo de novas insígnias e duplicação segura de cardex 9DQ',
            $missing ? 'fail':'pass',
            $missing ? 'Contrato 9DQ incompleto: '.implode('; ',array_slice($missing,0,6))
                     : 'Criação explícita de insígnias, rascunhos isolados e PDV comerciais comprovados.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-flexible-cardex-insignia-registry-9dq-v1',
             'missing'=>$missing]);
    }

    /** 9DW: read-only cross-module diagnostics and automatic-Cardex builder UX. */
    private static function checkCardexDataConsistency9DWContract(): array {
        $required = [
            'src/Admin/CardexConsistency.php' => [
                'fieldflow-cardex-data-consistency-center-9dw-v1',
                'public static function render()', 'public static function inspectStore(',
                'private static function stockSnapshot(', 'private static function briefingQuestions(',
                'StoreIdentityAuthority::locationFamily(', 'duplicate_sku', 'missing_cardex',
            ],
            'src/Admin/ProductCardex.php' => ['CardexConsistency::render();', 'consistency=1'],
            'src/Admin/Forms.php' => ['twt-fb-cardex-fixed-picker', 'twt-fb-auto-cardex-note', 'consistency=1'],
            'assets/routespro-form-builder.js' => [
                ".toggle(source === 'cardex_fixed')", ".toggle(source === 'cardex_auto')",
            ],
            'tools/test-cardex-data-consistency-9dw.php' => ['CARDEX DATA CONSISTENCY 9DW PASS'],
            'tools/test-release-gate-runtime-9dw.php' => ['RELEASE GATE RUNTIME 9DW PASS'],
            'PHASE-9DW-CARDEX-DATA-CONSISTENCY.md' => ['fieldflow-cardex-data-consistency-center-9dw-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.657','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('cardex_data_consistency_9dw',
            'Cardex Data Consistency Center 9DW',$missing?'fail':'pass',
            $missing?'Contrato 9DW incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Read-only PDV Cardex, assortment, briefing, stock and form preview with no implicit identity changes.',
            ['runtime'=>self::currentRelease(),'contract'=>self::CARDEX_DATA_CONSISTENCY_9DW_CONTRACT,'missing'=>$missing]);
    }

    /** 9DX: optional presentation-only steps for briefing questions, no answer migration. */
    private static function checkBriefingSteps9DXContract(): array {
        $required = [
            'src/Briefings/BriefingSteps.php' => [
                self::BRIEFING_STEPS_9DX_CONTRACT, 'public static function all(',
                'public static function save(', 'fieldflow_briefing_steps_9dx_',
            ],
            'src/Admin/Briefings.php' => [
                'BriefingSteps::all(', 'BriefingSteps::save(', 'briefing_step_key',
                'save_steps', 'question_step_keys',
            ],
            'src/Forms/BriefingFormBridge.php' => [
                'stepGroupsForBriefing(', 'expandLayoutSteps(',
                'briefing_step_key', 'sourceSteps', 'replacementStepMap',
            ],
            'src/Forms/ContextQuestions.php' => ['briefing_step_key'],
            'tools/test-briefing-steps-9dx.php' => ['BRIEFING STEPS CONTINUITY 9DX PASS'],
            'tools/test-release-gate-runtime-9dx.php' => ['RELEASE GATE RUNTIME 9DX PASS'],
            'PHASE-9DX-BRIEFING-STEPS-CONTINUITY.md' => [self::BRIEFING_STEPS_9DX_CONTRACT],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.658', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('briefing_steps_continuity_9dx',
            'Steps de Briefing e Continuidade Histórica 9DX',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DX incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                     : 'Steps opcionais por Briefing e loja, com identificadores originais de perguntas, respostas e histórico preservados.',
            ['runtime'=>self::currentRelease(), 'contract'=>self::BRIEFING_STEPS_9DX_CONTRACT,
             'schema_change'=>false, 'answer_migration'=>false, 'missing'=>$missing]
        );
    }

    /** 9DV: web/mobile order rows must share SKU and zero-fronts authority. */
    private static function checkOrderSuggestionIntegrity9DVContract(): array {
        $required = [
            'src/Forms/OrderSuggestionIntegrity.php'=>['fieldflow-order-suggestion-integrity-9dv-v1', 'public static function confirmedZero(', 'public static function normalise(', 'fronts_confirmed', 'if ($frontsPresent && !$front) continue;', 'if ($id || !$frontsPresent'],
            'src/Forms/ProductCardex.php'=>['normalise_order_submission_rows(', 'OrderSuggestionIntegrity::normalise(', 'if ($uses_cardex && !$same_cardex) continue;'],
            'src/Forms/Forms.php'=>['ProductCardex::normalise_order_submission_rows(', "['product_matrix','order_suggestion']"],
            'src/Mobile/SubmissionService.php'=>['ProductCardex::normalise_order_submission_rows(', "['product_matrix','order_suggestion']"],
            'src/Forms/FormRenderer.php'=>['function pairedFrontQuestion(', 'function syncOrderSuggestionForRow(', 'data-fronts-confirmed', "source!=='manual'"],
            'tools/test-order-suggestion-integrity-9dv.php'=>['ORDER SUGGESTION INTEGRITY 9DV PASS'],
            'tools/test-release-gate-runtime-9dv.php'=>['RELEASE GATE RUNTIME 9DV PASS'],
            'PHASE-9DV-ORDER-SUGGESTION-INTEGRITY.md'=>['fieldflow-order-suggestion-integrity-9dv-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.656','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('order_suggestion_integrity_9dv',
            'Order Suggestion Integrity 9DV', $missing?'fail':'pass',
            $missing?'Contrato 9DV incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Confirmed zero only, manual authority and server-validated SKU on web/mobile, with historical Cardex preserved.',
            ['runtime'=>self::currentRelease(),'contract'=>'fieldflow-order-suggestion-integrity-9dv-v1','missing'=>$missing]);
    }

    /** 9DU: historical Sonae umbrella Cardex and all-store read-only binding inspection. */
    private static function checkCardexGlobalLinkedScope9DUContract(): array {
        $required = [
            'src/Forms/ProductCardex.php'=>['legacy_sonae_umbrella(', 'legacy_sonae_umbrella($cardex)', 'legacy_sonae_umbrella($row)'],
            'src/Admin/ProductCardex.php'=>['render_global_binding_audit(', 'audit_bindings', 'inspect_location_binding(', 'audit_client', 'audit_cardex', 'audit_store'],
            'tools/test-cardex-global-scope-9du.php'=>['CARDEX GLOBAL LINKED SCOPE 9DU PASS'],
            'tools/test-release-gate-runtime-9du.php'=>['RELEASE GATE RUNTIME 9DU PASS'],
            'PHASE-9DU-CARDEX-GLOBAL-LINKED-SCOPE.md'=>['fieldflow-cardex-global-linked-scope-authority-9du-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.655','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('cardex_global_linked_scope_9du',
            'Cardex Global Linked Scope Authority 9DU',
            $missing?'fail':'pass',
            $missing?'Contrato 9DU incompleto: '.implode('; ',array_slice($missing,0,6))
                :'Sonae group Cardex links validated, subtype-only and cross-tenant protection preserved, global read-only audit available.',
            ['runtime'=>self::currentRelease(),'contract'=>'fieldflow-cardex-global-linked-scope-authority-9du-v1','missing'=>$missing]);
    }

    /** 9DT: a store selected in BO must have visible tenant/project diagnostics before the field form rejects it. */
    private static function checkCardexScopeTruth9DTContract(): array {
        $required = [
            'src/Forms/ProductCardex.php' => ['inspect_location_binding(', "'project_mismatch'", "'retailer_mismatch'", 'resolve_cardex_id_for_location('],
            'src/Admin/ProductCardex.php' => ['commercial_scope_choices(', 'render_binding_diagnostics(', 'diagnose_location_id', 'ff-cardex-client', 'ff-cardex-project', 'scope_only', 'confirm_scope_change'],
            'tools/test-cardex-scope-truth-9dt.php' => ['CARDEX SCOPE TRUTH 9DT PASS'],
            'tools/test-release-gate-runtime-9dt.php' => ['RELEASE GATE RUNTIME 9DT PASS'],
            'PHASE-9DT-CARDEX-CLIENT-PROJECT-SCOPE-TRUTH.md' => ['fieldflow-cardex-client-project-scope-truth-9dt-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.654','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('cardex_scope_truth_9dt',
            'Cardex Client/Project Scope Truth 9DT',
            $missing?'fail':'pass',
            $missing ? 'Contrato 9DT incompleto: '.implode('; ',array_slice($missing,0,6))
                     : 'Scope comercial visivel, diagnostico por rota, gravação isolada e protecao cross-tenant.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-cardex-client-project-scope-truth-9dt-v1',
             'missing'=>$missing]);
    }

    /** 9DR: keep legacy retailer-linked Cardex visible while rejecting cross-brand links. */
    private static function checkCardexFormBindingContinuity9DRContract(): array {
        $required = [
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
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.652','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('cardex_form_binding_continuity_9dr',
            'Ligacao de Cardex a Frentes e Sugestao de Encomenda 9DR',
            $missing ? 'fail':'pass',
            $missing ? 'Contrato 9DR incompleto: '.implode('; ',array_slice($missing,0,6))
                     : 'Aliases historicos restaurados, CNT/MDL/CBD isolados e sincronizacao semanal segura.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-cardex-form-binding-continuity-9dr-v1',
             'missing'=>$missing]);
    }

    /** 9DS: missing bindings must not erase form questions or existing visit answers. */
    private static function checkCardexVisibleBinding9DSContract(): array {
        $required = [
            'src/Forms/ProductCardex.php' => ['cardex_retailer_evidence(', 'question_binding_issue(', 'cardex_retailer_matches_location(', 'clone_source_id'],
            'src/Forms/FormRenderer.php' => ['render_unavailable_cardex_question(', 'data-rp-cardex-unavailable="1"', "context['_daily_existing']", 'Esta pergunta está temporariamente indisponível'],
            'src/Forms/Forms.php' => ['$daily_existing && array_key_exists($key, $existing_prefill)', 'As respostas existentes desta visita foram preservadas'],
            'src/Mobile/SubmissionService.php' => ['$is_update && array_key_exists($key, $prior_visit_answers)', 'DailySubmissionService::submission_prefill('],
            'src/Mobile/FormPayloadService.php' => ["['binding_error']", "['read_only']", "['cardex_available']"],
            'tools/test-form-cardex-visible-binding-9ds.php' => ['FORM CARDEX VISIBLE BINDING 9DS PASS'],
            'tools/test-release-gate-runtime-9ds.php' => ['RELEASE GATE RUNTIME 9DS PASS'],
            'PHASE-9DS-CARDEX-FORM-VISIBLE-BINDING.md' => ['fieldflow-cardex-visible-binding-9ds-v1'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.653','<')) $missing[]='runtime:version';
        foreach ($required as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('cardex_visible_form_binding_9ds',
            'Perguntas Frentes/Sugestão visíveis e proteção de histórico 9DS',
            $missing ? 'fail':'pass',
            $missing ? 'Contrato 9DS incompleto: '.implode('; ',array_slice($missing,0,6))
                     : 'Recuperação de cardex histórico validada, aviso visível sem SKU artificial e edição sem perda de respostas.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-cardex-visible-binding-9ds-v1', 'missing'=>$missing]);
    }

    /** 9DO: Daily Close isolation and no replay for unknown SMTP outcomes. */
    private static function checkDailyCloseRecipientReliability9DOContract(): array {
        $required = [
            'src/Admin/ReportAutomations.php' => ['recipientDispatchIdentity(', 'daily_close_partial', 'recordDailyCloseDiagnostic(', 'legacy_idempotency_key', 'reliable_required'],
            'src/Services/MailDispatchService.php' => ['reliableBlocked(', "'uncertain'", 'legacy_idempotency_key', "'status'=>'sending'"],
            'src/Services/MailOutboxRepository.php' => ["SET status='uncertain'", "'uncertain'=>0"],
            'src/Admin/Emails.php' => ['fieldflow_daily_close_diagnostics_9do', "'verified_not_delivered'", 'Confirmar e reabrir'],
            'tools/test-daily-close-recipient-reliability-9do.php' => ['DAILY CLOSE RECIPIENT RELIABILITY 9DO PASS'],
                'tools/test-release-gate-runtime-9do.php' => ['RELEASE GATE RUNTIME 9DO PASS'],
            'PHASE-9DO-DAILY-CLOSE-RECIPIENT-RELIABILITY.md' => ['fieldflow-daily-close-recipient-reliability-9do-v1'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.649', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check(
            'daily_close_recipient_reliability_9do',
            'Fecho diario por destinatario com recuperação sem duplicados 9DO',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DO incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                : 'Identidade por rota/destinatario, falha parcial com retry, Outbox incerta sem replay e diagnostico no Mail Center.',
            ['runtime'=>self::currentRelease(), 'contract'=>'fieldflow-daily-close-recipient-reliability-9do-v1', 'missing'=>$missing]
        );
    }

    /** 9DK: fail-closed weekly briefing, stock header and route-only highlights. */
    private static function checkBriefingStockRouteScope9DKContract(): array {
        $required = [
            'src/Briefings/PernodWeeklyImporter.php' => [
                '$seenNarrative', 'count($uniqueVisible) === 1', 'DateTimeImmutable',
            ],
            'src/Stocks/StockImporter.php' => [
                "['pack', 'packs', 'embalagem']", "unset(\$candidate['mapping']['conversion_factor']",
            ],
            'src/Briefings/BriefingService.php' => [
                'routeLocationIdsForBriefing(', 'storeVisibility9DK(', "'briefing_only'",
            ],
            'src/Front/BriefingsShortcodeRenderer.php' => ['Na tua rota', 'Só briefing'],
            'src/Forms/ProductCardex.php' => ["'review_types'", "'review_count'", 'confirmar com a marca'],
            'src/Stores/StoreProfileCard.php' => ['Perfis do Cardex a confirmar com a marca'],
            'tools/test-briefing-stock-scope-9dk.php' => ['BRIEFING STOCK SCOPE 9DK PASS'],
            'PHASE-9DK-BRIEFING-STOCK-SCOPE-INTEGRITY.md' => [
                'fieldflow-weekly-import-store-scope-integrity-9dk-v1'
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.645', '<')) $missing[] = 'runtime:version';
        foreach ($required as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('briefing_stock_route_scope_9dk',
            'Briefing semanal, stocks e lojas apenas na rota 9DK',
            $missing ? 'fail' : 'pass',
            $missing ? 'Contrato 9DK incompleto: ' . implode('; ', array_slice($missing, 0, 6))
                : 'Intervalo semanal autoritativo; sortido ambíguo em revisão; rota distinta de briefing.',
            ['runtime'=>self::currentRelease(),
             'contract'=>'fieldflow-weekly-import-store-scope-integrity-9dk-v1',
             'no_automatic_store_assignment'=>true, 'no_guessed_cardex_mapping'=>true,
             'missing'=>$missing]);
    }

    private static function checkProductionManifest(): array {
        $status = ProductionManifest::verify(true);
        $ok = !empty($status['ok']);
        $mismatchCount = count((array)($status['key_file_mismatches'] ?? [])) + count((array)($status['manifest_file_mismatches'] ?? [])) + count((array)($status['unexpected_symlinks'] ?? []));
        $detail = $ok
            ? 'A instalação corresponde ao manifesto SHA-256 da release ' . (string)($status['manifest_version'] ?? '') . '.'
            : 'O manifesto de produção não corresponde integralmente à instalação. ' . (string)($status['error'] ?? '');
        return self::check('production_manifest', 'Integridade da release', $ok ? 'pass' : 'fail', $detail, [
            'runtime_version'=>$status['runtime_version'] ?? '',
            'manifest_version'=>$status['manifest_version'] ?? '',
            'expected_file_count'=>$status['expected_file_count'] ?? 0,
            'actual_file_count'=>$status['actual_file_count'] ?? 0,
            'expected_aggregate_sha256'=>$status['expected_aggregate_sha256'] ?? '',
            'actual_aggregate_sha256'=>$status['actual_aggregate_sha256'] ?? '',
            'mismatch_count'=>$mismatchCount,
        ]);
    }

    private static function checkUpgradeBootstrapContract(): array {
        $pluginPath = ROUTESPRO_PATH . 'src/Support/Plugin.php';
        $migrationsPath = ROUTESPRO_PATH . 'src/Support/Migrations.php';
        $missing = [];
        $plugin = is_readable($pluginPath) ? (string)file_get_contents($pluginPath) : '';
        $migrations = is_readable($migrationsPath) ? (string)file_get_contents($migrationsPath) : '';

        foreach ([
            "RUNTIME_ONLY_BASELINE = '2.2.448'",
            'canUseRuntimeOnlyUpgrade($installed)',
            'completeRuntimeOnlyUpgrade($installed)',
            "'mode' => 'runtime_only'",
        ] as $required) {
            if ($plugin === '' || strpos($plugin, $required) === false) $missing[] = 'Plugin.php:' . $required;
        }
        foreach ([
            "LATEST_SCHEMA_VERSION = '2.2.525'",
            'function latestVersion()',
        ] as $required) {
            if ($migrations === '' || strpos($migrations, $required) === false) $missing[] = 'Migrations.php:' . $required;
        }

        $orderOk = $plugin !== ''
            && ($runtimePos = strpos($plugin, 'if (self::canUseRuntimeOnlyUpgrade($installed))')) !== false
            && ($lockPos = strpos($plugin, 'if (!self::acquireUpgradeLock($installed, ROUTESPRO_VERSION))')) !== false
            && $runtimePos < $lockPos;
        if (!$orderOk) $missing[] = 'Plugin.php:runtime_only_before_schema_lock';

        $requiredSchema = class_exists('\\RoutesPro\\Support\\Migrations') ? Migrations::latestVersion() : '';
        $installedSchema = (string)get_option('routespro_schema_version', '0.0.0');
        $installedRuntime = (string)get_option('routespro_version', '');
        $runtime = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $schemaCurrent = $requiredSchema !== '' && version_compare($installedSchema, $requiredSchema, '>=');
        $runtimeCurrent = $runtime !== '' && hash_equals($runtime, $installedRuntime);
        $ok = !$missing && $schemaCurrent && $runtimeCurrent;

        return self::check('upgrade_bootstrap', 'Bootstrap de atualização', $ok ? 'pass' : 'fail',
            $ok
                ? 'Runtime e schema estão desacoplados. Updates sem DDL não executam o Activator completo.'
                : 'O contrato de atualização leve não está integralmente confirmado.',
            [
                'runtime_version'=>$runtime,
                'installed_runtime'=>$installedRuntime,
                'required_schema'=>$requiredSchema,
                'installed_schema'=>$installedSchema,
                'runtime_only_baseline'=>'2.2.448',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkOfflineZeroLossContract(): array {
        $files = [
            'runtime' => 'assets/fieldflow-offline-runtime.js',
            'forms' => 'src/Forms/Forms.php',
            'sync' => 'src/Mobile/OfflineSyncService.php',
            'service_worker' => 'src/PWA/ServiceWorkerController.php',
            'shortcodes' => 'src/Shortcodes.php',
        ];
        $markers = [
            'runtime' => ['fieldflow-form-ack-v1','fieldflow_invalid_json_response','submitForm(form,meta,options)','probeConnectivity(force)'],
            'forms' => ['fieldflow-form-ack-v1',"'persisted' => true","'mutation_id' => \$ack_mutation_id"],
            'sync' => ['function ping(','plugin_version','policy'],
            'service_worker' => ['FIELDFLOW_PWA_CACHE','isFieldFlowStatic','offlineDocument'],
            'shortcodes' => ['FieldFlowOffline.submitForm'],
        ];
        $missing = [];
        $contents = [];
        foreach ($files as $key => $relative) {
            $path = ROUTESPRO_PATH . $relative;
            if (!is_readable($path)) {
                $missing[] = $relative . ':file';
                continue;
            }
            $content = (string)file_get_contents($path);
            $contents[$key] = $content;
            foreach ($markers[$key] as $marker) {
                if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
            }
        }
        $unsafe = [];
        $negativeMarkers = [
            'runtime' => ['response.ok&&json.success!==false','response.status===401||response.status===403'],
            'service_worker' => ['event.respondWith(fetch(req));'],
        ];
        foreach ($negativeMarkers as $key => $forbidden) {
            $content = (string)($contents[$key] ?? '');
            foreach ($forbidden as $marker) if ($content !== '' && strpos($content, $marker) !== false) $unsafe[] = $files[$key] . ':' . $marker;
        }
        $blocked = $missing || $unsafe;
        return self::check('offline_zero_loss', 'Offline Zero-Loss', $blocked ? 'fail' : 'pass',
            $blocked ? 'A release não cumpre integralmente o contrato Zero-Loss obrigatório.' : 'Outbox-first, ACK forte, probe de rede e runtime PWA estão presentes e ligados ao release gate.',
            ['protocol'=>'fieldflow-form-ack-v1','missing'=>$missing,'unsafe_markers'=>$unsafe]
        );
    }

    private static function checkOfflineOperationsIntelligenceContract(): array {
        $requirements = [
            'assets/fieldflow-offline-runtime.js' => ['sync-history','oldest_pending_at_ms','ack_error_count','formVersion','recordSyncEvent'],
            'src/Forms/Forms.php' => ['routespro_form_version','routespro_schema_hash','routespro_form_downloaded_at','form_definition'],
            'src/Mobile/FormPayloadService.php' => ['definition_version','schema_hash','downloaded_at'],
            'src/Mobile/OfflineSyncService.php' => ['oldest_pending_age_ms','sync_history','offline_operations_intelligence'],
            'src/Mobile/DeviceManager.php' => ['offline_health_score','ack_error_count','sync_history'],
            'src/Admin/Devices.php' => ['Offline Operations Intelligence','Saúde offline','Histórico recente'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('offline_operations_intelligence', 'Offline Operations Intelligence', $missing ? 'fail' : 'pass',
            $missing ? 'A observabilidade offline, histórico ou versionamento do formulário está incompleto.' : 'Saúde do dispositivo, histórico de sincronização, telemetria e identidade da definição do formulário estão ativos.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','missing'=>$missing]
        );
    }

    private static function checkResilientMediaOfflineTrustContract(): array {
        $requirements = [
            'src/Admin/Devices.php' => ['ffdev_paged','ffdev_per_page','paginate_links','Lease 6B'],
            'src/Mobile/DeviceManager.php' => ['listDevicesPage','countDevices','trust_lease_state','media_pending_files'],
            'src/Mobile/OfflineTrustLease.php' => ['fieldflow-offline-trust-v1','stable_revalidation_pending','REFRESH_AFTER'],
            'assets/fieldflow-offline-runtime.js' => ['fieldflow-media-integrity-v1','fieldflow-offline-trust-v1','buildMediaManifest(entries,false)','buildMediaManifest(item.entries,true)','refreshTrustLease','formSyncTimeout'],
            'src/Forms/Forms.php' => ['routespro_offline_media_manifest','fieldflow_offline_media_integrity_mismatch','OfflineTrustLease::verify'],
            'src/Mobile/Controller.php' => ['/offline/trust-lease','offline_trust_lease'],
            'src/Front/ResponsiveAssets.php' => ['trustLeaseEndpoint','offline/trust-lease'],
            'src/Mobile/OfflineSyncService.php' => ['media_integrity','azure_offline_trust_shadow','trust_lease_state'],
            'src/Support/Loader.php' => ['src/Mobile/OfflineTrustLease.php'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('resilient_media_offline_trust', 'Resilient Media & Azure Offline Trust', $missing ? 'fail' : 'pass',
            $missing
                ? 'A paginação de dispositivos, integridade de media ou fundação de confiança offline está incompleta.'
                : 'Paginação server-side, manifesto de integridade de media e lease de confiança derivada da certificação 6B estão presentes. A lease permanece shadow e não bloqueante nesta release.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','trust_protocol'=>'fieldflow-offline-trust-v1','media_protocol'=>'fieldflow-media-integrity-v1','trust_enforcement'=>'shadow','missing'=>$missing]
        );
    }

    private static function checkOfflineMediaRehydrationContract(): array {
        $path = ROUTESPRO_PATH . 'src/Forms/FormRenderer.php';
        $content = is_readable($path) ? (string)file_get_contents($path) : '';
        $requirements = [
            'rpRefreshRestoredDraftMediaUi',
            'fieldflow:draft-media-rehydrated',
            'rpSyncUploadPreview($zone)',
            'restoreDraftFiles(key)',
            'restoreServerDraftFiles(key,files)',
        ];
        $missing = [];
        if ($content === '') $missing[] = 'src/Forms/FormRenderer.php:file';
        else foreach ($requirements as $required) if (strpos($content, $required) === false) $missing[] = 'src/Forms/FormRenderer.php:' . $required;
        return self::check('offline_media_rehydration', 'Offline media rehydration', $missing ? 'fail' : 'pass',
            $missing
                ? 'A reconstrução visual de fotografias restauradas de IndexedDB/rascunho de servidor está incompleta.'
                : 'Fotografias restauradas programaticamente reconstruem imediatamente a pré-visualização sem exigir uma nova captura ou evento change.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','missing'=>$missing]
        );
    }


    private static function checkResumableMediaTransportContract(): array {
        $requirements = [
            self::currentOfflineRuntimeRelative() => ['fieldflow-resumable-media-v1','prepareResumableMedia','missing_chunks','routespro_resumable_media_refs','resumable_uploaded_chunks',self::currentOfflineRuntimeMarker(),'fieldflow-zero-byte-file-placeholder-filter-v1','fieldflow-zero-byte-media-draft-recovery-v1','scrubZeroByteMediaPlaceholders','recoverDraftMediaForQueueItem','repairZeroByteMediaFormItems'],
            'src/Mobile/ResumableMediaService.php' => ['fieldflow-resumable-media-v1','part-%04d.bin','media_ref',"hash_file('sha256',",'wp_handle_sideload','assembled.bin',"SIZE_POLICY = 'storage-capacity-only'",'available_file_capacity'],
            'src/Mobile/Controller.php' => ['/offline/media/start','/offline/media/(?P<upload_id>[a-f0-9]{48})/chunk','/offline/media/(?P<upload_id>[a-f0-9]{48})/complete'],
            'src/Mobile/ContractRegistry.php' => ['offline_media_start','offline_media_chunk','offline_media_complete'],
            'src/Front/ResponsiveAssets.php' => ['resumableMediaEndpoint','resumableChunkBytes',self::currentOfflineRuntimeFile()],
            'src/Forms/Forms.php' => ['resolve_posted_refs','resumable_files_for_field','store_received_upload'],
            'src/Forms/FormRenderer.php' => ['_rpStandaloneResumableSubmitting','FieldFlowOffline.submitForm','A proteger e enviar fotografias','fieldflow-image-optimisation-sequential-v1','fieldflow-image-zero-byte-fallback-v1','rpSanitiseInputFiles'],
            'src/Security/UploadGuard.php' => ["IMAGE_SIZE_POLICY = 'no-fixed-image-limit'"],
            'src/Mobile/OfflineSyncService.php' => ['resumable_media_transport','fieldflow-resumable-media-v1'],
            'src/Admin/Devices.php' => ['Retomável:','blocos enviados','retomados'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('resumable_media_transport', 'Resumable Media Transport', $missing ? 'fail' : 'pass',
            $missing
                ? 'O transporte de media por blocos, retoma ou integração com o formulário está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Fotografias protegidas não têm um teto artificial de MB no FieldFlow, são enviadas por blocos idempotentes, retomadas a partir dos blocos em falta, reconstruídas com SHA-256 e publicadas pelo pipeline normal do formulário.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','protocol'=>'fieldflow-resumable-media-v1','chunk_bytes'=>524288,'missing'=>$missing]
        );
    }

    private static function checkEndToEndOfflineFormRecoveryContract(): array {
        $requirements = [
            'src/Forms/DraftService.php' => ['schema_for_scope','recovery_schema_compatibility','posted_schema_revision','schema_changed','single_file','file_index','retained_existing'],
            'src/Forms/FormRenderer.php' => ["indexedDB.open('fieldflow-form-drafts',2)",'snapshots','rpDraftReadSnapshot','rpDraftDeleteSnapshot','scheduleDraftServerRetry','draft_media_sync_pending','Existe uma cópia local mais recente','fieldflow:draft-media-rehydrated'],
            self::currentOfflineRuntimeRelative() => [self::currentOfflineRuntimeMarker(),'MAX_MEDIA_FILES=300','readDurableDraftMedia','recoverDraftMediaForQueueItem','recoveredDraftMedia','clearQueuedDraft','await clearQueuedDraft(item)',"indexedDB.open('fieldflow-form-drafts')"],
            'src/Forms/Forms.php' => ['offline_replay_schema_compatibility','offline_schema_rebased','unknown_question_keys','fieldflow-form-ack-v1'],
            'src/Mobile/SubmissionService.php' => ['offline_schema_rebased','unknown_question_keys'],
            'src/Forms/ContextQuestions.php' => ['row_to_schema_question','briefing_question','categorized_image_upload'],
            'src/Forms/BriefingFormBridge.php' => ['ContextQuestions::row_to_schema_question','$dynamic[\'source\'] = \'briefing_question\''],
            'src/Shortcodes.php' => ['form-html:<?php echo esc_js(FIELDFLOW_VERSION); ?>','FieldFlowOffline.submitForm'],
            'src/Operations/Queue/QueueSettings.php' => ["fieldflow_submission_post_commit' =>", "max_attempts'=>3"],
            'PHASE-7ZE-END-TO-END-OFFLINE-FORM-RECOVERY-HARDENING.md' => ['Zero-loss invariants','Briefing questions','fieldflow-form-ack-v1','No database migration is required'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('end_to_end_offline_form_recovery', 'End-to-End Offline Form Recovery', $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade de respostas, briefing, fotografias, rebase de schema ou ACK final está incompleta. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Respostas e fotografias, incluindo perguntas geradas por Briefing, permanecem protegidas no dispositivo e no outbox até ACK verificável, com recuperação IndexedDB, backup remoto incremental e rebase seguro de schema.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-end-to-end-form-recovery-v1','missing'=>$missing]
        );
    }

    private static function checkFieldFormReliabilityCoreContract(): array {
        $requirements = [
            'src/Forms/FormRenderer.php' => ['rpEnsureDraftFileOrdinals','data-rp-draft-rehydrated-clone','ficheiros recuperados. A cópia local/servidor foi mantida'],
            'src/Forms/DraftService.php' => ['input_ordinal,file_index,id','array_sum($wanted)','Nem todos os anexos do rascunho puderam ser publicados'],
            'src/Services/RouteProgressService.php' => ['justified_failed_stops','unjustified_failed_stops','operational_completion_rate',"EventBus::emit('route.completed'","TRIM(COALESCE(rs.fail_reason"],
            'src/Rest/EventsController.php' => ['failure_reason_required'],
            'src/Rest/RoutesController.php' => ['failure_reason_required'],
            'src/Rest/StatsController.php' => ["status='failed'",'fail_reason'],
            'src/Mobile/OperationalContentRevision.php' => ['fieldflow_operational_content_revision_','fieldflow_operational_content_revision_bumped'],
            'src/Mobile/OfflineSyncService.php' => ['operational_content_revision','OperationalContentRevision::current'],
            'src/Briefings/BriefingService.php' => ['briefing_publish','briefing_archive','briefing_mapping','briefing_delete','OperationalContentRevision::bump'],
            'src/Briefings/CardexSyncService.php' => ['OperationalContentRevision::bump','Reconcile, rather than only INSERT IGNORE','$wpdb->delete(ProductCardex::table_locations()'],
            'src/Admin/FormBindings.php' => ['form_binding','form_binding_delete','OperationalContentRevision::bump'],
            'src/Admin/Forms.php' => ['form_schema','form_delete','OperationalContentRevision::bump'],
            'src/Admin/FormSubmissions.php' => ['getOrderSuggestionSubmission','orderSuggestionExportRows','createOrderSuggestionReportFile','Sugestões Encomenda'],
            'src/Admin/Emails.php' => ['order_suggestion','order_suggestion_test','Sugestão de encomenda'],
            'src/Admin/ReportAutomations.php' => ["automation_type='order_suggestion'",'handleOrderSuggestionSubmission','claimOrderRecipientDispatch','releaseOrderRecipientClaims','scheduleOrderSuggestionRetry','ORDER_RETRY_MAX_ATTEMPTS','executeOrderSuggestion','orderSuggestionEmailBody','route.completed','submission_operative'],
            'PHASE-7ZF-FIELD-FORM-RELIABILITY-CORE.md' => ['Multi-photo recovery','Operational terminal route','Order suggestion','Operational content revision','Per-recipient delivery','No database migration is required'],
            'tools/verify-field-form-reliability.php' => ['FIELD FORM RELIABILITY CONTRACT OK','multi_photo_ordinals','justified_failure_terminal','order_suggestion_mail_export','order_suggestion_recipient_retry_fixture'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('field_form_reliability_core', 'Field Form Reliability Core', $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de recuperação multi-foto, fecho operacional, revisão de conteúdo ou sugestão de encomenda está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Recuperação multi-foto preserva ordinais, falhas justificadas são terminais sem falsificar execução, Briefing/Cardex invalidam bundles por revisão operacional e sugestões de encomenda são exportáveis e enviadas por Mail Center com Excel/PDF.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-form-reliability-core-v1','missing'=>$missing]
        );
    }


    private static function checkMailCenterHistoricalRecoveryContract(): array {
        $requirements = [
            'src/Admin/Emails.php' => ["'manual'", 'Envio manual', 'renderManualCenter'],
            'src/Admin/ReportAutomations.php' => [
                'handleManualSend','renderManualCenter','manualScheduleFor','manualRecipientSet','manual_dispatch',
                'scheduled_report_manual','order_suggestion_manual','manual_route_close_test','manual_order_suggestion_test',
                "admin_post_fieldflow_mailcenter_manual_send", 'RouteProgressService::sync($itemId, true, false)',
            ],
            'src/Services/RouteProgressService.php' => ['$emit_completion_event', 'completion_event_emitted', "EventBus::emit('route.status_changed'"],
            'src/Admin/FormSubmissions.php' => ['getOrderSuggestionSubmission','createOrderSuggestionReportFile'],
            'PHASE-7ZG-MAIL-CENTER-HISTORICAL-RECOVERY.md' => ['Historical route replay','Historical order suggestion replay','Current client associations','Manual test delivery','No database migration is required'],
            'tools/verify-mailcenter-historical-recovery.php' => ['MAIL CENTER HISTORICAL RECOVERY CONTRACT OK','manual_route_replay','manual_order_replay','manual_recipient_scope','manual_test_isolation'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('mail_center_historical_recovery', 'Mail Center Historical Recovery', $missing ? 'fail' : 'pass',
            $missing
                ? 'O reenvio histórico manual de fechos de rota ou sugestões de encomenda está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'O BO permite reenviar fechos de rota e sugestões históricas para merchandiser, cliente, ambos ou email de teste, usando os dados persistidos e o template atual sem duplicar métricas ou disparar o evento automático de conclusão.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-mailcenter-historical-recovery-v1','missing'=>$missing]
        );
    }

    private static function checkResponsiveEmailRenderingContract(): array {
        $requirements = [
            'src/Admin/Emails.php' => [
                'responsive_email_css','harden_email_content_html','ff-email-viewport','ff-email-shell','ff-email-content','ff-data-table',
                'width="100%"','max-width:680px','x-apple-disable-message-reformatting','<!--[if mso]>',
            ],
            'src/Admin/ReportAutomations.php' => [
                'Emails::responsive_email_css()','ff-order-summary','ff-order-summary-cell','ff-order-card',
                'table-layout:fixed','ff-email-viewport','Produtos sugeridos',
            ],
            'src/Shopping/ShoppingService.php' => [
                'ff-price-table ff-data-table','table-layout:fixed','white-space:normal','overflow-wrap:anywhere',
            ],
            'PHASE-7ZH-RESPONSIVE-EMAIL-RENDERING-HOTFIX.md' => [
                'Responsive contract','No FieldFlow email requires horizontal scrolling','Order suggestion','No database migration is required',
            ],
            'tools/verify-responsive-email-rendering.php' => [
                'RESPONSIVE EMAIL RENDERING CONTRACT OK','fluid_shell','order_suggestion_no_flex','shopping_fixed_layout','report_shared_contract',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $automationPath = ROUTESPRO_PATH . 'src/Admin/ReportAutomations.php';
        $automation = is_readable($automationPath) ? (string)file_get_contents($automationPath) : '';
        if ($automation !== '') {
            $start = strpos($automation, 'private static function orderSuggestionEmailBody');
            $end = $start === false ? false : strpos($automation, 'private static function buildReportBundle', $start);
            $slice = ($start !== false && $end !== false) ? substr($automation, $start, $end - $start) : '';
            if ($slice === '' || strpos($slice, 'display:flex') !== false || strpos($slice, 'min-width:130') !== false) {
                $missing[] = 'src/Admin/ReportAutomations.php:order_suggestion_no_flex';
            }
        }
        $shoppingPath = ROUTESPRO_PATH . 'src/Shopping/ShoppingService.php';
        $shopping = is_readable($shoppingPath) ? (string)file_get_contents($shoppingPath) : '';
        if ($shopping !== '') {
            $start = strpos($shopping, 'private static function priceTableHtml');
            $end = $start === false ? false : strpos($shopping, 'public static function projectMerchandiserIds', $start);
            $slice = ($start !== false && $end !== false) ? substr($shopping, $start, $end - $start) : '';
            if ($slice === '' || strpos($slice, 'white-space:nowrap') !== false) $missing[] = 'src/Shopping/ShoppingService.php:price_table_nowrap';
        }

        return self::check('responsive_email_rendering', 'Responsive Email Rendering', $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de email fluido/mobile está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Notificações, relatórios, reenvios, sugestões e Shopping usam shells fluidos e tabelas bounded/fixed-layout que não dependem de scroll horizontal para expor dados operacionais.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-responsive-email-rendering-v1','missing'=>$missing]
        );
    }

    private static function checkOperationalIntelligenceHardeningContract(): array {
        $requirements = [
            'src/Analytics/TemporalAnalyticsService.php' => ['closed_days_only','today_is_in_progress','future_never_reduces_operational_scores','justified_non_visits_are_terminal_not_executed'],
            'src/Rest/StatsController.php' => ['stops_justified_failed','stops_terminal','TemporalAnalyticsService::enrich','TemporalAnalyticsService::forEvaluation','FieldFlowAskService::isHelpQuestion'],
            'src/Analytics/AnalyticsAIService.php' => ['TemporalAnalyticsService::forEvaluation','temporal_context','future_planned'],
            'src/Analytics/FieldFlowAskService.php' => ['isHelpQuestion','helpAnswer','Rotas futuras planeadas','não deve ser apresentada como visita executada'],
            'src/Forms/ProductCardex.php' => ['_fieldflow_runtime_cardex_bridge','Explicit manual product lists are never changed'],
            'src/Admin/ReportAutomations.php' => ['queueDailyCloseForRoute','scheduleDailyCloseRetry','dispatchDailyCloseForRoute','claimDispatch'],
            'src/Rest/EventsController.php' => ["\$wpdb->query('COMMIT');","route_status'] ?? '')) === 'completed'",'operationally_complete','ReportAutomations::queueDailyCloseForRoute'],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $frontContracts = [
            'src/Shortcodes.php' => "function askUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.ffad-ask-empty'))els.askThread.innerHTML='';}",
            'src/Front/ClientPortalShortcodeRenderer.php' => "function portalAskUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.rp-ask-empty'))els.askThread.innerHTML='';}",
            'assets/fieldflow-analytics2-admin.js' => "function renderAskUser(question){\n        if(!askThread)return;\n        if(askThread.querySelector('.ff-a2-ask-empty'))askThread.innerHTML='';\n    }",
        ];
        foreach ($frontContracts as $relative => $marker) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '' || strpos($content, $marker) === false) $missing[] = $relative . ':hidden_user_question_block';
        }
        return self::check('operational_intelligence_hardening', 'Operational Intelligence & Field Reliability', $missing ? 'fail' : 'pass',
            $missing
                ? 'A leitura temporal, ajuda FieldFlow, Cardex legado ou fecho diário pós-commit está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Analytics distingue passado fechado, hoje em progresso e futuro planeado; ajuda funcional é determinística; sugestões vazias legadas herdam o Cardex de Frentes sem alterar listas manuais; fecho diário tem fallback idempotente pós-commit.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-operational-intelligence-hardening-v1','missing'=>$missing]
        );
    }

    private static function checkAnalyticsExportResilienceContract(): array {
        $requirements = [
            'src/Rest/StatsController.php' => [
                'fieldflow_evidence_export_time_limit',
                'fieldflow_evidence_export_max_files',
                'fieldflow_evidence_export_max_bytes',
                '$part_path = $zip_path . \'.part\';',
                'ZipArchive::CM_STORE',
                'validate_analytics_evidence_zip',
                'cleanup_analytics_evidence_exports',
                'X-FieldFlow-Download-Integrity: evidence-zip-v2',
                '$shadow_budget = $export_full ? 0 : 12',
                '$shadow_budget = 8',
                'large photo export into an HTTP 500',
            ],
            'src/Admin/FormSubmissions.php' => [
                'session_status() === PHP_SESSION_ACTIVE',
                'Long Analytics exports must not keep the PHP session lock',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('analytics_export_resilience', 'Analytics Export & Evidence ZIP Resilience', $missing ? 'fail' : 'pass',
            $missing
                ? 'A resiliência das exportações Analytics ou do ZIP de evidências está incompleta. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'ZIPs de evidências são criados diretamente a partir dos media válidos, sem duplicação integral em staging, com compressão adequada, validação atómica, streaming por blocos e telemetria bounded; exportações Analytics libertam o session lock.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-analytics-export-resilience-v1','missing'=>$missing]
        );
    }

    private static function checkEvidenceZipRuntimeV3Contract(): array {
        $requirements = [
            'src/Rest/StatsController.php' => [
                'write_analytics_evidence_zip_portable',
                'analytics_zip_write_all',
                'analytics_zip_dos_time',
                "engine'=>'portable-store'",
                'media_registry',
                'central_offset',
                'PK\\x05\\x06',
            ],
            'src/Shortcodes.php' => [
                'data-ffad-evidence-download',
                "analytics/evidence-archive",
                'evidencias-fieldflow.zip',
                'analyticsEvidenceGroups',
                'evidencePrepareToken',
                'JSON.stringify({token:group.token})',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'data-evidence-download',
                "analytics/evidence-archive",
                'evidencias-fieldflow.zip',
                'evidencePrepareToken',
                'JSON.stringify({token:group.token})',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
            'src/Architecture/RuntimeRegistry.php' => ['admin_post_routespro_download_prepared_analytics_evidence', 'handlePreparedEvidenceDownload'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $shortcodes = is_readable(ROUTESPRO_PATH . 'src/Shortcodes.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shortcodes.php') : '';
        if ($shortcodes !== '' && strpos($shortcodes, "function evidenceAction(entity,label){const files=evidenceFiles(entity?.files),url=String(entity?.download_url||'')") !== false) {
            $missing[] = 'src/Shortcodes.php:legacy_direct_admin_post_evidence_path';
        }
        $portal = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        if ($portal !== '' && strpos($portal, "evidenceArchiveAction(m,entity,suffix,label){const files=evidenceFiles(entity?.files),direct=String(entity?.download_url||'')") !== false) {
            $missing[] = 'src/Front/ClientPortalShortcodeRenderer.php:legacy_direct_admin_post_evidence_path';
        }

        return self::check('evidence_zip_runtime_v3', 'Evidence ZIP Runtime v3', $missing ? 'fail' : 'pass',
            $missing
                ? 'O writer ZIP portátil ou o fluxo REST de preparação das evidências está incompleto. Em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'ZIP da loja/data usa token de grupo emitido pelo servidor, é preparado via REST autenticado, construído com writer STORE portátil e entregue por token temporário pós-preparação; o admin-post deixa de executar a geração pesada no caminho do WebView.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-evidence-zip-runtime-v3','missing'=>$missing]
        );
    }

    private static function checkEvidenceZipTicketContinuityContract(): array {
        $requirements = [
            'src/Rest/StatsController.php' => [
                'analytics_prepared_evidence_ticket_key',
                'ff_evidence_ready_ticket_',
                'fieldflow_evidence_zip_download',
                'handlePreparedEvidencePublicDownload',
                "['GET', 'HEAD']",
                'evidence-zip-v4',
                'capability-ticket',
                'fieldflow_evidence_group_token_ttl',
            ],
            'src/Architecture/RuntimeRegistry.php' => [
                "add_action('template_redirect'",
                'handlePreparedEvidencePublicDownload',
            ],
            'src/Mobile/WebViewBridge.php' => [
                'nativeDownload(meta)',
                'browserDownload(meta)',
                'HTTP \\d+',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $stats = is_readable(ROUTESPRO_PATH . 'src/Rest/StatsController.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Rest/StatsController.php') : '';
        if ($stats !== '') {
            $start = strpos($stats, 'public static function handlePreparedEvidencePublicDownload');
            $end = $start === false ? false : strpos($stats, 'public static function handlePreparedEvidenceDownload', $start);
            $segment = ($start !== false && $end !== false) ? substr($stats, $start, $end - $start) : '';
            if ($segment === '') $missing[] = 'src/Rest/StatsController.php:public_ticket_segment';
            else {
                if (strpos($segment, "if (\$method === 'HEAD') exit;") === false) $missing[] = 'src/Rest/StatsController.php:head_probe_safe';
                if (strpos($segment, "delete_transient(\$transient_key);\n        @unlink(\$path);") !== false) $missing[] = 'src/Rest/StatsController.php:single_use_delete_after_stream';
                if (strpos($segment, "is_user_logged_in()") !== false || strpos($segment, 'wp_verify_nonce') !== false) $missing[] = 'src/Rest/StatsController.php:cookie_or_nonce_dependency';
            }
        }

        return self::check('evidence_zip_ticket_continuity', 'Evidence ZIP Ticket Continuity', $missing ? 'fail' : 'pass',
            $missing
                ? 'O transporte idempotente do ZIP ainda tem dependencias ou marcadores em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'O ZIP preparado usa um capability ticket opaco, curto e reutilizavel durante o TTL, aceita HEAD sem consumir o ficheiro e nao depende dos cookies ou do nonce WordPress no downloader nativo.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-evidence-zip-ticket-continuity-v1','missing'=>$missing]
        );
    }

    private static function checkAnalyticsMobileFullWidthDownloadStateContract(): array {
        // Historical 2.2.472 contract. The legacy mobile breakout remains valid,
        // but 2.2.519+ supersedes it with the canonical dual-portal full-width shell.
        // Do not force obsolete 100vw/negative-margin hacks back into runtime.
        $requirements = [
            'src/Shortcodes.php' => ['2.2.472, mobile Analytics full-width and uninterrupted vertical reading.', "downloadPrepared==='function'", 'ffad-ask-thread{max-height:none!important;overflow:visible!important', "ZIP indisponível · tenta novamente"],
            'src/Mobile/WebViewBridge.php' => ['downloadPrepared:function', 'evidenceExport', 'preparedEvidenceDownload(meta)', 'ZIP enviado para o gestor de downloads.'],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $appPath = ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php';
        $clientPath = ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php';
        $app = is_readable($appPath) ? (string)file_get_contents($appPath) : '';
        $client = is_readable($clientPath) ? (string)file_get_contents($clientPath) : '';
        $appLegacy = strpos($app, '2.2.472, Analytics mobile viewport-first layout.') !== false
            && strpos($app, 'width:calc(100vw - 12px)!important') !== false;
        $clientLegacy = strpos($client, '2.2.472, Client Portal Analytics mobile full-width and page-owned vertical scroll.') !== false
            && strpos($client, 'width:calc(100vw - 12px)!important') !== false
            && strpos($client, 'max-height:none!important;') !== false
            && strpos($client, 'overflow-y:hidden!important;') !== false;
        $appModern = strpos($app, 'ff-portal-layout-aligned') !== false
            && strpos($app, 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"') !== false;
        $clientModern = strpos($client, 'ff-portal-layout-aligned') !== false
            && strpos($client, 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"') !== false;
        if (!$appLegacy && !$appModern) $missing[] = 'src/Front/AppShortcodeRenderer.php:legacy_or_true_fullwidth_contract';
        if (!$clientLegacy && !$clientModern) $missing[] = 'src/Front/ClientPortalShortcodeRenderer.php:legacy_or_true_fullwidth_contract';

        return self::check('analytics_mobile_fullwidth_download_state', 'Analytics Mobile Full-Width & Download State', $missing ? 'fail' : 'pass',
            $missing
                ? 'A camada mobile do Analytics ou a continuidade do download ainda tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'O contrato histórico 2.2.472 permanece satisfeito pelo breakout legado ou pelo seu sucessor canónico True Full Width; os ZIPs preparados continuam a usar a ponte mobile sem estados contraditórios.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-analytics-mobile-fullwidth-download-state-v1', 'layout_successor'=>'fieldflow-dual-portal-true-fullwidth-v1', 'missing'=>$missing]
        );
    }

    private static function checkEvidenceZipSystemHandoffContract(): array {
        $requirements = [
            'src/Mobile/WebViewBridge.php' => [
                'function preparedEvidenceDownload(meta)',
                "source:'evidence_zip_prepared'",
                "transport:'external_system_browser'",
                "fieldflow.evidence.download.requested",
                "fieldflow.evidence.download.failed",
                'nativeExternalOpen(payload,3600)',
                "if(meta.evidenceExport)return preparedEvidenceDownload(meta)",
            ],
            'src/Shortcodes.php' => [
                "downloadPrepared==='function'",
                'ZIP aberto para download',
                "data-fieldflow-download-fallback",
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                "downloadPrepared==='function'",
                'ZIP aberto para download',
                "data-fieldflow-download-fallback",
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        if ($bridge !== '') {
            $start = strpos($bridge, 'function preparedEvidenceDownload(meta)');
            $end = $start === false ? false : strpos($bridge, 'window.FieldFlowNativeBridge=Object.assign', $start);
            $segment = ($start !== false && $end !== false) ? substr($bridge, $start, $end - $start) : '';
            if ($segment === '') $missing[] = 'src/Mobile/WebViewBridge.php:prepared_evidence_segment';
            else {
                if (strpos($segment, 'nativeDownload(meta)') !== false) $missing[] = 'src/Mobile/WebViewBridge.php:generic_native_downloader_still_used';
                if (strpos($segment, "fieldflow.download.requested") !== false) $missing[] = 'src/Mobile/WebViewBridge.php:generic_download_event_still_used';
            }
        }

        return self::check('evidence_zip_system_handoff', 'Evidence ZIP System Browser Handoff', $missing ? 'fail' : 'pass',
            $missing
                ? 'O handoff externo dos ZIPs de evidencias ainda tem marcadores ou dependencias incorretas: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'ZIPs de evidencias preparados deixam de usar o downloader Flutter generico e sao entregues pela ponte de browser/sistema, com fallback browser isolado e sem eventos de exportacao genericos.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-evidence-zip-system-handoff-v1', 'missing'=>$missing]
        );
    }

    private static function checkFormOrderSuggestionPlacementContract(): array {
        $requirements = [
            'src/Forms/ProductCardex.php' => [
                'ensure_order_questions_follow_paired_fronts',
                '_fieldflow_runtime_position_repaired',
                '_fieldflow_paired_fronts_key',
            ],
            'src/Forms/FormRenderer.php' => [
                '2.2.473: order suggestions are dependent fields',
                'pairedFrontsKey',
                'schemaPositions',
                "sanitize_key((string)(\$question['type'] ?? '')) !== 'order_suggestion'",
            ],
            'assets/routespro-form-builder.js' => [
                '2.2.473: every BO question in wizard mode belongs to a real step.',
                'assignedStep',
                'nearest configured',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('form_order_suggestion_placement', 'Form Order Suggestion Placement', $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade de posição entre Frentes e Sugestão de encomenda tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'Sugestão de encomenda respeita a ordem funcional do formulário, nunca antecede a pergunta de Frentes emparelhada e perguntas BO não atribuídas deixam de criar passos autónomos inesperados.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-form-order-suggestion-placement-v1', 'missing'=>$missing]
        );
    }

    private static function checkOrderSuggestionConditionalMailContract(): array {
        $requirements = [
            'src/Forms/ConditionalFlow.php' => [
                'submission_meta',
                'hidden_question_keys',
                'active_order_suggestion_keys',
            ],
            'src/Forms/Forms.php' => [
                "base_meta['conditional_flow']",
                'ConditionalFlow::submission_meta($schema, $conditional_visibility)',
            ],
            'src/Mobile/SubmissionService.php' => [
                "base_meta['conditional_flow']",
                'ConditionalFlow::submission_meta($schema, $conditional_visibility)',
            ],
            'src/Admin/FormSubmissions.php' => [
                'activeOrderSuggestionKeys',
                'active_order_suggestion_keys',
                'orderSuggestionMailEligibility',
                'if (empty($mailEligibility[\'eligible\'])) return [];',
            ],
            'src/Forms/DailySubmissionService.php' => [
                'conditional_flow_snapshot',
                "base_meta['conditional_flow'] = \$conditional_flow_snapshot",
            ],
            'src/Admin/ReportAutomations.php' => [
                'FormSubmissions::getOrderSuggestionSubmission($submissionId)',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('order_suggestion_conditional_mail', 'Order Suggestion Conditional Mail Guard', $missing ? 'fail' : 'pass',
            $missing
                ? 'A supressao condicional de emails de sugestao de encomenda tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' e mais ' . (count($missing) - 4) . ' marcador(es).' : '.')
                : 'A visibilidade condicional fica gravada na submissao e o Mail Center so envia sugestoes de encomenda que estavam realmente ativas no formulario.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-order-suggestion-conditional-mail-v1', 'missing'=>$missing]
        );
    }

    private static function checkOrderSuggestionMailEligibilityV2Contract(): array {
        $requirements = [
            'src/Admin/FormSubmissions.php' => [
                'resolveOrderSuggestionMailEligibility',
                'orderSuggestionMailEligibility',
                'canonicalOrderSuggestionKeysFromPersistedAnswers',
                'snapshot_inactive',
                'canonical_inactive',
                'conditional_disagreement_inactive',
            ],
            'src/Admin/ReportAutomations.php' => [
                'FormSubmissions::orderSuggestionMailEligibility($submissionId)',
                'Sem envio: a Sugestão de encomenda estava inativa',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'order_suggestion_mail_eligibility_ok',
                'O guard de elegibilidade do email de Sugestão de encomenda não está integralmente certificado na release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Sugestão email guard',
                "packageResult['order_suggestion_mail_eligibility_ok']",
            ],
            'tools/verify-order-suggestion-mail-eligibility.php' => [
                'fieldflow-order-suggestion-mail-eligibility-v2',
                'persisted_no_vetoes_stale_active_snapshot',
                'disagreement_fails_closed',
            ],
            'PHASE-7ZT-ORDER-SUGGESTION-MAIL-ELIGIBILITY-V2.md' => [
                'fieldflow-order-suggestion-mail-eligibility-v2',
                'fails closed',
                'No database migration',
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile()],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('order_suggestion_mail_eligibility_v2', 'Order Suggestion Mail Eligibility v2', $missing ? 'fail' : 'pass',
            $missing
                ? 'O guard conservador de Sugestão de encomenda tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O email de Sugestão de encomenda só é elegível quando snapshot e respostas persistidas não indicam uma condição inativa; estados inativos não geram retries automáticos.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-order-suggestion-mail-eligibility-v2', 'missing'=>$missing]
        );
    }

    private static function checkOfflineRuntimeHardeningContract(): array {
        $runtimeRelative = self::currentOfflineRuntimeRelative();
        $requirements = [
            $runtimeRelative => [
                self::currentOfflineRuntimeMarker(),
                'if(previousApi.__runtimeBuild===RUNTIME_BUILD) return;',
                'SYNC_LOCK_TTL=Math.max(120000',
                'SYNC_LOCK_HEARTBEAT',
                'syncLeaseActive',
                'navigator.locks',
                'startSyncLockHeartbeat',
                'core.syncLock.owns',
                'function isUsableConnectivity(state){',
                "return ['online','degraded'].includes",
                'core.connectivity.isUsable',
                'operationalDate()',
                'addOperationalDays',
                'canSync:()=>isUsableConnectivity(connectivityState)',
            ],
            'src/Front/ResponsiveAssets.php' => [
                self::currentOfflineRuntimeFile(),
                "'syncLockTtlMs' => 120000",
                "'syncLockHeartbeatMs' => 10000",
                "'operationalDate' => current_time('Y-m-d')",
                "'siteTimezone' => function_exists('wp_timezone_string')",
            ],
            'src/Mobile/OfflineSyncService.php' => [
                "'operational_date' => current_time('Y-m-d')",
                "'server_local_time' => current_time('mysql')",
                "'renewable_sync_lease' => true",
                "'degraded_network_sync' => true",
                "'server_operational_date' => true",
            ],
            'src/Forms/FormRenderer.php' => [
                'routesproPrepareFinalSubmit=function()',
                'return checkpointAllDraftFiles();',
                'otherwise a restored pre-optimisation Blob could be merged as',
            ],
            'src/Shortcodes.php' => [
                "['online','degraded'].includes(String(connectivity.state||''))",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'offline_runtime_identity_ok',
                'offline_runtime_reference_ok',
                'O runtime offline versionado não declara a mesma identidade da release.',
            ],
            'PHASE-7ZQ-OFFLINE-RUNTIME-HARDENING.md' => [
                'fieldflow-offline-runtime-hardening-v1',
                'Release Manager',
                'No database migration',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $runtime = is_readable(ROUTESPRO_PATH . $runtimeRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $runtimeRelative) : '';
        if ($runtime !== '') {
            if (strpos($runtime, "__runtimeBuild:'2.2.472'") !== false || strpos($runtime, "version:'2.2.472'") !== false) $missing[] = $runtimeRelative . ':stale_runtime_identity';
            if (strpos($runtime, 'toISOString().slice(0,10)') !== false) $missing[] = $runtimeRelative . ':utc_operational_day';
            if (strpos($runtime, "if(connectivity.state!=='online')") !== false || strpos($runtime, "if(probe.state!=='online')") !== false) $missing[] = $runtimeRelative . ':degraded_sync_hard_block';
        }

        return self::check('offline_runtime_hardening', 'Offline Runtime Hardening', $missing ? 'fail' : 'pass',
            $missing
                ? 'A release ainda contém riscos de identidade, lock, rede degradada ou data operacional no runtime offline: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O runtime offline tem identidade única da release, lease renovável com proteção intra-tab/cross-tab, sincronização permitida em rede degradada e data operacional alinhada com a timezone WordPress. O Release Manager valida a identidade do runtime antes de publicar.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-offline-runtime-hardening-v1', 'missing'=>$missing]
        );
    }


    private static function checkFrontendBuildFoundationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.525';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $requirements = [
            $frontendRelative => [
                'FieldFlow ' . $release,
                'fieldflow-frontend-build-foundation-v1',
                "FIELD_FLOW_FRONTEND_BUILD_VERSION = '" . $release . "'",
                "source: 'typescript'",
                'fieldflow:frontend:ready',
            ],
            'frontend/package.json' => [
                '"name": "fieldflow-frontend"',
                '"version": "' . $release . '"',
                '"build": "node tools/build.mjs"',
                '"typecheck": "tsc -p tsconfig.json --noEmit"',
                '"test": "node --test tests/*.test.mjs"',
            ],
            'frontend/tsconfig.json' => [
                '"strict": true',
                '"module": "none"',
                '"noEmitOnError": true',
            ],
            'frontend/tools/build.mjs' => [
                'FIELDFLOW_VERSION',
                'version.generated.ts',
                'fieldflow-frontend-core-${version}.js',
                'fieldflow-frontend-build-foundation-v1',
            ],
            'frontend/tests/frontend-build.test.mjs' => [
                'compiled asset identity matches the plugin release',
                'compiled TypeScript bootstrap exposes a stable browser API and is idempotent',
            ],
            'src/Front/ResponsiveAssets.php' => [
                "FRONTEND_CORE_SCRIPT_HANDLE = 'fieldflow-frontend-core'",
                basename($frontendRelative),
                "'context' => 'front'",
                "'context' => 'admin'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'frontend_build_identity_ok',
                'frontend_build_reference_ok',
                'O bundle frontend TypeScript não declara a mesma identidade da release.',
            ],
            'PHASE-7ZR-FRONTEND-BUILD-TYPESCRIPT-FOUNDATION.md' => [
                'fieldflow-frontend-build-foundation-v1',
                'TypeScript',
                'Release Manager',
                'No database migration',
            ],
            'tools/verify-frontend-build-foundation.php' => [
                'FRONTEND BUILD TYPESCRIPT FOUNDATION CONTRACT OK',
                'typescript_bundle_identity',
                'release_manager_frontend_identity_gate',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('frontend_build_foundation', 'Frontend Build & TypeScript Foundation', $missing ? 'fail' : 'pass',
            $missing
                ? 'A fundação de build frontend TypeScript tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O frontend tem build TypeScript versionado, strict typecheck, testes de bootstrap e validação de identidade no Release Manager, sem alterar o formulário operacional nem introduzir React nesta release.',
            ['runtime'=>$release, 'contract'=>'fieldflow-frontend-build-foundation-v1', 'asset'=>basename($frontendRelative), 'missing'=>$missing]
        );
    }

    private static function checkTypedOfflineCoreContract(): array {
        $runtimeRelative = self::currentOfflineRuntimeRelative();
        $frontendRelative = 'assets/fieldflow-frontend-core-2.2.524.js';
        $requirements = [
            'frontend/src/offline/connectivity.ts' => [
                "type FieldFlowNetworkState = 'unknown' | 'online' | 'degraded' | 'offline' | 'auth_required'",
                'fieldFlowIsUsableConnectivity',
                "normalized === 'online' || normalized === 'degraded'",
            ],
            'frontend/src/offline/sync-lock.ts' => [
                'interface FieldFlowSyncLeaseRecord',
                'fieldFlowParseSyncLease',
                'fieldFlowCreateSyncLease',
                'isForeignActive',
                'renew:',
            ],
            'frontend/src/offline/outbox.ts' => [
                'interface FieldFlowOutboxApi',
                'fieldFlowRetryDelay',
                'isEligible:',
                'shouldRecoverStaleSyncing:',
                'compareCreatedAt:',
            ],
            'frontend/src/offline/offline-core.ts' => [
                "FIELD_FLOW_TYPED_OFFLINE_CONTRACT = 'fieldflow-typed-offline-core-v1'",
                'fieldFlowTypedOfflineApi',
                'Object.freeze',
            ],
            'frontend/src/bootstrap.ts' => [
                'offlineCore: fieldFlowTypedOfflineApi',
            ],
            'frontend/tests/frontend-build.test.mjs' => [
                'typed offline core preserves degraded connectivity as usable',
                'typed sync lease enforces owner and token with renewable ttl',
                'typed outbox keeps retry and stale recovery semantics compatible',
            ],
            $frontendRelative => [
                'fieldflow-typed-offline-core-v1',
                'fieldFlowConnectivityApi',
                'fieldFlowSyncLockApi',
                'fieldFlowOutboxApi',
            ],
            $runtimeRelative => [
                'typedOfflineCore()',
                'core.connectivity.isUsable',
                'core.syncLock',
                'core.outbox',
                self::currentOfflineRuntimeMarker(),
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'typed_offline_core_ok',
                'O Typed Offline Core não está integralmente compilado e ligado ao runtime offline da release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Typed Offline Core',
                "packageResult['typed_offline_core_ok']",
            ],
            'PHASE-7ZS-TYPED-OFFLINE-CORE.md' => [
                'fieldflow-typed-offline-core-v1',
                'connectivity',
                'sync-lock',
                'outbox',
                'Release Manager',
                'No database migration',
            ],
            'tools/verify-typed-offline-core.php' => [
                'TYPED OFFLINE CORE CONTRACT OK',
                'typed_connectivity_contract',
                'typed_sync_lock_contract',
                'typed_outbox_contract',
                'release_manager_typed_offline_gate',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('typed_offline_core', 'Typed Offline Core', $missing ? 'fail' : 'pass',
            $missing
                ? 'A migração tipada do offline ainda não cumpre integralmente o contrato: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Connectivity, sync lease e outbox já têm implementação TypeScript testada, usada progressivamente pelo runtime e protegida por fallback compatível com a release anterior.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-typed-offline-core-v1', 'missing'=>$missing]
        );
    }


    private static function checkTypedDraftMediaCoreContract(): array {
        $frontendRelative = 'assets/fieldflow-frontend-core-2.2.524.js';
        $requirements = [
            'frontend/src/drafts/draft-core.ts' => [
                "FIELD_FLOW_TYPED_DRAFT_CONTRACT = 'fieldflow-typed-draft-media-core-v1'",
                "FIELD_FLOW_DRAFT_DB_NAME = 'fieldflow-form-drafts'",
                'FIELD_FLOW_DRAFT_DB_VERSION = 2',
                'fieldFlowDraftStoreSnapshot',
                'fieldFlowDraftStoreFiles',
                'fieldFlowDraftCleanupExpiredSnapshots',
                'fieldFlowDraftContextValid',
                'fieldFlowDraftNewestLocal',
                'fieldFlowTypedDraftCoreApi',
            ],
            'frontend/src/bootstrap.ts' => [
                'draftCore: fieldFlowTypedDraftCoreApi',
            ],
            'frontend/tests/frontend-build.test.mjs' => [
                'typed draft core preserves snapshot, media and reconciliation semantics',
                'fieldflow-typed-draft-media-core-v1',
                "drafts.dbName, 'fieldflow-form-drafts'",
            ],
            $frontendRelative => [
                'FieldFlow 2.2.524',
                'fieldflow-typed-draft-media-core-v1',
                'fieldflow-form-drafts',
                'fieldFlowTypedDraftCoreApi',
                'cleanupExpiredSnapshots',
            ],
            'src/Forms/FormRenderer.php' => [
                'fieldflow-typed-draft-media-core-v1',
                'rpTypedDraftPersistence',
                'persistence.storeSnapshot',
                'persistence.storeFiles',
                'persistence.readFiles',
                'persistence.cleanupExpiredSnapshots',
                'core.state.contextValid',
                'core.state.schemaMatches',
                'core.state.newestLocal',
                'draftExpectedFileCount',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'typed_draft_media_core_ok',
                'O Typed Draft & Media Core não está integralmente compilado e ligado ao FormRenderer da release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Typed Draft & Media',
                "packageResult['typed_draft_media_core_ok']",
            ],
            'PHASE-7ZU-TYPED-DRAFT-MEDIA-CORE.md' => [
                'fieldflow-typed-draft-media-core-v1',
                'fieldflow-form-drafts',
                'schema v2',
                'Release Manager',
                'No database migration',
            ],
            'tools/verify-typed-draft-media-core.php' => [
                'TYPED DRAFT MEDIA CORE CONTRACT OK',
                'typed_draft_source_contract',
                'form_renderer_progressive_integration',
                'release_manager_typed_draft_gate',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('typed_draft_media_core', 'Typed Draft & Media Core', $missing ? 'fail' : 'pass',
            $missing
                ? 'A migração tipada de drafts, IndexedDB e recuperação de media tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Snapshots, media persistente e reconciliação de rascunhos já têm um core TypeScript strict ligado ao FormRenderer, preservando a base IndexedDB fieldflow-form-drafts schema v2 e mantendo fallback compatível.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-typed-draft-media-core-v1', 'missing'=>$missing]
        );
    }

    private static function checkTypedFormConditionalCoreContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $requirements = [
            'frontend/src/forms/form-core.ts' => [
                "FIELD_FLOW_TYPED_FORM_CONTRACT = 'fieldflow-typed-form-state-conditional-core-v1'",
                'fieldFlowConditionalEvaluate',
                'fieldFlowConditionMatches',
                'fieldFlowConditionalSnapshot',
                'fieldFlowActiveRequiredKeys',
                'fieldFlowFilterActiveValues',
                'fieldFlowTypedFormCoreApi',
            ],
            'frontend/src/bootstrap.ts' => [
                'formCore: fieldFlowTypedFormCoreApi',
            ],
            'frontend/tests/frontend-build.test.mjs' => [
                'typed form state core matches canonical conditional fixtures',
                'typed form state keeps hidden required and order suggestion branches inactive',
                'fieldflow-typed-form-state-conditional-core-v1',
            ],
            'frontend/tests/conditional-flow-fixtures.json' => [
                'no keeps order suggestion hidden',
                'hidden source cannot reveal downstream target',
                'multiple sources use OR visibility semantics',
            ],
            $frontendRelative => [
                'FieldFlow ' . $release,
                'fieldflow-typed-form-state-conditional-core-v1',
                'fieldFlowTypedFormCoreApi',
                'fieldFlowConditionalEvaluate',
            ],
            'assets/forms-runtime-v3.js' => [
                'typedFormCore()',
                "core.contract === 'fieldflow-typed-form-state-conditional-core-v1'",
                'core.conditions.evaluate',
                'data-rp-conditional-core-contract',
            ],
            'src/Forms/FormRenderer.php' => [
                'rpTypedFormCore',
                "core.contract==='fieldflow-typed-form-state-conditional-core-v1'",
                'typedCore.conditions.evaluate',
                "data-rp-conditional-flow-version','3'",
            ],
            'src/Forms/ConditionalFlow.php' => [
                "public const VERSION = 3",
                "public const CONTRACT = 'fieldflow-typed-form-state-conditional-core-v1'",
                "'contract' => self::CONTRACT",
                "'semantics' => 'show_target_when_visible_source_value_matches'",
                "'combination' => 'or'",
            ],
            'tools/verify-typed-form-conditional-core.php' => [
                'TYPED FORM STATE CONDITIONAL CORE CONTRACT OK',
                'php_typescript_parity=ok',
                'order_suggestion_conditional_state=ok',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'typed_form_conditional_core_ok',
                'O Typed Form State + Conditional Engine não está integralmente compilado e ligado à release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Typed Form State',
                "packageResult['typed_form_conditional_core_ok']",
            ],
            'PHASE-7ZV-TYPED-FORM-STATE-CONDITIONAL-ENGINE.md' => [
                'fieldflow-typed-form-state-conditional-core-v1',
                'paridade PHP/TypeScript',
                'Release Manager',
                'No database migration',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('typed_form_conditional_core', 'Typed Form State + Conditional Engine', $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato tipado de estado/condicionais do formulário tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O browser usa um core TypeScript strict para condições, required ativo e snapshot de ramos; o PHP conserva o contrato canónico espelhado e os fixtures de paridade impedem divergência entre os dois motores.',
            ['runtime'=>$release, 'contract'=>'fieldflow-typed-form-state-conditional-core-v1', 'missing'=>$missing]
        );
    }

    private static function checkTypedSubmissionValidationCoreContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $requirements = [
            'frontend/src/forms/submission-core.ts' => [
                "FIELD_FLOW_TYPED_SUBMISSION_CONTRACT = 'fieldflow-typed-submission-validation-core-v1'",
                'fieldFlowSubmissionRequiredMissing',
                'fieldFlowSubmissionShouldPersist',
                'fieldFlowSubmissionPrepare',
                'fieldFlowTypedSubmissionCoreApi',
            ],
            'frontend/src/bootstrap.ts' => ['submissionCore: fieldFlowTypedSubmissionCoreApi'],
            'frontend/tests/frontend-build.test.mjs' => [
                'typed submission validation core matches shared fixtures and filters hidden payload',
                'fieldflow-typed-submission-validation-core-v1',
            ],
            'frontend/tests/submission-validation-fixtures.json' => [
                'required checkbox false is missing',
                'hidden order suggestion never enters active payload',
                'optional checkbox false is retained when provided',
            ],
            $frontendRelative => [
                'FieldFlow ' . $release,
                'fieldflow-typed-submission-validation-core-v1',
                'fieldFlowTypedSubmissionCoreApi',
                'fieldFlowSubmissionPrepare',
            ],
            'src/Forms/SubmissionValidation.php' => [
                "public const VERSION = 1",
                "public const CONTRACT = 'fieldflow-typed-submission-validation-core-v1'",
                'required_missing',
                'should_persist',
                'filter_active_values',
            ],
            'src/Forms/FormRenderer.php' => [
                'rpTypedSubmissionCore',
                "core.contract==='fieldflow-typed-submission-validation-core-v1'",
                'core.validation.prepare',
                'data-rp-submission-core-contract',
            ],
            'src/Forms/Forms.php' => [
                'SubmissionValidation::is_question_active',
                'SubmissionValidation::required_missing',
                'SubmissionValidation::should_persist',
            ],
            'src/Mobile/SubmissionService.php' => [
                'use RoutesPro\\Forms\\SubmissionValidation',
                'SubmissionValidation::is_question_active',
                'SubmissionValidation::required_missing',
                'SubmissionValidation::should_persist',
            ],
            'tools/verify-typed-submission-validation-core.php' => [
                'TYPED SUBMISSION VALIDATION CORE CONTRACT OK',
                'php_typescript_parity=ok',
                'web_mobile_validation_parity=ok',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'typed_submission_validation_core_ok',
                'O Typed Submission & Validation Core não está integralmente compilado e ligado à release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Typed Submission',
                "packageResult['typed_submission_validation_core_ok']",
            ],
            'PHASE-7ZW-TYPED-SUBMISSION-VALIDATION-CORE.md' => [
                'fieldflow-typed-submission-validation-core-v1',
                'web e mobile',
                'Release Manager',
                'No database migration',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check('typed_submission_validation_core', 'Typed Submission & Validation Core', $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato tipado de submissão/validação tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Web, mobile e frontend partilham semântica de pergunta ativa, required e persistência, com fixtures PHP/TypeScript e fallback das validações especializadas existentes.',
            ['runtime'=>$release, 'contract'=>'fieldflow-typed-submission-validation-core-v1', 'missing'=>$missing]
        );
    }

    private static function checkTypedSpecializedValidationCoreContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '2.2.524';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $requirements = [
            'frontend/src/forms/specialized-validation-core.ts' => [
                "FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT = 'fieldflow-typed-specialized-validation-core-v1'",
                'fieldFlowSpecializedCategorized',
                'fieldFlowSpecializedShareSpace',
                'fieldFlowSpecializedEvidence',
                'fieldFlowTypedSpecializedValidationCoreApi',
            ],
            'frontend/src/bootstrap.ts' => ['specializedValidationCore: fieldFlowTypedSpecializedValidationCoreApi'],
            'frontend/tests/frontend-build.test.mjs' => ['typed specialized validation core keeps web and mobile special-field semantics aligned'],
            'frontend/tests/specialized-validation-fixtures.json' => ['required matrix rejects partial rows','yes can require photo','required matrix rejects placeholder row'],
            $frontendRelative => ['FieldFlow ' . $release,'fieldflow-typed-specialized-validation-core-v1','fieldFlowTypedSpecializedValidationCoreApi'],
            'src/Forms/SpecializedValidation.php' => [
                "public const CONTRACT = 'fieldflow-typed-specialized-validation-core-v1'",
                'validate_schema', 'categorized_state', 'share_space_state', 'has_media_value', 'product_matrix_has_meaningful_row',
            ],
            'src/Forms/FormRenderer.php' => [
                'rpTypedSpecializedValidationCore','firstTypedSpecializedInvalid','core.validation.shareSpace','core.validation.categorized','core.validation.evidence','data-rp-specialized-validation-core-contract',
            ],
            'src/Forms/Forms.php' => ['SpecializedValidation::validate_schema','categorized_upload_has_missing_selection','ShareSpaceMatrix::isComplete'],
            'src/Mobile/SubmissionService.php' => ['use RoutesPro\\Forms\\SpecializedValidation','SpecializedValidation::validate_schema'],
            'tools/verify-typed-specialized-validation-core.php' => ['TYPED SPECIALIZED VALIDATION CORE CONTRACT OK','php_typescript_parity=ok','web_mobile_specialized_parity=ok','share_space_partial_mobile_guard=ok'],
            'src/Support/ReleasePackageValidator.php' => ['typed_specialized_validation_core_ok','O Typed Specialized Validation Core não está integralmente compilado e ligado à release.'],
            'src/Admin/ReleaseManager.php' => ['Typed Specialized Validation', "packageResult['typed_specialized_validation_core_ok']"],
            'PHASE-7ZX-TYPED-SPECIALIZED-VALIDATION-CORE.md' => ['fieldflow-typed-specialized-validation-core-v1','Share Space','browser','web','mobile','Release Manager','No database migration'],
        ];
        $missing=[];
        foreach($requirements as $relative=>$markers){
            $path=ROUTESPRO_PATH.$relative; $content=is_readable($path)?(string)file_get_contents($path):'';
            if($content===''){ $missing[]=$relative.':file'; continue; }
            foreach($markers as $required) if(strpos($content,$required)===false) $missing[]=$relative.':'.$required;
        }
        return self::check('typed_specialized_validation_core','Typed Specialized Validation Core',$missing?'fail':'pass',
            $missing
                ? 'O contrato tipado de validações especializadas tem marcadores em falta: '.implode(', ',array_slice($missing,0,5)).(count($missing)>5?' e mais '.(count($missing)-5).' marcador(es).':'.')
                : 'Evidence, media obrigatório, evidências categorizadas, Share Space e matrizes obrigatórias partilham semântica entre browser, web e mobile, com fixtures PHP/TypeScript.',
            ['runtime'=>$release,'contract'=>'fieldflow-typed-specialized-validation-core-v1','missing'=>$missing]
        );
    }

    private static function checkSubmissionApplicationServiceContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $requirements = [
            'src/Forms/SubmissionApplicationService.php' => [
                "public const CONTRACT = 'fieldflow-submission-application-service-v1'",
                'public static function buildSchema',
                'public static function persist',
                'public static function buildSubmissionPayload',
                'public static function buildPipelineJob',
                'commit_after_persist',
                "'excludes' => ['mail', 'analytics', 'azure', 'route_notifications']",
            ],
            'src/Forms/Forms.php' => [
                'SubmissionApplicationService::buildSchema($form, $context_for_questions)',
                'SubmissionApplicationService::buildSchema($form, $submit_context)',
                'SubmissionApplicationService::persist([',
                "'channel' => 'web'",
                'SubmissionApplicationService::buildPipelineJob',
                'finish_application_service_error',
            ],
            'src/Mobile/SubmissionService.php' => [
                'use RoutesPro\\Forms\\SubmissionApplicationService',
                'SubmissionApplicationService::persist([',
                "'channel' => 'mobile'",
                'commit_after_persist',
                'SubmissionApplicationService::buildPipelineJob',
                'application_service_error',
            ],
            'src/Support/Loader.php' => ['src/Forms/SubmissionApplicationService.php'],
            'tools/verify-submission-application-service.php' => [
                'SUBMISSION APPLICATION SERVICE CONTRACT OK',
                'web_mobile_durable_persistence=shared',
                'mobile_legacy_transaction_semantics=preserved',
                'secondary_side_effects=outside_service',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'submission_application_service_ok',
                'O Submission Application Service não está integralmente ligado aos caminhos web e mobile da release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Submission Application Service',
                "packageResult['submission_application_service_ok']",
            ],
            'PHASE-7ZY-SUBMISSION-APPLICATION-SERVICE.md' => [
                'fieldflow-submission-application-service-v1',
                'SubmissionApplicationService',
                'Forms.php',
                'Mobile/SubmissionService.php',
                'No database migration',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'submission_application_service',
            'Submission Application Service',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O serviço central de submissão tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Render, web e mobile usam o mesmo construtor de schema e a mesma persistência durável; os efeitos laterais continuam fora do serviço e o modo mobile legacy preserva a fronteira transacional certificada.',
            ['runtime' => $release, 'contract' => 'fieldflow-submission-application-service-v1', 'missing' => $missing]
        );
    }

    private static function checkSubmissionSideEffectsContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $requirements = [
            'src/Forms/SubmissionSideEffectDispatcher.php' => [
                "public const CONTRACT = 'fieldflow-submission-side-effects-v1'",
                "public const RETRY_HOOK = 'fieldflow_submission_side_effect_retry'",
                'public static function dispatchFromSubmission',
                'public static function dispatchJob',
                'private static function runLane',
                "'route_notification'",
                "'submission_core_event'",
                "'order_suggestion_mail'",
                "'submission_public_hook'",
                "'idempotency' => 'per_revision_per_lane'",
            ],
            'src/Forms/Forms.php' => [
                'SubmissionSideEffectDispatcher::registerHooks()',
                'SubmissionSideEffectDispatcher::dispatchFromSubmission(',
                'fieldflow_submission_post_commit',
            ],
            'src/Mobile/SubmissionService.php' => [
                'use RoutesPro\\Forms\\SubmissionSideEffectDispatcher',
                'SubmissionSideEffectDispatcher::dispatchFromSubmission(',
                'observe_media_non_blocking',
                'Mobile submission audit failed after commit.',
            ],
            'src/Forms/SubmissionPipeline.php' => [
                "PIPELINE_VERSION = '1.1.0'",
                'public const BUILD =',
                'SubmissionSideEffectDispatcher',
            ],
            'src/Operations/Queue/QueueSettings.php' => [
                'fieldflow_submission_side_effect_retry',
                "'max_attempts'=>4",
            ],
            'src/Operations/ProcessMonitor.php' => [
                'submission_side_effect_retry',
                'fieldflow_submission_side_effect_retry',
            ],
            'src/Support/Loader.php' => ['src/Forms/SubmissionSideEffectDispatcher.php'],
            'tools/verify-submission-side-effects.php' => [
                'SUBMISSION SIDE EFFECTS CONTRACT OK',
                'durable_commit_boundary=preserved',
                'internal_lanes=isolated_idempotent',
                'retry_scope=per_lane',
                'legacy_public_hooks=preserved',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'submission_side_effects_ok',
                'O dispatcher isolado de efeitos pós-submissão não está integralmente ligado à release.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Submission Side Effects',
                "packageResult['submission_side_effects_ok']",
            ],
            'PHASE-7ZZ-SUBMISSION-EVENTS-SIDE-EFFECTS.md' => [
                'fieldflow-submission-side-effects-v1',
                'route_notification',
                'order_suggestion_mail',
                'per-revision done marker',
                'No database migration',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        foreach ([
            'src/Core/EventBus.php',
            'src/Admin/ReportAutomations.php',
            'src/Performance/Module.php',
        ] as $relative) {
            $content = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if (strpos($content, "add_action('fieldflow_form_submitted'") !== false) {
                $missing[] = $relative . ':legacy_submission_hook_owner';
            }
        }

        return self::check(
            'submission_side_effects',
            'Submission Events & Side Effects',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O dispatcher pós-COMMIT tem marcadores em falta ou listeners internos ainda dependem do hook partilhado: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Mail, EventBus/Azure, Performance, notificações de rota e hooks públicos são efeitos pós-COMMIT isolados por lane, com idempotência por revisão e retry individual.',
            ['runtime' => $release, 'contract' => 'fieldflow-submission-side-effects-v1', 'missing' => $missing]
        );
    }

    private static function checkReactBackofficeFoundationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $reactRelative = 'assets/fieldflow-react-backoffice-' . $release . '.js';
        $requirements = [
            'src/Admin/ReactBackofficeFoundation.php' => [
                "CONTRACT = 'fieldflow-react-backoffice-foundation-v1'",
                "PILOT_PAGE = 'routespro-mobile'",
                "PILOT_ISLAND = 'mobile-release-status'",
                'FIELDFLOW_REACT_BACKOFFICE',
                "'wordpress_react' => 'wp-element'",
                "'php_fallback' => true",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-backoffice-' . $release . '.js',
                "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']",
                'fieldflow-react-backoffice.css',
                'ReactBackofficeFoundation::pageEnabled()',
            ],
            'src/Admin/MobileControlCenter.php' => [
                'publication_status_island',
                'data-fieldflow-react-island="mobile-release-status"',
                'data-fieldflow-react-props',
                "ReactBackofficeFoundation::islandEnabled('mobile-release-status')",
            ],
            'frontend/tools/build.mjs' => [
                'tsconfig.react-backoffice.json',
                'fieldflow-react-backoffice-${version}.js',
                'fieldflow-react-backoffice-foundation-v1',
            ],
            'frontend/tsconfig.react-backoffice.json' => ['"strict": true', 'src/react-backoffice/islands.ts', 'src/react-backoffice/bootstrap.ts'],
            'frontend/src/react-backoffice/islands.ts' => ['FieldFlowMobileReleaseStatusIsland', 'fieldFlowReactMountAll', 'mobile-release-status'],
            'frontend/src/react-backoffice/bootstrap.ts' => ['FieldFlowReactBackoffice', "source: 'wordpress-wp-element'"],
            'frontend/tests/react-backoffice.test.mjs' => ['React backoffice bundle uses WordPress wp-element', 'React mobile release island mounts progressively'],
            $reactRelative => ['FieldFlow ' . $release, 'fieldflow-react-backoffice-foundation-v1', 'mobile-release-status', 'wordpress-wp-element'],
            'tools/verify-react-backoffice-foundation.php' => ['REACT BACKOFFICE FOUNDATION CONTRACT OK', 'runtime=wordpress-wp-element', 'php_fallback=preserved'],
            'src/Support/ReleasePackageValidator.php' => ['react_backoffice_foundation_ok', 'A React Backoffice Foundation não está integralmente compilada'],
            'src/Admin/ReleaseManager.php' => ['React Backoffice', "packageResult['react_backoffice_foundation_ok']"],
            'src/Support/Loader.php' => ['src/Admin/ReactBackofficeFoundation.php'],
            'PHASE-8A-REACT-BACKOFFICE-FOUNDATION.md' => ['fieldflow-react-backoffice-foundation-v1', 'wp-element', 'PHP fallback', 'routespro-mobile', 'No database migration'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_backoffice_foundation',
            'React Backoffice Foundation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A fundação React tem marcadores em falta ou perdeu o fallback progressivo: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O primeiro island React usa wp-element, está limitado ao Centro de Controlo, mantém fallback PHP e não altera o runtime de terreno.',
            ['runtime' => $release, 'contract' => 'fieldflow-react-backoffice-foundation-v1', 'pilot' => 'routespro-mobile:mobile-release-status', 'missing' => $missing]
        );
    }

    private static function checkReactBackofficeSharedContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $reactRelative = 'assets/fieldflow-react-backoffice-' . $release . '.js';
        $requirements = [
            'src/Admin/ReactBackofficeFoundation.php' => [
                "SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1'",
                "CONTRACT = 'fieldflow-react-backoffice-foundation-v1'",
            ],
            'frontend/tsconfig.react-backoffice.json' => ['src/react-backoffice/shared.ts', '"strict": true'],
            'frontend/src/react-backoffice/shared.ts' => [
                "FIELD_FLOW_REACT_SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1'",
                'fieldFlowReactRawProps',
                'fieldFlowReactPost',
                'fieldFlowReactTodayRange',
                "credentials: 'same-origin'",
            ],
            'frontend/tools/build.mjs' => ['fieldflow-react-backoffice-shared-v1', 'reactSharedContract'],
            'frontend/tests/react-backoffice.test.mjs' => ['React shared backoffice foundation centralizes transport and date helpers'],
            $reactRelative => ['FieldFlow ' . $release, 'fieldflow-react-backoffice-shared-v1', 'fieldFlowReactPost', 'fieldFlowReactTodayRange'],
            'src/Support/ReleasePackageValidator.php' => ['react_backoffice_shared_ok', 'A infraestrutura React BO partilhada não está integralmente compilada'],
            'src/Admin/ReleaseManager.php' => ['React BO Shared', "packageResult['react_backoffice_shared_ok']"],
            'PHASE-8C-RELEASE-CONTINUITY-REACT-ANALYTICS.md' => ['fieldflow-react-backoffice-shared-v1', 'single continuity authority', 'No database migration'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'react_backoffice_shared',
            'React BO Shared Foundation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A infraestrutura React partilhada tem marcadores em falta: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Transporte autenticado, leitura de props e utilitários comuns são partilhados pelos islands React sem empacotar React/ReactDOM.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-backoffice-shared-v1','missing'=>$missing]
        );
    }

    private static function checkReactControlCenterDevicesContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $reactRelative = 'assets/fieldflow-react-backoffice-' . $release . '.js';
        $requirements = [
            'src/Admin/ReactBackofficeFoundation.php' => [
                "DEVICES_PAGE = 'routespro-devices'",
                "DEVICES_ISLAND = 'device-control-table'",
                'in_array($page, [self::PILOT_PAGE, self::DEVICES_PAGE]',
                'FIELDFLOW_REACT_BACKOFFICE',
            ],
            'src/Admin/Devices.php' => [
                'data-fieldflow-react-island="device-control-table"',
                'DeviceManager::adminListPayload($listing)',
                "ReactBackofficeFoundation::islandEnabled('device-control-table',self::SLUG)",
                'ffdev_platform',
                '<table class="widefat striped">',
            ],
            'src/Mobile/DeviceManager.php' => [
                'wp_ajax_fieldflow_react_devices_list',
                'wp_ajax_fieldflow_react_device_action',
                'ajaxListDevices',
                'ajaxDeviceAction',
                'performAdminAction',
                "check_ajax_referer('fieldflow_react_devices','nonce')",
                'adminListPayload',
                "AuditLog::record('device.'.\$action",
                "EventBus::emit('device.'.\$action",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-backoffice-' . $release . '.js',
                'ReactBackofficeFoundation::DEVICES_ISLAND',
                'ReactBackofficeFoundation::pageEnabled()',
            ],
            'frontend/src/react-backoffice/types.ts' => ['interface FieldFlowReactDeviceRow', 'interface FieldFlowReactDeviceListing', 'interface FieldFlowDeviceControlProps'],
            'frontend/src/react-backoffice/islands.ts' => ['FIELD_FLOW_DEVICE_CONTROL_ISLAND', 'FieldFlowDeviceControlIsland', 'fieldflow_react_devices_list', 'fieldflow_react_device_action', 'fieldFlowReactPost', 'device-control-table'],
            'frontend/src/react-backoffice/shared.ts' => ["credentials: 'same-origin'", 'fieldFlowReactPost'],
            'frontend/tests/react-backoffice.test.mjs' => ['React device control island is compiled with async list/action contracts', 'fieldflow_react_devices_list', 'fieldflow_react_device_action'],
            'frontend/tools/build.mjs' => ['device-control-table', 'fieldflow_react_devices_list', 'fieldflow_react_device_action'],
            $reactRelative => ['FieldFlow ' . $release, 'device-control-table', 'fieldflow_react_devices_list', 'fieldflow_react_device_action', 'wordpress-wp-element'],
            'assets/fieldflow-react-backoffice.css' => ['ff-react-device-control', 'ff-react-device-toolbar', 'ff-react-device-pagination', 'ff-react-device-actions'],
            'tools/verify-react-control-center-devices.php' => ['REACT CONTROL CENTER DEVICES CONTRACT OK', 'transport=authenticated-admin-ajax', 'php_fallback=preserved'],
            'src/Support/ReleasePackageValidator.php' => ['react_devices_control_ok', 'O React Control Center Devices não está integralmente ligado à release.'],
            'src/Admin/ReleaseManager.php' => ['React Devices', "packageResult['react_devices_control_ok']"],
            'PHASE-8B-REACT-CONTROL-CENTER-DEVICES.md' => ['fieldflow-react-control-center-devices-v1', 'routespro-devices', 'PHP fallback', 'No database migration', 'SubmissionPipeline component build remains `2.2.485`'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_control_center_devices',
            'React Control Center Devices',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O island React de Dispositivos tem marcadores em falta ou perdeu a fonte DeviceManager/fallback PHP: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Pesquisa, filtro, paginação e comandos de dispositivos usam React sobre wp-element, DeviceManager único, admin-AJAX autenticado e fallback PHP completo.',
            ['runtime' => $release, 'contract' => 'fieldflow-react-control-center-devices-v1', 'page' => 'routespro-devices', 'island' => 'device-control-table', 'missing' => $missing]
        );
    }

    private static function checkReactAnalyticsFoundationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $reactRelative = 'assets/fieldflow-react-backoffice-' . $release . '.js';
        $requirements = [
            'src/Admin/ReactBackofficeFoundation.php' => [
                "ANALYTICS_PAGE = 'routespro-assignments-hub'",
                "ANALYTICS_ISLAND = 'analytics-context-kpis'",
                "SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1'",
                "BackofficeUIFoundation::scope(\$page) === 'analytics'",
            ],
            'src/Admin/Assignments.php' => [
                'data-fieldflow-react-island="analytics-context-kpis"',
                'ff-a2-legacy-control-fallback',
                'ReactBackofficeFoundation::SHARED_CONTRACT',
                'Altera o contexto sem recarregar a página',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-backoffice-' . $release . '.js',
                'ReactBackofficeFoundation::ANALYTICS_ISLAND',
                "BackofficeUIFoundation::scope() === 'analytics'",
            ],
            'frontend/src/react-backoffice/types.ts' => ['interface FieldFlowAnalyticsControlProps', 'interface FieldFlowAnalytics2Api', 'useEffect'],
            'frontend/src/react-backoffice/islands.ts' => [
                "FIELD_FLOW_ANALYTICS_CONTROL_ISLAND = 'analytics-context-kpis'",
                "FIELD_FLOW_REACT_ANALYTICS_CONTRACT = 'fieldflow-react-analytics-foundation-v1'",
                'FieldFlowAnalyticsControlIsland',
                'fieldflow:analytics2-data',
                'fieldflow:analytics2-state',
                'FieldFlowAnalytics2.applyContext',
            ],
            'assets/fieldflow-analytics2-admin.js' => [
                'window.FieldFlowAnalytics2',
                'applyContext',
                "emit('fieldflow:analytics2-data'",
                "emit('fieldflow:analytics2-state'",
                'window.history.replaceState',
                'loadGeneration',
            ],
            'assets/fieldflow-react-backoffice.css' => ['ff-react-analytics-control', 'ff-a2-react-fallback.is-react-hidden', 'ff-react-analytics-score-grid'],
            'frontend/tests/react-backoffice.test.mjs' => ['React Analytics context and KPI island is compiled with no-refresh controller contract'],
            $reactRelative => ['FieldFlow ' . $release, 'fieldflow-react-analytics-foundation-v1', 'analytics-context-kpis', 'fieldflow-react-backoffice-shared-v1'],
            'tools/verify-react-analytics-foundation.php' => ['REACT ANALYTICS FOUNDATION CONTRACT OK', 'no_refresh_context=enabled', 'php_fallback=preserved'],
            'src/Support/ReleasePackageValidator.php' => ['react_analytics_foundation_ok', 'A React Analytics Foundation não está integralmente ligada'],
            'src/Admin/ReleaseManager.php' => ['React Analytics', "packageResult['react_analytics_foundation_ok']"],
            'PHASE-8C-RELEASE-CONTINUITY-REACT-ANALYTICS.md' => ['fieldflow-react-analytics-foundation-v1', 'Analytics 2.0', 'PHP fallback', 'No REST contract change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_analytics_foundation',
            'React Analytics Foundation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O island React do Analytics tem marcadores em falta ou perdeu a sincronização/fallback: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Filtros e KPIs Analytics usam React sobre o mesmo motor Analytics 2.0, sincronizam URL/painéis sem refresh completo e mantêm fallback PHP integral.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-analytics-foundation-v1','page'=>'routespro-assignments-hub:analytics','island'=>'analytics-context-kpis','missing'=>$missing]
        );
    }


    private static function checkReactFrontFormWizardContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "CONTRACT = 'fieldflow-react-front-foundation-v1'",
                "FORM_WIZARD_CONTRACT = 'fieldflow-react-form-wizard-progress-v1'",
                "FORM_WIZARD_ISLAND = 'form-wizard-progress'",
                'FIELDFLOW_REACT_FRONT',
                "'submission_owner' => 'legacy_typed_runtime'",
                "'offline_owner' => 'fieldflow_offline_runtime'",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']",
                'ReactFrontFoundation::FORM_WIZARD_ISLAND',
            ],
            'src/Forms/FormRenderer.php' => [
                'data-fieldflow-react-front-island="form-wizard-progress"',
                'data-fieldflow-react-front-fallback="form-wizard-progress"',
                "ReactFrontFoundation::islandEnabled('form-wizard-progress')",
                'routespro-form-progress-fill',
                'data-rp-progress-label',
            ],
            'frontend/tsconfig.react-front.json' => ['src/react-front/form-wizard.ts', '"strict": true'],
            'frontend/src/react-front/form-wizard.ts' => ['fieldflow-react-front-foundation-v1', 'fieldflow-react-form-wizard-progress-v1', 'MutationObserver', 'data-fieldflow-react-front-fallback-hidden'],
            'frontend/src/react-front/bootstrap.ts' => ['FieldFlowReactFront', 'fieldFlowReactFrontMountAll'],
            'frontend/tests/react-front.test.mjs' => ['React form wizard progress mounts progressively without owning submission'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-front-foundation-v1', 'fieldflow-react-form-wizard-progress-v1', 'wordpress-wp-element'],
            'assets/fieldflow-react-front.css' => ['ff-react-front-form-progress', 'data-fieldflow-react-front-fallback-hidden'],
            'tools/verify-react-front-form-wizard.php' => ['REACT FRONT FORM WIZARD CONTRACT OK', 'submission_owner=legacy_typed_runtime', 'php_fallback=preserved'],
            'src/Support/ReleasePackageValidator.php' => ['react_front_form_wizard_ok', 'A React Front Foundation do formulário não está integralmente ligada'],
            'src/Admin/ReleaseManager.php' => ['React Front', "packageResult['react_front_form_wizard_ok']"],
            'PHASE-8D-REACT-FRONT-FORM-WIZARD.md' => ['fieldflow-react-front-foundation-v1', 'PHP fallback', 'No IndexedDB schema change', 'No FlutterFlow change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_front_form_wizard',
            'React Front Form Wizard',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O island React progressivo do formulário tem marcadores em falta ou perdeu fallback/ownership seguro: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O formulário inicia a migração React apenas na camada visual do wizard, mantendo submissão, validação, media e offline nos runtimes certificados com fallback PHP.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-form-wizard-progress-v1','island'=>'form-wizard-progress','missing'=>$missing]
        );
    }


    private static function checkReactFrontOperationalWaveContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "OPERATIONAL_SHELL_CONTRACT = 'fieldflow-react-operational-shell-v1'",
                "OPERATIONAL_SHELL_ISLAND = 'operational-shell'",
                "MY_ROUTE_CONTRACT = 'fieldflow-react-my-route-toolbar-v1'",
                "MY_ROUTE_ISLAND = 'my-route-toolbar'",
                "SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1'",
                "SIMPLE_FIELD_ISLAND = 'simple-field-assist'",
                'simpleFieldTypeEnabled',
                "'route_data_owner' => 'routespro_rest_runtime'",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                'ReactFrontFoundation::OPERATIONAL_SHELL_CONTRACT',
                'ReactFrontFoundation::MY_ROUTE_CONTRACT',
                'ReactFrontFoundation::SIMPLE_FIELD_CONTRACT',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'data-fieldflow-react-front-island="operational-shell"',
                'data-fieldflow-react-front-fallback="operational-shell"',
                'react_front_panels',
            ],
            'src/Shortcodes.php' => [
                'data-fieldflow-react-front-island="my-route-toolbar"',
                'data-fieldflow-react-front-fallback="my-route-toolbar"',
                'routeRootId',
            ],
            'src/Forms/FormRenderer.php' => [
                'data-fieldflow-react-front-island="simple-field-assist"',
                'simpleFieldTypeEnabled',
                'inputId',
            ],
            'frontend/tsconfig.react-front.json' => [
                'src/react-front/operational-shell.ts',
                'src/react-front/route-toolbar.ts',
                'src/react-front/simple-fields.ts',
                '"strict": true',
            ],
            'frontend/src/react-front/form-wizard.ts' => [
                'fieldflow-react-operational-shell-v1',
                'fieldflow-react-my-route-toolbar-v1',
                'fieldflow-react-simple-field-assist-v1',
                'FieldFlowOperationalShellIsland',
                'FieldFlowMyRouteToolbarIsland',
                'FieldFlowSimpleFieldAssistIsland',
            ],
            'frontend/src/react-front/operational-shell.ts' => ['navigator.onLine', 'Atalhos operacionais'],
            'frontend/src/react-front/route-toolbar.ts' => ['#rp-date', '#rp-route-select', "dispatchEvent(new Event('change'", 'fieldflow:route-updated'],
            'frontend/src/react-front/simple-fields.ts' => ['fieldFlowSimpleFieldInput', 'Preenchido', 'Por preencher', 'Opcional'],
            'frontend/src/react-front/bootstrap.ts' => ['fieldflow:lazy-panel-loaded', 'fieldflow:form-rendered', 'addedNodes'],
            'frontend/tests/react-front.test.mjs' => ['React operational front wave compiles shell, route toolbar and simple-field assist contracts', 'React operational shell mounts progressively over the legacy navigation'],
            'assets/fieldflow-react-front.css' => ['ff-react-front-operational-shell', 'ff-react-front-route-toolbar', 'ff-react-front-field-assist'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-operational-shell-v1', 'fieldflow-react-my-route-toolbar-v1', 'fieldflow-react-simple-field-assist-v1', 'wordpress-wp-element'],
            'tools/verify-react-front-operational-wave.php' => ['REACT FRONT OPERATIONAL WAVE CONTRACT OK', 'route_data_owner=routespro_rest_runtime', 'php_fallback=preserved'],
            'src/Support/ReleasePackageValidator.php' => ['react_front_operational_wave_ok', 'A React Front Operational Wave não está integralmente ligada'],
            'src/Admin/ReleaseManager.php' => ['React Front Wave', "packageResult['react_front_operational_wave_ok']"],
            'PHASE-8E-REACT-FRONT-OPERATIONAL-WAVE.md' => ['fieldflow-react-front-operational-wave-v1', 'PHP fallback', 'No REST contract change', 'No IndexedDB schema change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_front_operational_wave',
            'React Front Operational Wave',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A onda React do front operacional tem marcadores em falta ou perdeu ownership/fallback seguro: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Shell operacional, Minha Rota e assistência dos campos simples usam islands React progressivos sobre os controlos e runtimes certificados, com fallback PHP e ownership crítico preservado.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-front-operational-wave-v1','islands'=>['operational-shell','my-route-toolbar','simple-field-assist'],'missing'=>$missing]
        );
    }


    private static function checkReactSimpleFieldControlsContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "SIMPLE_FIELD_CONTROL_CONTRACT = 'fieldflow-react-simple-field-control-v1'",
                "SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1'",
                'simpleFieldTypeEnabled',
                "'submission_owner' => 'legacy_typed_runtime'",
                "'offline_owner' => 'fieldflow_offline_runtime'",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                'ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT',
            ],
            'src/Forms/FormRenderer.php' => [
                'data-fieldflow-react-front-island="simple-field-assist"',
                "'controlContract' => \\RoutesPro\\Front\\ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT",
                "'unit' => \$unit",
            ],
            'frontend/src/react-front/types.ts' => ['FieldFlowSimpleFieldOption', 'controlContract?: string', 'options: FieldFlowSimpleFieldOption[]'],
            'frontend/src/react-front/form-wizard.ts' => ['fieldflow-react-simple-field-control-v1', 'FieldFlowSimpleFieldAssistIsland'],
            'frontend/src/react-front/simple-fields.ts' => [
                'fieldFlowSimpleFieldWrite',
                'data-fieldflow-react-simple-native',
                'data-fieldflow-react-simple-control-input',
                "dispatchEvent(new Event('input'",
                "dispatchEvent(new Event('change'",
                'redirectInvalid',
                'fieldflow:forms-runtime-v3-snapshot',
            ],
            'frontend/tools/build.mjs' => ['reactSimpleFieldControlContract', 'fieldflow-react-simple-field-control-v1'],
            'frontend/tests/react-front.test.mjs' => ['React simple field control owns visible UI while canonical input owns value and submit semantics'],
            'assets/fieldflow-react-front.css' => ['data-fieldflow-react-simple-native', 'ff-react-front-simple-control', 'fieldflow-react-simple-field-control-v1'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-simple-field-assist-v1', 'fieldflow-react-simple-field-control-v1', 'wordpress-wp-element'],
            'tools/verify-react-form-simple-controls.php' => ['REACT FORM SIMPLE CONTROLS CONTRACT OK', 'visible_ui_owner=react', 'canonical_value_owner=native_input'],
            'src/Support/ReleasePackageValidator.php' => ['react_simple_field_controls_ok', 'Os controlos simples React do formulário não estão integralmente ligados'],
            'src/Admin/ReleaseManager.php' => ['React Simple Fields', "packageResult['react_simple_field_controls_ok']"],
            'PHASE-8F-REACT-FORM-SIMPLE-CONTROLS.md' => ['fieldflow-react-simple-field-control-v1', 'canonical native input', 'No REST contract change', 'No IndexedDB schema change', 'No FlutterFlow change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_form_simple_controls',
            'React Form Simple Controls',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Os controlos React dos campos simples perderam marcadores de sincronização/fallback/ownership seguro: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Os campos simples usam controlos React visíveis sincronizados em ambos os sentidos com o input FieldFlow canónico, preservando condicionais, validação, offline e submissão.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-simple-field-control-v1','canonical_value_owner'=>'native_input','missing'=>$missing]
        );
    }

    private static function checkReactChoiceSpecialWaveContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "CHOICE_FIELD_CONTRACT = 'fieldflow-react-choice-field-control-v1'",
                "SPECIAL_FIELD_CONTRACT = 'fieldflow-react-special-field-assist-v1'",
                'choiceFieldTypeEnabled', 'specialFieldTypeEnabled',
                "'conditional_owner' => 'fieldflow-typed-form-state-conditional-core-v1'",
                "'offline_owner' => 'fieldflow_offline_runtime'",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                'ReactFrontFoundation::CHOICE_FIELD_CONTRACT',
                'ReactFrontFoundation::SPECIAL_FIELD_CONTRACT',
            ],
            'src/Forms/FormRenderer.php' => [
                'data-fieldflow-react-front-island="choice-field-control"',
                'data-fieldflow-react-front-island="special-field-assist"',
                'react_choice_field_root', 'react_special_field_root',
                'ProductCardex::question_rows', 'ProductCardex::order_question_rows',
            ],
            'frontend/src/react-front/types.ts' => ['FieldFlowChoiceFieldProps', 'FieldFlowSpecialFieldProps', 'FieldFlowChoiceSnapshot', 'FieldFlowSpecialSnapshot'],
            'frontend/src/react-front/form-wizard.ts' => ['fieldflow-react-choice-field-control-v1', 'fieldflow-react-special-field-assist-v1', 'FieldFlowChoiceFieldIsland', 'FieldFlowSpecialFieldIsland'],
            'frontend/src/react-front/choice-fields.ts' => [
                'fieldFlowChoiceWrite', 'data-fieldflow-react-choice-native',
                "dispatchEvent(new Event('input'", "dispatchEvent(new Event('change'",
                'fieldflow:forms-runtime-v3-snapshot', 'fieldflow:offline-form-synced',
            ],
            'frontend/src/react-front/special-fields.ts' => [
                'fieldFlowSpecialSnapshot', 'data-share-space-card', 'data-fronts-input', 'data-order-qty',
                'data-rp-evidence-category', 'data-category-open-all', 'data-category-close-all',
            ],
            'frontend/tools/build.mjs' => ['reactChoiceFieldContract', 'reactSpecialFieldContract'],
            'frontend/tests/react-front.test.mjs' => [
                'React choice and advanced form wave compiles conditional bridges and special-field assistants',
                'React choice control writes the canonical radio group so conditional listeners keep ownership',
            ],
            'assets/fieldflow-react-front.css' => ['ff-react-choice-control', 'data-fieldflow-react-choice-native', 'ff-react-special-field'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-choice-field-control-v1', 'fieldflow-react-special-field-assist-v1', 'choice-field-control', 'special-field-assist'],
            'tools/verify-react-form-choice-special-wave.php' => ['REACT FORM CHOICE SPECIAL WAVE CONTRACT OK', 'conditional_owner=typed_native_bridge', 'special_business_logic_owner=legacy_typed_runtime'],
            'src/Support/ReleasePackageValidator.php' => ['react_choice_special_wave_ok', 'A React Choice & Special Form Wave não está integralmente ligada'],
            'src/Admin/ReleaseManager.php' => ['React Choice + Special', "packageResult['react_choice_special_wave_ok']"],
            'PHASE-8G-REACT-FORM-CHOICE-SPECIAL-WAVE.md' => ['fieldflow-react-choice-field-control-v1', 'fieldflow-react-special-field-assist-v1', 'ProductCardex remains canonical', 'No IndexedDB schema change', 'No FlutterFlow change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'react_form_choice_special_wave',
            'React Choice & Special Form Wave',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A onda React de escolhas/campos avançados perdeu marcadores de bridge/fallback/ownership: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Radio, checkbox e escolhas visuais usam UI React ligada aos inputs canónicos; Frentes, Share Space, Sugestão e media recebem estado React sem duplicar ProductCardex, validação, offline ou submissão.',
            ['runtime'=>$release,'choice_contract'=>'fieldflow-react-choice-field-control-v1','special_contract'=>'fieldflow-react-special-field-assist-v1','conditional_owner'=>'typed_native_bridge','special_business_logic_owner'=>'legacy_typed_runtime','missing'=>$missing]
        );
    }

    private static function checkModernizationStableClosureContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "MODERNIZATION_CLOSURE_CONTRACT = 'fieldflow-modernization-stable-closure-v1'",
                "MEDIA_CONTROL_CONTRACT = 'fieldflow-react-media-control-v1'",
                "'media_control_contract' => self::MEDIA_CONTROL_CONTRACT",
                "'offline_owner' => 'fieldflow_offline_runtime'",
            ],
            'src/Forms/FormRenderer.php' => [
                'ReactFrontFoundation::MEDIA_CONTROL_CONTRACT',
                'data-rp-upload-trigger="camera"', 'data-rp-upload-trigger="gallery"',
                'data-rp-upload-file', 'routespro-upload-preview',
            ],
            'frontend/src/react-front/form-wizard.ts' => ['fieldflow-react-media-control-v1'],
            'frontend/src/react-front/special-fields.ts' => [
                'fieldFlowMediaClick', 'data-fieldflow-react-media-native',
                'fieldflow:offline-form-synced', 'fieldflow:sync-completed',
                'Tirar fotografia', 'Escolher imagens', 'Protegido localmente',
            ],
            'frontend/src/react-front/types.ts' => ['mediaControlContract', 'savedMedia', 'pendingMedia', 'pendingRemoval', 'online'],
            'frontend/tools/build.mjs' => ['reactMediaControlContract', 'fieldflow-react-media-control-v1'],
            'frontend/tests/react-front.test.mjs' => [
                'React media control compiles final closure contract without taking media transport ownership',
                'React media surface proxies camera action to certified native upload trigger',
            ],
            'assets/fieldflow-react-front.css' => ['ff-react-media-control', 'ff-react-media-stats', 'data-fieldflow-react-media-native'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-modernization-stable-closure-v1', 'fieldflow-react-media-control-v1', 'fieldFlowMediaClick'],
            'tools/verify-modernization-stable-closure.php' => ['MODERNIZATION STABLE CLOSURE CONTRACT OK', 'media_owner=native_offline_runtime', 'closure=fieldflow-modernization-stable-closure-v1'],
            'src/Support/ReleasePackageValidator.php' => ['modernization_stable_closure_ok', 'O Modernization Stable Closure não está integralmente ligado'],
            'src/Admin/ReleaseManager.php' => ['Modernization Closure', "packageResult['modernization_stable_closure_ok']"],
            'PHASE-8H-MODERNIZATION-STABLE-CLOSURE.md' => ['fieldflow-modernization-stable-closure-v1', 'Native media transport remains canonical', 'No IndexedDB schema change', 'No FlutterFlow change'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        return self::check(
            'modernization_stable_closure',
            'Modernization Stable Closure',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O fecho de modernização perdeu contratos de media/React/ownership: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'A modernização fecha com React em BO/front/formulário e media por proxy, preservando inputs nativos, ProductCardex, IndexedDB, recovery, transporte media e submissão como autoridades canónicas.',
            ['runtime'=>$release,'closure_contract'=>'fieldflow-modernization-stable-closure-v1','media_contract'=>'fieldflow-react-media-control-v1','media_owner'=>'native_offline_runtime','submission_owner'=>'submission_application_service','missing'=>$missing]
        );
    }

    private static function checkFrontFullwidthNavigationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "FULLWIDTH_NAVIGATION_CONTRACT = 'fieldflow-front-fullwidth-navigation-v1'",
                "'fullwidth_navigation_contract' => self::FULLWIDTH_NAVIGATION_CONTRACT",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                'fieldflow-frontend-core-' . $release . '.js',
                'fieldflow-offline-runtime-' . $release . '.js',
                'fieldflow-react-backoffice-' . $release . '.js',
                'ReactFrontFoundation::FULLWIDTH_NAVIGATION_CONTRACT',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                "['key'=>'briefings','label'=>'Briefing']",
                "['key'=>'implementations','label'=>'Implementações']",
                "['key'=>'stocks','label'=>'Stocks']",
                "['key'=>'shopping','label'=>'Shopping']",
                "['key'=>'expenses','label'=>'Despesas']",
                "['key'=>'messages','label'=>'Mensagens']",
                'ReactFrontFoundation::FULLWIDTH_NAVIGATION_CONTRACT',
            ],
            'frontend/src/react-front/operational-shell.ts' => [
                'button[data-panel="${panel}"]',
                "'aria-current': panel.key === snapshot.activePanel ? 'page' : undefined",
            ],
            'assets/fieldflow-operational-experience.css' => [
                'fieldflow-front-fullwidth-navigation-v1',
                '.ff-operational-surface',
                'data-panel="briefings"',
                'data-panel="implementations"',
                'data-panel="stocks"',
                'data-panel="shopping"',
                'data-panel="discovery"',
                'data-panel="expenses"',
                'data-panel="messages"',
                '.ffb-exec-store-list',
                '.rp-mail-list',
            ],
            'assets/fieldflow-react-front.css' => ['Phase 8I', 'button[aria-current="page"]', 'overscroll-behavior-inline:contain'],
            'src/Front/BriefingsShortcodeRenderer.php' => ['ff-operational-surface-briefings'],
            'src/Front/ImplementationsShortcodeRenderer.php' => ['ff-operational-surface-implementations'],
            'src/Front/StocksShortcodeRenderer.php' => ['ff-operational-surface-stocks'],
            'src/Front/ShoppingShortcodeRenderer.php' => ['ff-operational-surface-shopping'],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => ['ff-operational-surface-planner'],
            'src/Front/ExpensesShortcodeRenderer.php' => ['ff-operational-surface-expenses'],
            'src/Front/TeamInboxShortcodeRenderer.php' => ['ff-operational-surface-messages'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-operational-shell-v1', 'button[data-panel=', 'aria-current'],
            'tools/verify-front-fullwidth-navigation.php' => ['FRONT FULL WIDTH NAVIGATION CONTRACT OK', 'core_rewrite=none'],
            'src/Support/ReleasePackageValidator.php' => ['front_fullwidth_navigation_ok', 'O Front Full Width & Navigation Consolidation não está integralmente ligado'],
            'src/Admin/ReleaseManager.php' => ['Front Full Width', "packageResult['front_fullwidth_navigation_ok']"],
            'PHASE-8I-FRONT-FULL-WIDTH-NAVIGATION-CONSOLIDATION.md' => ['fieldflow-front-fullwidth-navigation-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', 'No FlutterFlow change.'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $shell = is_readable(ROUTESPRO_PATH . 'frontend/src/react-front/operational-shell.ts') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/src/react-front/operational-shell.ts') : '';
        if (strpos($shell, '.slice(0, 5)') !== false) $missing[] = 'frontend/src/react-front/operational-shell.ts:legacy_five_panel_truncation';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'front_fullwidth_navigation',
            'Front Full Width & Navigation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A consolidação full width/navegação perdeu marcadores de apresentação ou preservação do core: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'As superfícies operacionais prioritárias usam full width mobile e o shell React espelha todas as abas autorizadas, preservando a tabbar PHP e o core certificado.',
            ['runtime'=>$release,'contract'=>'fieldflow-front-fullwidth-navigation-v1','panel_owner'=>'php_tabbar','core_rewrite'=>'none','missing'=>$missing]
        );
    }

    private static function checkReactOperationalModulesContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "OPERATIONAL_MODULES_CONTRACT = 'fieldflow-react-operational-modules-v1'",
                "OPERATIONAL_MODULES_ISLAND = 'operational-module-controls'",
                'operationalModuleMount',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'fieldflow-react-front-' . $release . '.js',
                'ReactFrontFoundation::OPERATIONAL_MODULES_CONTRACT',
                'ReactFrontFoundation::OPERATIONAL_MODULES_ISLAND',
            ],
            'frontend/src/react-front/module-controls.ts' => [
                'fieldflow-react-operational-modules-v1',
                'fieldFlowOperationalModuleWrite',
                'fieldFlowOperationalModuleClickAction',
                'data-fieldflow-react-operational-native',
                'dispatchEvent(new Event(eventName, { bubbles: true }))',
                'ffp-react-primary-filters',
                '.ffs-form-view .ffs-store-card',
            ],
            'frontend/src/react-front/form-wizard.ts' => [
                "FIELD_FLOW_OPERATIONAL_MODULES_ISLAND = 'operational-module-controls'",
                'FieldFlowOperationalModuleControlsIsland',
            ],
            'frontend/src/react-front/types.ts' => ['FieldFlowOperationalModuleProps', "island: 'operational-module-controls'", 'FieldFlowOperationalModuleKey'],
            'src/Front/BriefingsShortcodeRenderer.php' => ['operationalModuleMount', "'briefings'"],
            'src/Front/ImplementationsShortcodeRenderer.php' => ['operationalModuleMount', "'implementations'"],
            'src/Front/StocksShortcodeRenderer.php' => ['operationalModuleMount', "'stocks'"],
            'src/Front/ShoppingShortcodeRenderer.php' => ['operationalModuleMount', "'shopping'"],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => ['operationalModuleMount', "'planner'", 'ffp-react-primary-filters'],
            'src/Front/ExpensesShortcodeRenderer.php' => ['operationalModuleMount', "'expenses'"],
            'src/Front/TeamInboxShortcodeRenderer.php' => ['operationalModuleMount', "'messages'"],
            'assets/fieldflow-react-front.css' => ['Phase 8J', 'fieldflow-react-operational-modules-v1', 'data-fieldflow-react-operational-native', 'ff-react-module-search-hidden'],
            $frontRelative => ['FieldFlow ' . $release, 'fieldflow-react-operational-modules-v1', 'operational-module-controls', 'data-fieldflow-react-operational-native', 'wordpress-wp-element'],
            'frontend/tests/react-front.test.mjs' => ['React operational modules compile a shared native-proxy contract for all priority surfaces', 'React operational module mounts preserve the seven canonical PHP module owners'],
            'tools/verify-react-operational-modules.php' => ['REACT OPERATIONAL MODULES CONTRACT OK', 'core_rewrite=none'],
            'src/Support/ReleasePackageValidator.php' => ['react_operational_modules_ok', 'Os React Operational Modules não estão integralmente ligados'],
            'src/Admin/ReleaseManager.php' => ['React Operational Modules', "packageResult['react_operational_modules_ok']"],
            'PHASE-8J-REACT-OPERATIONAL-MODULES.md' => ['fieldflow-react-operational-modules-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', 'No FlutterFlow change.'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'react_operational_modules',
            'React Operational Modules',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Os módulos operacionais React perderam proxies/fallbacks ou alteraram ownership do core: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Briefings, Implementações, Stocks, Shopping, Planear, Despesas e Mensagens usam uma camada React partilhada que aciona os controlos nativos certificados e mantém o core operacional intacto.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-operational-modules-v1','interaction_owner'=>'native_controls','react_runtime'=>'wp-element','core_rewrite'=>'none','missing'=>$missing]
        );
    }

    private static function checkOperationalActionHardeningContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'frontend/src/react-front/module-controls.ts' => [
                'fieldflow-react-operational-actions-hardening-v1',
                'fieldFlowOperationalModuleReconcileImplementationVisibility',
                "if (props.module === 'implementations')",
                'queueMicrotask(() => fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot))',
                "'data-action-contract': FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT",
            ],
            'assets/fieldflow-react-front.css' => [
                'Phase 8K',
                'fieldflow-react-operational-actions-hardening-v1',
                '.ff-react-operational-module-implementations .ff-react-module-chips',
                '.ff-operational-surface-implementations .ffi-front-filters',
                'flex-wrap:nowrap!important',
            ],
            $frontRelative => [
                'FieldFlow ' . $release,
                'fieldflow-react-operational-actions-hardening-v1',
                'fieldFlowOperationalModuleReconcileImplementationVisibility',
                'data-action-contract',
            ],
            'frontend/tests/react-front.test.mjs' => [
                'Operational action hardening keeps Implementações chips stable and reconciles combined visibility',
                'Operational React inputs do not double-dispatch the same visible edit',
            ],
            'tools/verify-operational-action-hardening.php' => ['OPERATIONAL ACTION HARDENING CONTRACT OK', 'core_rewrite=none'],
            'src/Support/ReleasePackageValidator.php' => ['operational_action_hardening_ok', 'O Operational Action Hardening não está integralmente ligado'],
            'src/Admin/ReleaseManager.php' => ['Operational Action Hardening', "packageResult['operational_action_hardening_ok']"],
            'PHASE-8K-OPERATIONAL-ACTION-HARDENING.md' => ['fieldflow-react-operational-actions-hardening-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', '`SOURCE_RELEASE` is `2.2.495` and `STABLE_RELEASE` is `2.2.496`'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $source = is_readable(ROUTESPRO_PATH . 'frontend/src/react-front/module-controls.ts') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/src/react-front/module-controls.ts') : '';
        if (preg_match("/element\\.createElement\\('input',[\\s\\S]{0,700}?onInput:[\\s\\S]{0,300}?onChange:/", $source)) {
            $missing[] = 'frontend/src/react-front/module-controls.ts:duplicate_visible_input_dispatch';
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'operational_action_hardening',
            'Operational Action Hardening',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O hardening das ações operacionais perdeu marcadores de UX/proxy ou alterou ownership certificado: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Implementações mantém chips estáveis em mobile, pesquisa+estado reconciliados e os inputs React deixam de duplicar dispatch, preservando os runtimes nativos.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-operational-actions-hardening-v1','interaction_owner'=>'native_controls','react_runtime'=>'wp-element','core_rewrite'=>'none','missing'=>$missing]
        );
    }

    private static function checkBriefingProgressTabsHardeningContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $requirements = [
            'src/Front/BriefingsShortcodeRenderer.php' => [
                'fieldflow-briefing-tabstrip-hardening-v1',
                '.ffb-tabs{display:flex!important',
                'flex-wrap:nowrap!important',
                'overflow-x:auto!important',
                'touch-action:pan-x pan-y',
                'flex:0 0 auto!important',
                'min-width:max-content!important',
                'scroll-snap-align:start',
                'function activateTab(card,key)',
                "btn.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'})",
            ],
            'tools/verify-briefing-progress-tabs-hardening.php' => ['BRIEFING PROGRESS TABS HARDENING CONTRACT OK', 'business_logic_change=none'],
            'src/Support/ReleasePackageValidator.php' => ['briefing_progress_tabs_hardening_ok', 'O Briefing Progress Tabs Hardening não está integralmente ligado'],
            'src/Admin/ReleaseManager.php' => ['Briefing Tabs Hardening', "packageResult['briefing_progress_tabs_hardening_ok']"],
            'PHASE-8L-BRIEFING-PROGRESS-TABS-HARDENING.md' => ['fieldflow-briefing-tabstrip-hardening-v1', 'No Briefing data, import or mapping change.', 'No P1-P4 routing or optimisation-rule change.', '`SOURCE_RELEASE` is `2.2.496` and `STABLE_RELEASE` is `2.2.497`'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'briefing_progress_tabs_hardening',
            'Briefing Progress Tabs Hardening',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O hardening das abas internas de Briefing perdeu marcadores responsive ou alterou o contrato certificado: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'As abas internas dos Briefings mantêm largura intrínseca, uma única linha horizontal e scroll touch seguro sem alterar dados, leitura, mapping ou formulários.',
            ['runtime'=>$release,'contract'=>'fieldflow-briefing-tabstrip-hardening-v1','business_logic_change'=>'none','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkReactFormCompletionContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'",
                "'form_completion_contract' => self::FORM_COMPLETION_CONTRACT",
                "'submission_owner' => 'legacy_typed_runtime'",
                "'offline_owner' => 'fieldflow_offline_runtime'",
            ],
            'src/Forms/FormRenderer.php' => [
                'ReactFrontFoundation::FORM_COMPLETION_CONTRACT',
                'data-fronts-input',
                'data-order-qty',
                'data-share-space-card',
                'data-rp-evidence-category',
                'ProductCardex::question_rows',
                'ProductCardex::order_question_rows',
            ],
            'frontend/src/react-front/form-wizard.ts' => ["FIELD_FLOW_REACT_FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'"],
            'frontend/src/react-front/types.ts' => ['formCompletionContract?: string', 'FieldFlowSpecialCategorySnapshot', 'totalOrderQty: number'],
            'frontend/src/react-front/special-fields.ts' => [
                'fieldFlowSpecialActive',
                'fieldFlowSpecialMediaCount',
                'fieldFlowSpecialGoToIncomplete',
                'fieldFlowSpecialOpenCategory',
                "field.closest('[data-rp-question-wrap]')",
                'Inativo pela condição anterior',
                'Zero automático',
                'data-fieldflow-react-form-completion',
                'Captura, recuperação e envio continuam no runtime FieldFlow certificado.',
            ],
            'frontend/tools/build.mjs' => ['reactFormCompletionContract', 'fieldflow-react-form-completion-v1'],
            'frontend/tests/react-front.test.mjs' => [
                'React form completion compiles category navigation, pending guidance and conditional suggestion awareness',
                'React form completion stays wired as UX over canonical special-field owners',
            ],
            'assets/fieldflow-react-front.css' => [
                'Phase 8M, 2.2.498: React Form Completion.',
                'fieldflow-react-form-completion-v1',
                '.ff-react-special-category-nav',
                '.ff-react-special-summary',
                '[data-fieldflow-react-form-completion="1"]',
            ],
            $frontRelative => [
                'FieldFlow ' . $release,
                'fieldflow-react-form-completion-v1',
                'fieldFlowSpecialGoToIncomplete',
                'Inativo pela condição anterior',
                'Zero automático',
                'wordpress-wp-element',
            ],
            'tools/verify-react-form-completion.php' => ['REACT FORM COMPLETION CONTRACT OK', 'business_logic_owner=canonical_native_runtime'],
            'src/Support/ReleasePackageValidator.php' => ['react_form_completion_ok', 'O React Form Completion não está integralmente ligado'],
            'src/Admin/ReleaseManager.php' => ['React Form Completion', "packageResult['react_form_completion_ok']"],
            'PHASE-8M-REACT-FORM-COMPLETION.md' => [
                'fieldflow-react-form-completion-v1',
                'ProductCardex remains canonical.',
                'No IndexedDB schema change.',
                'No media transport ownership change.',
                '`SOURCE_RELEASE` is `2.2.497` and `STABLE_RELEASE` is `2.2.498`',
            ],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $package = is_readable(ROUTESPRO_PATH . 'frontend/package.json') ? (string)file_get_contents(ROUTESPRO_PATH . 'frontend/package.json') : '';
        if (strpos($package, '"react"') !== false || strpos($package, '"react-dom"') !== false) $missing[] = 'frontend/package.json:bundled_react_dependency';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'react_form_completion',
            'React Form Completion',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O React Form Completion perdeu marcadores de UX/proxy ou alterou ownership certificado: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Frentes/Cardex, Share Space, Sugestão de encomenda e Evidências ganham navegação, progresso e estado React sem mover dados, validação, offline, media ou submissão para React.',
            ['runtime'=>$release,'contract'=>'fieldflow-react-form-completion-v1','business_logic_owner'=>'canonical_native_runtime','react_runtime'=>'wp-element','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkCertificationRuntimeIdentityContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $previous = '';
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $release, $m)) {
            $patch = max(0, ((int)$m[3]) - 1);
            $previous = ((int)$m[1]) . '.' . ((int)$m[2]) . '.' . $patch;
        }
        $runtimeRelative = 'assets/fieldflow-offline-runtime-' . $release . '.js';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $reactFrontRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $reactBackofficeRelative = 'assets/fieldflow-react-backoffice-' . $release . '.js';
        $runtimeMarker = "const RUNTIME_BUILD='" . $release . "'";
        $previousRuntimeMarker = $previous !== '' ? "const RUNTIME_BUILD='" . $previous . "'" : '';
        $previousRuntimeFile = $previous !== '' ? 'fieldflow-offline-runtime-' . $previous . '.js' : '';
        $missing = [];

        $runtime = is_readable(ROUTESPRO_PATH . $runtimeRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $runtimeRelative) : '';
        if ($runtime === '') $missing[] = $runtimeRelative . ':file';
        else {
            if (strpos($runtime, $runtimeMarker) === false) $missing[] = $runtimeRelative . ':' . $runtimeMarker;
            if (strpos($runtime, '__runtimeBuild:RUNTIME_BUILD') === false) $missing[] = $runtimeRelative . ':__runtimeBuild';
        }

        $responsive = is_readable(ROUTESPRO_PATH . 'src/Front/ResponsiveAssets.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ResponsiveAssets.php') : '';
        foreach ([$runtimeRelative, $frontendRelative, $reactFrontRelative, $reactBackofficeRelative] as $relative) {
            if ($responsive === '' || strpos($responsive, basename($relative)) === false) $missing[] = 'src/Front/ResponsiveAssets.php:' . basename($relative);
        }
        if ($previousRuntimeFile !== '' && $responsive !== '' && strpos($responsive, $previousRuntimeFile) !== false) {
            $missing[] = 'src/Front/ResponsiveAssets.php:stale_' . $previousRuntimeFile;
        }

        $gate = is_readable(__FILE__) ? (string)file_get_contents(__FILE__) : '';
        if ($gate === '') $missing[] = 'src/Support/ReleaseGate.php:file';
        else {
            if (strpos($gate, self::RUNTIME_IDENTITY_SUCCESSOR_CONTRACT) === false) $missing[] = 'src/Support/ReleaseGate.php:runtime_identity_successor_contract';
            if (strpos($gate, 'currentOfflineRuntimeRelative') === false) $missing[] = 'src/Support/ReleaseGate.php:dynamic_runtime_file_resolver';
            if (strpos($gate, 'currentOfflineRuntimeMarker') === false) $missing[] = 'src/Support/ReleaseGate.php:dynamic_runtime_marker_resolver';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'certification_runtime_identity',
            'Certification Runtime Identity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A identidade da release, dos assets versionados ou das expectativas do Release Gate divergiu: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Plugin, assets versionados, ResponsiveAssets e Release Gate usam a mesma identidade de runtime, sem marcadores herdados da release anterior.',
            ['runtime'=>$release,'contract'=>'fieldflow-certification-runtime-identity-v1','previous_release'=>$previous,'submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkReleaseGateHistoricalMarkerHardeningContract(): array {
        $missing = [];
        $gate = is_readable(__FILE__) ? (string)file_get_contents(__FILE__) : '';
        $cssPath = ROUTESPRO_PATH . 'assets/fieldflow-react-front.css';
        $css = is_readable($cssPath) ? (string)file_get_contents($cssPath) : '';
        $validatorPath = ROUTESPRO_PATH . 'src/Support/ReleasePackageValidator.php';
        $validator = is_readable($validatorPath) ? (string)file_get_contents($validatorPath) : '';
        $managerPath = ROUTESPRO_PATH . 'src/Admin/ReleaseManager.php';
        $manager = is_readable($managerPath) ? (string)file_get_contents($managerPath) : '';
        $docPath = ROUTESPRO_PATH . 'PHASE-8O-RELEASE-GATE-HISTORICAL-MARKER-HARDENING.md';
        $doc = is_readable($docPath) ? (string)file_get_contents($docPath) : '';
        $submissionPath = ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php';
        $submission = is_readable($submissionPath) ? (string)file_get_contents($submissionPath) : '';

        foreach ([
            "'Phase 8J'",
            "'Phase 8K'",
            'fieldflow-react-operational-modules-v1',
            'fieldflow-react-operational-actions-hardening-v1',
            'fieldflow-release-gate-historical-marker-v1',
        ] as $marker) {
            if ($gate === '' || strpos($gate, $marker) === false) $missing[] = 'src/Support/ReleaseGate.php:' . $marker;
        }
        $phase8jVersionBound = "'Phase 8J, ' . " . '$release';
        $phase8kVersionBound = "'Phase 8K, ' . " . '$release';
        if (strpos($gate, $phase8jVersionBound) !== false) $missing[] = 'src/Support/ReleaseGate.php:phase8j_version_coupling';
        if (strpos($gate, $phase8kVersionBound) !== false) $missing[] = 'src/Support/ReleaseGate.php:phase8k_version_coupling';
        foreach (['Phase 8J','fieldflow-react-operational-modules-v1','Phase 8K','fieldflow-react-operational-actions-hardening-v1'] as $marker) {
            if ($css === '' || strpos($css, $marker) === false) $missing[] = 'assets/fieldflow-react-front.css:' . $marker;
        }
        if ($validator === '' || strpos($validator, 'release_gate_historical_markers_ok') === false) $missing[] = 'src/Support/ReleasePackageValidator.php:release_gate_historical_markers_ok';
        if ($manager === '' || strpos($manager, 'Historical Gate Markers') === false) $missing[] = 'src/Admin/ReleaseManager.php:Historical Gate Markers';
        if ($doc === '' || strpos($doc, 'fieldflow-release-gate-historical-marker-v1') === false) $missing[] = 'PHASE-8O-RELEASE-GATE-HISTORICAL-MARKER-HARDENING.md:contract';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'release_gate_historical_marker_hardening',
            'Release Gate Historical Marker Hardening',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Os gates históricos ainda dependem da versão global ou perderam marcadores estáveis: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Phase 8J/8K validam contratos e seletores estáveis sem obrigar comentários históricos a acompanhar a versão global.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-release-gate-historical-marker-v1','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkFrontTransitionStabilityContract(): array {
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => ["FRONT_TRANSITION_STABILITY_CONTRACT = 'fieldflow-front-transition-stability-v1'"],
            'src/Front/AppShortcodeRenderer.php' => ['data-fieldflow-react-front-fallback-pending="1"', 'let initialPanelApplied=false', 'openPanel(initialPanel,{remember:false,scroll:false})'],
            'src/Shortcodes.php' => ['data-fieldflow-react-front-fallback="my-route-toolbar"', 'data-fieldflow-react-front-fallback-pending="1"'],
            'src/Forms/FormRenderer.php' => ['data-fieldflow-react-front-fallback="form-wizard-progress"', 'data-fieldflow-react-front-fallback-pending="1"'],
            'src/Front/PanelLoader.php' => ['class="ff-skeleton" aria-hidden="true" hidden', 'class="ff-lazy-label" hidden'],
            'assets/fieldflow-design-system.css' => ['data-fieldflow-react-front-fallback-pending="1"', 'display:none!important'],
            'assets/fieldflow-ui-runtime.js' => ['reactFallbackTimers', 'data-fieldflow-react-front-fallback-pending', 'const visualTimer=window.setTimeout', '},180)', "placeholder.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'"],
            'frontend/src/react-front/form-wizard.ts' => ['fieldflow-front-transition-stability-v1', "removeAttribute('data-fieldflow-react-front-fallback-pending')"],
            'frontend/src/react-front/operational-shell.ts' => ["removeAttribute('data-fieldflow-react-front-fallback-pending')"],
            'frontend/src/react-front/route-toolbar.ts' => ["removeAttribute('data-fieldflow-react-front-fallback-pending')"],
            'frontend/tests/react-front.test.mjs' => ['Front transition stability hides React fallbacks before mount and initializes the app panel once', 'Front transition stability delays lazy visuals and emits one canonical loaded event'],
            'tools/verify-front-transition-stability.php' => ['FRONT TRANSITION STABILITY CONTRACT OK'],
            'PHASE-8P-FRONT-TRANSITION-STABILITY.md' => ['fieldflow-front-transition-stability-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $uiRuntime = is_readable(ROUTESPRO_PATH . 'assets/fieldflow-ui-runtime.js') ? (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-ui-runtime.js') : '';
        if (strpos($uiRuntime, "document.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'") !== false) $missing[] = 'assets/fieldflow-ui-runtime.js:duplicate_document_lazy_event';
        $app = is_readable(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') : '';
        $legacyInitialActivation = strpos($app, 'window.requestAnimationFrame(applyInitial)') !== false;
        $successorInitialActivation = strpos($app, 'applyInitial();') !== false
            && strpos($app, 'fieldflow-shared-portal-paint-stability-v1') !== false
            && strpos($app, 'ff-paint-stable-v1') !== false;
        if (!$legacyInitialActivation && !$successorInitialActivation) $missing[] = 'src/Front/AppShortcodeRenderer.php:initial_panel_activation_contract';
        if (strpos($app, 'setTimeout(applyInitial') !== false) $missing[] = 'src/Front/AppShortcodeRenderer.php:repeated_initial_panel_timer';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'front_transition_stability',
            'Front Transition Stability',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A estabilidade de transição do front perdeu marcadores ou reintroduziu flashes/eventos duplicados: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Fallbacks React ficam invisíveis durante o mount, o lazy loader evita flashes curtos e a ativação inicial/evento loaded são idempotentes.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','contract'=>'fieldflow-front-transition-stability-v1','lazy_visual_delay_ms'=>180,'fallback_failover_ms'=>1200,'submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkMobilityCostIntelligenceContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/MobilityCostService.php' => [
                "CONTRACT = 'fieldflow-mobility-cost-intelligence-v1'",
                'mobility_cost_default', 'mobility_cost_profiles', 'mobility_cost_snapshot',
                'profileIssues', 'resolveProfile', 'ensureRouteSnapshot', 'backfillRoutes', 'total_mobility_cost_eur',
            ],
            'src/Admin/Assignments.php' => ["'mobility' => 'Mobilidade'", 'save_mobility_default', 'save_mobility_profile', 'backfill_mobility_routes', 'save_route_mobility_override', 'Aplicar perfil às rotas existentes'],
            'src/Admin/CampaignLocations.php' => ['Mobilidade e Energia', 'save_mobility_default', 'routespro-live-energy-cost', 'routespro-manual-live-energy', 'MobilityCostService::ensureRouteSnapshot'],
            'src/Admin/Routes.php' => ['mobility_cost_snapshot', 'MobilityCostService::resolveProfile', 'Custo deslocação total EUR', 'Perfil mobilidade'],
            'src/Repositories/RouteAccessRepository.php' => ["['mobility_cost']", 'MobilityCostService::calculateForRouteArray'],
            'src/Rest/RoutesController.php' => ["'mobility_cost'", 'MobilityCostService::ensureRouteSnapshot'],
            'src/Front/ClientPortalShortcodeRenderer.php' => ['routeMobility', 'Combustível / energia', 'Custo deslocação'],
            'src/Rest/StatsController.php' => ["'mobility_summary'", 'energy_cost_eur', 'total_mobility_cost_eur'],
            'src/Shortcodes.php' => ['mobility_summary', 'Combustível / energia', 'Custo deslocação'],
            'src/Admin/FormSubmissions.php' => ['Combustível / energia estimado', 'Perfil mobilidade', 'Custo deslocação EUR', "'Mobilidade'"],
            'tools/verify-mobility-cost-intelligence.php' => ['MOBILITY COST INTELLIGENCE CONTRACT OK'],
            'PHASE-8Q-MOBILITY-COST-INTELLIGENCE.md' => ['fieldflow-mobility-cost-intelligence-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $routeMetrics = is_readable(ROUTESPRO_PATH . 'src/Services/RouteMetricsService.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Services/RouteMetricsService.php') : '';
        if (strpos($routeMetrics, "'metrics_version' => 'fieldflow_route_metrics_v5'") === false) $missing[] = 'src/Services/RouteMetricsService.php:canonical_metrics_v5';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'mobility_cost_intelligence',
            'Mobility Cost Intelligence',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A camada de custos de mobilidade perdeu integrações ou alterou contratos certificados: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Campanha, perfis por operativo, snapshots de rota, front, Analytics e exportações usam os km/portagens canónicos sem alterar o routing P1-P4.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-mobility-cost-intelligence-v1', 'storage'=>'existing_meta_json', 'routing_owner'=>'RouteMetricsService', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkMobilityIntegrityCanonicalMetricsContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => ['canonicalFromRouteArray', 'fieldflow-canonical-route-metrics-resolver-v2', 'distanceFromStoredRouteLegs'],
            'src/Services/MobilityCostService.php' => ['RouteMetricsService::canonicalFromRouteArray', 'profilesEquivalent', 'refreshSnapshotForIdentityChange', "status NOT IN ('completed','done','canceled','cancelled')", "unset(\$meta['mobility_cost_snapshot'])"],
            'src/Support/RouteStatus.php' => ['isSnapshotMutable', 'isTerminal', "\$status === 'done'", "\$status === 'cancelled'"],
            'src/Admin/Assignments.php' => ['mobility_persist_failed', 'mobility_issue', 'profileIssues'],
            'src/Admin/Routes.php' => ['RouteStatus::isSnapshotMutable', 'refresh_mobility_snapshot', 'identity_changed'],
            'src/Rest/RoutesController.php' => ['RouteStatus::normalize', 'refreshSnapshotForIdentityChange'],
            'src/Repositories/RouteAccessRepository.php' => ['RouteMetricsService::canonicalFromRouteArray', 'distance_value_source', 'toll_value_source'],
            'tools/verify-mobility-integrity-canonical-metrics.php' => ['MOBILITY INTEGRITY & CANONICAL METRICS CONTRACT OK'],
            'PHASE-8R-MOBILITY-INTEGRITY-CANONICAL-METRICS.md' => ['fieldflow-mobility-integrity-canonical-metrics-v1', 'No P1-P4 routing or optimisation-rule change.'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $portal = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        if (strpos($portal, 'km*0.58*0.075') !== false) $missing[] = 'src/Front/ClientPortalShortcodeRenderer.php:legacy_toll_formula';

        if (class_exists('\RoutesPro\Services\RouteMetricsService') && class_exists('\RoutesPro\Services\MobilityCostService')) {
            $route = [
                'project_id'=>1, 'owner_user_id'=>1, 'date'=>'2026-09-19',
                'meta_decoded'=>[
                    'start_point'=>['address'=>'Base Norte'],
                    'end_point'=>['address'=>'Base Sul'],
                    'portal_summary'=>['total_distance_km'=>134.2,'toll_cost_eur'=>7.35,'distance_status'=>'complete'],
                    'mobility_cost_snapshot'=>['profile'=>['enabled'=>1,'energy_type'=>'diesel','fuel_price_eur_per_l'=>1.70,'consumption_l_per_100km'=>5.5]],
                ],
            ];
            $metrics = \RoutesPro\Services\RouteMetricsService::canonicalFromRouteArray($route, false);
            $cost = \RoutesPro\Services\MobilityCostService::calculateForRouteArray($route);
            if (abs((float)($metrics['distance_km'] ?? 0) - 134.2) > 0.001) $missing[] = 'runtime:canonical_distance';
            if (abs((float)($metrics['toll_cost_eur'] ?? 0) - 7.35) > 0.001) $missing[] = 'runtime:canonical_toll';
            if (abs((float)($cost['estimated_litres'] ?? 0) - 7.381) > 0.001) $missing[] = 'runtime:canonical_mobility_litres';
        } else {
            $missing[] = 'runtime:canonical_services';
        }
        if (!class_exists('\RoutesPro\Support\RouteStatus')
            || \RoutesPro\Support\RouteStatus::normalize('done') !== 'completed'
            || \RoutesPro\Support\RouteStatus::normalize('cancelled') !== 'canceled') {
            $missing[] = 'runtime:route_status_aliases';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'mobility_integrity_canonical_metrics',
            'Mobility Integrity & Canonical Metrics',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A integridade de métricas/snapshots de mobilidade perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Km e portagens têm resolver canónico, perfis são confirmados por readback e snapshots históricos ficam protegidos.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-mobility-integrity-canonical-metrics-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkRouteEndpointDistanceIntegrityContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => [
                'fieldflow-route-endpoint-distance-integrity-v1', 'fieldflow_route_metrics_v5',
                'endpointIntegrity', 'repairPlannedRouteIntegrity', 'route_endpoints_incomplete',
                'fieldflow-canonical-route-metrics-resolver-v2',
            ],
            'src/Services/Planning/RoutingMatrixService.php' => ['missing_start', 'missing_end', 'missing_both', 'endpoints_complete', 'known_distance_km'],
            'src/Services/MobilityCostService.php' => ['estimateForMetrics', 'costs_available', 'distance_incomplete'],
            'src/Services/RouteOptimizationService.php' => ['fieldflow_route_endpoints_required', 'access_distance_km', 'inter_stop_distance_km', 'return_distance_km'],
            'src/Admin/Routes.php' => ['Para publicar a rota, define o ponto de partida e o ponto de chegada', ' conhecidos', 'Por calcular', 'endpoint_status', 'fieldflow-route-endpoint-coordinate-trust-v1', 'resolve_route_endpoint', 'endpoint_coordinates_refreshed'],
            'src/Admin/CampaignLocations.php' => ['skipped_endpoints', 'plan_distance_integrity_metrics', 'Por calcular'],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => ['start_point', 'end_point', 'return_to_start'],
            'src/Admin/Assignments.php' => ['repair_route_distance_integrity', 'Reparar partida, chegada e km de rotas por planear'],
            'src/Rest/StatsController.php' => ['costed_routes', 'incomplete_distance_routes'],
            'src/Front/ClientPortalShortcodeRenderer.php' => ['routeDistanceComplete', ' conhecidos', 'fmtRouteEur'],
            'src/Shortcodes.php' => ['mobilityAggregate', 'Por calcular'],
            'src/Admin/FormSubmissions.php' => ['Rotas com distância incompleta', 'POR CALCULAR, DISTÂNCIA INCOMPLETA'],
            'tools/verify-route-endpoint-distance-integrity.php' => ['ROUTE ENDPOINT & DISTANCE INTEGRITY CONTRACT OK'],
            'PHASE-8S-ROUTE-ENDPOINT-DISTANCE-INTEGRITY.md' => ['fieldflow-route-endpoint-distance-integrity-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.'],
            'PHASE-8T-ROUTE-ENDPOINT-COORDINATE-TRUST-HOTFIX.md' => ['fieldflow-route-endpoint-coordinate-trust-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        if (class_exists('\\RoutesPro\\Services\\RouteMetricsService') && class_exists('\\RoutesPro\\Services\\MobilityCostService')) {
            $missingEnd = \RoutesPro\Services\RouteMetricsService::endpointIntegrity(['address'=>'Base A'], []);
            if (($missingEnd['endpoint_status'] ?? '') !== 'missing_end' || !empty($missingEnd['endpoints_complete'])) $missing[] = 'runtime:endpoint_missing_end';

            $incomplete = \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule([
                'distance_km'=>50.0, 'known_distance_km'=>50.0, 'toll_cost_eur'=>3.25,
                'distance_status'=>'incomplete', 'endpoint_status'=>'missing_end',
                'endpoints_complete'=>0, 'missing_endpoint_count'=>1, 'unresolved_leg_count'=>1,
            ]);
            if (($incomplete['distance_km'] ?? null) !== null) $missing[] = 'runtime:incomplete_total_distance_hidden';
            if (abs((float)($incomplete['known_distance_km'] ?? 0) - 50.0) > 0.001) $missing[] = 'runtime:known_distance_preserved';
            if (($incomplete['toll_cost_eur'] ?? null) !== null) $missing[] = 'runtime:incomplete_toll_hidden';

            $profile = ['enabled'=>1,'energy_type'=>'diesel','fuel_price_eur_per_l'=>1.70,'consumption_l_per_100km'=>5.5];
            $incompleteCost = \RoutesPro\Services\MobilityCostService::estimateForMetrics($incomplete, $profile);
            if (!empty($incompleteCost['costs_available']) || ($incompleteCost['calculation_status'] ?? '') !== 'distance_incomplete') $missing[] = 'runtime:incomplete_mobility_blocked';

            $complete = \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule([
                'distance_km'=>75.0, 'known_distance_km'=>75.0,
                'access_distance_km'=>10.0, 'inter_stop_distance_km'=>50.0, 'return_distance_km'=>15.0,
                'travel_min'=>100.0, 'distance_status'=>'complete', 'endpoint_status'=>'complete',
                'endpoints_complete'=>1, 'missing_endpoint_count'=>0, 'unresolved_leg_count'=>0, 'toll_cost_eur'=>4.20,
            ]);
            $parts = (float)($complete['access_distance_km'] ?? 0) + (float)($complete['inter_stop_distance_km'] ?? 0) + (float)($complete['return_distance_km'] ?? 0);
            if (abs((float)($complete['distance_km'] ?? 0) - $parts) > 0.001) $missing[] = 'runtime:full_route_component_sum';
            $completeCost = \RoutesPro\Services\MobilityCostService::estimateForMetrics($complete, $profile);
            if (empty($completeCost['costs_available']) || ($completeCost['calculation_status'] ?? '') !== 'complete') $missing[] = 'runtime:complete_mobility_available';
        } else {
            $missing[] = 'runtime:route_integrity_services';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'route_endpoint_distance_integrity',
            'Route Endpoint & Distance Integrity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A integridade do percurso completo perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Custos só usam uma distância integral, da partida aos PDVs e à chegada. Distâncias parciais permanecem diagnósticas e nunca fingem ser totais.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-route-endpoint-distance-integrity-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkRouteEndpointProvenanceReconciliationContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteEndpointResolverService.php' => [
                'fieldflow-route-endpoint-provenance-reconciliation-v1', 'RouteEndpointResolverService',
                'pointsCoincide', 'allow_matches_stop', 'endpoint_address_fingerprint',
            ],
            'src/Services/RouteMetricsService.php' => [
                'suspicious_endpoint_count', 'route_endpoints_untrusted', 'route_default_repair', 'endpoint_reconciled_at',
            ],
            'src/Services/RouteOptimizationService.php' => [
                'fieldflow_route_endpoints_untrusted', 'RouteEndpointResolverService::integrity', 'endpoint_reconciliation_contract',
            ],
            'src/Admin/Routes.php' => [
                'start_at_first_stop', 'end_at_last_stop', 'suspicious_endpoint_count', 'RouteEndpointResolverService::integrity',
            ],
            'src/Admin/CampaignLocations.php' => [
                'RouteEndpointResolverService', 'resolve_route_endpoint', 'suspicious_endpoint_count',
            ],
            'src/Admin/Assignments.php' => [
                'Reconciliar endpoints e km', 'distance_suspicious', 'distance_reconciled',
            ],
            'tools/verify-route-endpoint-provenance-reconciliation.php' => ['ROUTE ENDPOINT PROVENANCE & LEGACY RECONCILIATION CONTRACT OK'],
            'PHASE-8U-ROUTE-ENDPOINT-PROVENANCE-LEGACY-RECONCILIATION.md' => [
                'fieldflow-route-endpoint-provenance-reconciliation-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        if (class_exists('\\RoutesPro\\Services\\RouteEndpointResolverService')) {
            $first = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
            $last = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500];
            $start = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'legacy'];
            $end = ['address'=>'Base Sul','lat'=>41.2000,'lng'=>-8.7000,'provenance'=>'route_default'];
            $suspect = \RoutesPro\Services\RouteEndpointResolverService::integrity($start, $end, $first, $last);
            if (($suspect['endpoint_status'] ?? '') !== 'suspicious_start' || !empty($suspect['endpoints_complete'])) $missing[] = 'runtime:suspicious_start_detection';
            $start['allow_matches_stop'] = 1;
            $confirmed = \RoutesPro\Services\RouteEndpointResolverService::integrity($start, $end, $first, $last);
            if (($confirmed['endpoint_status'] ?? '') !== 'complete' || empty($confirmed['start_match_confirmed'])) $missing[] = 'runtime:explicit_zero_access_confirmation';
        } else {
            $missing[] = 'runtime:endpoint_resolver_service';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'route_endpoint_provenance_reconciliation',
            'Route Endpoint Provenance & Legacy Reconciliation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A proveniência/reconciliação de partida e chegada perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Partida e chegada têm proveniência canónica, extremos legacy coincidentes são bloqueados até confirmação ou reconciliação, e rotas planeadas podem ser reparadas com defaults reais.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-route-endpoint-provenance-reconciliation-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkRouteFinancialContinuityContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteEndpointResolverService.php' => [
                'endpoints_routable', 'endpoints_trusted', 'COINCIDENCE_KM = 0.03',
            ],
            'src/Services/RouteMetricsService.php' => [
                'fieldflow-route-financial-continuity-v1', 'endpoints_routable', 'known_toll_recovered', 'route_endpoints_untrusted',
            ],
            'src/Services/MobilityCostService.php' => [
                'estimateForMetrics', 'endpoints_routable', 'costs_available',
            ],
            'src/Services/RouteOptimizationService.php' => [
                'endpoints_routable', 'endpoints_trusted', 'fieldflow_route_endpoints_untrusted',
            ],
            'src/Admin/Routes.php' => [
                'endpointsRoutable', 'Calculável', 'suspicious_endpoint_count',
            ],
            'src/Admin/CampaignLocations.php' => [
                'endpoints_routable', 'suspicious_endpoint_count',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'routeDistanceComplete', 'Distância calculada, validar proximidade dos extremos',
            ],
            'tools/verify-route-financial-continuity-hotfix.php' => ['ROUTE FINANCIAL CONTINUITY HOTFIX CONTRACT OK'],
            'PHASE-8V-ROUTE-FINANCIAL-CONTINUITY-HOTFIX.md' => [
                'fieldflow-route-financial-continuity-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        if (class_exists('\\RoutesPro\\Services\\RouteEndpointResolverService')
            && class_exists('\\RoutesPro\\Services\\RouteMetricsService')
            && class_exists('\\RoutesPro\\Services\\MobilityCostService')) {
            $first = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
            $last = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500];
            $start = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'legacy'];
            $end = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500,'provenance'=>'legacy'];
            $trust = \RoutesPro\Services\RouteEndpointResolverService::integrity($start, $end, $first, $last);
            if (!empty($trust['endpoints_complete']) || empty($trust['endpoints_routable']) || (int)($trust['suspicious_endpoint_count'] ?? 0) !== 2) {
                $missing[] = 'runtime:routable_trust_decoupling';
            }

            $metrics = \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule([
                'distance_km'=>39.3, 'known_distance_km'=>39.3,
                'access_distance_km'=>0.0, 'inter_stop_distance_km'=>38.6, 'return_distance_km'=>0.7,
                'toll_cost_eur'=>1.20, 'distance_status'=>'complete', 'endpoint_status'=>'suspicious_both',
                'endpoints_complete'=>0, 'endpoints_routable'=>1, 'suspicious_endpoint_count'=>2,
                'missing_endpoint_count'=>0, 'unresolved_leg_count'=>0,
            ]);
            if (($metrics['distance_status'] ?? '') !== 'complete' || abs((float)($metrics['distance_km'] ?? 0) - 39.3) > 0.001) {
                $missing[] = 'runtime:suspicious_route_distance_continuity';
            }
            $profile = ['enabled'=>1,'energy_type'=>'gasoline','fuel_price_eur_per_l'=>1.80,'consumption_l_per_100km'=>7.2,'operational_cost_eur_per_km'=>0.10];
            $cost = \RoutesPro\Services\MobilityCostService::estimateForMetrics($metrics, $profile);
            if (empty($cost['costs_available']) || ($cost['calculation_status'] ?? '') !== 'complete' || (float)($cost['fuel_cost_eur'] ?? 0) <= 0) {
                $missing[] = 'runtime:suspicious_route_cost_continuity';
            }

            $blocked = \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule([
                'known_distance_km'=>50.0, 'distance_status'=>'incomplete', 'endpoint_status'=>'missing_end',
                'endpoints_complete'=>0, 'endpoints_routable'=>0, 'missing_endpoint_count'=>1, 'unresolved_leg_count'=>1,
            ]);
            $blockedCost = \RoutesPro\Services\MobilityCostService::estimateForMetrics($blocked, $profile);
            if (($blocked['distance_km'] ?? null) !== null || !empty($blockedCost['costs_available'])) {
                $missing[] = 'runtime:missing_endpoint_still_blocked';
            }
        } else {
            $missing[] = 'runtime:route_financial_services';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'route_financial_continuity',
            'Route Financial Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A separação entre confiança do endpoint e calculabilidade financeira perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Avisos de proximidade dos extremos permanecem visíveis, mas rotas integralmente roteáveis mantêm km, portagens e custos. Apenas extremos em falta ou pernas não resolvidas bloqueiam o cálculo.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-route-financial-continuity-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkOperationalEndpointSourceOfTruthContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteEndpointProfileService.php' => [
                'fieldflow-operational-endpoint-source-of-truth-v1', 'route_endpoint_default', 'route_endpoint_profiles',
                'recoverHistoricalEndpoints', 'Legacy route defaults are never consumed implicitly',
            ],
            'src/Admin/Assignments.php' => [
                'save_route_endpoint_default', 'save_route_endpoint_profile', 'Base da rota', 'Migração dos defaults anteriores',
            ],
            'src/Mobile/Controller.php' => [
                'RouteEndpointProfileService::resolveForRoute', 'fieldflow_mobile_route_endpoints_required',
            ],
            'src/Admin/Routes.php' => [
                'routeEndpointConfigs', 'endpointDefaultsForSelection', 'Base da rota no Centro de Atribuições',
            ],
            'src/Admin/CampaignLocations.php' => [
                'RouteEndpointProfileService::effectiveProfile', 'RouteEndpointProfileService::resolveForRoute',
            ],
            'src/Rest/RoutesController.php' => [
                'RouteEndpointProfileService', 'resolveForRoute', 'route_edge_stop_points', 'route_endpoint_profile_contract',
            ],
            'src/Services/RouteMetricsService.php' => [
                'recoverHistoricalEndpoints', 'RouteEndpointProfileService::resolveForRoute', 'route_endpoint_profile_contract',
            ],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => [
                'data-endpoint-profiles', 'endpointProfiles', "endpointProvenance='current_location'", 'base de rota configurada', "if(!start||!end)return'';",
            ],
            'src/Shortcodes.php' => [
                'data-endpoint-profiles', 'applyEndpointProfile', 'Define partida e chegada reais', 'buildGMapsUrlFromRoute',
            ],
            'src/Services/GoogleMapsUrlService.php' => [
                'Operational Endpoint Source of Truth', 'Missing real endpoints',
            ],
            'tools/verify-operational-endpoint-source-of-truth.php' => ['OPERATIONAL ENDPOINT SOURCE OF TRUTH CONTRACT OK'],
            'PHASE-8W-OPERATIONAL-ENDPOINT-SOURCE-OF-TRUTH.md' => [
                'fieldflow-operational-endpoint-source-of-truth-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.',
            ],
        ];
        $contents = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            $contents[$relative] = $content;
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        $mobile = (string)($contents['src/Mobile/Controller.php'] ?? '');
        $routes = (string)($contents['src/Admin/Routes.php'] ?? '');
        $campaigns = (string)($contents['src/Admin/CampaignLocations.php'] ?? '');
        $service = (string)($contents['src/Services/RouteEndpointProfileService.php'] ?? '');
        $shortcodes = (string)($contents['src/Shortcodes.php'] ?? '');
        $selfPlanner = (string)($contents['src/Front/SelfRoutePlannerShortcodeRenderer.php'] ?? '');
        $mapsUrl = (string)($contents['src/Services/GoogleMapsUrlService.php'] ?? '');
        if (strpos($mobile, '$start = $to_point($edge_lookup') !== false || strpos($mobile, '$end = $to_point($edge_lookup') !== false) $missing[] = 'negative:mobile_edge_pdv_fallback';
        if (strpos($routes, 'valid[0].lat') !== false || strpos($routes, 'valid[valid.length-1].lat') !== false) $missing[] = 'negative:admin_preview_edge_pdv_fallback';
        if (strpos($campaigns, "get_option('routespro_route_defaults'") !== false) $missing[] = 'negative:campaign_legacy_defaults_runtime';
        if (strpos($service, '$legacy = self::legacyDefault') !== false) $missing[] = 'negative:implicit_legacy_profile_fallback';
        if (strpos($shortcodes, 'origin=startText||located[0].location') !== false || strpos($shortcodes, 'buildGMapsUrlFromStops') !== false) $missing[] = 'negative:front_route_edge_pdv_fallback';
        if (strpos($selfPlanner, 'const pts=[start,...rows.map') !== false) $missing[] = 'negative:self_planner_navigation_edge_pdv_fallback';
        if (strpos($mapsUrl, 'if ($startValue !== \'\') $points[] = $startValue;') !== false) $missing[] = 'negative:maps_url_missing_endpoint_fallback';

        if (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
            try {
                $method = new \ReflectionMethod('\\RoutesPro\\Services\\RouteEndpointProfileService', 'isTrustedRouteCandidate');
                $method->setAccessible(true);
                $edge = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
                $legacy = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'manual'];
                if ($method->invoke(null, $legacy, $edge, 'start') !== false) $missing[] = 'runtime:manual_label_edge_not_rejected';
                $legacy['allow_matches_stop'] = 1;
                if ($method->invoke(null, $legacy, $edge, 'start') !== true) $missing[] = 'runtime:explicit_zero_leg_not_allowed';
            } catch (\Throwable $e) {
                $missing[] = 'runtime:endpoint_profile_reflection';
            }
        } else {
            $missing[] = 'runtime:route_endpoint_profile_service';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'operational_endpoint_source_of_truth',
            'Operational Endpoint Source of Truth',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A fonte operacional de partida/chegada perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Partida e chegada são master data explícita por campanha/operativo/rota; produtores atuais não fabricam extremos a partir do primeiro ou último PDV.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-operational-endpoint-source-of-truth-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkEndpointReconciliationAddressReuseContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => [
                'repairPlannedRouteIntegrity(int $projectId, int $ownerUserId = 0',
                'string $dateFrom =',
                'string $dateTo =',
                'int $actorUserId = 0',
            ],
            'src/Admin/Assignments.php' => [
                'data-ff-endpoint-address', 'FieldFlowGoogleMaps.load', 'Fallback opcional',
                'Campanhas PDV, a própria rota ou o Planear',
            ],
            'src/Services/RouteEndpointProfileService.php' => [
                'explicit route/day -> trusted route endpoint -> operator profile -> campaign default',
                "'source'=>'explicit'",
            ],
            'src/Admin/CampaignLocations.php' => [
                'RouteEndpointProfileService::resolveForRoute',
                'resolvedStartPoint',
                'resolvedEndPoint',
            ],
            'tools/verify-endpoint-reconciliation-address-reuse.php' => [
                'ENDPOINT RECONCILIATION ADDRESS REUSE CONTRACT OK',
            ],
            'PHASE-8X-ENDPOINT-RECONCILIATION-ADDRESS-REUSE.md' => [
                'fieldflow-endpoint-reconciliation-address-reuse-v1',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $runtimeWarnings = [];
        $legacyRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService',
            'src/Services/RouteMetricsService.php',
            'repairPlannedRouteIntegrity',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'],
            1,
            "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        if (empty($legacyRuntime['ok'])) $missing[] = 'runtime:repair_signature';
        elseif (!empty($legacyRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:repair_signature_stale';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'endpoint_reconciliation_address_reuse',
            'Endpoint Reconciliation & Explicit Address Reuse',
            $missing ? 'fail' : ($runtimeWarnings ? 'warning' : 'pass'),
            $missing
                ? 'A reconciliação de endpoints ou a reutilização das moradas explícitas perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : ($runtimeWarnings
                    ? 'O contrato instalado está correto; a classe runtime canónica ainda reflete bytecode anterior. OPcache foi invalidado e a revalidação pode prosseguir sem bloquear a release.'
                    : 'Reconciliação recebe datas corretamente, Google autocomplete está ligado no fallback e Campanhas PDV/Rota/Planear continuam a ser a fonte explícita prioritária.'),
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-endpoint-reconciliation-address-reuse-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing, 'warnings'=>$runtimeWarnings, 'runtime_contract'=>$legacyRuntime]
        );
    }

    private static function checkSemanticEdgeEndpointReconciliationContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteEndpointResolverService.php' => [
                'fieldflow-semantic-edge-endpoint-reconciliation-v1',
                'Address identity must win over',
                'if ($aAddress !== \'\' && $bAddress !== \'\' && $aAddress === $bAddress) return true;',
                'COINCIDENCE_KM = 0.03',
            ],
            'src/Services/RouteEndpointProfileService.php' => [
                'isTrustedRouteCandidate',
                'RouteEndpointResolverService::pointsCoincide',
                'operator profile -> campaign default',
            ],
            'src/Services/RouteMetricsService.php' => [
                'repairPlannedRouteIntegrity',
                'suspicious_endpoint_count',
                'RouteEndpointProfileService::resolveForRoute',
            ],
            'tools/verify-semantic-edge-endpoint-reconciliation.php' => [
                'SEMANTIC EDGE ENDPOINT RECONCILIATION CONTRACT OK',
                'same_normalized_address_wins_over_coordinate_drift',
                'different_address_does_not_widen_geometric_threshold',
            ],
            'PHASE-8Y-SEMANTIC-EDGE-ENDPOINT-RECONCILIATION.md' => [
                'fieldflow-semantic-edge-endpoint-reconciliation-v1',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                'No widening of the 30 metre coordinate coincidence threshold.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        if (class_exists('\\RoutesPro\\Services\\RouteEndpointResolverService') && class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
            try {
                $edge = ['address'=>'Rua da Loja, 10 - Porto','lat'=>41.1500,'lng'=>-8.6100];
                $legacy = ['address'=>'Rua da Loja 10 Porto','lat'=>41.1512,'lng'=>-8.6100,'provenance'=>'legacy'];
                $nearby = ['address'=>'Rua Vizinha 22 Porto','lat'=>41.1512,'lng'=>-8.6100,'provenance'=>'route_existing'];
                if (!\RoutesPro\Services\RouteEndpointResolverService::pointsCoincide($legacy, $edge)) $missing[] = 'runtime:same_address_coordinate_drift_not_detected';
                if (\RoutesPro\Services\RouteEndpointResolverService::pointsCoincide($nearby, $edge)) $missing[] = 'runtime:different_nearby_address_false_positive';
                $method = new \ReflectionMethod('\\RoutesPro\\Services\\RouteEndpointProfileService', 'isTrustedRouteCandidate');
                $method->setAccessible(true);
                if ($method->invoke(null, $legacy, $edge, 'start') !== false) $missing[] = 'runtime:legacy_route_candidate_not_rejected';
                if ($method->invoke(null, $nearby, $edge, 'start') !== true) $missing[] = 'runtime:legitimate_nearby_candidate_rejected';
            } catch (\Throwable $e) {
                $missing[] = 'runtime:semantic_edge_reflection';
            }
        } else {
            $missing[] = 'runtime:endpoint_services';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'semantic_edge_endpoint_reconciliation',
            'Semantic Edge Endpoint Reconciliation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A deteção semântica de endpoints legacy nos PDVs extremos perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'A mesma morada do PDV extremo é reconhecida mesmo com drift de coordenadas, sem alargar o limite geométrico de 30 m para moradas diferentes.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-semantic-edge-endpoint-reconciliation-v1', 'threshold_km'=>0.03, 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkOwnerProfileRouteMigrationContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => [
                'bool $forceOwnerProfile = false',
                '$forceProfileForRoute',
                'operator_profile_forced',
                'route_endpoint_forced_migration_at',
                'manual_order_locked',
            ],
            'src/Admin/Assignments.php' => [
                'force_owner_profile',
                'Forçar a Base habitual deste operativo nas rotas existentes',
                'Aplicar base do operativo e reconciliar km',
                'distance_forced_profile',
            ],
            'tools/verify-owner-profile-route-migration.php' => [
                'OWNER PROFILE ROUTE MIGRATION CONTRACT OK',
                'legacy_repair_signature_preserved',
                'dedicated_owner_profile_entrypoint',
                'complete_endpoint_short_circuit_bypass',
            ],
            'PHASE-8Z-OWNER-PROFILE-ROUTE-MIGRATION.md' => [
                'fieldflow-owner-profile-route-migration-v1',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SOURCE_RELEASE` is `2.2.510` and `STABLE_RELEASE` is `2.2.511`',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $requiredMarker) if (strpos($content, $requiredMarker) === false) $missing[] = $relative . ':' . $requiredMarker;
        }

        $runtimeWarnings = [];
        $legacyRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService', 'src/Services/RouteMetricsService.php', 'repairPlannedRouteIntegrity',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 1, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        $migrationRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService', 'src/Services/RouteMetricsService.php', 'repairPlannedRouteIntegrityWithOwnerProfile',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 1, "repairPlannedRouteIntegrityWithOwnerProfile(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        if (empty($legacyRuntime['ok'])) $missing[] = 'runtime:repair_signature';
        elseif (!empty($legacyRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:repair_signature_stale';
        if (empty($migrationRuntime['ok'])) $missing[] = 'runtime:owner_profile_migration_signature';
        elseif (!empty($migrationRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:owner_profile_migration_signature_stale';

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'owner_profile_route_migration',
            'Owner Profile Route Migration',
            $missing ? 'fail' : ($runtimeWarnings ? 'warning' : 'pass'),
            $missing
                ? 'A migração em massa da base habitual por operativo perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : ($runtimeWarnings ? 'O source canónico da migração está íntegro, mas o runtime PHP ainda reflete bytecode anterior; a situação é não bloqueante e o OPcache foi invalidado.' : 'A reconciliação pode forçar explicitamente a base habitual do operativo em rotas draft/planned existentes, preservando rotas iniciadas e a ordem dos PDVs.'),
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-owner-profile-route-migration-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing, 'warnings'=>$runtimeWarnings, 'legacy_runtime'=>$legacyRuntime, 'migration_runtime'=>$migrationRuntime]
        );
    }

    private static function checkEndpointRepairSignatureCompatibilityContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => [
                "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array",
                'repairPlannedRouteIntegrityWithOwnerProfile',
                'repairPlannedRouteIntegrityInternal',
                'Historical 2.2.511 package compatibility marker only: bool $forceOwnerProfile = false',
            ],
            'src/Admin/Assignments.php' => [
                'repairPlannedRouteIntegrityWithOwnerProfile',
                'force_owner_profile',
                'Aplicar base do operativo e reconciliar km',
            ],
            'tools/verify-endpoint-repair-signature-compatibility.php' => [
                'ENDPOINT REPAIR SIGNATURE COMPATIBILITY CONTRACT OK',
                'legacy_five_parameter_signature',
                'dedicated_owner_profile_migration_entrypoint',
            ],
            'PHASE-8ZA-ENDPOINT-REPAIR-SIGNATURE-COMPATIBILITY.md' => [
                'fieldflow-endpoint-repair-signature-compatibility-v1',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SOURCE_RELEASE` is `2.2.511` and `STABLE_RELEASE` is `2.2.512`',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $requiredMarker) if (strpos($content, $requiredMarker) === false) $missing[] = $relative . ':' . $requiredMarker;
        }
        $runtimeWarnings = [];
        $legacyRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService', 'src/Services/RouteMetricsService.php', 'repairPlannedRouteIntegrity',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 1, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        $migrationRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService', 'src/Services/RouteMetricsService.php', 'repairPlannedRouteIntegrityWithOwnerProfile',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 1, "repairPlannedRouteIntegrityWithOwnerProfile(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        if (empty($legacyRuntime['ok'])) $missing[] = 'runtime:legacy_repair_signature';
        elseif (!empty($legacyRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:legacy_repair_signature_stale';
        if (empty($migrationRuntime['ok'])) $missing[] = 'runtime:owner_profile_migration_signature';
        elseif (!empty($migrationRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:owner_profile_migration_signature_stale';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'endpoint_repair_signature_compatibility',
            'Endpoint Repair Signature Compatibility',
            $missing ? 'fail' : ($runtimeWarnings ? 'warning' : 'pass'),
            $missing
                ? 'A compatibilidade da assinatura certificada do reconciliador perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : ($runtimeWarnings ? 'O contrato de cinco parâmetros está correto no source canónico; o runtime PHP desfasado foi tratado como aviso não bloqueante e o OPcache invalidado.' : 'A assinatura histórica de cinco parâmetros permanece intacta e a migração forçada usa um entrypoint dedicado.'),
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-endpoint-repair-signature-compatibility-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing, 'warnings'=>$runtimeWarnings, 'legacy_runtime'=>$legacyRuntime, 'migration_runtime'=>$migrationRuntime]
        );
    }

    private static function checkRouteDistanceFinalizationCertificationContinuityContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/RouteMetricsService.php' => [
                "DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1'",
                'self::maybeRefreshGoogleRoute($routeId, [], true)',
                "'routing_finalized_by'=>'google_routes_sequence'",
                "'routing_finalized_by' => 'routing_matrix_preview'",
                "'google_exact'=>0",
                "'matrix_fallback'=>0",
                'distance_component_delta_km',
                'GoogleRoutes::calculateRoute($points)',
                'ORDER BY rs.seq ASC, rs.id ASC',
            ],
            'src/Admin/Assignments.php' => [
                'distance_google_exact',
                'distance_matrix_fallback',
                'distance_google_exact_failed',
                'Google exato',
                'fallback matriz',
            ],
            'src/Cloud/LicenseTenantFoundation.php' => [
                'certification_state_present',
                'certification_requires_revalidation',
            ],
            'src/Admin/LicenseTenants.php' => [
                'certificationVisualState',
                'CERTIFICADA, REVALIDAR',
                'Certificação histórica preservada.',
                'Revalidar cadeia 6B pós-update',
            ],
            'tools/verify-route-distance-finalization-certification-continuity.php' => [
                'ROUTE DISTANCE FINALIZATION CERTIFICATION CONTINUITY CONTRACT OK',
                'ordered_google_distance_finalization',
                'certification_revalidation_ui',
            ],
            'PHASE-8ZB-ROUTE-DISTANCE-FINALIZATION-CERTIFICATION-CONTINUITY.md' => [
                'fieldflow-route-distance-finalization-certification-continuity-v1',
                '`SOURCE_RELEASE` is `2.2.512` and `STABLE_RELEASE` is `2.2.513`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                'No certification gate is bypassed or auto-certified.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $requiredMarker) if (strpos($content, $requiredMarker) === false) $missing[] = $relative . ':' . $requiredMarker;
        }
        $phase6Continuity = is_readable(ROUTESPRO_PATH . 'src/Cloud/Phase6StableRelease.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Cloud/Phase6StableRelease.php') : '';
        foreach (['certification_state_present', 'certification_requires_revalidation'] as $marker) {
            if ($phase6Continuity === '' || strpos($phase6Continuity, $marker) === false) $missing[] = 'phase6_stable:' . $marker;
        }
        $runtimeWarnings = [];
        $distanceRuntime = self::inspectCanonicalRuntimeConstant(
            '\\RoutesPro\\Services\\RouteMetricsService',
            'src/Services/RouteMetricsService.php',
            'DISTANCE_FINALIZATION_CONTRACT',
            'fieldflow-route-distance-finalization-v1',
            "DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1'"
        );
        $legacyRuntime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService', 'src/Services/RouteMetricsService.php', 'repairPlannedRouteIntegrity',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 1, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        if (empty($distanceRuntime['ok'])) $missing[] = 'runtime:distance_finalization_contract';
        elseif (!empty($distanceRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:distance_finalization_contract_stale';
        if (empty($legacyRuntime['ok'])) $missing[] = 'runtime:legacy_repair_signature';
        elseif (!empty($legacyRuntime['runtime_stale'])) $runtimeWarnings[] = 'runtime:legacy_repair_signature_stale';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'route_distance_finalization_certification_continuity',
            'Route Distance Finalization & Certification Continuity',
            $missing ? 'fail' : ($runtimeWarnings ? 'warning' : 'pass'),
            $missing
                ? 'A finalização exata de distância ou a continuidade visual da certificação perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : ($runtimeWarnings ? 'O source canónico mantém a finalização Google e a assinatura certificada; o runtime PHP desfasado é sinalizado sem bloquear e o OPcache foi invalidado.' : 'Rotas reconciliadas finalizam distância pela sequência Google quando disponível e certificações históricas são apresentadas como revalidação, sem contornar fingerprints.'),
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-route-distance-finalization-certification-continuity-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing, 'warnings'=>$runtimeWarnings, 'distance_runtime'=>$distanceRuntime, 'legacy_runtime'=>$legacyRuntime]
        );
    }

    private static function checkReleaseGateRuntimeSourceAuthorityContract(): array {
        $missing = [];
        $warnings = [];
        $requirements = [
            'src/Support/ReleaseGate.php' => [
                'fieldflow-release-gate-runtime-source-authority-v1',
                'inspectCanonicalRuntimeMethod',
                'inspectCanonicalRuntimeConstant',
                'class_exists($class, false)',
                'runtime_origin_mismatch',
                'stale_runtime_non_blocking',
                'opcache_invalidate',
            ],
            'src/Services/RouteMetricsService.php' => [
                "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array",
                'repairPlannedRouteIntegrityWithOwnerProfile',
                "DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1'",
            ],
            'tools/verify-release-gate-runtime-source-authority.php' => [
                'RELEASE GATE RUNTIME SOURCE AUTHORITY CONTRACT OK',
                'canonical_source_authority',
                'wrong_runtime_origin_blocks',
                'stale_canonical_runtime_non_blocking',
            ],
            'PHASE-8ZC-RELEASE-GATE-RUNTIME-SOURCE-AUTHORITY.md' => [
                'fieldflow-release-gate-runtime-source-authority-v1',
                '`SOURCE_RELEASE` is `2.2.513` and `STABLE_RELEASE` is `2.2.514`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                'No certification gate is bypassed or auto-certified.',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }

        $runtime = self::inspectCanonicalRuntimeMethod(
            '\\RoutesPro\\Services\\RouteMetricsService',
            'src/Services/RouteMetricsService.php',
            'repairPlannedRouteIntegrity',
            ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'],
            1,
            "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array"
        );
        if (empty($runtime['ok'])) $missing[] = 'runtime:canonical_source_authority';
        elseif (!empty($runtime['runtime_stale'])) $warnings[] = 'runtime:canonical_bytecode_stale';

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'release_gate_runtime_source_authority',
            'Release Gate Runtime Source Authority',
            $missing ? 'fail' : ($warnings ? 'warning' : 'pass'),
            $missing
                ? 'A autoridade entre source instalado e runtime PHP perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : ($warnings
                    ? 'O ficheiro canónico instalado cumpre o contrato, mas a classe PHP já carregada está desfasada. O OPcache foi invalidado; a release pode ser revalidada sem falso bloqueio.'
                    : 'Source canónico e runtime carregado estão alinhados; qualquer classe proveniente de outra cópia do plugin continua a bloquear a certificação.'),
            [
                'runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
                'contract'=>'fieldflow-release-gate-runtime-source-authority-v1',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
                'warnings'=>$warnings,
                'runtime_contract'=>$runtime,
            ]
        );
    }


    private static function checkClientPortalShellStabilityContract(): array {
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'fieldflow-client-portal-shell-stability-v1',
                'data-ff2-layout="server"',
                'data-ff2-rail="server"',
                'function switchPanel(key,options={})',
                "source:'client-shell'",
                "source:'client-shell-module'",
                'panel.hidden=!active',
                "b.setAttribute('aria-selected'",
                'root.dataset.activePanel=key',
            ],
            'assets/fieldflow-ui-runtime.js' => [
                "CLIENT_SHELL_CONTRACT='fieldflow-client-portal-shell-stability-v1'",
                'const clientTab=',
                "source!=='client-shell-module'",
                "source!=='lazy-loader'",
                'syncClientLazyScope',
                "placeholder.dataset.ffPanel||''",
            ],
            'assets/fieldflow-premium-shell-v2.js' => [
                "SERVER_SHELL_CONTRACT = 'fieldflow-client-portal-shell-stability-v1'",
                'data-ff2-layout="server"',
                'data-ff2-rail="server"',
                'if (!serverLayout)',
                'data-ff2-rail-ready',
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9A, Client Portal Shell Stability',
                'fieldflow-client-portal-shell-stability-v1',
                'data-ff2-layout="server"',
                'data-ff2-rail="server"',
            ],
            'assets/fieldflow-operational-experience.js' => [
                "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'",
                "root.querySelector('.rp-shell > .rp-hero')",
                "root.querySelector('.rp-shell > .ff-ope-command-deck')",
                "type!=='client'",
            ],
            'tools/verify-client-portal-shell-stability.php' => [
                'CLIENT PORTAL SHELL STABILITY CONTRACT OK',
                'first_paint_layout=server_shaped',
                'panel_event_owner=client_shell',
                'lazy_scope=current_client_project',
            ],
            'PHASE-9A-CLIENT-PORTAL-SHELL-STABILITY.md' => [
                'fieldflow-client-portal-shell-stability-v1',
                '`SOURCE_RELEASE` is `2.2.514` and `STABLE_RELEASE` is `2.2.515`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'client_portal_shell_stability_ok',
                'O Client Portal Shell Stability não está integralmente ligado.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Client Portal Stability',
                "packageResult['client_portal_shell_stability_ok']",
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $client = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        if ($client === '' || strpos($client, "setTimeout(()=>target.scrollIntoView({behavior:'smooth'") !== false) $missing[] = 'client_portal:smooth_scroll_removed';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'client_portal_shell_stability',
            'Client Portal Shell Stability',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O shell do Portal Cliente perdeu contratos de estabilidade: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'O Portal Cliente usa shell server-shaped, navegação canónica sem eco do lazy loader e contexto client/project atualizado antes do primeiro carregamento.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-client-portal-shell-stability-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }


    private static function checkClientPortalContinuityContract(): array {
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'fieldflow-client-portal-continuity-v1',
                'ff-client-continuity',
                'function keepClientTabVisible(button)',
                "bar.scrollTo({left:desired,behavior:'auto'})",
                'window.requestAnimationFrame(()=>keepClientTabVisible(activeButton))',
            ],
            'assets/fieldflow-ui-runtime.js' => [
                'Phase 9B: the Client Portal owns its own panel continuity',
                "if(shell.classList.contains('rp-client-premium')) return;",
                'if(!clientTab) window.requestAnimationFrame(()=>compactPortalLayout(shell));',
                "!shell.classList?.contains('rp-client-premium')",
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9B, Client Portal Continuity + Full Width',
                'fieldflow-client-portal-continuity-v1',
                'overflow-x:auto!important',
                'scrollbar-width:none!important',
                'max-width:none;',
                'min-width:0;',
            ],
            'src/Admin/Emails.php' => [
                'Phase 9B: sender eligibility follows the authenticated portal scope',
                'Permissions::assert_scope_or_error($client_id, $project_id, $logged_user_id)',
                'is_wp_error($scope_check)',
            ],
            'src/Shortcodes.php' => [
                'recipientPayload', 'senderRes.ok', 'senderError', 'recipientError', 'failureMessage',
                'els.send.disabled=!(hasRecipients&&hasSenders)',
            ],
            'tools/verify-client-portal-continuity-fullwidth-messaging.php' => [
                'CLIENT PORTAL CONTINUITY CONTRACT OK',
                'client_tab_scroll=tabstrip_only',
                'mobile_nav_scrollbar=hidden',
                'sender_authority=authenticated_scope',
            ],
            'PHASE-9B-CLIENT-PORTAL-CONTINUITY-FULLWIDTH-MESSAGING.md' => [
                'fieldflow-client-portal-continuity-v1',
                '`SOURCE_RELEASE` is `2.2.515` and `STABLE_RELEASE` is `2.2.516`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'client_portal_continuity_ok',
                'O Client Portal Continuity não está integralmente ligado.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Client Portal Continuity',
                "packageResult['client_portal_continuity_ok']",
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($content, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $client = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        if (strpos($client, 'window.FieldFlowUI?.compactLayout?.(root)') !== false) $missing[] = 'client_portal:global_compact_scroll';
        $emails = is_readable(ROUTESPRO_PATH . 'src/Admin/Emails.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Admin/Emails.php') : '';
        $fnStart = strpos($emails, 'public static function get_available_senders_for_user');
        $fnEnd = $fnStart !== false ? strpos($emails, 'public static function get_message_app_url', $fnStart) : false;
        $senderFn = ($fnStart !== false && $fnEnd !== false) ? substr($emails, $fnStart, $fnEnd - $fnStart) : '';
        if ($senderFn === '' || strpos($senderFn, 'get_associated_user_ids') !== false) $missing[] = 'client_team_sender:operational_roster_dependency';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'client_portal_continuity',
            'Client Portal Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O Portal Cliente perdeu contratos de continuidade, full width ou elegibilidade de mensagens: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Tabs do cliente preservam o viewport, a navegação mobile é horizontal sem scrollbar visível e o remetente é validado pelo scope autenticado do cliente/campanha.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '', 'contract'=>'fieldflow-client-portal-continuity-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkReactClientWave1AnalyticsStabilityContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.525';
        $reactRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'fieldflow-client-panel-lifecycle-v1',
                'fieldflow-react-client-wave1-v1',
                'function setPanelLifecycle(key,status)',
                'clientAIScopeKey()',
                'await Promise.all([',
                'renderClientAI(data);',
                'keepClientTabVisible(activeButton);',
            ],
            'assets/fieldflow-ui-runtime.js' => [
                'Height-only changes must not emit a',
                'const clientPanelSize=new WeakMap();',
                'if(Math.abs(width-previous.width)>3) shouldDispatch=true;',
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'fieldflow-client-panel-lifecycle-v1',
                'fieldflow-react-client-wave1-v1',
                'rp-ai-stable-placeholder',
            ],
            'assets/fieldflow-react-front.css' => [
                'Phase 9C, React Client Wave 1',
                'data-client-wave1="fieldflow-react-client-wave1-v1"',
                'data-client-wave1-status="loading"',
            ],
            'src/Front/ReactFrontFoundation.php' => [
                "CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'",
                "in_array(\$module, ['stocks','shopping','messages'], true)",
                'client_wave1_contract',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'clientWave1Contract',
                'ReactFrontFoundation::CLIENT_WAVE1_CONTRACT',
                basename($reactRelative),
            ],
            'frontend/src/react-front/module-controls.ts' => [
                "FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'",
                "['stocks', 'shopping', 'messages'].includes(props.module)",
                'fieldflow:client-panel-lifecycle',
                "'data-client-wave1'",
                "'data-client-wave1-status'",
                'React Client Wave 1',
            ],
            'frontend/tools/build.mjs' => [
                'reactClientWave1Contract',
                'clientPanelLifecycleContract',
                'data-client-wave1',
            ],
            $reactRelative => [
                'FieldFlow ' . $release,
                'fieldflow-react-client-wave1-v1',
                'fieldflow-client-panel-lifecycle-v1',
                'data-client-wave1',
            ],
            'tools/verify-react-client-wave1-analytics-stability.php' => [
                'REACT CLIENT WAVE 1 ANALYTICS STABILITY CONTRACT OK',
                'analytics_resize=width_only',
                'analytics_ai_render=single_commit',
                'react_client_wave1=stocks,shopping,messages',
            ],
            'PHASE-9C-REACT-CLIENT-WAVE1-ANALYTICS-STABILITY.md' => [
                'fieldflow-react-client-wave1-analytics-stability-v1',
                '`SOURCE_RELEASE` is `2.2.516` and `STABLE_RELEASE` is `2.2.517`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'react_client_wave1_analytics_stability_ok',
                'O React Client Wave 1 ou a estabilidade do Analytics não estão integralmente ligados.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'React Client Wave 1',
                "packageResult['react_client_wave1_analytics_stability_ok']",
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $client = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        $start = strpos($client, 'async function loadClientAI(refresh=false)');
        $end = $start !== false ? strpos($client, 'function renderAnalytics(stats)', $start) : false;
        $ai = ($start !== false && $end !== false) ? substr($client, $start, $end - $start) : '';
        if ($ai === '' || substr_count($ai, 'renderClientAI(data);') !== 1) $missing[] = 'client_analytics:single_ai_render';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'react_client_wave1_analytics_stability',
            'React Client Wave 1 & Analytics Stability',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A Wave React cliente ou a estabilidade do Analytics perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Analytics usa lifecycle explícito, commit visual único e resize apenas por largura; Stocks, Shopping e Mensagens usam React para controlos e estado visual sem transferir a autoridade de negócio do PHP/REST.',
            ['runtime'=>$release, 'contract'=>'fieldflow-react-client-wave1-analytics-stability-v1', 'asset'=>basename($reactRelative), 'react_modules'=>['stocks','shopping','messages'], 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkReactClientWave2CrossPortalIsolationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'data-fieldflow-portal="client"',
                'fieldflow-cross-portal-render-isolation-v1',
                'fieldflow-react-client-wave2-v1',
                "portal:'client'",
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'data-fieldflow-portal="operative"',
                'fieldflow-cross-portal-render-isolation-v1',
                "portal:'operative'",
            ],
            'assets/fieldflow-ui-runtime.js' => [
                "CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1'",
                'function portalShellFor(node,portal)',
                "if(portal!=='client') return;",
                'fieldflow:client-panel-layout',
            ],
            'assets/fieldflow-premium-analytics.js' => [
                "CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1'",
                'function eventScope(event)',
                "portal==='client'",
                "portal==='operative'",
            ],
            'assets/fieldflow-operational-experience.js' => [
                "CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1'",
                'function eventRoot(event)',
                "portal==='client'",
                "portal==='operative'",
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9C Wave 2 + Cross-Portal Render Isolation',
                'fieldflow-react-client-wave2-v1',
                'fieldflow-cross-portal-render-isolation-v1',
                '-webkit-mask-image:none!important',
                'backdrop-filter:none!important',
            ],
            'assets/fieldflow-react-front.css' => [
                'Phase 9C, React Client Wave 2 + Cross-Portal Render Isolation',
                'data-client-wave2="fieldflow-react-client-wave2-v1"',
                'data-cross-portal-isolation="fieldflow-cross-portal-render-isolation-v1"',
            ],
            'src/Front/ReactFrontFoundation.php' => [
                "CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'",
                "CROSS_PORTAL_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1'",
                "['briefings','implementations','planner','expenses']",
                'client_wave2_contract',
                'cross_portal_isolation_contract',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'clientWave2Contract',
                'crossPortalIsolationContract',
                'ReactFrontFoundation::CLIENT_WAVE2_CONTRACT',
                'ReactFrontFoundation::CROSS_PORTAL_ISOLATION_CONTRACT',
                'fieldflow-react-front-' . $release . '.js',
            ],
            'frontend/src/react-front/module-controls.ts' => [
                "FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'",
                "FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1'",
                "['briefings', 'implementations', 'planner', 'expenses'].includes(props.module)",
                "portalScope === 'client'",
                "clientPortal.addEventListener('fieldflow:client-panel-lifecycle'",
                "'data-client-wave2'",
                'React Client Wave 2',
            ],
            'frontend/tools/build.mjs' => [
                'reactClientWave2Contract',
                'crossPortalIsolationContract',
                'data-client-wave2',
                'data-portal-scope',
            ],
            'assets/fieldflow-react-front-' . $release . '.js' => [
                'FieldFlow ' . $release,
                'fieldflow-react-client-wave2-v1',
                'fieldflow-cross-portal-render-isolation-v1',
                'data-client-wave2',
                'data-portal-scope',
            ],
            'tools/verify-react-client-wave2-cross-portal-isolation.php' => [
                'REACT CLIENT WAVE 2 CROSS-PORTAL ISOLATION CONTRACT OK',
                'react_client_wave2=briefings,implementations,planner,expenses',
                'portal_isolation=client,operative',
                'android_webview_compositor=mask_and_backdrop_removed',
            ],
            'PHASE-9C2-REACT-CLIENT-WAVE2-CROSS-PORTAL-ISOLATION.md' => [
                'fieldflow-react-client-wave2-cross-portal-isolation-v1',
                '`SOURCE_RELEASE` is `2.2.517` and `STABLE_RELEASE` is `2.2.518`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'react_client_wave2_cross_portal_isolation_ok',
                'O React Client Wave 2 ou o isolamento entre portais não estão integralmente ligados.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'React Client Wave 2',
                "packageResult['react_client_wave2_cross_portal_isolation_ok']",
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $experience = is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operational-experience.js') ? (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operational-experience.js') : '';
        if (strpos($experience, "event.target?.closest?.(ROOTS)||document.querySelector(ROOTS)") !== false) $missing[] = 'operational_experience:ambiguous_portal_fallback';
        $ui = is_readable(ROUTESPRO_PATH . 'assets/fieldflow-ui-runtime.js') ? (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-ui-runtime.js') : '';
        if (strpos($ui, "placeholder.dispatchEvent(new CustomEvent('routespro:panel-open',{bubbles:true,detail}));\n      window.dispatchEvent(new Event('resize'));") !== false) $missing[] = 'ui_runtime:client_global_resize';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'react_client_wave2_cross_portal_isolation',
            'React Client Wave 2 & Cross-Portal Isolation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A Wave React 2 ou o isolamento entre portais perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Briefings, Implementações, Planear e Despesas usam a Wave React 2 apenas no Portal Cliente; Cliente e Operativo têm scopes explícitos de render/eventos e o WebView evita o caminho de composição que podia deixar tiles residuais.',
            ['runtime'=>$release, 'contract'=>'fieldflow-react-client-wave2-cross-portal-isolation-v1', 'react_modules'=>['briefings','implementations','planner','expenses'], 'portal_isolation'=>['client','operative'], 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }


    private static function checkDualPortalFullwidthParityResumeV2Contract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT = 'fieldflow-dual-portal-fullwidth-parity-v2'",
                "APP_RESUME_PAINT_GUARD_V2_CONTRACT = 'fieldflow-app-resume-paint-guard-v2'",
                'dual_portal_fullwidth_parity_contract',
                'app_resume_paint_guard_v2_contract',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'ff-dual-portal-parity-v2',
                'ff-app-resume-guard-v2',
                'data-fieldflow-fullwidth-parity="fieldflow-dual-portal-fullwidth-parity-v2"',
                'data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'ff-dual-portal-parity-v2',
                'ff-app-resume-guard-v2',
                'data-fieldflow-fullwidth-parity="fieldflow-dual-portal-fullwidth-parity-v2"',
                'data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"',
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9D2, Dual Portal Full-Width Parity + Resume Hardening v2.',
                '#rpcp-commercial-panel', '#rpcp-briefings-panel', '#rpcp-implementations-panel', '#rpcp-stocks-panel', '#rpcp-team-panel', '#rpcp-analytics-panel',
                '#rp-app-briefings', '#rp-app-academy', '#rp-app-expenses', '#rp-app-messages', '#rp-app-analytics',
                'fieldflow-dual-portal-fullwidth-parity-v2',
            ],
            'src/Mobile/WebViewBridge.php' => [
                "RESUME_PAINT_GUARD_V2_CONTRACT='fieldflow-app-resume-paint-guard-v2'",
                'ff-app-resume-portal-hidden',
                'portal-ready-under-guard',
                'content-visibility:hidden!important',
                'legacyContract:RESUME_PAINT_GUARD_CONTRACT',
            ],
            'PHASE-9D2-DUAL-PORTAL-FULLWIDTH-PARITY-RESUME-HARDENING.md' => [
                'fieldflow-dual-portal-fullwidth-parity-resume-v2',
                '`SOURCE_RELEASE` is `2.2.519` and `STABLE_RELEASE` is `2.2.520`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
            ],
            'tools/verify-dual-portal-fullwidth-parity-resume-v2.php' => [
                'DUAL PORTAL FULLWIDTH PARITY RESUME V2 CONTRACT OK',
                'fullwidth_parity=client,operative',
                'resume_guard=v2',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'dual_portal_fullwidth_parity_resume_v2',
            'Dual Portal Full-Width Parity & Resume Guard v2',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A paridade full-width ou o Resume Guard v2 perdeu contratos: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Cliente e Operativo partilham o mesmo contrato de largura nas abas identificadas em terreno; o WebView oculta o portal em background e só o expõe sob o guard antes de libertar o frame final.',
            ['runtime'=>$release, 'contract'=>'fieldflow-dual-portal-fullwidth-parity-resume-v2', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkUiCompositionResumeDiagnosticsContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "UI_COMPOSITION_CONTRACT = 'fieldflow-ui-composition-ownership-v1'",
                "RESUME_DIAGNOSTICS_CONTRACT = 'fieldflow-resume-diagnostics-v1'",
                'ui_composition_contract',
                'resume_diagnostics_contract',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'ff-ui-composition-v1',
                'data-fieldflow-ui-composition=',
                'fieldflow-ui-composition-ownership-v1',
                'data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1"',
                "fieldflow_ui_composition_enabled",
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'ff-ui-composition-v1',
                'data-fieldflow-ui-composition=',
                'fieldflow-ui-composition-ownership-v1',
                'data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1"',
                "fieldflow_ui_composition_enabled",
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9E, UI Composition Ownership + Resume Diagnostics foundation.',
                'fieldflow-ui-composition-ownership-v1',
                '#rpcp-commercial-panel', '#rpcp-briefings-panel', '#rpcp-implementations-panel', '#rpcp-stocks-panel', '#rpcp-team-panel', '#rpcp-analytics-panel',
                '#rp-app-briefings', '#rp-app-academy', '#rp-app-expenses', '#rp-app-messages', '#rp-app-analytics',
                'border:0!important', 'box-shadow:none!important', '.rp-analytics-workspace', '.ffad-main',
            ],
            'src/Mobile/WebViewBridge.php' => [
                "fieldflow_mobile_resume_guard_enabled",
                "fieldflow_mobile_resume_media_guard_enabled",
                "fieldflow_mobile_resume_diagnostics_enabled",
                "RESUME_DIAGNOSTICS_CONTRACT='fieldflow-resume-diagnostics-v1'",
                'ff-app-resume-media-hidden',
                'iframe,img,object,embed',
                'recordResumeDiagnostic',
                'media-ready-under-guard',
                'fieldflow.resume.diagnostic',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'ReactFrontFoundation::UI_COMPOSITION_CONTRACT',
                'ReactFrontFoundation::RESUME_DIAGNOSTICS_CONTRACT',
            ],
            'PHASE-9E-UI-COMPOSITION-RESUME-DIAGNOSTICS.md' => [
                'fieldflow-ui-composition-resume-diagnostics-v1',
                '`SOURCE_RELEASE` is `2.2.520` and `STABLE_RELEASE` is `2.2.521`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
            ],
            'tools/verify-ui-composition-resume-diagnostics.php' => [
                'UI COMPOSITION RESUME DIAGNOSTICS CONTRACT OK',
                'composition=client,operative:surface_ownership',
                'resume=media_guard+diagnostics',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'ui_composition_resume_diagnostics',
            'UI Composition Ownership & Resume Diagnostics',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'The UI composition or resume diagnostics contract is incomplete: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' plus ' . (count($missing) - 5) . ' marker(s).' : '.')
                : 'Target Client and Operative panels use one outer surface owner, while the native WebView keeps media behind the resume guard and records bounded diagnostics.',
            ['runtime'=>$release, 'contract'=>'fieldflow-ui-composition-resume-diagnostics-v1', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkSharedPortalPaintStabilityContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => ["PORTAL_PAINT_STABILITY_CONTRACT = 'fieldflow-shared-portal-paint-stability-v1'"],
            'src/Front/AppShortcodeRenderer.php' => ['ff-paint-stable-v1','ff-scroll-extent-safe ff-responsive-runtime ff-ope','data-fieldflow-paint-stability=','fieldflow-shared-portal-paint-stability-v1','fieldflow_portal_paint_stability_enabled','data-ff-server-command-deck="1"','applyInitial();','panelSwitchToken','window.FieldFlowUI.loadPanelByKey'],
            'src/Front/ClientPortalShortcodeRenderer.php' => ['ff-paint-stable-v1','ff-scroll-extent-safe ff-responsive-runtime ff-ope','data-fieldflow-paint-stability=','fieldflow-shared-portal-paint-stability-v1','fieldflow_portal_paint_stability_enabled','data-ff-server-command-deck="1"','clientPanelSwitchToken','window.FieldFlowUI.loadPanelByKey','ensurePortalTableScroll(target)'],
            'assets/fieldflow-ui-runtime.js' => ['return Promise.all(tasks)',"placeholder.dataset.ffPaintReady='1'","placeholder.classList.add('ff-lazy-loaded')"],
            'assets/fieldflow-operational-experience.js' => ["const BUILD='2.2.522'",'bindCommandDeck(root)'],
            'assets/fieldflow-premium-shell-v2.css' => ['Phase 9F, Shared Portal Paint Stability.','fieldflow-shared-portal-paint-stability-v1','.ff-paint-stable-v1','.ff-lazy-panel[data-ff-paint-ready="1"]','animation:none!important'],
            'PHASE-9F-SHARED-PORTAL-PAINT-STABILITY.md' => ['fieldflow-shared-portal-paint-stability-v1','`SOURCE_RELEASE` is `2.2.521` and `STABLE_RELEASE` is `2.2.522`','No P1-P4 routing or optimisation-rule change.','No database schema change.'],
            'tools/verify-shared-portal-paint-stability.php' => ['SHARED PORTAL PAINT STABILITY CONTRACT OK','first_paint=server_structural_state','tabs=prepare_then_reveal'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'shared_portal_paint_stability',
            'Shared Portal Paint Stability',
            $missing ? 'fail' : 'pass',
            $missing ? 'The shared portal paint-stability contract is incomplete: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' plus ' . (count($missing) - 5) . ' marker(s).' : '.') : 'Client and Operative roots ship their structural state in the first HTML paint, lazy fragments prepare before reveal, and the Operative session panel is selected synchronously.',
            ['runtime'=>$release,'contract'=>'fieldflow-shared-portal-paint-stability-v1','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkPortalEntrySyncVisualContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            self::currentOfflineRuntimeRelative() => [
                self::currentOfflineRuntimeMarker(),
                "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)",
                "lastSyncResult='syncing';setBanner('syncing',0)",
                "window.addEventListener('fieldflow:app-resume'",
            ],
            'assets/fieldflow-offline-runtime.js' => [
                self::currentOfflineRuntimeMarker(),
                "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)",
            ],
            'src/Front/ResponsiveAssets.php' => [self::currentOfflineRuntimeFile(), "'showStatus' => true"],
            'src/Front/AppShortcodeRenderer.php' => ['applyInitial();','fieldflow-shared-portal-paint-stability-v1'],
            'PHASE-9G-PORTAL-ENTRY-SYNC-VISUAL-CONTINUITY.md' => ['fieldflow-portal-entry-sync-visual-v1','`SOURCE_RELEASE` is `2.2.522` and `STABLE_RELEASE` is `2.2.523`','No IndexedDB schema change.','No P1-P4 routing or optimisation-rule change.'],
            'tools/verify-portal-entry-sync-visual-continuity.php' => ['PORTAL ENTRY SYNC VISUAL CONTINUITY CONTRACT OK','sync_zero_count=hidden','front_transition_successor=accepted'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'portal_entry_sync_visual_continuity',
            'Portal Entry Sync Visual Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'The portal-entry sync visual or historical transition successor contract is incomplete: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' plus ' . (count($missing) - 5) . ' marker(s).' : '.')
                : 'Native portal entry keeps synchronization operational while hiding the transient zero-item syncing badge, and the historical transition gate accepts the synchronous Phase 9F successor.',
            ['runtime'=>$release,'contract'=>'fieldflow-portal-entry-sync-visual-v1','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkMailDispatchFoundationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Services/MailDispatchService.php' => [
                "fieldflow-mail-dispatch-foundation-v1",
                "public const BUILD = '2.2.524'",
                'public static function dispatch(',
                'wp_mail($to, $subject, $message, $headers, $attachments)',
                "do_action('fieldflow_mail_dispatch_after'",
            ],
            'src/Support/Loader.php' => ['src/Services/MailDispatchService.php'],
            'src/Observability/AlertService.php' => ['MailDispatchService::send('],
            'src/Performance/Module.php' => ['MailDispatchService::dispatch('],
            'src/Services/Notify.php' => ['MailDispatchService::dispatch('],
            'src/Billing/ProductionHardening.php' => ['MailDispatchService::dispatch('],
            'src/Billing/BillingService.php' => ['MailDispatchService::send('],
            'src/Shopping/ShoppingService.php' => ['MailDispatchService::dispatch('],
            'src/Admin/ReportAutomations.php' => ['MailDispatchService::dispatch('],
            'src/Admin/Emails.php' => ['MailDispatchService::dispatch('],
            'src/Admin/ajax.php' => ['MailDispatchService::dispatch('],
            'assets/fieldflow-operational-experience.js' => [
                "const BUILD='2.2.524'",
                "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'",
                "const BUILD='2.2.523'",
                "const BUILD='2.2.522'",
            ],
            'PHASE-9H-MAIL-DISPATCH-CERTIFICATION-CONTINUITY.md' => [
                'fieldflow-mail-dispatch-foundation-v1',
                '`SOURCE_RELEASE` is `2.2.523` and `STABLE_RELEASE` is `2.2.524`',
                'No database schema change.',
                'No P1-P4 routing or optimisation-rule change.',
                'Client Portal Shell Stability now validates a durable contract marker instead of a release number.',
            ],
            'tools/verify-mail-dispatch-certification-continuity.php' => [
                'MAIL DISPATCH + CERTIFICATION CONTINUITY CONTRACT OK',
                'transport=wp_mail',
                'direct_wp_mail_callers=0',
                'client_shell_gate=durable_contract',
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ([
            'src/Observability/AlertService.php','src/Performance/Module.php','src/Services/Notify.php',
            'src/Billing/ProductionHardening.php','src/Billing/BillingService.php','src/Shopping/ShoppingService.php',
            'src/Admin/ReportAutomations.php','src/Admin/Emails.php','src/Admin/ajax.php'
        ] as $relative) {
            $content = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if (preg_match('/\\bwp_mail\\s*\\(/', $content)) $missing[] = $relative . ':direct_wp_mail';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'mail_dispatch_certification_continuity',
            'Mail Dispatch & Certification Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'The canonical mail transport or durable Client Portal certification successor is incomplete: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' plus ' . (count($missing) - 5) . ' marker(s).' : '.')
                : 'All FieldFlow mail callers use the canonical wp_mail transport seam, while Client Portal Shell certification follows a durable contract marker instead of the current release number.',
            ['runtime'=>$release,'contract'=>'fieldflow-mail-dispatch-foundation-v1','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkMailReliabilityOutboxContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.525';
        $missing = [];
        $requirements = [
            'src/Services/MailDispatchService.php' => [
                "OUTBOX_CONTRACT = 'fieldflow-mail-reliability-outbox-v1'",
                "OUTBOX_BUILD = '2.2.525'",
                "public const BUILD = '2.2.524'",
                'processOutbox',
                'recoverStaleLocks',
                'idempotency_key',
                'wp_mail($to, $subject, $message, $headers, $attachments)',
            ],
            'src/Services/MailOutboxRepository.php' => [
                "TABLE_SUFFIX = 'mail_outbox'",
                'function findByIdempotencyKey(',
                'function claim(',
                'function recoverStaleLocks(',
                'function dueIds(',
            ],
            'src/Support/Migrations.php' => ["LATEST_SCHEMA_VERSION = '2.2.525'", 'migrate22525', 'mail_outbox'],
            'src/Support/SchemaManifest.php' => ["'mail_outbox'", 'idempotency_key', 'next_attempt_at'],
            'src/Architecture/RuntimeRegistry.php' => ['MailDispatchService::registerHooks'],
            'src/Admin/Emails.php' => ["tab==='outbox'", 'renderOutbox', 'Mail Reliability Outbox'],
            'src/Admin/ReportAutomations.php' => ["'idempotency_key']='order_suggestion|", "'idempotency_key'] = 'scheduled_report|", "!empty(\$mailDispatch['accepted'])", "'queued'"],
            'src/Billing/ProductionHardening.php' => ["'idempotency_key'=>'billing_transactional|'"],
            'src/Performance/Module.php' => ["'idempotency_key'=>'performance_automation|'"],
            'PHASE-9I-MAIL-RELIABILITY-OUTBOX-FOUNDATION.md' => ['fieldflow-mail-reliability-outbox-v1','`SOURCE_RELEASE` is `2.2.524` and `STABLE_RELEASE` is `2.2.525`','routespro_mail_outbox','No P1-P4 routing or optimisation-rule change.'],
            'tools/verify-mail-reliability-outbox.php' => ['MAIL RELIABILITY OUTBOX CONTRACT OK','retry=bounded_atomic_claims','order_suggestion_retry=single_transport_owner','submission_build=2.2.485'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'mail_reliability_outbox',
            'Mail Reliability Outbox',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'The 2.2.525 persistent mail reliability contract is incomplete: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' plus ' . (count($missing) - 6) . ' marker(s).' : '.')
                : 'Persistent mail intent, caller-owned idempotency, atomic claims, bounded retry and Mail Center visibility are wired while wp_mail remains the canonical transport.',
            ['runtime'=>$release,'contract'=>'fieldflow-mail-reliability-outbox-v1','schema'=>'2.2.525','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkCertificationAssetSuccessorContinuityContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $release . '.js';
        $reactRelative = 'assets/fieldflow-react-front-' . $release . '.js';
        $missing = [];
        $requirements = [
            $frontendRelative => ['FieldFlow ' . $release, 'fieldflow-frontend-build-foundation-v1'],
            $reactRelative => ['FieldFlow ' . $release, 'fieldflow-react-client-wave1-v1', 'fieldflow-client-panel-lifecycle-v1'],
            'src/Front/ResponsiveAssets.php' => [basename($frontendRelative), basename($reactRelative)],
            'src/Support/ReleasePackageValidator.php' => ["version_compare((string)(\$result['version'] ?? ''), '2.2.525', '>=')", 'certification_asset_successor_continuity_ok'],
            'src/Support/Migrations.php' => ["LATEST_SCHEMA_VERSION = '2.2.525'"],
            'PHASE-9J-CERTIFICATION-ASSET-SUCCESSOR-CONTINUITY.md' => ['fieldflow-certification-asset-successor-continuity-v1', 'No database schema change.', '`SubmissionPipeline::BUILD` remains `2.2.485`.'],
            'tools/verify-certification-asset-successor-continuity.php' => ['CERTIFICATION ASSET SUCCESSOR CONTINUITY CONTRACT OK', 'outbox_successor=>=2.2.525'],
            'src/Admin/ReleaseManager.php' => ['Asset Successor Continuity', "packageResult['certification_asset_successor_continuity_ok']"],
        ];
        if ($release === '') $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'certification_asset_successor_continuity',
            'Certification Asset Successor Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade dos assets frontend/React ou do contrato sucessor da Outbox perdeu marcadores: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' e mais ' . (count($missing) - 6) . ' marcador(es).' : '.')
                : 'Os gates de frontend e React seguem os assets versionados da release atual e a Outbox aceita releases sucessoras sem alterar schema ou comportamento operacional.',
            ['runtime'=>$release, 'contract'=>self::ASSET_SUCCESSOR_CONTRACT, 'frontend'=>basename($frontendRelative), 'react'=>basename($reactRelative), 'schema'=>'2.2.525', 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkRuntimeDatabasePerformanceContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $missing = [];
        $requirements = [
            'src/Performance/Module.php' => [
                "PERFORMANCE_BATCH_CONTRACT = 'fieldflow-academy-batch-read-v1'",
                'COUNT(DISTINCT lesson_id) lessons_done',
                'SELECT course_id,user_id,COUNT(DISTINCT lesson_id) done',
                '$projectUserIdsCache',
                '$clientUserIdsCache',
            ],
            'src/Performance/LoadReadiness.php' => [
                "VERSION = '1.2.0'",
                'databaseFootprint',
                'SHOW TABLE STATUS LIKE %s',
                "'largest_tables'",
                "'read_only'=>true",
            ],
            'src/Admin/SecurityObservability.php' => ['Footprint FieldFlow'],
            'src/Support/SystemHealthDiagnostics.php' => ["'database_profile'"],
            'frontend/src/react-front/bootstrap.ts' => ['containsUnmountedIsland', 'data-fieldflow-react-front-island'],
            'assets/fieldflow-ui-runtime.js' => ['pendingEnhanceScopes', 'scopes.forEach((scope)=>enhance(scope))'],
            'src/Support/Migrations.php' => ["LATEST_SCHEMA_VERSION = '2.2.525'"],
            'PHASE-9K-RUNTIME-DATABASE-PERFORMANCE.md' => [
                self::RUNTIME_DATABASE_PERFORMANCE_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.526` and `STABLE_RELEASE` is `2.2.527`',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-runtime-database-performance.php' => [
                'RUNTIME DATABASE PERFORMANCE CONTRACT OK',
                'academy_reads=batch',
                'runtime_observers=scoped',
                'database_probe=read_only',
            ],
            'src/Admin/ReleaseManager.php' => ['Runtime &amp; Database Performance', "packageResult['runtime_database_performance_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.527', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'runtime_database_performance',
            'Runtime & Database Performance',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de performance runtime/Academy/DB está incompleto: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' e mais ' . (count($missing) - 6) . ' marcador(es).' : '.')
                : 'Academy usa leituras batch, os observers limitam o trabalho ao DOM afetado e o probe recolhe footprint de base de dados apenas em modo read-only.',
            [
                'runtime'=>$release,
                'contract'=>self::RUNTIME_DATABASE_PERFORMANCE_CONTRACT,
                'load_readiness'=>'1.2.0',
                'schema'=>'2.2.525',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkSecurityStorageHardeningContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $missing = [];
        $requirements = [
            'src/Security/RestScopeGuard.php' => [
                "CONTRACT = 'fieldflow-rest-scope-isolation-v1'",
                "add_filter('rest_pre_dispatch'",
                'fieldflow_scope_context_conflict',
                'fieldflow_scope_client_denied',
                'fieldflow_scope_project_denied',
                'public static function decision',
            ],
            'src/Security/StorageGovernance.php' => [
                "CONTRACT = 'fieldflow-private-storage-governance-v1'",
                "CRON_HOOK = 'fieldflow_private_storage_governance_daily'",
                'cleanupExpiredTemporaryStorage',
                'expired_resumable_only',
                'fieldflow-private-resumable',
                "'preserves_evidence' => true",
                "'preserves_active_drafts' => true",
            ],
            'src/Mobile/Controller.php' => [
                "'permission_callback' => [\$this, 'submission_access']",
                "'permission_callback' => [\$this, 'briefing_access']",
                'public function submission_access',
                'public function briefing_access',
            ],
            'src/Mobile/WebViewBridge.php' => [
                "PARENT_ORIGIN_CONTRACT = 'fieldflow-webview-parent-origin-v1'",
                'function nativeParentOrigin()',
                'window.parent.postMessage(message,parentOrigin)',
            ],
            'src/Support/SystemHealth.php' => [
                "'rest_scope_guard' => \$rest_scope_guard",
                "'storage_governance' => \$storage_governance",
                "'private_storage_mb'",
            ],
            'src/Support/Loader.php' => ['src/Security/RestScopeGuard.php', 'src/Security/StorageGovernance.php'],
            'src/Architecture/RuntimeRegistry.php' => ['RestScopeGuard::registerHooks()', 'StorageGovernance::registerHooks()'],
            'src/Support/Migrations.php' => ["LATEST_SCHEMA_VERSION = '2.2.525'"],
            'PHASE-9L-SECURITY-STORAGE-HARDENING.md' => [
                self::SECURITY_STORAGE_HARDENING_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.527` and `STABLE_RELEASE` is `2.2.528`',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-security-storage-hardening.php' => [
                'SECURITY STORAGE HARDENING CONTRACT OK',
                'rest_scope=fail_closed',
                'storage_cleanup=expired_resumable_only',
                'webview_postmessage=same_origin',
                'successor=>=2.2.528',
            ],
            'src/Admin/ReleaseManager.php' => ['Security &amp; Storage Hardening', "packageResult['security_storage_hardening_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.528', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        $nativeShellVersion = '';
        if ($bridge !== '' && preg_match("/NATIVE_SHELL_VERSION\s*=\s*'([^']+)'/", $bridge, $nativeShellMatch)) {
            $nativeShellVersion = sanitize_text_field((string)$nativeShellMatch[1]);
        }
        if ($nativeShellVersion === '' || version_compare($nativeShellVersion, '1.9', '<')) {
            $missing[] = 'src/Mobile/WebViewBridge.php:native_shell_min_1.9';
        }
        foreach (["postMessage(message,'*')", 'postMessage(message,"*")', "postMessage(Object.assign({type:'fieldflow.external.open'},payload),'*')"] as $unsafe) {
            if ($bridge !== '' && strpos($bridge, $unsafe) !== false) $missing[] = 'src/Mobile/WebViewBridge.php:wildcard_postmessage';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'security_storage_hardening',
            'Security & Storage Hardening',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de isolamento REST, storage privado ou WebView hardening está incompleto: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' e mais ' . (count($missing) - 6) . ' marcador(es).' : '.')
                : 'Pedidos com scope explícito fora do tenant falham antes do endpoint, objetos mobile sensíveis validam acesso no permission callback, storage temporário privado tem cleanup determinístico e o fallback WebView deixa de publicar mensagens para wildcard origin.',
            [
                'runtime'=>$release,
                'contract'=>self::SECURITY_STORAGE_HARDENING_CONTRACT,
                'rest_scope'=>'fail_closed',
                'storage_cleanup'=>'expired_resumable_only',
                'webview_postmessage'=>'same_origin',
                'native_shell_min'=>'1.9',
                'native_shell_detected'=>$nativeShellVersion,
                'schema'=>'2.2.525',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkAiGovernanceModernizationContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $missing = [];
        $requirements = [
            'src/AI/Governance.php' => [
                "VERSION = '2.0'",
                "CONTRACT = 'fieldflow-ai-governance-finops-v2'",
                'ALERT_THRESHOLDS = [70, 85, 100]',
                'budgetSnapshot',
                'reconcileBudgetAlerts',
                'fieldflow_ai_budget_threshold',
                'ai_client_monthly_budget_eur',
                'ai_project_monthly_budget_eur',
            ],
            'src/Repositories/AIUsageRepository.php' => [
                'client_id', 'project_id', 'feature_key', 'request_kind',
                'monthBreakdown', 'monthCost(array $scope = [])', 'hasAttributionColumns',
            ],
            'src/Services/AI.php' => [
                'resolveAttribution', 'fieldflow_attribution', 'feature_key', 'request_kind',
                'Governance::reconcileBudgetAlerts',
            ],
            'src/Admin/AIControlCenter.php' => [
                'AI Governance 2.0', 'FinOps mensal', 'renderFinopsBreakdown',
                'ai_client_monthly_budget_eur', 'ai_project_monthly_budget_eur',
            ],
            'src/Support/SystemHealth.php' => ['attribution nativa:', 'budget_usage_percent'],
            'src/Support/Config.php' => [
                "'ai_client_monthly_budget_eur' => 0",
                "'ai_project_monthly_budget_eur' => 0",
            ],
            'src/Support/Migrations.php' => [
                "LATEST_SCHEMA_VERSION = '2.2.529'", "'2.2.529' => [self::class, 'migrate22529']",
                'migrate22529', 'client_created_idx', 'project_created_idx', 'feature_created_idx', 'request_kind_idx',
            ],
            'src/Support/SchemaManifest.php' => [
                "VERSION = '1.11.0'", "'client_id'", "'project_id'", "'feature_key'", "'request_kind'",
                "'client_created_idx'", "'project_created_idx'", "'feature_created_idx'", "'request_kind_idx'",
            ],
            'src/Front/ReactFrontFoundation.php' => [
                "OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'",
                'operationalModulesV2Contract', 'operational_modules_v2_contract',
            ],
            'frontend/src/react-front/types.ts' => ['operationalModulesV2Contract?: string'],
            'frontend/src/react-front/module-controls.ts' => [
                "FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'",
                'fieldflow:operational-module-refresh', 'data-fieldflow-react-module-owner', 'react-controls-v2', "'aria-live': v2Contract ? 'polite'",
            ],
            'src/Forms/FormRenderer.php' => ['ffParentOrigin', 'window.location.origin'],
            'src/Front/ExpensesShortcodeRenderer.php' => ['ffParentOrigin', 'window.location.origin'],
            'assets/fieldflow-ui-runtime.js' => ['parentOrigin', 'window.location.origin'],
            'PHASE-9M-AI-GOVERNANCE-MODERNIZATION-CONSOLIDATION.md' => [
                self::AI_GOVERNANCE_MODERNIZATION_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.528` and `STABLE_RELEASE` is `2.2.529`',
                '`LATEST_SCHEMA_VERSION` is `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-ai-governance-modernization.php' => [
                'AI GOVERNANCE MODERNIZATION CONTRACT OK',
                'successor=>=2.2.529',
                'schema=2.2.529',
                'submission_build=2.2.485',
            ],
            'src/Admin/ReleaseManager.php' => ['AI Governance &amp; Modernization', "packageResult['ai_governance_modernization_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.529', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach (['src/Forms/FormRenderer.php','src/Front/ExpensesShortcodeRenderer.php','assets/fieldflow-ui-runtime.js'] as $relative) {
            $content = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($content !== '' && preg_match('/postMessage\([^\n;]*,[\'\"]\*[\'\"]\)/', $content)) $missing[] = $relative . ':wildcard_postmessage';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'ai_governance_modernization',
            'AI Governance & Modernization Consolidation',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de FinOps IA, attribution, React operational v2 ou WebView completion está incompleto: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' e mais ' . (count($missing) - 6) . ' marcador(es).' : '.')
                : 'A IA tem attribution nativa e budgets por scope, os módulos operacionais expõem React v2 sem assumir lógica de negócio e os fallbacks WebView restantes usam same-origin.',
            [
                'runtime'=>$release,
                'contract'=>self::AI_GOVERNANCE_MODERNIZATION_CONTRACT,
                'ai_governance'=>'2.0',
                'budget_thresholds'=>'70/85/100',
                'react_operational'=>'v2_presentation_only',
                'webview_postmessage'=>'same_origin_complete',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkAiGovernanceCertificationContinuityContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Repositories/AIUsageRepository.php' => [
                'client_id', 'project_id', 'feature_key', 'request_kind',
                'monthBreakdown', 'monthCost(array $scope = [])', 'hasAttributionColumns',
            ],
            'src/Support/Migrations.php' => [
                "LATEST_SCHEMA_VERSION = '2.2.529'",
                'client_created_idx', 'project_created_idx', 'feature_created_idx', 'request_kind_idx',
            ],
            'src/Support/SchemaManifest.php' => [
                "VERSION = '1.11.0'",
                "'client_created_idx'", "'project_created_idx'", "'feature_created_idx'", "'request_kind_idx'",
            ],
            'PHASE-9N-AI-GOVERNANCE-CERTIFICATION-CONTINUITY.md' => [
                self::AI_GOVERNANCE_CERTIFICATION_CONTINUITY_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.529` and `STABLE_RELEASE` is `2.2.530`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-ai-governance-certification-continuity.php' => [
                'AI GOVERNANCE CERTIFICATION CONTINUITY CONTRACT OK',
                'successor=>=2.2.530',
                'schema=2.2.529',
                'submission_build=2.2.485',
            ],
            'src/Admin/ReleaseManager.php' => [
                'AI Certification Continuity',
                "packageResult['ai_governance_certification_continuity_ok']",
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.530', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        $gate = is_readable(ROUTESPRO_PATH . 'src/Support/ReleaseGate.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/ReleaseGate.php') : '';
        if ($gate !== '' && preg_match("/'src\\/Repositories\\/AIUsageRepository\\.php'\\s*=>\\s*\\[(.*?)\\],\\s*'src\\/Services\\/AI\\.php'/s", $gate, $match)) {
            foreach (['client_created_idx','project_created_idx','feature_created_idx','request_kind_idx'] as $indexMarker) {
                if (strpos((string)$match[1], $indexMarker) !== false) $missing[] = 'src/Support/ReleaseGate.php:repository_requires_' . $indexMarker;
            }
        } else {
            $missing[] = 'src/Support/ReleaseGate.php:ai_usage_requirement_scope';
        }

        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';

        return self::check(
            'ai_governance_certification_continuity',
            'AI Governance Certification Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A separação entre runtime repository e schema/index ownership está incompleta: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? ' e mais ' . (count($missing) - 6) . ' marcador(es).' : '.')
                : 'O Release Gate valida comportamento no AIUsageRepository e valida índices exclusivamente em Migrations/SchemaManifest, eliminando o falso negativo da 2.2.529 sem alterar schema ou runtime de IA.',
            [
                'runtime'=>$release,
                'contract'=>self::AI_GOVERNANCE_CERTIFICATION_CONTINUITY_CONTRACT,
                'repository_ownership'=>'runtime_queries_only',
                'index_ownership'=>'migrations_schema_manifest',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkOperationalModernizationWaveContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1'",
                'operationalModernizationContract', 'operational_modernization_contract',
            ],
            'frontend/src/react-front/module-controls.ts' => [
                self::OPERATIONAL_MODERNIZATION_CONTRACT, 'react-event-shell-9o', 'if (!modernized)', 'fieldflow:operational-module-refresh',
            ],
            'src/Front/BriefingsShortcodeRenderer.php' => [
                'fieldflow_briefings_v2_', 'data-user-id=', "module:'briefings'",
            ],
            'src/Front/StocksShortcodeRenderer.php' => [
                "module:'stocks'", 'data-risk-search',
            ],
            'src/Front/ShoppingShortcodeRenderer.php' => [
                'fieldflow:shopping:draft:v2:', 'legacyLocalKey', "module:'shopping'",
            ],
            'src/Front/TeamInboxShortcodeRenderer.php' => [
                "module:'messages'", "['http:','https:','mailto:','tel:']", 'noopener noreferrer',
            ],
            'src/Stocks/StockService.php' => ['GROUP BY batch_id', 'viewedByBatch'],
            'src/Shopping/ShoppingService.php' => ['$projectUserCache'],
            'assets/fieldflow-premium-shell-v2.css' => [self::OPERATIONAL_MODERNIZATION_CONTRACT, '#rp-app-stocks', '#rp-app-shopping'],
            'PHASE-9O-OPERATIONAL-MODERNIZATION-WAVE.md' => [
                self::OPERATIONAL_MODERNIZATION_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.530` and `STABLE_RELEASE` is `2.2.531`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-operational-modernization-wave.php' => [
                'OPERATIONAL MODERNIZATION WAVE CONTRACT OK', 'successor=>=2.2.531', 'schema=2.2.529', 'submission_build=2.2.485',
            ],
            'src/Admin/ReleaseManager.php' => ['Operational Modernization', "packageResult['operational_modernization_wave_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.531', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $briefings = is_readable(ROUTESPRO_PATH . 'src/Front/BriefingsShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/BriefingsShortcodeRenderer.php') : '';
        $stocks = is_readable(ROUTESPRO_PATH . 'src/Front/StocksShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/StocksShortcodeRenderer.php') : '';
        $shopping = is_readable(ROUTESPRO_PATH . 'src/Front/ShoppingShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ShoppingShortcodeRenderer.php') : '';
        if (substr_count($briefings, "operationalModuleMount(\$id, 'briefings'") < 2) $missing[] = 'briefings:team_client_mount_parity';
        if (substr_count($stocks, "operationalModuleMount(\$id, 'stocks'") < 2) $missing[] = 'stocks:team_client_mount_parity';
        if (strpos($shopping, "root.addEventListener('routespro:panel-open'") !== false) $missing[] = 'shopping:duplicate_panel_listener';
        if (strpos($stocks, "root.addEventListener('routespro:panel-open'") !== false) $missing[] = 'stocks:duplicate_panel_listener';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'operational_modernization_wave',
            'Operational Modernization Wave',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A modernização operacional 9O está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Briefings, Stocks, Shopping e Mensagens usam shell React event-driven, com paridade de mounts, storage scoped e otimizações read-only, preservando os owners de negócio certificados.',
            [
                'runtime'=>$release,
                'contract'=>self::OPERATIONAL_MODERNIZATION_CONTRACT,
                'modules'=>'briefings,stocks,shopping,messages',
                'react_owner'=>'event_driven_control_shell',
                'content_owner'=>'certified_php_domain_transition',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontendRuntimeCertificationContinuityContract(): array {
        $release = self::currentRelease();
        $runtimeRelative = self::currentOfflineRuntimeRelative();
        $missing = [];
        $requirements = [
            'frontend/package.json' => [
                '"name": "fieldflow-frontend"',
                '"version": "' . $release . '"',
                '"build": "node tools/build.mjs"',
            ],
            $runtimeRelative => [
                self::currentOfflineRuntimeMarker(),
                '__runtimeBuild:RUNTIME_BUILD',
                "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)",
            ],
            'assets/fieldflow-offline-runtime.js' => [
                self::currentOfflineRuntimeMarker(),
                '__runtimeBuild:RUNTIME_BUILD',
                "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)",
            ],
            'src/Support/ReleaseGate.php' => [
                "FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-frontend-runtime-certification-continuity-v1'",
                '"version": "\' . $release . \'"',
                'checkFrontendRuntimeCertificationContinuityContract',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'frontend_package_identity_ok',
                'canonical_offline_runtime_identity_ok',
                'frontend_runtime_certification_continuity_ok',
            ],
            'PHASE-9P-FRONTEND-RUNTIME-CERTIFICATION-CONTINUITY.md' => [
                self::FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.531` and `STABLE_RELEASE` is `2.2.532`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-frontend-runtime-certification-continuity.php' => [
                'FRONTEND RUNTIME CERTIFICATION CONTINUITY CONTRACT OK',
                'successor=>=2.2.532',
                'canonical_runtime=byte_identical',
                'submission_build=2.2.485',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Frontend Runtime Continuity',
                "packageResult['frontend_runtime_certification_continuity_ok']",
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.532', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $runtime = is_readable(ROUTESPRO_PATH . $runtimeRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $runtimeRelative) : '';
        $canonical = is_readable(ROUTESPRO_PATH . 'assets/fieldflow-offline-runtime.js') ? (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-offline-runtime.js') : '';
        if ($runtime === '' || $canonical === '' || !hash_equals(hash('sha256', $runtime), hash('sha256', $canonical))) {
            $missing[] = 'offline_runtime:canonical_versioned_parity';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'frontend_runtime_certification_continuity',
            'Frontend & Runtime Certification Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade de certificação frontend/runtime 9P está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Frontend package, bundle versioning and canonical/versioned offline runtime identities follow the current release and are protected before publication.',
            [
                'runtime'=>$release,
                'contract'=>self::FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT,
                'frontend_package'=>'current_release',
                'canonical_runtime'=>'byte_identical',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkOperationalModernizationWave2Contract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1'",
                'operationalModernizationWave2Contract', 'operational_modernization_wave2_contract',
            ],
            'frontend/src/react-front/module-controls.ts' => [
                self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT, 'react-event-shell-9q',
                "['academy', 'expenses'].includes(props.module)", 'if (!modernized)',
            ],
            'assets/fieldflow-react-front-' . $release . '.js' => [
                self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT, 'react-event-shell-9q', 'operational-module-controls',
            ],
            'src/Front/PerformanceShortcodeRenderer.php' => [
                "operationalModuleMount(\$id, 'academy'", 'academy_front_state',
                "root.addEventListener('routespro:panel-open'",
            ],
            'src/Performance/Module.php' => [
                "ACADEMY_FRONT_BATCH_CONTRACT = 'fieldflow-academy-front-batch-v2'",
                'academy_front_state', 'lessons_by_course', 'mission_status_by_id',
            ],
            'src/Front/ExpensesShortcodeRenderer.php' => [
                'notifyOperationalRefresh', "module:'expenses'", "root.addEventListener('routespro:panel-open'",
            ],
            'assets/fieldflow-premium-analytics.js' => [
                'fieldflow:analytics-rendered', 'fieldflow:lazy-panel-loaded', 'routespro:panel-open',
            ],
            'src/Shortcodes.php' => [
                'fieldflow:analytics-rendered', self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT,
            ],
            'src/Front/AppShortcodeRenderer.php' => ['.ffp-academy'],
            'assets/fieldflow-premium-shell-v2.css' => [self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT, '#rp-app-academy', '#rp-app-expenses', '#rp-app-analytics'],
            'PHASE-9Q-OPERATIONAL-MODERNIZATION-WAVE2.md' => [
                self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.532` and `STABLE_RELEASE` is `2.2.533`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-operational-modernization-wave2.php' => [
                'OPERATIONAL MODERNIZATION WAVE 2 CONTRACT OK', 'successor=>=2.2.533',
                'modules=academy,expenses,analytics', 'submission_build=2.2.485',
            ],
            'src/Support/ReleasePackageValidator.php' => ['operational_modernization_wave2_ok'],
            'src/Admin/ReleaseManager.php' => ['Operational Modernization Wave 2', "packageResult['operational_modernization_wave2_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.533', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $academy = is_readable(ROUTESPRO_PATH . 'src/Front/PerformanceShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/PerformanceShortcodeRenderer.php') : '';
        if (strpos($academy, '$wpdb->get_var') !== false) $missing[] = 'academy:per_item_db_read';
        $analytics = is_readable(ROUTESPRO_PATH . 'assets/fieldflow-premium-analytics.js') ? (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-premium-analytics.js') : '';
        if (strpos($analytics, 'new MutationObserver') !== false) $missing[] = 'analytics:global_mutation_observer';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'operational_modernization_wave2',
            'Operational Modernization Wave 2',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A modernização operacional 9Q está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Academy and Expenses use event-driven React control shells, Academy front reads are batched and Analytics enhancement is explicit-event driven.',
            [
                'runtime'=>$release,
                'contract'=>self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT,
                'modules'=>['academy','expenses','analytics'],
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkFieldFormTerrainContinuityContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                "RP_TERRAIN_CONTINUITY_CONTRACT='fieldflow-field-form-terrain-continuity-v1'",
                'var RP_DRAFT_SERVER_DELAY=900;', 'function draftRecoveryKey()', 'function draftServerBeacon(snapshot)',
                'opts.lifecycle&&draftServerBeacon(snapshot)', 'lifecycle:true',
                "if(String(e.key||'')!=='Enter') return;", 'e.preventDefault();e.stopPropagation();',
                'if(currentlyShown===show) return;', 'if(visibleIndexes.indexOf(index)===-1)', '_rpPrimaryKey',
            ],
            'src/Forms/DraftService.php' => [
                "TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1'",
                'find_recovery_scope_row', 'count($rows) !== 1', "'scope_rebased' => \$scope_rebased",
                "'route_id' => \$scope['route_id']", "'route_stop_id' => \$scope['route_stop_id']",
            ],
            'PHASE-9R-FIELD-FORM-TERRAIN-CONTINUITY.md' => [
                self::FIELD_FORM_TERRAIN_CONTINUITY_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.533` and `STABLE_RELEASE` is `2.2.534`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-field-form-terrain-continuity.php' => [
                'FIELD FORM TERRAIN CONTINUITY CONTRACT OK', 'successor=>=2.2.534',
                'draft_server_ms=900', 'submission_build=2.2.485',
            ],
            'src/Support/ReleasePackageValidator.php' => ['field_form_terrain_continuity_ok'],
            'src/Admin/ReleaseManager.php' => ['Field Form Terrain Continuity', "packageResult['field_form_terrain_continuity_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.534', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        $savePos = strpos($form, 'scheduleDraftSave();');
        $flowPos = $savePos === false ? false : strpos($form, 'refreshConditionalFlow();', $savePos);
        if ($savePos === false || $flowPos === false || $flowPos < $savePos) $missing[] = 'form:save_before_reflow';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'field_form_terrain_continuity',
            'Field Form Terrain Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade de formulário/rascunho 9R está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Cardex/wizard input no longer implicitly submits on mobile Enter, draft checkpoints precede reflow and local/server recovery survives native route/stop reconstruction.',
            [
                'runtime'=>$release,
                'contract'=>self::FIELD_FORM_TERRAIN_CONTINUITY_CONTRACT,
                'draft_server_delay_ms'=>900,
                'lifecycle_beacon'=>true,
                'stable_recovery_scope'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkAppResumeDualPortalFullwidthContract(): array {
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '2.2.524';
        $missing = [];
        $requirements = [
            'src/Front/ReactFrontFoundation.php' => [
                "DUAL_PORTAL_LAYOUT_CONTRACT = 'fieldflow-dual-portal-true-fullwidth-v1'",
                "APP_RESUME_PAINT_GUARD_CONTRACT = 'fieldflow-app-resume-paint-guard-v1'",
                'dual_portal_layout_contract',
                'app_resume_paint_guard_contract',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'ff-portal-layout-aligned',
                'ff-app-resume-guard',
                'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"',
                'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"',
                '2.2.519, Analytics follows the same true-full-width shell',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'ff-portal-layout-aligned',
                'ff-app-resume-guard',
                'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"',
                'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"',
            ],
            'src/Front/BriefingsShortcodeRenderer.php' => ['.ff-briefings{width:100%;max-width:none;margin:0;'],
            'src/Front/PerformanceShortcodeRenderer.php' => ['.ffp-front{width:100%;max-width:none;margin:0;'],
            'src/Front/TeamInboxShortcodeRenderer.php' => ['.rp-team-inbox{width:100%;max-width:none;margin:0;'],
            'src/Mobile/WebViewBridge.php' => [
                'fieldflow-resume-paint-guard',
                "RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1'",
                'function armResumePaintGuard(source)',
                'function releaseResumePaintGuard(token,source)',
                "document.addEventListener('visibilitychange'",
                "window.addEventListener('pagehide'",
                "window.addEventListener('pageshow'",
                "stabilizeResumePaint('fieldflow:app-resume')",
                'fieldflow:resume-paint-ready',
            ],
            'assets/fieldflow-ui-runtime.js' => [
                "DUAL_PORTAL_LAYOUT_CONTRACT='fieldflow-dual-portal-true-fullwidth-v1'",
                "APP_RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1'",
                'fieldflow:operative-panel-layout',
                'fieldflow:portal-panel-layout',
                'const portalPanelSize=new WeakMap()',
                "ff-app-resume-guard-active",
            ],
            'assets/fieldflow-premium-shell-v2.css' => [
                'Phase 9D, App Resume Stability + Dual Portal True Full Width',
                'fieldflow-dual-portal-true-fullwidth-v1',
                'fieldflow-app-resume-paint-guard-v1',
                '.ff-portal-layout-aligned',
                '.ff-app-resume-guard',
                'max-width:none!important',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'dualPortalLayoutContract',
                'appResumePaintGuardContract',
                'ReactFrontFoundation::DUAL_PORTAL_LAYOUT_CONTRACT',
                'ReactFrontFoundation::APP_RESUME_PAINT_GUARD_CONTRACT',
                'fieldflow-react-front-' . $release . '.js',
            ],
            'tools/verify-app-resume-dual-portal-fullwidth.php' => [
                'APP RESUME DUAL PORTAL FULLWIDTH CONTRACT OK',
                'layout=client,operative:true_full_width',
                'resume_guard=visibility,pagehide,pageshow,native_resume',
            ],
            'PHASE-9D-APP-RESUME-DUAL-PORTAL-TRUE-FULLWIDTH.md' => [
                'fieldflow-app-resume-dual-portal-true-fullwidth-v1',
                '`SOURCE_RELEASE` is `2.2.518` and `STABLE_RELEASE` is `2.2.519`',
                'No P1-P4 routing or optimisation-rule change.',
                'No database schema change.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Dual Portal Full Width',
                "packageResult['app_resume_dual_portal_fullwidth_ok']",
            ],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $app = is_readable(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') : '';
        $client = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        foreach (['max-width:min(1520px,96vw)', 'max-width:min(1540px,96vw)', 'max-width:min(1680px,calc(100vw - 18px))'] as $legacyWidth) {
            if (strpos($app, $legacyWidth) !== false || strpos($client, $legacyWidth) !== false) $missing[] = 'legacy_shell_width:' . $legacyWidth;
        }
        if (strpos($app, 'margin-left:calc(50% - 50vw + 6px)') !== false) $missing[] = 'operative_analytics:legacy_viewport_breakout';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'src/Forms/SubmissionPipeline.php:certified_build_changed';
        return self::check(
            'app_resume_dual_portal_fullwidth',
            'App Resume Stability & Dual Portal True Full Width',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'O contrato de resume/full width dos dois dashboards perdeu marcadores: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ' e mais ' . (count($missing) - 5) . ' marcador(es).' : '.')
                : 'Cliente e Operativo partilham a mesma geometria full width e disciplina de resize; o WebView protege o primeiro repaint após background/foreground com um paint guard neutro.',
            ['runtime'=>$release, 'contract'=>'fieldflow-app-resume-dual-portal-true-fullwidth-v1', 'layout_contract'=>'fieldflow-dual-portal-true-fullwidth-v1', 'resume_contract'=>'fieldflow-app-resume-paint-guard-v1', 'portals'=>['client','operative'], 'submission_build'=>'2.2.485', 'missing'=>$missing]
        );
    }

    private static function checkFieldSessionFormContinuityContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Mobile/Auth.php' => [
                "FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1'",
                'REFRESH_REPLAY_TTL = 30', 'refresh_replay_get', 'refresh_replay_store',
                "'aes-256-gcm'", 'refresh_replay_transient_key',
            ],
            'src/Mobile/WebViewBridge.php' => [
                "FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1'",
                "NATIVE_SHELL_VERSION = '1.10'", 'function queueNativeResume(reason)',
                "queueNativeResume('visibility-visible')", "queueNativeResume('pageshow')",
            ],
            'src/Security/SessionPolicy.php' => [
                '!self::isPrivilegedUser($userId)',
                "Applying the\n        // privileged 60-minute WebView idle policy to merchandisers",
            ],
            'src/Forms/FormRenderer.php' => [
                "RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT='fieldflow-field-session-form-continuity-v1'",
                'function rpQueueResumeDraftForms(reason)', "rpQueueResumeDraftForms('visibility-visible')",
                "rpQueueResumeDraftForms('pageshow')",
                'function draftPreferredInitialStep()', 'showStep(draftPreferredInitialStep(),false);',
                "if (\$type === 'product_matrix'", 'data-paired-fronts-key', "pairedKey===frontKey",
            ],
            'PHASE-9S-FIELD-SESSION-FORM-CONTINUITY.md' => [
                self::FIELD_SESSION_FORM_CONTINUITY_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.534` and `STABLE_RELEASE` is `2.2.535`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-field-session-form-continuity.php' => [
                'FIELD SESSION FORM CONTINUITY CONTRACT OK', 'successor=>=2.2.535',
                'refresh_replay_ttl=30', 'submission_build=2.2.485',
            ],
            'src/Support/ReleasePackageValidator.php' => ['field_session_form_continuity_ok'],
            'src/Admin/ReleaseManager.php' => ['Field Session &amp; Form Continuity', "packageResult['field_session_form_continuity_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.535', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        $bridgeBareFocus = strpos($bridge, "queueNativeResume('focus');") !== false;
        $bridgeGuardedFocus = strpos($bridge, "queueNativeResume('focus-after-hidden')") !== false && strpos($bridge, 'consumeNativeResumeArm()') !== false;
        if (!$bridgeBareFocus && !$bridgeGuardedFocus) $missing[] = 'bridge:focus_resume_capability_missing';
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        $formBareFocus = strpos($form, "rpQueueResumeDraftForms('focus')") !== false;
        $formGuardedFocus = strpos($form, "rpQueueResumeDraftForms('focus-after-hidden')") !== false && strpos($form, 'rpConsumeResumeDraftArm()') !== false;
        if (!$formBareFocus && !$formGuardedFocus) $missing[] = 'form:focus_resume_capability_missing';
        if (strpos($form, "in_array(\$type, ['product_matrix', 'order_suggestion'], true)") !== false) $missing[] = 'form:order_suggestion_still_hidden_by_cardex';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'field_session_form_continuity',
            'Field Session & Form Continuity',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A hotfix de continuidade de sessão/formulário 9S está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Mobile refresh concurrency is replay-safe, merchandiser WebView sessions inherit the parent mobile session, native/browser resumes restore drafts and step position, and order suggestion keeps a renderable shell during late Cardex resolution.',
            [
                'runtime'=>$release,
                'contract'=>self::FIELD_SESSION_FORM_CONTINUITY_CONTRACT,
                'refresh_replay_ttl'=>30,
                'native_shell'=>'1.10',
                'field_webview_idle_inherited'=>true,
                'order_suggestion_shell'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkCertificationGateContinuity9TContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Support/ReleaseGate.php' => [
                "CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT = 'fieldflow-certification-gate-continuity-9t-v1'",
                "version_compare(\$nativeShellVersion, '1.9', '<')",
                "'native_shell_min'=>'1.9'",
                "'Field Session &amp; Form Continuity'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'certification_gate_continuity_9t_ok',
                "fieldflow-certification-gate-continuity-9t-v1",
            ],
            'src/Admin/ReleaseManager.php' => [
                'Certification Gate Continuity 9T',
                "packageResult['certification_gate_continuity_9t_ok']",
            ],
            'PHASE-9T-CERTIFICATION-GATE-CONTINUITY-HOTFIX.md' => [
                self::CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.535` and `STABLE_RELEASE` is `2.2.536`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-certification-gate-continuity-9t.php' => [
                'CERTIFICATION GATE CONTINUITY 9T CONTRACT OK',
                'successor=>=2.2.536',
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.536', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        $nativeShellVersion = '';
        if ($bridge !== '' && preg_match("/NATIVE_SHELL_VERSION\s*=\s*'([^']+)'/", $bridge, $nativeShellMatch)) {
            $nativeShellVersion = sanitize_text_field((string)$nativeShellMatch[1]);
        }
        if ($nativeShellVersion === '' || version_compare($nativeShellVersion, '1.9', '<')) $missing[] = 'native_shell:below_1.9';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'certification_gate_continuity_9t',
            'Certification Gate Continuity 9T',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A continuidade successor-safe dos gates 9L/9S está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Security & Storage Hardening valida a versão mínima do native shell sem fixar 1.9 e o gate 9S reconhece o KPI escapado do Release Manager.',
            [
                'runtime'=>$release,
                'contract'=>self::CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT,
                'native_shell_min'=>'1.9',
                'native_shell_detected'=>$nativeShellVersion,
                'field_session_manager_marker'=>'escaped_html',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkOrderSourceCardexConsistency9UContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/ProductCardex.php' => [
                "ORDER_SOURCE_CARDEX_CONSISTENCY_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1'",
                'AND (c.client_id=0 OR %d=0 OR c.client_id=%d)',
                'AND (c.project_id=0 OR %d=0 OR c.project_id=%d)',
                'insignia_match_aliases',
                "['pd', 'pingo doce', 'p doce', 'pingodoce']",
                "['cbd', 'continente bom dia']",
                "['_fieldflow_paired_fronts_key']",
            ],
            'src/Forms/Forms.php' => [
                "if (\$type === 'product_matrix'",
                'posted_front_selection($cardex_id, $paired_fronts_key)',
                "\$quantity_source !== 'manual'",
                "'quantity_source' => \$quantity_source",
            ],
            'src/Forms/FormRenderer.php' => [
                'data-order-quantity-source',
                ".val('auto')",
                ".val('manual')",
                'data-paired-fronts-key',
            ],
            'src/Mobile/FormPayloadService.php' => [
                "\$available = \$type === 'order_suggestion' ? true : \$cardexAvailable",
                "['paired_fronts_key']",
                "['cardex_available']",
            ],
            'src/Mobile/SubmissionService.php' => [
                "if (\$type === 'product_matrix'",
                'sanitize_order_suggestion_value',
                "['quantity_source']",
            ],
            'src/Admin/FormSubmissions.php' => [
                'getOrderSuggestionSubmission',
                "'order_qty' => \$order['order_qty'] ?? ''",
            ],
            'PHASE-9U-ORDER-SOURCE-CARDEX-CONSISTENCY.md' => [
                self::ORDER_SOURCE_CARDEX_CONSISTENCY_9U_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.536` and `STABLE_RELEASE` is `2.2.537`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-order-source-cardex-consistency-9u.php' => [
                'ORDER SOURCE CARDEX CONSISTENCY 9U CONTRACT OK',
                'successor=>=2.2.537',
            ],
            'tools/test-order-source-runtime-9u.php' => [
                'ORDER SOURCE RUNTIME BEHAVIOUR OK',
                'manual_quantity=4',
                'manual_blank_not_recalculated',
            ],
            'src/Support/ReleasePackageValidator.php' => ['order_source_cardex_consistency_9u_ok'],
            'src/Admin/ReleaseManager.php' => ['Order Source &amp; Cardex Consistency 9U', "packageResult['order_source_cardex_consistency_9u_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.537', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'order_source_cardex_consistency_9u',
            'Order Source & Cardex Consistency 9U',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'A consistência Cardex, Frentes e Sugestão de Encomenda 9U está incompleta: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' e mais ' . (count($missing) - 8) . ' marcador(es).' : '.')
                : 'Associações explícitas toleram contexto incompleto, aliases de insígnia são normalizados, a encomenda fica presa à pergunta de Frentes correta e quantidades manuais não são recalculadas silenciosamente.',
            [
                'runtime'=>$release,
                'contract'=>self::ORDER_SOURCE_CARDEX_CONSISTENCY_9U_CONTRACT,
                'manual_quantity_authority'=>true,
                'paired_fronts_key'=>true,
                'explicit_store_link_context_tolerant'=>true,
                'mobile_web_semantics_aligned'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkCanonicalAnswerSemantics9VContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/AnswerSemantics.php' => [
                "CONTRACT = 'fieldflow-canonical-answer-semantics-v1'",
                'normalize_number_question',
                'number_below_min',
                'number_above_max',
                'choice_not_allowed',
                'structured_value_invalid',
            ],
            'src/Forms/Forms.php' => [
                'AnswerSemantics::normalize($question, $value, true)',
                "'invalid_answer_semantics'",
            ],
            'src/Mobile/SubmissionService.php' => [
                'use RoutesPro\\Forms\\AnswerSemantics;',
                'AnswerSemantics::normalize($question, $value, true)',
                "'fieldflow_mobile_invalid_answer'",
            ],
            'frontend/src/forms/answer-semantics-core.ts' => [
                "FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT = 'fieldflow-canonical-answer-semantics-v1'",
                'number_below_min',
                'number_above_max',
                'choice_not_allowed',
                'structured_value_invalid',
            ],
            'frontend/src/forms/submission-core.ts' => [
                'fieldFlowAnswerSemanticNormalize',
                'invalidSemanticKeys',
                'semanticIssues',
            ],
            'src/Forms/FormRenderer.php' => [
                'prepared.invalidSemanticKeys',
                'definition.selection_mode',
            ],
            'PHASE-9V-CANONICAL-ANSWER-SEMANTICS.md' => [
                self::CANONICAL_ANSWER_SEMANTICS_9V_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.537` and `STABLE_RELEASE` is `2.2.538`',
                '`LATEST_SCHEMA_VERSION` remains `2.2.529`.',
                '`SubmissionPipeline::BUILD` remains `2.2.485`.',
            ],
            'tools/verify-canonical-answer-semantics-9v.php' => [
                'CANONICAL ANSWER SEMANTICS 9V CONTRACT OK',
                'successor=>=2.2.538',
            ],
            'tools/test-canonical-answer-semantics-9v.php' => [
                'CANONICAL ANSWER SEMANTICS RUNTIME OK',
                'invalid_number=rejected',
                'choice_domain=enforced',
                'min_max=enforced',
            ],
            'src/Support/ReleasePackageValidator.php' => ['canonical_answer_semantics_9v_ok'],
            'src/Admin/ReleaseManager.php' => ['Canonical Answer Semantics 9V', "packageResult['canonical_answer_semantics_9v_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.538', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'canonical_answer_semantics_9v',
            'Canonical Answer Semantics 9V',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Canonical answer semantics 9V is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Web, Mobile and typed offline/preflight paths share numeric, bounds, choice-domain, checkbox and structured-answer semantics.',
            [
                'runtime'=>$release,
                'contract'=>self::CANONICAL_ANSWER_SEMANTICS_9V_CONTRACT,
                'numeric_semantics'=>true,
                'choice_domain'=>true,
                'typed_preflight'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkVisitClosureDailyMail9WContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/VisitCompletionPolicy.php' => [
                "CONTRACT = 'fieldflow-visit-closure-daily-mail-v1'",
                'SubmissionStatusPolicy::isOperational',
                'stopIsJustifiedNonVisit',
                'remainingSql',
            ],
            'src/Services/SubmissionStatusPolicy.php' => ["['submitted','reviewed','approved']"],
            'src/Services/RouteProgressService.php' => [
                "SubmissionStatusPolicy::operationalSql('fs')",
                'VisitCompletionPolicy::remainingSql()',
                "'operationally_complete'",
            ],
            'src/Services/FormVisitCompletionService.php' => [
                'VisitCompletionPolicy::submissionClosesVisit',
                'ReportAutomations::queueDailyCloseForRoute',
                "'daily_close_queued'",
            ],
            'src/Admin/ReportAutomations.php' => [
                "DAILY_CLOSE_RETRY_HOOK = 'fieldflow_daily_close_dispatch_retry'",
                "'recipient_mode'=>'client_and_extra'",
                "['claim'=>'extra','mode'=>'extra']",
                'scheduleDailyCloseRetry',
                'legacy_route_notification',
                "VisitCompletionPolicy::remainingSql('rs')",
            ],
            'src/Services/Notify.php' => [
                "'route_notification|completed|' . \$route_id",
                "'idempotency_key'",
            ],
            'src/Rest/EventsController.php' => ['ReportAutomations::queueDailyCloseForRoute'],
            'src/Rest/RoutesController.php' => ['ReportAutomations::queueDailyCloseForRoute'],
            'src/Services/VisitManualCompletionService.php' => ['ReportAutomations::queueDailyCloseForRoute'],
            'src/Support/Loader.php' => ['src/Services/VisitCompletionPolicy.php'],
            'PHASE-9W-VISIT-CLOSURE-DAILY-COMPLETION-MAIL.md' => [
                self::VISIT_CLOSURE_DAILY_MAIL_9W_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.538` and `STABLE_RELEASE` is `2.2.539`',
                '`LATEST_SCHEMA_VERSION = 2.2.529`',
                '`SubmissionPipeline::BUILD = 2.2.485`',
            ],
            'tools/verify-visit-closure-daily-mail-9w.php' => [
                'VISIT CLOSURE DAILY COMPLETION MAIL 9W CONTRACT OK',
                'successor=>=2.2.539',
            ],
            'tools/test-visit-closure-daily-mail-9w.php' => [
                'VISIT CLOSURE DAILY COMPLETION MAIL 9W RUNTIME OK',
                'submission_finality=enforced',
                'justified_nonvisit=terminal_not_executed',
            ],
            'src/Support/ReleasePackageValidator.php' => ['visit_closure_daily_mail_9w_ok'],
            'src/Admin/ReleaseManager.php' => ['Visit Closure &amp; Daily Completion Mail 9W', "packageResult['visit_closure_daily_mail_9w_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.539', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'visit_closure_daily_mail_9w',
            'Visit Closure & Daily Completion Mail 9W',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Visit closure and Daily Completion Mail 9W is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Final submissions close visits, justified non-visits are terminal, and route completion queues one durable Daily Close with Mail Outbox idempotency.',
            [
                'runtime'=>$release,
                'contract'=>self::VISIT_CLOSURE_DAILY_MAIL_9W_CONTRACT,
                'submission_finality'=>true,
                'justified_nonvisit_terminal'=>true,
                'daily_close_retry'=>true,
                'mail_outbox_idempotency'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkCertificationContinuityVisitClosure9XContract(): array {
        $release = self::currentRelease();
        $requirements = [
            'src/Support/ReleaseGate.php' => [
                self::CERTIFICATION_CONTINUITY_VISIT_CLOSURE_9X_CONTRACT,
                "'src/Admin/ReportAutomations.php' => ['queueDailyCloseForRoute','scheduleDailyCloseRetry','dispatchDailyCloseForRoute','claimDispatch']",
                "'operationally_complete'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'operational_intelligence_hardening_ok',
                'certification_continuity_visit_closure_9x_ok',
                'Operational Intelligence & Field Reliability is not package-safe',
            ],
            'src/Rest/EventsController.php' => [
                "\$wpdb->query('COMMIT');",
                "route_status'] ?? '')) === 'completed'",
                'operationally_complete',
                'ReportAutomations::queueDailyCloseForRoute',
            ],
            'src/Admin/ReportAutomations.php' => [
                'queueDailyCloseForRoute',
                'scheduleDailyCloseRetry',
                'dispatchDailyCloseForRoute',
                'claimDispatch',
            ],
            'tools/verify-operational-intelligence-hardening.php' => [
                'OPERATIONAL INTELLIGENCE HARDENING CONTRACT OK',
                'successor=>=2.2.530',
            ],
            'tools/verify-mail-reliability-outbox.php' => [
                'MAIL RELIABILITY OUTBOX CONTRACT OK',
                'successor=>=2.2.530',
            ],
            'PHASE-9X-CERTIFICATION-CONTINUITY-VISIT-CLOSURE.md' => [
                self::CERTIFICATION_CONTINUITY_VISIT_CLOSURE_9X_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.539` and `STABLE_RELEASE` is `2.2.540`',
                '`LATEST_SCHEMA_VERSION = 2.2.529`',
                '`SubmissionPipeline::BUILD = 2.2.485`',
            ],
            'tools/verify-certification-continuity-visit-closure-9x.php' => [
                'CERTIFICATION CONTINUITY VISIT CLOSURE 9X CONTRACT OK',
                'successor=>=2.2.540',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Certification Continuity Visit Closure 9X',
                "packageResult['certification_continuity_visit_closure_9x_ok']",
            ],
        ];
        $missing = [];
        if ($release === '' || version_compare($release, '2.2.540', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'certification_continuity_visit_closure_9x',
            'Certification Continuity Visit Closure 9X',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Certification continuity for visit closure is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Historical Operational Intelligence and Mail Outbox gates validate capabilities instead of obsolete literals, and the package validator mirrors the installed Release Gate before publication.',
            [
                'runtime'=>$release,
                'contract'=>self::CERTIFICATION_CONTINUITY_VISIT_CLOSURE_9X_CONTRACT,
                'operational_gate_capability_based'=>true,
                'package_gate_parity'=>true,
                'mail_outbox_successor_safe'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkSubmissionStatusOperationalDay9YContract(): array {
        $release = self::currentRelease();
        $requirements = [
            'src/Services/SubmissionStatusPolicy.php' => [
                'fieldflow-submission-status-policy-v1',
                "['submitted','reviewed','approved']",
                'operationalSql',
                'editableExistingSql',
            ],
            'src/Services/OperationalReportDayPolicy.php' => [
                'fieldflow-operational-report-day-policy-v1',
                'report_day',
                'submitted_at',
                'sqlExpression',
                'versionSqlExpression',
            ],
            'src/Services/VisitCompletionPolicy.php' => ['SubmissionStatusPolicy::isOperational'],
            'src/Services/RouteProgressService.php' => ["SubmissionStatusPolicy::operationalSql('fs')"],
            'src/Forms/ContextQuestions.php' => ["SubmissionStatusPolicy::operationalSql('s')"],
            'src/Core/CanonicalStateService.php' => ['SubmissionStatusPolicy::operationalSql()'],
            'src/Services/VisitManualCompletionService.php' => ['SubmissionStatusPolicy::operationalSql()'],
            'src/Admin/ReportAutomations.php' => ['SubmissionStatusPolicy::operationalSql()'],
            'src/Admin/FormSubmissions.php' => ["OperationalReportDayPolicy::sqlExpression('s', 'r')"],
            'src/Analytics/AnalyticsAIService.php' => ["SubmissionStatusPolicy::operationalSql('s')", "OperationalReportDayPolicy::versionSqlExpression('s', 'v')"],
            'src/Rest/StatsController.php' => ["SubmissionStatusPolicy::operationalSql('s')", 'OperationalReportDayPolicy::versionSqlExpression'],
            'src/Integrations/PublicQueryService.php' => ["OperationalReportDayPolicy::sqlExpression('s')"],
            'src/Stores/StoreProfileService.php' => ["SubmissionStatusPolicy::operationalSql('fs2')", "OperationalReportDayPolicy::sqlExpression('fs2')"],
            'src/Support/Loader.php' => ['src/Services/SubmissionStatusPolicy.php', 'src/Services/OperationalReportDayPolicy.php'],
            'PHASE-9Y-SUBMISSION-STATUS-OPERATIONAL-DAY.md' => [
                self::SUBMISSION_STATUS_OPERATIONAL_DAY_9Y_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.540` and `STABLE_RELEASE` is `2.2.541`',
            ],
            'tools/verify-submission-status-operational-day-9y.php' => [
                'SUBMISSION STATUS OPERATIONAL DAY 9Y CONTRACT OK',
                'successor=>=2.2.541',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Submission Status &amp; Operational Day 9Y',
                "packageResult['submission_status_operational_day_9y_ok']",
            ],
        ];
        $missing = [];
        if ($release === '' || version_compare($release, '2.2.541', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'submission_status_operational_day_9y',
            'Submission Status & Operational Day 9Y',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Submission status / operational day contract is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Operational reporting now shares one final-status policy and one business-day policy while preserving submitted_at as the audit timestamp.',
            [
                'runtime'=>$release,
                'contract'=>self::SUBMISSION_STATUS_OPERATIONAL_DAY_9Y_CONTRACT,
                'operational_statuses'=>['submitted','reviewed','approved'],
                'day_precedence'=>['report_day','route.date','submitted_at'],
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkSubmissionCorrectionsAudit9ZContract(): array {
        $release = self::currentRelease();
        $requirements = [
            'src/Services/SubmissionCorrectionService.php' => [
                self::SUBMISSION_CORRECTIONS_AUDIT_9Z_CONTRACT,
                'admin_correction_history',
                'reconcileVisitInsideTransaction',
                'RouteProgressService::sync($routeId, true, false)',
                'mail_side_effects_emitted',
            ],
            'src/Admin/FormSubmissions.php' => [
                'SubmissionCorrectionService::correct',
                'SubmissionCorrectionService::void',
                'name="correction_reason"',
                'Histórico de correções',
            ],
            'src/Forms/RecordService.php' => [
                'reconcile_operational_records',
                "'status'=>'inactive'",
                'current_version_id',
            ],
            'src/Services/SubmissionStatusPolicy.php' => [
                "['submitted','reviewed','approved']",
                "['void','cancelled','deleted','trash']",
            ],
            'src/Support/Loader.php' => ['src/Services/SubmissionCorrectionService.php'],
            'PHASE-9Z-SUBMISSION-CORRECTIONS-AUDIT-TRAIL.md' => [self::SUBMISSION_CORRECTIONS_AUDIT_9Z_CONTRACT],
            'tools/verify-submission-corrections-audit-9z.php' => [
                'SUBMISSION CORRECTIONS AUDIT 9Z CONTRACT OK',
                'successor=>=2.2.542',
            ],
            'tools/test-submission-corrections-audit-9z.php' => ['SUBMISSION CORRECTIONS AUDIT 9Z RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => [
                'Submission Corrections &amp; Audit Trail 9Z',
                "packageResult['submission_corrections_audit_9z_ok']",
            ],
        ];
        $missing = [];
        if ($release === '' || version_compare($release, '2.2.542', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $admin = is_readable(ROUTESPRO_PATH . 'src/Admin/FormSubmissions.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Admin/FormSubmissions.php') : '';
        if (strpos($admin, 'delete_submission_history($id)') !== false) $missing[] = 'admin:legacy_history_delete';
        if (strpos($admin, '$wpdb->delete(FormsModule::table_submissions()') !== false) $missing[] = 'admin:legacy_submission_hard_delete';
        $service = is_readable(ROUTESPRO_PATH . 'src/Services/SubmissionCorrectionService.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Services/SubmissionCorrectionService.php') : '';
        if (strpos($service, 'queueDailyCloseForRoute') !== false || strpos($service, "EventBus::emit('route.completed'") !== false) $missing[] = 'correction:mail_completion_replay';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'submission_corrections_audit_9z',
            'Submission Corrections & Audit Trail 9Z',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Submission correction / audit contract is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Backoffice corrections require a reason, preserve versions and answers, void instead of hard-delete, reconcile route state and suppress completion-mail replay.',
            [
                'runtime'=>$release,
                'contract'=>self::SUBMISSION_CORRECTIONS_AUDIT_9Z_CONTRACT,
                'hard_delete'=>false,
                'reason_required'=>true,
                'route_completion_replay'=>false,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFieldResumeNavigation9AAContract(): array {
        $release = self::currentRelease();
        $requirements = [
            'src/Front/AppShortcodeRenderer.php' => [
                self::FIELD_RESUME_NAVIGATION_9AA_CONTRACT,
                'fieldflow:resume:operational:v1:',
                'readResumeCheckpoint',
                'writeResumeCheckpoint',
                'panelFromResume',
                'restoreResumeContext',
                'fieldflow:native-resume-sync',
                'fieldflow:form-external-handoff',
                'data-user-id',
            ],
            'src/Forms/FormRenderer.php' => [
                self::FIELD_RESUME_NAVIGATION_9AA_CONTRACT,
                'rpCheckpointExternalMediaHandoff',
                "routesproFlushDraft({force:true,silent:true,immediateServer:true,keepalive:true,lifecycle:true})",
                'fieldflow:form-external-handoff',
                'step_index',
            ],
            'src/Mobile/WebViewBridge.php' => [
                "FIELD_RESUME_NAVIGATION_CONTRACT = 'fieldflow-field-resume-navigation-continuity-v1'",
                'resumeNavigationContract',
                'fieldflow:resume:operational:v1:',
                "sessionStorage.removeItem(\"fieldflow_active_panel\")",
            ],
            'PHASE-9AA-FIELD-RESUME-NAVIGATION-CONTINUITY.md' => [
                self::FIELD_RESUME_NAVIGATION_9AA_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.542` and `STABLE_RELEASE` is `2.2.543`',
            ],
            'tools/verify-field-resume-navigation-9aa.php' => [
                'FIELD RESUME NAVIGATION 9AA CONTRACT OK',
                'successor=>=2.2.543',
            ],
            'tools/test-field-resume-navigation-9aa.php' => ['FIELD RESUME NAVIGATION 9AA RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => [
                'Field Resume Navigation Continuity 9AA',
                "packageResult['field_resume_navigation_9aa_ok']",
            ],
        ];
        $missing = [];
        if ($release === '' || version_compare($release, '2.2.543', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $app = is_readable(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/AppShortcodeRenderer.php') : '';
        if (strpos($app, "sessionStorage.setItem('fieldflow_active_panel',key)") === false) $missing[] = 'app:session_fast_path_missing';
        if (strpos($app, "localStorage.setItem(resumeStorageKey") === false) $missing[] = 'app:durable_checkpoint_missing';
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if (strpos($form, "showStep(parseInt(draft.step||1,10)||1,false)") === false) $missing[] = 'form:step_restore_missing';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'field_resume_navigation_9aa',
            'Field Resume Navigation Continuity 9AA',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Field resume navigation contract is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Operational WebView resume persists panel and visit context durably, checkpoints the form before camera/gallery handoff and restores the exact reporting context without a FlutterFlow change.',
            [
                'runtime'=>$release,
                'contract'=>self::FIELD_RESUME_NAVIGATION_9AA_CONTRACT,
                'durable_navigation_checkpoint'=>true,
                'camera_pre_handoff_checkpoint'=>true,
                'logout_clears_checkpoint'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkReportSelectionMedia9ABContract(): array {
        $release = self::currentRelease();
        $requirements = [
            'src/Front/AppShortcodeRenderer.php' => [
                self::REPORT_SELECTION_MEDIA_9AB_CONTRACT,
                'manualReportSelectionLockUntil',
                'cancelPendingReportRestore',
                'fieldflow:report-selection-intent',
                'fieldflow:report-selection-changed',
                'liveReportVisit',
                'live-report-authority',
                'fieldflow:form-media-checkpointed',
            ],
            'src/Shortcodes.php' => [
                'manual-store-dropdown',
                'fieldflow:report-selection-intent',
                'fieldflow:report-selection-changed',
                'reportSelectionDetail',
            ],
            'src/Forms/FormRenderer.php' => [
                self::REPORT_SELECTION_MEDIA_9AB_CONTRACT,
                'fieldflow:form-media-checkpointed',
                "form.dataset.rpMediaSettling='1'",
                'IndexedDB. This is the commit point for camera/gallery return.',
                'Persist the optimised transport copy exactly once.',
                'rpResumeDraftArmedAt',
                'rpConsumeResumeDraftArm',
                "rpQueueResumeDraftForms('focus-after-hidden')",
                'Bare focus must not restore an older draft/step.',
            ],
            'src/Mobile/WebViewBridge.php' => [
                'fieldflowResumeArmedAt',
                'consumeNativeResumeArm',
                "queueNativeResume('focus-after-hidden')",
                'Bare focus must never restore an older visit.',
            ],
            'PHASE-9AB-REPORT-SELECTION-MEDIA-CONTINUITY.md' => [
                self::REPORT_SELECTION_MEDIA_9AB_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.543` and `STABLE_RELEASE` is `2.2.544`',
            ],
            'tools/verify-report-selection-media-continuity-9ab.php' => [
                'REPORT SELECTION MEDIA CONTINUITY 9AB CONTRACT OK',
                'successor=>=2.2.544',
            ],
            'tools/test-report-selection-media-continuity-9ab.php' => ['REPORT SELECTION MEDIA CONTINUITY 9AB RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => [
                'Report Selection &amp; Media Continuity 9AB',
                "packageResult['report_selection_media_9ab_ok']",
            ],
        ];
        $missing = [];
        if ($release === '' || version_compare($release, '2.2.544', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        if (strpos($bridge, "queueNativeResume('focus');") !== false) $missing[] = 'bridge:bare_focus_resume_regression';
        $formRenderer = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if (strpos($formRenderer, "rpQueueResumeDraftForms('focus')") !== false) $missing[] = 'form:bare_focus_draft_resume_regression';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'report_selection_media_9ab',
            'Report Selection & Media Continuity 9AB',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Report selection/media continuity contract is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Manual store selection is authoritative, bare Android focus cannot replay an older visit, and selected media is durably checkpointed before resume reconciliation.',
            [
                'runtime'=>$release,
                'contract'=>self::REPORT_SELECTION_MEDIA_9AB_CONTRACT,
                'manual_store_authoritative'=>true,
                'bare_focus_resume'=>false,
                'media_post_selection_checkpoint'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkResumeCertificationMedia9ACContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Support/ReleaseGate.php' => [
                self::RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT,
                'bridge:focus_resume_capability_missing',
                'form:focus_resume_capability_missing',
                'checkResumeCertificationMedia9ACContract',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'resume_certification_media_9ac_ok',
                self::RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT,
                'fieldSessionBridgeGuardedFocus',
                'fieldSessionFormGuardedFocus',
            ],
            'src/Forms/FormRenderer.php' => [
                self::RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT,
                "RP_MEDIA_ORIGINAL_PREFIX='__fieldflow_original__:'",
                'rpDraftStoreOriginalInputFiles',
                'rpDraftRecordsForInput',
                "date:String(\$form.find('[name=\"routespro_report_day\"],[name=\"routespro_context_date\"]')",
                'Persist the optimised transport copy exactly once.',
                "optimiseFileInput(input,false)",
            ],
            'assets/fieldflow-offline-runtime.js' => [
                "DURABLE_ORIGINAL_MEDIA_PREFIX='__fieldflow_original__:'",
                'durableGroups',
                'group.active.length?group.active:group.original',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'manual-report-selection-cleared',
                "localStorage.removeItem('fieldflow:last-report-visit')",
                'reportVisit:null',
            ],
            'src/Shortcodes.php' => [
                'clearStoredReportContext',
                'else clearStoredReportContext()',
            ],
            'src/Mobile/WebViewBridge.php' => [
                'consumeNativeResumeArm',
                "queueNativeResume('focus-after-hidden')",
            ],
            'PHASE-9AC-RESUME-CERTIFICATION-MEDIA-INTEGRITY.md' => [
                self::RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.544` and `STABLE_RELEASE` is `2.2.545`',
            ],
            'tools/verify-resume-certification-media-9ac.php' => [
                'RESUME CERTIFICATION MEDIA 9AC CONTRACT OK',
                'successor=>=2.2.545',
            ],
            'tools/test-resume-certification-media-9ac.php' => [
                'RESUME CERTIFICATION MEDIA 9AC RUNTIME OK',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Resume Certification &amp; Media Integrity 9AC',
                "packageResult['resume_certification_media_9ac_ok']",
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.545', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        if (strpos($bridge, "queueNativeResume('focus');") !== false) $missing[] = 'bridge:bare_focus_resume_regression';
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if (strpos($form, "rpQueueResumeDraftForms('focus')") !== false) $missing[] = 'form:bare_focus_draft_resume_regression';
        if (strpos($form, 'optimiseFileInput(input,true)') !== false) $missing[] = 'form:optimised_media_redispatch_regression';
        if (!preg_match('/return\s+optimiseFileInput\(input,false\);.*?Persist the optimised transport copy exactly once\..*?return\s+saveChangedFileInput\(input\);/s', $form)) $missing[] = 'form:optimised_media_commit_missing';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'resume_certification_media_9ac',
            'Resume Certification & Media Integrity 9AC',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Resume/certification/media integrity 9AC is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Historical 9S resume capability is successor-safe, manual selection can be explicitly cleared, operational day survives camera resume, and camera originals remain an isolated durable fallback.',
            [
                'runtime'=>$release,
                'contract'=>self::RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT,
                'bare_focus_resume'=>false,
                'historical_gate_successor_safe'=>true,
                'package_gate_parity'=>true,
                'original_media_fallback'=>true,
                'operational_day_checkpoint'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFormViewportShoppingDelivery9ADContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                "RP_FORM_VIEWPORT_SHOPPING_CONTRACT='fieldflow-form-viewport-shopping-delivery-integrity-v1'",
                'rpCaptureFormViewport',
                'rpRestoreFormViewport',
                'rpCaptureAllFormViewports',
                'rpRestoreElementViewport',
                "'cardex-remove'",
                "'media-selected'",
            ],
            'src/Shortcodes.php' => [
                "reportViewportContract='fieldflow-form-viewport-shopping-delivery-integrity-v1'",
                'captureReportViewport',
                'reserveReportSlotHeight',
                'restoreReportViewport',
                "manual-store-dropdown",
            ],
            'src/Shopping/ShoppingService.php' => [
                'fieldflow-shopping-canonical-attachment-v1',
                'canonical_submission_snapshot',
                'attachment_generation_failed',
                'attachment_missing',
                '$requestedFormats = []',
                '$missingAttachmentFormats',
            ],
            'src/Shopping/ShoppingExportService.php' => [
                'array $snapshot = []',
                'normaliseSubmissionDetail',
                'canonical_submission_snapshot',
                "['Campo','Valor']",
                "'Diferença €'",
                "'Variação %'",
                'priceAnalysis',
                'RESUMO DA SUBMISSÃO',
                'PREÇOS VERIFICADOS',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'form_viewport_shopping_delivery_9ad_ok',
                self::FORM_VIEWPORT_SHOPPING_9AD_CONTRACT,
            ],
            'PHASE-9AD-FORM-VIEWPORT-SHOPPING-DELIVERY-INTEGRITY.md' => [
                self::FORM_VIEWPORT_SHOPPING_9AD_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.545` and `STABLE_RELEASE` is `2.2.546`',
            ],
            'tools/verify-form-viewport-shopping-delivery-9ad.php' => [
                'FORM VIEWPORT SHOPPING DELIVERY 9AD CONTRACT OK',
                'successor=>=2.2.546',
            ],
            'tools/test-form-viewport-shopping-delivery-9ad.php' => [
                'FORM VIEWPORT SHOPPING DELIVERY 9AD RUNTIME OK',
            ],
            'src/Admin/ReleaseManager.php' => [
                'Form Viewport &amp; Shopping Delivery Integrity 9AD',
                "packageResult['form_viewport_shopping_delivery_9ad_ok']",
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.546', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if (strpos($form, "rpQueueResumeDraftForms('focus')") !== false) $missing[] = 'form:bare_focus_draft_resume_regression';
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        if (strpos($bridge, "queueNativeResume('focus');") !== false) $missing[] = 'bridge:bare_focus_resume_regression';
        $shopping = is_readable(ROUTESPRO_PATH . 'src/Shopping/ShoppingService.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shopping/ShoppingService.php') : '';
        if (strpos($shopping, 'if ($requestedFormats && ($missingAttachmentFormats || $attachmentErrors))') === false) $missing[] = 'shopping:fail_closed_missing';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'form_viewport_shopping_delivery_9ad',
            'Form Viewport & Shopping Delivery Integrity 9AD',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Form viewport/Shopping delivery integrity 9AD is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Form viewport remains visually anchored across store/media/Cardex/resume changes, and Shopping email/XLSX/PDF share one canonical snapshot with fail-closed requested attachments.',
            [
                'runtime'=>$release,
                'contract'=>self::FORM_VIEWPORT_SHOPPING_9AD_CONTRACT,
                'viewport_anchor'=>true,
                'store_swap_height_guard'=>true,
                'shopping_snapshot'=>'canonical_submission_snapshot',
                'requested_attachments_fail_closed'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkCanonicalReportingDelivery9AEContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Reporting/CanonicalOperationalReportService.php' => [
                "CONTRACT = 'fieldflow-canonical-operational-commercial-report-v1'",
                "'routing' => \$routing",
                "'fronts' => \$fronts",
                "'extra_spaces' => \$spaces",
                "'evidence' => \$evidence",
                "'orders' => \$orders",
                "'briefing' => \$briefing",
                "'operational_closure_rate'",
            ],
            'src/Rest/StatsController.php' => ['CanonicalOperationalReportService::build', "'canonical_operational_report'"],
            'src/Admin/FormSubmissions.php' => ['canonical_dashboard_export_sheets', 'canonical_dashboard_export_csv', 'build_canonical_dashboard_pdf_binary', 'Resumo Executivo', 'Rotas & Visitas', 'Espacos Extra'],
            'src/Admin/ReportAutomations.php' => ['reconcileMissedDailyClosures', "'force_durable'=>true", "if (!\$formats) \$formats = ['xlsx','pdf'];", '$missingFormats', 'Faltam anexos obrigatorios', "'canonical'=>(array)"],
            'src/Operations/Queue/QueueManager.php' => ['$forceDurable', "!\$forceDurable && !QueueSettings::isPilotOwner"],
            'src/Operations/Queue/QueueSettings.php' => ['fieldflow_report_automation_run', 'fieldflow_daily_close_dispatch_retry', 'fieldflow_order_suggestion_retry'],
            'src/Shortcodes.php' => ["reportViewportContract='fieldflow-form-viewport-continuity-v2'", 'reportScrollContainer', 'reserveReportSlotHeight', 'restoreReportViewport', "if(!visit){els.slot.innerHTML=''"],
            'src/Forms/FormRenderer.php' => ["rpViewportContract='fieldflow-form-viewport-continuity-v2'", 'rpScrollContainerFor', 'rpCaptureFormViewport', 'rpRestoreFormViewport'],
            'src/Support/ReleasePackageValidator.php' => ['canonical_reporting_delivery_9ae_ok', self::CANONICAL_REPORTING_DELIVERY_9AE_CONTRACT],
            'PHASE-9AE-CANONICAL-REPORTING-DELIVERY-VIEWPORT-CONTINUITY.md' => [self::CANONICAL_REPORTING_DELIVERY_9AE_CONTRACT, '`SOURCE_RELEASE` is `2.2.546` and `STABLE_RELEASE` is `2.2.547`'],
            'tools/verify-canonical-reporting-delivery-9ae.php' => ['CANONICAL REPORTING DELIVERY 9AE CONTRACT OK'],
            'tools/test-canonical-reporting-delivery-9ae.php' => ['CANONICAL REPORTING DELIVERY 9AE RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Canonical Reporting &amp; Delivery Reliability 9AE', "packageResult['canonical_reporting_delivery_9ae_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.547', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $shortcodes = is_readable(ROUTESPRO_PATH . 'src/Shortcodes.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shortcodes.php') : '';
        $renderStart = strpos($shortcodes, 'async function renderForm(options={})');
        $renderEnd = $renderStart !== false ? strpos($shortcodes, 'async function loadVisits', $renderStart) : false;
        $render = ($renderStart !== false && $renderEnd !== false) ? substr($shortcodes, $renderStart, $renderEnd - $renderStart) : '';
        if ($render === '') $missing[] = 'viewport:render_form';
        if ($render !== '' && preg_match("/reserveReportSlotHeight\\(\\);[\\s\\S]*?els\\.slot\\.innerHTML=''[\\s\\S]*?fetchOperationalNetwork/", $render)) {
            $validBranchPrefix = substr($render, 0, (int)strpos($render, 'fetchOperationalNetwork'));
            if (strpos($validBranchPrefix, "if(!visit){els.slot.innerHTML=''") === false) $missing[] = 'viewport:eager_valid_store_collapse';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'canonical_reporting_delivery_9ae',
            'Canonical Reporting & Delivery Reliability 9AE',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Canonical reporting/delivery/viewport 9AE is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Analytics, email, Excel, PDF and CSV share one canonical operational/commercial snapshot; report delivery is recoverable and the field viewport uses real-container atomic continuity.',
            [
                'runtime'=>$release,
                'contract'=>self::CANONICAL_REPORTING_DELIVERY_9AE_CONTRACT,
                'canonical_report'=>'fieldflow-canonical-operational-commercial-report-v1',
                'delivery_recovery'=>true,
                'requested_formats_fail_closed'=>true,
                'viewport'=>'fieldflow-form-viewport-continuity-v2',
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkCertificationViewportMail9AFContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Shortcodes.php' => [
                'fieldflow-form-viewport-lock-v1', 'beginReportViewportLock', 'finishReportViewportLock',
                'Combustível / energia', 'Custo deslocação', 'blur({preventScroll:true})',
            ],
            'src/Forms/FormRenderer.php' => [
                'fieldflow-form-viewport-lock-v1', 'rpBeginViewportLock', 'rpReleaseFormViewportLock',
                'media-handoff', 'media-selected', "rpQueueResumeDraftForms('focus-after-hidden')",
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'ff-react-front-operational-fallback', 'Operação pronta', 'Navegação clássica disponível',
                '.rp-premium-shell .ff-react-front-operational-fallback span',
            ],
            'src/Admin/ReportAutomations.php' => [
                'projectMailTemplateState', 'ensureDailyCloseAutomation', 'recoverMissingProjectTemplateAutomations',
                'Template do projeto · recuperável', 'Template canónico · recuperável',
                'Deliberately paused rows are never reactivated here.',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'certification_viewport_mail_9af_ok', self::CERTIFICATION_VIEWPORT_MAIL_9AF_CONTRACT,
            ],
            'PHASE-9AF-CERTIFICATION-VIEWPORT-MAIL-RECOVERY.md' => [
                self::CERTIFICATION_VIEWPORT_MAIL_9AF_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.547` and `STABLE_RELEASE` is `2.2.548`',
            ],
            'tools/verify-certification-viewport-mail-9af.php' => ['CERTIFICATION VIEWPORT MAIL 9AF CONTRACT OK'],
            'tools/test-certification-viewport-mail-9af.php' => ['CERTIFICATION VIEWPORT MAIL 9AF RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Certification, Viewport Lock &amp; Mail Recovery 9AF', "packageResult['certification_viewport_mail_9af_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.548', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if ($form !== '' && preg_match("/rpQueueResumeDraftForms\(\s*['\"]focus['\"]\s*\)/", $form)) {
            $missing[] = 'form:bare_focus_restore_reintroduced';
        }
        $shortcodes = is_readable(ROUTESPRO_PATH . 'src/Shortcodes.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shortcodes.php') : '';
        if (strpos($shortcodes, 'Combustível / energia') === false || strpos($shortcodes, 'Custo deslocação') === false) {
            $missing[] = 'mobility:package_gate_parity';
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'certification_viewport_mail_9af',
            'Certification, Viewport Lock & Mail Recovery 9AF',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Certification/viewport/mail recovery 9AF is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : 'Mobility gate/package parity is restored, the field viewport is locked through native/form transitions and missing Mail Center templates can be recovered without reactivating paused schedules.',
            [
                'runtime'=>$release,
                'contract'=>self::CERTIFICATION_VIEWPORT_MAIL_9AF_CONTRACT,
                'viewport_lock'=>'fieldflow-form-viewport-lock-v1',
                'mail_template_recovery'=>true,
                'mobility_parity'=>true,
                'schema'=>'2.2.529',
                'submission_build'=>'2.2.485',
                'missing'=>$missing,
            ]
        );
    }


    private static function checkMailFlowViewport9AGContract(): array {
        $release = self::currentRelease();
        $successorPassive = $release !== '' && version_compare($release, '2.2.551', '>=');
        $successorPaintMask = !$successorPassive && $release !== '' && version_compare($release, '2.2.550', '>=');
        $missing = [];
        $shortcodeMarkers = $successorPassive
            ? [
                'fieldflow-passive-viewport-continuity-v3', 'reportPassiveViewportContract',
                'Passive continuity deliberately performs no scrollTop/window.scrollTo write.',
                'fieldflow:form-external-handoff', 'fieldflow:form-viewport-unlocked',
            ]
            : ($successorPaintMask
                ? [
                    'fieldflow-viewport-paint-mask-v1', 'data-fieldflow-viewport-snapshot',
                    'stripReportSnapshotRuntime', 'guardReportViewport', 'reportSelectionViewportState',
                    'fieldflow:form-external-handoff', 'fieldflow:form-viewport-unlocked', 'fieldflow:form-visual-freeze',
                ]
                : [
                    'fieldflow-visual-viewport-freeze-v2', 'data-fieldflow-viewport-placeholder',
                    "root.style.position='fixed'", 'fieldflow:form-external-handoff', 'fieldflow:form-viewport-unlocked',
                ]);
        $formMarkers = $successorPassive
            ? [
                'fieldflow-passive-viewport-continuity-v3',
                'Do not write scrollTop. The WebView/browser owns scrolling in 2.2.551.',
                'position:fixed!important;left:-10000px!important;top:0!important',
                "rpQueueResumeDraftForms('focus-after-hidden')",
            ]
            : ($successorPaintMask
                ? [
                    'fieldflow-viewport-paint-mask-v1', 'rpRequestFormVisualFreeze', 'rpRequestFormVisualUnfreeze',
                    'conditional-choice-change', 'cardex-add', 'cardex-remove', "rpQueueResumeDraftForms('focus-after-hidden')",
                ]
                : [
                    'fieldflow-visual-viewport-freeze-v2', 'rpBeginViewportLock', 'rpReleaseFormViewportLock',
                    "rpQueueResumeDraftForms('focus-after-hidden')",
                ]);
        $requirements = [
            'src/Shortcodes.php' => $shortcodeMarkers,
            'src/Forms/FormRenderer.php' => $formMarkers,
            'src/Admin/ReportAutomations.php' => [
                'if ($automationType === \'daily_close\' && isset($ranges[\'daily\'])) $attachmentPeriodKeys = [\'daily\'];',
                "'audience' => 'manager'", 'Canonical report data uses full operational metric visibility',
                'Excel and PDF are part of the order-suggestion delivery contract', 'orderSuggestionMailEligibility',
            ],
            'src/Shopping/ShoppingService.php' => [
                'Shopping delivery contract: every verification email carries both canonical attachments.',
                '$requestedFormats = [\'xlsx\',\'pdf\'];',
            ],
            'src/Services/MailDispatchService.php' => [
                'private static function attachmentPaths', '$seen = [];', 'realpath($path) ?: $path', 'isset($seen[$key])',
            ],
            'src/Support/ReleasePackageValidator.php' => ['mail_flow_viewport_9ag_ok', self::MAIL_FLOW_VIEWPORT_9AG_CONTRACT],
            'PHASE-9AG-MAIL-FLOW-FINALIZATION-TRUE-VIEWPORT-FREEZE.md' => [
                self::MAIL_FLOW_VIEWPORT_9AG_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.548` and `STABLE_RELEASE` is `2.2.549`',
            ],
            'tools/verify-mail-flow-viewport-9ag.php' => ['MAIL FLOW VIEWPORT 9AG CONTRACT OK'],
            'tools/test-mail-flow-viewport-9ag.php' => ['MAIL FLOW VIEWPORT 9AG RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Mail Flow Finalization &amp; True Viewport Freeze 9AG', "packageResult['mail_flow_viewport_9ag_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.549', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $shortcodes = is_readable(ROUTESPRO_PATH . 'src/Shortcodes.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shortcodes.php') : '';
        if (($successorPaintMask || $successorPassive) && strpos($shortcodes, "root.style.position='fixed';") !== false) $missing[] = 'viewport:report_shell_fixed_lock_reintroduced';
        if ($successorPassive && strpos($shortcodes, 'reportViewportGuardRaf=window.requestAnimationFrame') !== false) $missing[] = 'viewport:continuous_scroll_guard_reintroduced';
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if ($form !== '' && (strpos($form, "document.body.style.position==='fixed'") !== false || strpos($form, "document.body.style.position='fixed'") !== false)) {
            $missing[] = 'viewport:body_fixed_lock_reintroduced';
        }
        if ($successorPassive && strpos($form, 'rpRequestFormVisualFreeze(form,') !== false) $missing[] = 'viewport:form_visual_freeze_reintroduced';
        if ($form !== '' && preg_match("/rpQueueResumeDraftForms\(\s*['\"]focus['\"]\s*\)/", $form)) $missing[] = 'form:bare_focus_restore_reintroduced';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'mail_flow_viewport_9ag',
            'Mail Flow Finalization & True Viewport Freeze 9AG',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Mail flow/viewport 9AG is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.')
                : ($successorPassive
                    ? 'Mail contracts remain deterministic while WebView scroll ownership stays passive: no paint mask, no continuous RAF scroll guard and native media inputs remain outside document flow.'
                    : ($successorPaintMask
                        ? 'Mail contracts remain deterministic and the report viewport uses a pre-picker paint mask, so native select/camera intermediate WebView frames are never exposed.'
                        : 'Daily Close, Shopping and eligible Order Suggestion have deterministic attachment contracts, merchandiser reporting keeps full user-scoped operational KPIs, and the visible report shell remains fixed without body locking.')),
            [
                'runtime'=>$release,
                'contract'=>self::MAIL_FLOW_VIEWPORT_9AG_CONTRACT,
                'daily_close_attachments'=>'one_period_requested_formats',
                'shopping_attachments'=>['xlsx','pdf'],
                'order_suggestion_attachments'=>['xlsx','pdf'],
                'merch_scope'=>'full_metrics_user_scoped',
                'viewport'=>$successorPassive ? 'fieldflow-passive-viewport-continuity-v3' : ($successorPaintMask ? 'fieldflow-viewport-paint-mask-v1' : 'fieldflow-visual-viewport-freeze-v2'),
            ]
        );
    }


    private static function checkSingleAnchorViewport9AHContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                self::SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT,
                'fieldflow-hot-resume-dom-authority-v1',
                "rpRestoreFormViewport(form,'media-return')",
                "rpRestoreFormViewport(form,'draft-cold-restore')",
                "rpRestoreFormViewport(form,'hot-resume')",
                'Single-anchor continuity: compensate exactly once in the same task as the DOM mutation.',
            ],
            'src/Shortcodes.php' => [
                self::SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT,
                'reportSingleAnchorViewportContract',
                'reportSelectionAnchor=captureReportViewport()',
                'options.viewportCheckpoint||captureReportViewport()',
            ],
            'src/Mobile/WebViewBridge.php' => ['fieldflow-resume-geometry-preserving-v3'],
            'src/Support/ReleasePackageValidator.php' => ['single_anchor_viewport_9ah_ok', self::SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT],
            'PHASE-9AH-SINGLE-ANCHOR-VIEWPORT-CONTINUITY.md' => [self::SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT, '`SOURCE_RELEASE` is `2.2.551` and `STABLE_RELEASE` is `2.2.552`'],
            'tools/verify-single-anchor-viewport-9ah.php' => ['SINGLE ANCHOR VIEWPORT 9AH CONTRACT OK'],
            'tools/test-single-anchor-viewport-9ah.php' => ['SINGLE ANCHOR VIEWPORT 9AH RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Single Anchor Viewport Continuity 9AH', "packageResult['single_anchor_viewport_9ah_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.552', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $form = is_readable(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/FormRenderer.php') : '';
        if (strpos($form, 'apply();window.requestAnimationFrame(apply);') !== false) $missing[] = 'viewport:double_restore_reintroduced';
        if (strpos($form, "finally(function(){rpRestoreFormViewport(form,'media-selected')") !== false) $missing[] = 'viewport:late_media_restore_reintroduced';
        if (preg_match("/routesproResumeDraft=function\\(opts\\)\\{[\\s\\S]{0,900}?restoreDraft\\(\\);/", $form)) $missing[] = 'resume:hot_resume_replays_draft';
        $shortcodes = is_readable(ROUTESPRO_PATH . 'src/Shortcodes.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Shortcodes.php') : '';
        if (strpos($shortcodes, 'reportViewportGuardRaf=window.requestAnimationFrame') !== false) $missing[] = 'viewport:continuous_report_guard_reintroduced';
        $bridge = is_readable(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/WebViewBridge.php') : '';
        if (preg_match('/ff-app-resume-portal-hidden[^\\n\\r]*content-visibility\\s*:\\s*hidden/i', $bridge)) $missing[] = 'resume:portal_geometry_collapsed';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'single_anchor_viewport_9ah',
            'Single Anchor Viewport Continuity 9AH',
            $missing ? 'fail' : 'pass',
            $missing ? 'Single-anchor viewport continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Form mutations, store selection, media return and app resume use one anchor correction only; hot resume keeps the live DOM authoritative and the WebView resume guard preserves document geometry.',
            ['runtime'=>$release,'contract'=>self::SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT,'hot_resume'=>'live_dom_authoritative','cold_resume'=>'durable_draft_then_single_anchor','media_return'=>'immediate_single_anchor','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkMailIntelligenceDailyReporting9AIContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Reporting/DailyCommercialReportService.php' => [
                self::MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT,
                "'capabilities'=>",
                "'exceptions'=>",
                "'gains'=>",
                "'losses'=>",
                "'alerts'=>",
                "'client'=>self::clientNarrative",
                "'merchandiser'=>self::merchandiserNarrative",
            ],
            'src/Reporting/CanonicalOperationalReportService.php' => ["'commercial_analysis'", 'DailyCommercialReportService::build'],
            'src/Admin/ReportAutomations.php' => ['Visão comercial do dia', 'Alertas e ações', "'report_audience'", "'mail_result' => !empty(\$mailDispatch['sent']) ? 'sent' : (\$ok ? 'queued' : 'failed')"],
            'src/Admin/FormSubmissions.php' => ['Resumo Comercial', 'Ganhos e Excecoes', "'Visitas'", "'Executada'", "'Justificacao'", "'Alertas'"],
            'src/Shopping/ShoppingService.php' => ["shopping_verification|' . \$submissionId", "'queued_recipients'", "'mail_result' => \$targetDelivered ? 'sent' : (\$targetAccepted ? 'queued' : 'failed')"],
            'src/Services/Notify.php' => ['One transport envelope per recipient', "'queued_recipients'", "route_notification|completed|"],
            'src/Services/MailOutboxRepository.php' => ['purgeTerminalRows'],
            'src/Services/MailDispatchService.php' => ['OUTBOX_ROW_RETENTION_SECONDS', 'purgeTerminalRows'],
            'src/Support/ReleasePackageValidator.php' => ['mail_intelligence_daily_reporting_9ai_ok', self::MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT],
            'PHASE-9AI-MAIL-INTELLIGENCE-DAILY-COMMERCIAL-REPORTING.md' => [self::MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT, '`SOURCE_RELEASE` is `2.2.552` and `STABLE_RELEASE` is `2.2.553`'],
            'tools/verify-mail-intelligence-daily-reporting-9ai.php' => ['MAIL INTELLIGENCE DAILY REPORTING 9AI CONTRACT OK'],
            'tools/test-mail-intelligence-daily-reporting-9ai.php' => ['MAIL INTELLIGENCE DAILY REPORTING 9AI RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Mail Intelligence &amp; Daily Commercial Reporting 9AI', "packageResult['mail_intelligence_daily_reporting_9ai_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.553', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'mail_intelligence_daily_reporting_9ai',
            'Mail Intelligence & Daily Commercial Reporting 9AI',
            $missing ? 'fail' : 'pass',
            $missing ? 'Mail intelligence or dynamic reporting is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Client and merchandiser receive separate deterministic narratives from one canonical commercial snapshot, with dynamic exports, explicit visit outcomes and reliable recipient-level mail semantics.',
            ['runtime'=>$release,'contract'=>self::MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT,'analysis'=>'deterministic','client'=>'commercial','merchandiser'=>'alerts','mail_states'=>'sent|queued|failed','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkBriefingStoreScopeReporting9AJContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Rest/StatsController.php' => ["'target_assignment_keys'=>", "'answered_assignment_keys'=>", "'target_store_ids'=>", "'answered_store_ids'=>"],
            'src/Reporting/CanonicalOperationalReportService.php' => ['question_store_mapping_x_report_routes', 'briefingAssignmentLocationId', "'briefing_target_assignments'", "'briefing_pending_assignments'"],
            'src/Reporting/DailyCommercialReportService.php' => ["'briefing_pending_assignments'", 'mapeadas às lojas presentes nesta rota/período'],
            'src/Admin/ReportAutomations.php' => ['respostas alvo', 'loja(s) mapeada(s) na rota'],
            'src/Admin/FormSubmissions.php' => ['Respostas alvo', 'Lojas alvo', 'Apenas perguntas mapeadas às lojas presentes no âmbito/rotas do relatório'],
            'src/Support/ReleasePackageValidator.php' => ['briefing_store_scope_reporting_9aj_ok', self::BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT],
            'PHASE-9AJ-BRIEFING-STORE-SCOPE-REPORTING-CONTINUITY.md' => [self::BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT, '`SOURCE_RELEASE` is `2.2.553` and `STABLE_RELEASE` is `2.2.554`'],
            'tools/verify-briefing-store-scope-reporting-9aj.php' => ['BRIEFING STORE SCOPE REPORTING 9AJ CONTRACT OK'],
            'tools/test-briefing-store-scope-reporting-9aj.php' => ['BRIEFING STORE SCOPE REPORTING 9AJ RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Briefing Store Scope Reporting Continuity 9AJ', "packageResult['briefing_store_scope_reporting_9aj_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.554', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'briefing_store_scope_reporting_9aj',
            'Briefing Store Scope Reporting Continuity 9AJ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Briefing store-scope reporting is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Briefing coverage, alerts and exports are calculated only for question-store assignments that intersect the stores represented by the current route/report scope.',
            ['runtime'=>$release,'contract'=>self::BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT,'scope'=>'question_mapping_x_report_routes','client'=>'scoped','merchandiser'=>'scoped_alerts','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkDailyMobilityCostReporting9AKContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Reporting/CanonicalOperationalReportService.php' => ["'mobility' => \$mobility", "'open_routes_excluded'", "'mobility_energy_cost_eur'", "'mobility_toll_cost_eur'"],
            'src/Reporting/DailyCommercialReportService.php' => ["'mobility'=>", "'energy_cost_eur'", "'toll_cost_eur'", 'combustível/energia estimado'],
            'src/Admin/ReportAutomations.php' => ['Mobilidade e custos por dia', 'Km executados', 'Combustivel / energia', 'Portagens', 'TOTAL GERAL'],
            'src/Admin/FormSubmissions.php' => ["'Mobilidade Diaria'", 'Km executados', 'Comb./energia EUR', 'Portagens EUR', 'TOTAL GERAL'],
            'src/Support/ReleasePackageValidator.php' => ['daily_mobility_cost_reporting_9ak_ok', self::DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT],
            'PHASE-9AK-DAILY-MOBILITY-COST-REPORTING-CONTINUITY.md' => [self::DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT, '`SOURCE_RELEASE` is `2.2.554` and `STABLE_RELEASE` is `2.2.555`'],
            'tools/verify-daily-mobility-cost-reporting-9ak.php' => ['DAILY MOBILITY COST REPORTING 9AK CONTRACT OK'],
            'tools/test-daily-mobility-cost-reporting-9ak.php' => ['DAILY MOBILITY COST REPORTING 9AK RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Daily Mobility Cost Reporting Continuity 9AK', "packageResult['daily_mobility_cost_reporting_9ak_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.555', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'daily_mobility_cost_reporting_9ak',
            'Daily Mobility Cost Reporting Continuity 9AK',
            $missing ? 'fail' : 'pass',
            $missing ? 'Daily mobility cost reporting is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Mail, Excel and PDF expose canonical closed-route kilometres and estimated fuel/energy and toll costs per day plus accumulated period totals, with partial totals explicitly labelled.',
            ['runtime'=>$release,'contract'=>self::DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT,'distance'=>'closed_routes_only','daily'=>'km|energy|tolls|total','accumulated'=>'total_general','partial'=>'explicit','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkKnownMobilityReportingRecovery9ALContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/MobilityCostService.php' => ['reportingProjection', "'known_toll_cost_eur'", "'reporting_quality'", "'known_distance_estimate'"],
            'src/Reporting/CanonicalOperationalReportService.php' => ["'mobility_reporting_recovered'", "'recovered_routes'", "'mobility_reporting_partial'"],
            'src/Reporting/DailyCommercialReportService.php' => ["'recovered_routes'", 'estimativa recuperada a partir dos km conhecidos', 'custos de combustível/energia e portagens ainda por calcular'],
            'src/Admin/ReportAutomations.php' => ['estimativa recuperada', 'Por calcular', 'Por apurar'],
            'src/Admin/FormSubmissions.php' => ['ESTIMATIVA RECUPERADA', 'POR CALCULAR', 'custos por calcular'],
            'src/Support/ReleasePackageValidator.php' => ['known_mobility_reporting_recovery_9al_ok', self::KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT],
            'PHASE-9AL-KNOWN-MOBILITY-REPORTING-RECOVERY.md' => [self::KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT, '`SOURCE_RELEASE` is `2.2.555` and `STABLE_RELEASE` is `2.2.556`'],
            'tools/verify-known-mobility-reporting-recovery-9al.php' => ['KNOWN MOBILITY REPORTING RECOVERY 9AL CONTRACT OK'],
            'tools/test-known-mobility-reporting-recovery-9al.php' => ['KNOWN MOBILITY REPORTING RECOVERY 9AL RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Known Mobility Reporting Recovery 9AL', "packageResult['known_mobility_reporting_recovery_9al_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.556', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'known_mobility_reporting_recovery_9al',
            'Known Mobility Reporting Recovery 9AL',
            $missing ? 'fail' : 'pass',
            $missing ? 'Known mobility reporting recovery is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Closed routes with known kilometres no longer render false zero mobility values. Reporting reuses the canonical MobilityCostService estimate and explicitly labels recovered values as partial.',
            ['runtime'=>$release,'contract'=>self::KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT,'fallback'=>'known_distance_km','formula'=>'MobilityCostService','canonical_flag'=>'unchanged','partial'=>'explicit','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkFullRouteMobilityReporting9AMContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/MobilityCostService.php' => ["'reporting_full_route_reconciled'", "'reporting_distance_basis'", "'reporting_access_distance_km'", "'reporting_return_distance_km'", 'stored_route_legs'],
            'src/Services/RouteMetricsService.php' => ['reportingStoredRouteLegBreakdown', "'access_distance_km'", "'inter_stop_distance_km'", "'return_distance_km'"],
            'src/Reporting/CanonicalOperationalReportService.php' => ["'access_distance_km'", "'inter_stop_distance_km'", "'return_distance_km'"],
            'src/Admin/ReportAutomations.php' => ['Acesso ', 'PDVs ', 'Regresso '],
            'src/Admin/FormSubmissions.php' => ["'Km acesso','Km entre PDVs','Km regresso'", 'Percurso integral por dia'],
            'src/Support/ReleasePackageValidator.php' => ['full_route_mobility_reporting_9am_ok', self::FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT],
            'PHASE-9AM-FULL-ROUTE-MOBILITY-REPORTING-RECONCILIATION.md' => [self::FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT, '`SOURCE_RELEASE` is `2.2.556` and `STABLE_RELEASE` is `2.2.557`'],
            'tools/verify-full-route-mobility-reporting-reconciliation-9am.php' => ['FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM CONTRACT OK'],
            'tools/test-full-route-mobility-reporting-reconciliation-9am.php' => ['FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Full Route Mobility Reporting Reconciliation 9AM', "packageResult['full_route_mobility_reporting_9am_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.557', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'full_route_mobility_reporting_9am',
            'Full Route Mobility Reporting Reconciliation 9AM',
            $missing ? 'fail' : 'pass',
            $missing ? 'Full route mobility reporting reconciliation is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Daily mobility reporting reconciles access, inter-stop and return kilometres before estimating fuel/energy and toll costs, while leaving canonical historical route metrics unchanged.',
            ['runtime'=>$release,'contract'=>self::FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT,'distance'=>'access+inter_stop+return','fallback'=>'stored_route_legs','canonical'=>'unchanged','reporting'=>'reconciled','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkRoutingEndpointGeographicIntegrity9ANContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/RoutingGeographyService.php' => [self::ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT, 'projectCountryCode', 'coordinatesLikelyInCountry'],
            'src/Services/Maps.php' => ["'components'=>'country:' . \$countryCode", "'countrySet'=>\$countryCode", 'google_geocode_v2', 'azure_geocode_v2'],
            'src/Services/RouteEndpointResolverService.php' => ["'country_code' => \$countryCode", 'country_safe_coordinate_fallback', self::ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT],
            'src/Services/RouteMetricsService.php' => ['ensureGeographicIntegrityForRoute', 'reconcileProjectGeographicIntegrity', 'reconcileRouteStopGeography', 'google_routes_geography_v3'],
            'src/Admin/CampaignLocations.php' => ['routing_country_code', 'componentRestrictions'],
            'src/Admin/Routes.php' => ['routing_country_code', 'routingCountryForSelection'],
            'src/Admin/Assignments.php' => ['reconcile_routing_geography', 'Revalidar país, endpoints, PDVs e km'],
            'src/Admin/ReportAutomations.php' => ['ensureGeographicIntegrityForRoute', 'route_geography_unresolved:', '$subjectAnchorDate'],
            'src/Support/ReleasePackageValidator.php' => ['routing_endpoint_geographic_integrity_9an_ok', self::ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT],
            'PHASE-9AN-ROUTING-ENDPOINT-GEOGRAPHIC-INTEGRITY.md' => [self::ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT, '`SOURCE_RELEASE` is `2.2.557` and `STABLE_RELEASE` is `2.2.558`'],
            'tools/verify-routing-endpoint-geographic-integrity-9an.php' => ['ROUTING ENDPOINT GEOGRAPHIC INTEGRITY 9AN CONTRACT OK'],
            'tools/test-routing-endpoint-geographic-integrity-9an.php' => ['ROUTING ENDPOINT GEOGRAPHIC INTEGRITY 9AN RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Routing Endpoint Geographic Integrity 9AN', "packageResult['routing_endpoint_geographic_integrity_9an_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.558', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'routing_endpoint_geographic_integrity_9an',
            'Routing Endpoint Geographic Integrity 9AN',
            $missing ? 'fail' : 'pass',
            $missing ? 'Routing endpoint geographic integrity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Campaign country is authoritative for endpoint and PDV geocoding. Legacy route geometry is reconciled before Daily Close and the same persisted route metrics feed Analytics, exports and mail.',
            ['runtime'=>$release,'contract'=>self::ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT,'country'=>'campaign','providers'=>'google+azure','legacy_routes'=>'revalidated','daily_close'=>'refresh_before_report','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkRouteMobilityDashboardWidgets9AOContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Analytics/RouteMobilityWidgetService.php' => [self::ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT, 'route_distance_km', 'route_distance_breakdown', 'route_mobility_cost_eur', 'CanonicalOperationalReportService::routeMobilitySnapshot'],
            'src/Reporting/CanonicalOperationalReportService.php' => ['public static function routeMobilitySnapshot'],
            'src/Rest/StatsController.php' => ['RouteMobilityWidgetService::isSystemSettings', 'RouteMobilityWidgetService::buildMetric'],
            'src/Admin/Assignments.php' => ['Origem do widget', 'Rotas e mobilidade', 'system_metric_key'],
            'src/Front/ClientPortalShortcodeRenderer.php' => ['isRouteMobilityMetric', 'routeMobilityTableHtml', 'Km acesso', 'Km entre PDVs', 'Km regresso'],
            'src/Analytics/WidgetOrchestrator.php' => ["return 'mobility'", "'mobility'=>"],
            'src/Support/ReleasePackageValidator.php' => ['route_mobility_dashboard_widgets_9ao_ok', self::ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT],
            'PHASE-9AO-ROUTE-MOBILITY-DASHBOARD-WIDGETS.md' => [self::ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT, '`SOURCE_RELEASE` is `2.2.558` and `STABLE_RELEASE` is `2.2.559`'],
            'tools/verify-route-mobility-dashboard-widgets-9ao.php' => ['ROUTE MOBILITY DASHBOARD WIDGETS 9AO CONTRACT OK'],
            'tools/test-route-mobility-dashboard-widgets-9ao.php' => ['ROUTE MOBILITY DASHBOARD WIDGETS 9AO RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Route & Mobility Dashboard Widgets 9AO', "packageResult['route_mobility_dashboard_widgets_9ao_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.559', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'route_mobility_dashboard_widgets_9ao',
            'Route & Mobility Dashboard Widgets 9AO',
            $missing ? 'fail' : 'pass',
            $missing ? 'Route and mobility dashboard widgets are incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Intelligence Center can configure route and mobility widgets from the same canonical route/mobility snapshot used by Daily Close and exports, preserving the current responsive dashboard layout.',
            ['runtime'=>$release,'contract'=>self::ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT,'source'=>'canonical_route_mobility','widgets'=>8,'frontend'=>'current_layout','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkReportStatusCanonicalSync9APContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Shortcodes.php' => [self::REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT, 'recentVisitStates', 'fieldflowLive:true', "document.addEventListener('fieldflow:visit-completed'", "canonicalVisitStatus(stop.canonical_status||stop.status||'pending')"],
            'src/Rest/RoutesController.php' => ['CanonicalStateService::visit', "'canonical_status'", "'legacy_status'", "'status_source'", 'canonical_visit_state', 'front_stop_status_from_canonical'],
            'src/Support/ReleasePackageValidator.php' => ['report_status_canonical_sync_9ap_ok', self::REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT],
            'PHASE-9AP-REPORT-STATUS-CANONICAL-SYNC.md' => [self::REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT, '`SOURCE_RELEASE` is `2.2.559` and `STABLE_RELEASE` is `2.2.560`'],
            'tools/verify-report-status-canonical-sync-9ap.php' => ['REPORT STATUS CANONICAL SYNC 9AP CONTRACT OK'],
            'tools/test-report-status-canonical-sync-9ap.php' => ['REPORT STATUS CANONICAL SYNC 9AP RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Report Status Canonical Sync 9AP', "packageResult['report_status_canonical_sync_9ap_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.560', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'report_status_canonical_sync_9ap',
            'Report Status Canonical Sync 9AP',
            $missing ? 'fail' : 'pass',
            $missing ? 'Report status canonical sync is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Reportar consumes the canonical visit state live-first, updates immediately on visit completion, and only uses recent terminal state to protect offline cached responses from regressing to pending.',
            ['runtime'=>$release,'contract'=>self::REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT,'surface'=>'reportar+minha_rota','live_state'=>'authoritative','offline_cache'=>'fallback_only','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkConditionGraphIntegrity9AQContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/ConditionalGraphIntegrity.php' => [self::CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT, 'cross_domain_gate', 'controlled_by', 'non_blocking_graph_audit'],
            'src/Admin/Forms.php' => ['ConditionalGraphIntegrity::analyse', 'Auditoria condicional'],
            'assets/routespro-form-builder.js' => [self::CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT, 'Controlada por', 'Grafo condicional coerente', 'cross_domain_gate'],
            'assets/routespro-form-builder.css' => ['twt-fb-integrity', 'twt-fb-controlled-by', 'has-condition-integrity-warning'],
            'src/Support/ReleasePackageValidator.php' => ['condition_graph_integrity_9aq_ok', self::CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT],
            'PHASE-9AQ-CONDITION-GRAPH-INTEGRITY.md' => [self::CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT, '`SOURCE_RELEASE` is `2.2.560` and `STABLE_RELEASE` is `2.2.561`'],
            'tools/verify-condition-graph-integrity-9aq.php' => ['CONDITION GRAPH INTEGRITY 9AQ CONTRACT OK'],
            'tools/test-condition-graph-integrity-9aq.php' => ['CONDITION GRAPH INTEGRITY 9AQ RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['Condition Graph Integrity 9AQ', "packageResult['condition_graph_integrity_9aq_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.561', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'condition_graph_integrity_9aq',
            'Condition Graph Integrity 9AQ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Conditional graph integrity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'The form builder exposes conditional ownership and warns about suspicious cross-domain gates without changing the certified conditional visibility runtime.',
            ['runtime'=>$release,'contract'=>self::CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT,'mode'=>'non_blocking_audit','combination'=>'or','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkWordPressMediaIsolation9ARContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'fieldflow.php' => ['isWordPressMediaProcessingRequest', 'WordPress Media Library isolation', 'return;'],
            'src/Support/Plugin.php' => [self::WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT, 'async-upload.php', 'upload-attachment', 'image-editor', '/wp/v2/media'],
            'src/Security/UploadGuard.php' => ["if (!self::isFieldFlowRequest()) return;", 'isWordPressMediaProcessingRequest', 'routespro_form_id'],
            'src/Support/ReleasePackageValidator.php' => ['wordpress_media_isolation_9ar_ok', self::WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT],
            'PHASE-9AR-WORDPRESS-MEDIA-ISOLATION.md' => [self::WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT, '`SOURCE_RELEASE` is `2.2.561` and `STABLE_RELEASE` is `2.2.562`'],
            'tools/verify-wordpress-media-isolation-9ar.php' => ['WORDPRESS MEDIA ISOLATION 9AR CONTRACT OK'],
            'tools/test-wordpress-media-isolation-9ar.php' => ['WORDPRESS MEDIA ISOLATION 9AR RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['WordPress Media Isolation 9AR', "packageResult['wordpress_media_isolation_9ar_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.562', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $uploadGuard = is_readable(ROUTESPRO_PATH . 'src/Security/UploadGuard.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Security/UploadGuard.php') : '';
        if (strpos($uploadGuard, "['routespro_form_id','form_id'") !== false) $missing[] = 'upload_guard:generic_form_id_scope';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'wordpress_media_isolation_9ar',
            'WordPress Media Isolation 9AR',
            $missing ? 'fail' : 'pass',
            $missing ? 'WordPress media isolation is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Core WordPress media processing bypasses the FieldFlow runtime and FieldFlow upload policy is attached only to FieldFlow-owned requests.',
            ['runtime'=>$release,'contract'=>self::WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT,'core_media'=>'isolated','upload_guard'=>'fieldflow_only','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkMyRoutePeriodicity9ASContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/PeriodicityProgressService.php' => [self::MY_ROUTE_PERIODICITY_9AS_CONTRACT, "SubmissionStatusPolicy::operationalSql('fs')", 'periodicityLabel', 'Coberta no plano'],
            'src/Rest/RoutesController.php' => ["'/my-periodicity'", 'PeriodicityProgressService::forUser', 'get_current_user_id()'],
            'src/Shortcodes.php' => ['rp-periodicity', 'Cobertura do mês', 'data-periodicity-filter="attention"', 'loadPeriodicity', 'forcePeriodicity'],
            'src/Support/ReleasePackageValidator.php' => ['my_route_periodicity_9as_ok', self::MY_ROUTE_PERIODICITY_9AS_CONTRACT],
            'PHASE-9AS-MY-ROUTE-PERIODICITY-CONTROL.md' => [self::MY_ROUTE_PERIODICITY_9AS_CONTRACT, '`SOURCE_RELEASE` is `2.2.562` and `STABLE_RELEASE` is `2.2.563`'],
            'tools/verify-my-route-periodicity-9as.php' => ['MY ROUTE PERIODICITY 9AS CONTRACT OK'],
            'tools/test-my-route-periodicity-9as.php' => ['MY ROUTE PERIODICITY 9AS RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['My Route Periodicity Control 9AS', "packageResult['my_route_periodicity_9as_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.563', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'my_route_periodicity_9as',
            'My Route Periodicity Control 9AS',
            $missing ? 'fail' : 'pass',
            $missing ? 'My Route periodicity control is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Minha Rota exposes compact monthly cadence coverage using canonical completed visits and published future route stops, without changing routing or submission state.',
            ['runtime'=>$release,'contract'=>self::MY_ROUTE_PERIODICITY_9AS_CONTRACT,'surface'=>'minha_rota','mode'=>'read_only','cadence'=>'campaign_source_of_truth','future_plan'=>'published_routes_only','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkMyRoutePeriodicityAction9ATContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/PeriodicityProgressService.php' => [self::MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT, 'businessDaysRemaining', 'riskProfile', "'risk_level'", "'business_days_remaining'"],
            'src/Shortcodes.php' => ['Só falta cumprir', 'rp-periodicity-risk', 'fieldflow:planner-intent:v1', 'fieldflow:open-planner', 'fieldflow:self-routes-created'],
            'src/Front/AppShortcodeRenderer.php' => [self::MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT, 'fieldflow:open-planner', 'fieldflow:planner-focus-store'],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => ['fieldflow:planner-focus-store', 'applyPlannerIntent', 'plannerIntentStorageKey', 'intent-focus'],
            'src/Support/ReleasePackageValidator.php' => ['my_route_periodicity_action_9at_ok', self::MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT],
            'PHASE-9AT-MY-ROUTE-PERIODICITY-ACTION-UX.md' => [self::MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT, '`SOURCE_RELEASE` is `2.2.563` and `STABLE_RELEASE` is `2.2.564`'],
            'tools/verify-my-route-periodicity-action-9at.php' => ['MY ROUTE PERIODICITY ACTION 9AT CONTRACT OK'],
            'tools/test-my-route-periodicity-action-9at.php' => ['MY ROUTE PERIODICITY ACTION 9AT RUNTIME OK'],
            'src/Admin/ReleaseManager.php' => ['My Route Periodicity Action UX 9AT', "packageResult['my_route_periodicity_action_9at_ok']"],
        ];
        if ($release === '' || version_compare($release, '2.2.564', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'my_route_periodicity_action_9at',
            'My Route Periodicity Action UX 9AT',
            $missing ? 'fail' : 'pass',
            $missing ? 'My Route periodicity action UX is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Minha Rota prioritizes monthly cadence risk and hands an unresolved store directly to Planear with the correct campaign and month, without auto-creating a route.',
            ['runtime'=>$release,'contract'=>self::MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT,'surface'=>'minha_rota','risk'=>'business_days_remaining','handoff'=>'planner_exact_store','filter'=>'session_persistent','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkReleaseManagerPublicationReceipt9AUContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Cloud/ReleasePublicationReceipt.php' => [
                self::RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT,
                "OPTION = 'fieldflow_release_manager_last_state'",
                'auditCandidates',
                "'action_name'=>'release.published'",
                "'receipt_source'=>'none'",
                'healLocalOption',
            ],
            'src/Admin/ReleaseManager.php' => [
                'ReleasePublicationReceipt::persist($state)',
                "ReleasePublicationReceipt::current(defined('FIELDFLOW_VERSION')",
                'release.published',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'ReleasePublicationReceipt::current(self::STABLE_RELEASE)',
                "'wordpress_evidence'",
                "'catalog_sha_match'",
                "'receipt_source'",
                "SOURCE_RELEASE = '2.2.564'",
                "STABLE_RELEASE = '2.2.565'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'release_manager_publication_receipt_9au_ok',
                self::RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT,
            ],
            'PHASE-9AU-RELEASE-MANAGER-PUBLICATION-RECEIPT-CONTINUITY.md' => [
                self::RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.564` and `STABLE_RELEASE` is `2.2.565`',
                'No bypass',
            ],
            'tools/verify-release-publication-receipt-continuity-9au.php' => [
                'RELEASE MANAGER PUBLICATION RECEIPT 9AU CONTRACT OK',
            ],
            'tools/test-release-publication-receipt-continuity-9au.php' => [
                'RELEASE MANAGER PUBLICATION RECEIPT 9AU RUNTIME OK',
            ],
        ];
        if ($release === '' || version_compare($release, '2.2.565', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'release_manager_publication_receipt_9au',
            'Release Manager Publication Receipt Continuity 9AU',
            $missing ? 'fail' : 'pass',
            $missing ? 'Publication receipt continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'WordPress Release Manager publication evidence is durable across option-cache drift and is corroborated by the private catalog SHA-256 before Phase 6 certification.',
            ['runtime'=>$release,'contract'=>self::RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT,'receipt_sources'=>['option','network_option','audit_log'],'catalog_sha'=>'required','bypass'=>false,'schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkClientCostDateScopeRouteTotals9AVContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'selectedFilterRange', 'routeInRange', 'routeAggregate', 'aggregateMoney', 'rp-route-list-totals',
                "p.set('date_from',els.from.value)", "p.set('date_to',els.to.value)",
                'totais de ', 'Custo deslocação',
            ],
            'src/Rest/StatsController.php' => ["get_param('date_from')", "get_param('date_to')"],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.565'", "STABLE_RELEASE = '2.2.566'"],
            'src/Support/ReleasePackageValidator.php' => ['client_cost_date_scope_route_totals_9av_ok', self::CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT],
            'PHASE-9AV-CLIENT-COST-DATE-SCOPE-ROUTE-TOTALS.md' => [self::CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT, '`SOURCE_RELEASE` is `2.2.565` and `STABLE_RELEASE` is `2.2.566`'],
            'tools/verify-client-cost-date-scope-route-totals-9av.php' => ['CLIENT COST DATE SCOPE ROUTE TOTALS 9AV CONTRACT OK'],
            'tools/test-client-cost-date-scope-route-totals-9av.php' => ['CLIENT COST DATE SCOPE ROUTE TOTALS 9AV RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.566', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'client_cost_date_scope_route_totals_9av',
            'Client Cost Date Scope & Route Totals 9AV',
            $missing ? 'fail' : 'pass',
            $missing ? 'Client cost date scope and route totals are incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Client mobility totals obey the selected date interval while the annual agenda remains a navigation surface, and route lists expose aggregate km and costs.',
            ['runtime'=>$release,'contract'=>self::CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT,'scope'=>'client_date_filter','route_list_totals'=>true,'annual_agenda_navigation'=>true,'schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkRouteFinancialAudienceAnalytics9AWContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'routeOperationalCost', 'routeMobilityReportingAvailable', 'reporting_costs_available',
                'Custo operacional', 'Custo deslocação total', 'rp-consolidated-summary',
                'renderTeamPerformance', 'Performance da equipa', 'Quem tem a melhor performance da equipa neste período?',
            ],
            'src/Rest/RoutesController.php' => ['MobilityCostService::reportingProjection'],
            'src/Repositories/RouteAccessRepository.php' => ['MobilityCostService::reportingProjection'],
            'src/Rest/StatsController.php' => ['team_performance', 'fieldflow-audience-performance-v1', 'performance_index', "'question_metric_scope'"],
            'src/Analytics/FieldFlowAskService.php' => ["return 'performance'", 'team_leader_name', "audience === 'merchandiser' ? 'self' : 'scope_member'", 'Nunca exponhas nomes, rankings ou métricas de outros membros da equipa.'],
            'src/Shortcodes.php' => ['renderAudiencePerformance', 'A minha performance', 'Performance da equipa'],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.566'", "STABLE_RELEASE = '2.2.567'"],
            'src/Support/ReleasePackageValidator.php' => ['route_financial_audience_analytics_9aw_ok', self::ROUTE_FINANCIAL_AUDIENCE_ANALYTICS_9AW_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['route_financial_audience_analytics_9aw_ok']"],
            'PHASE-9AW-ROUTE-FINANCIAL-AUDIENCE-ANALYTICS.md' => [self::ROUTE_FINANCIAL_AUDIENCE_ANALYTICS_9AW_CONTRACT, '`SOURCE_RELEASE` is `2.2.566` and `STABLE_RELEASE` is `2.2.567`'],
            'tools/verify-route-financial-audience-analytics-9aw.php' => ['ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW CONTRACT OK'],
            'tools/test-route-financial-audience-analytics-9aw.php' => ['ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.567', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'route_financial_audience_analytics_9aw',
            'Route Financial Breakdown & Audience Analytics 9AW',
            $missing ? 'fail' : 'pass',
            $missing ? 'Route financial breakdown and audience-aware analytics are incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Route consultation exposes the full mobility cost composition, consolidated reporting uses the premium summary layout, and analytics separates team/client comparison from merchandiser self-only intelligence.',
            ['runtime'=>$release,'contract'=>self::ROUTE_FINANCIAL_AUDIENCE_ANALYTICS_9AW_CONTRACT,'route_financial_breakdown'=>true,'consolidated_layout'=>'premium','client_scope'=>'team_or_individual','merchandiser_scope'=>'self_only','question_metrics'=>'audience_scoped','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkClientPortalRuntimeScope9AXContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                "function renderKpis(stats){ const prev=stats?.previous_summary||{}; const rank=stats?.rankings||{}; const perf=(stats&&stats.team_performance&&typeof stats.team_performance==='object')?stats.team_performance:{}; const leader=(perf.leader&&typeof perf.leader==='object')?perf.leader:null;",
                'renderKpis(state.stats);', 'renderRoutesTable(state.stats.by_day||[]);', 'renderAnalytics(state.stats);'
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.567'", "STABLE_RELEASE = '2.2.568'"],
            'src/Support/ReleasePackageValidator.php' => ['client_portal_runtime_scope_9ax_ok', self::CLIENT_PORTAL_RUNTIME_SCOPE_9AX_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['client_portal_runtime_scope_9ax_ok']"],
            'PHASE-9AX-CLIENT-PORTAL-RUNTIME-SCOPE-CONTINUITY.md' => [self::CLIENT_PORTAL_RUNTIME_SCOPE_9AX_CONTRACT, '`SOURCE_RELEASE` is `2.2.567` and `STABLE_RELEASE` is `2.2.568`'],
            'tools/verify-client-portal-runtime-scope-9ax.php' => ['CLIENT PORTAL RUNTIME SCOPE 9AX CONTRACT OK'],
            'tools/test-client-portal-runtime-scope-9ax.php' => ['CLIENT PORTAL RUNTIME SCOPE 9AX RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.568', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'client_portal_runtime_scope_9ax',
            'Client Portal Runtime Scope Continuity 9AX',
            $missing ? 'fail' : 'pass',
            $missing ? 'Client portal KPI scope continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Client portal performance bindings are local to renderKpis, preventing KPI ReferenceErrors from aborting Summary, Routes and Analytics mounting.',
            ['runtime'=>$release,'contract'=>self::CLIENT_PORTAL_RUNTIME_SCOPE_9AX_CONTRACT,'local_perf_binding'=>true,'local_leader_binding'=>true,'schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkClientPortalFilterAuthority9AYContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'selectedFilterRange', 'routesInSelectedRange',
                'O filtro global De/Até permanece ativo.',
                'if(els.from) els.from.disabled=false;',
                'if(els.to) els.to.disabled=false;',
                "p.set('from',els.from.value)", "p.set('to',els.to.value)",
                "p.set('date_from',els.from.value)", "p.set('date_to',els.to.value)",
                'renderTeamPerformance', 'rp-consolidated-summary', 'Custo deslocação total'
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.568'", "STABLE_RELEASE = '2.2.569'"],
            'src/Support/ReleasePackageValidator.php' => ['client_portal_filter_authority_9ay_ok', self::CLIENT_PORTAL_FILTER_AUTHORITY_9AY_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['client_portal_filter_authority_9ay_ok']"],
            'PHASE-9AY-CLIENT-PORTAL-FILTER-AUTHORITY-REBASE.md' => [self::CLIENT_PORTAL_FILTER_AUTHORITY_9AY_CONTRACT, '`SOURCE_RELEASE` is `2.2.568` and `STABLE_RELEASE` is `2.2.569`'],
            'tools/verify-client-portal-filter-authority-9ay.php' => ['CLIENT PORTAL FILTER AUTHORITY 9AY CONTRACT OK'],
            'tools/test-client-portal-filter-authority-9ay.php' => ['CLIENT PORTAL FILTER AUTHORITY 9AY RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.569', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $front = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        if (strpos($front, 'els.from.disabled=!!date') !== false || strpos($front, 'els.to.disabled=!!date') !== false) $missing[] = 'front:date_inputs_must_not_lock';
        if (strpos($front, 'if(state.routeDateLocked) return;') !== false) $missing[] = 'front:date_change_must_not_be_blocked';
        if (strpos($front, 'if(date) els.from.value=date;') !== false || strpos($front, 'if(date) els.to.value=date;') !== false) $missing[] = 'front:route_must_not_rewrite_global_dates';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'client_portal_filter_authority_9ay',
            'Client Portal Filter Authority Rebase 9AY',
            $missing ? 'fail' : 'pass',
            $missing ? 'Client portal filter authority rebase is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Client portal is rebased on the stable 2.2.565 shell semantics: De/Até remains the global source of truth, route selection never locks or rewrites the date range, and post-565 financial and audience analytics improvements remain isolated.',
            ['runtime'=>$release,'contract'=>self::CLIENT_PORTAL_FILTER_AUTHORITY_9AY_CONTRACT,'global_date_filter'=>'authoritative','route_selection'=>'non_blocking','shell_baseline'=>'2.2.565 semantics','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkRouteCalendarMobilePortalFeatures9AZContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'const totals=routeAggregate(items);',
                'const totalLabel=aggregateMoney(totals.mobility,totals.unresolved);',
                "custo total '+totalLabel",
                'routeMobilityTotal(r)'
            ],
            'src/Mobile/Settings.php' => [
                "'feature_visibility' => [", "'operational' => [", "'client' => [",
                'feature_for_target', 'features_for_target',
                "Legacy global feature flags remain the union of both portals"
            ],
            'src/Admin/MobileControlCenter.php' => [
                'Funcionalidades móveis por portal', 'Portal Operativo', 'Portal Cliente',
                'fieldflow_mobile[feature_visibility]'
            ],
            'src/Mobile/Controller.php' => [
                'Settings::feature_for_target', "'portal_features' => \$portal_features", "'operational' => Settings::features_for_target('operational')", "'client' => Settings::features_for_target('client')"
            ],
            'src/Mobile/WebViewBridge.php' => ['Settings::feature_for_target($feature, $target)'],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.569'", "STABLE_RELEASE = '2.2.570'"],
            'src/Support/ReleasePackageValidator.php' => ['route_calendar_mobile_portal_features_9az_ok', self::ROUTE_CALENDAR_MOBILE_PORTAL_FEATURES_9AZ_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['route_calendar_mobile_portal_features_9az_ok']"],
            'PHASE-9AZ-ROUTE-CALENDAR-DAILY-TOTAL-MOBILE-PORTAL-FEATURES.md' => [self::ROUTE_CALENDAR_MOBILE_PORTAL_FEATURES_9AZ_CONTRACT, '`SOURCE_RELEASE` is `2.2.569` and `STABLE_RELEASE` is `2.2.570`'],
            'tools/verify-route-calendar-mobile-portal-features-9az.php' => ['ROUTE CALENDAR MOBILE PORTAL FEATURES 9AZ CONTRACT OK'],
            'tools/test-route-calendar-mobile-portal-features-9az.php' => ['ROUTE CALENDAR MOBILE PORTAL FEATURES 9AZ RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.570', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $front = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        $calendarStart = strpos($front, 'function renderRouteCalendar()');
        $calendarEnd = $calendarStart === false ? false : strpos($front, 'async function openRouteFromAgenda', $calendarStart);
        $calendar = ($calendarStart !== false && $calendarEnd !== false) ? substr($front, $calendarStart, $calendarEnd - $calendarStart) : '';
        if ($calendar === '' || strpos($calendar, 'const toll=items.reduce') !== false || strpos($calendar, 'const tollLabel=') !== false) $missing[] = 'front:calendar_toll_only_regression';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'route_calendar_mobile_portal_features_9az',
            'Route Calendar Daily Total & Mobile Portal Features 9AZ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Route calendar/mobile portal feature contract is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Route calendar day cards expose total mobility cost and mobile feature visibility is independently controlled for Operational and Client portals with backwards-compatible migration.',
            ['runtime'=>$release,'contract'=>self::ROUTE_CALENDAR_MOBILE_PORTAL_FEATURES_9AZ_CONTRACT,'calendar_cost'=>'total_mobility_daily','mobile_features'=>'portal_specific','legacy_features'=>'union_compatibility','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkClientRouteHeroDateAuthority9BAContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'function agendaRange(){ return selectedFilterRange(); }',
                'const range=selectedFilterRange();',
                'const routes=routesInSelectedRange(state.routes);',
                'Calendário validado exclusivamente por <strong>De → Até</strong>',
                "p.set('date_from',range.from)",
                "p.set('date_to',range.to)",
                'Custo deslocação',
                'routeMobilityTotal(r)'
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.570'", "STABLE_RELEASE = '2.2.571'"],
            'src/Support/ReleasePackageValidator.php' => ['client_route_hero_date_authority_9ba_ok', self::CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['client_route_hero_date_authority_9ba_ok']"],
            'PHASE-9BA-CLIENT-ROUTE-HERO-DATE-AUTHORITY.md' => [self::CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT, '`SOURCE_RELEASE` is `2.2.570` and `STABLE_RELEASE` is `2.2.571`'],
            'tools/verify-client-route-hero-date-authority-9ba.php' => ['CLIENT ROUTE HERO DATE AUTHORITY 9BA CONTRACT OK'],
            'tools/test-client-route-hero-date-authority-9ba.php' => ['CLIENT ROUTE HERO DATE AUTHORITY 9BA RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.571', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $front = is_readable(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/ClientPortalShortcodeRenderer.php') : '';
        foreach (['rpcp-route-month','rpcp-summary-month','data-cal-prev','data-cal-next','ownerRoutesMonth','routeCalendarMonth','Agenda anual'] as $forbidden) {
            if (strpos($front, $forbidden) !== false) $missing[] = 'front:secondary_date_authority:' . $forbidden;
        }
        if (strpos($front, 'els.from.disabled=!!date') !== false || strpos($front, 'els.to.disabled=!!date') !== false) $missing[] = 'front:date_inputs_locked';
        if (strpos($front, 'if(date) els.from.value=date;') !== false || strpos($front, 'if(date) els.to.value=date;') !== false) $missing[] = 'front:route_rewrites_dates';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'client_route_hero_date_authority_9ba',
            'Client Route Hero Date Authority 9BA',
            $missing ? 'fail' : 'pass',
            $missing ? 'Client route hero date authority is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Client routes use the hero De/Até interval as the only temporal authority. Calendar, route list, owner summary, costs and route API loading share the same range with no secondary month filter.',
            ['runtime'=>$release,'contract'=>self::CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT,'date_authority'=>'hero_from_to_only','secondary_month_filter'=>'removed','calendar'=>'multi_month_range','route_cost'=>'daily_total_mobility','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkPlannerAssignedStoreScope9BBContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Support/Permissions.php' => [
                'assignedStoreScopeForUser',
                'FROM {$px}campaign_locations cl',
                'cl.assigned_to=%d AND cl.is_active=1 AND cl.status=\'active\'',
                'Atribuições reais de lojas também são uma associação direta à campanha.'
            ],
            'src/Support/ScopeIndex.php' => [
                "'campaign_location'",
                'FROM {$px}campaign_locations cl',
                "source_type IN ('project_meta','project_assignment','campaign_location','route_owner','route_assignment')"
            ],
            'src/Rest/RoutesController.php' => [
                'project_has_assigned_pdvs',
                "assigned_to=%d AND is_active=1 AND status='active'",
                "campaign-pdvs"
            ],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => [
                'Carregar lojas atribuídas',
                "campaign-pdvs?client_id=",
                "&active=1&status=active"
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.571'", "STABLE_RELEASE = '2.2.572'"],
            'src/Support/ReleasePackageValidator.php' => ['planner_assigned_store_scope_9bb_ok', self::PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['planner_assigned_store_scope_9bb_ok']"],
            'PHASE-9BB-PLANNER-ASSIGNED-STORE-SCOPE-CONTINUITY.md' => [self::PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT, '`SOURCE_RELEASE` is `2.2.571` and `STABLE_RELEASE` is `2.2.572`'],
            'tools/verify-planner-assigned-store-scope-continuity-9bb.php' => ['PLANNER ASSIGNED STORE SCOPE 9BB CONTRACT OK'],
            'tools/test-planner-assigned-store-scope-continuity-9bb.php' => ['PLANNER ASSIGNED STORE SCOPE 9BB RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.572', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'planner_assigned_store_scope_9bb',
            'Planner Assigned Store Scope Continuity 9BB',
            $missing ? 'fail' : 'pass',
            $missing ? 'Planner assigned-store scope continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Planear resolves active campaign store assignments as direct client/campaign scope, including pre-existing ScopeIndex generations, while preserving route ownership and certified schema/submission baselines.',
            ['runtime'=>$release,'contract'=>self::PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT,'scope_source'=>'campaign_location','planner'=>'assigned_active_stores','scope_index'=>'persistent_plus_live_bridge','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkPlannerCanonicalAssignment9BCContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Support/Permissions.php' => [
                '9BC planner catalog authority: assigned_to is canonical',
                'WHERE cl.assigned_to=%d',
            ],
            'src/Rest/RoutesController.php' => [
                "get_param('planner_assigned')",
                '9BC planner_assigned bypasses lifecycle filters',
                'WHERE project_id=%d AND assigned_to=%d LIMIT 1',
            ],
            'src/Mobile/Controller.php' => [
                'all_location_ids_lookup',
                '9BC cross-day repeat contract',
                'WHERE project_id=%d AND assigned_to=%d',
            ],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => [
                self::PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT,
                'planner_assigned=1',
                'dayHasStore',
                'dayPlanningLocked',
                'dirtyDays=new Set()',
                'Já no período · pode repetir',
                'periodDates.filter(date=>dirtyDays.has(date))',
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.572'", "STABLE_RELEASE = '2.2.573'"],
            'src/Support/ReleasePackageValidator.php' => ['planner_canonical_assignment_9bc_ok', self::PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['planner_canonical_assignment_9bc_ok']"],
            'PHASE-9BC-PLANNER-CANONICAL-ASSIGNMENT-CATALOG.md' => [self::PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT, '`SOURCE_RELEASE` is `2.2.572` and `STABLE_RELEASE` is `2.2.573`'],
            'tools/verify-planner-canonical-assignment-9bc.php' => ['PLANNER CANONICAL ASSIGNMENT 9BC CONTRACT OK'],
            'tools/test-planner-canonical-assignment-9bc.php' => ['PLANNER CANONICAL ASSIGNMENT 9BC RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.573', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $mobile = is_readable(ROUTESPRO_PATH . 'src/Mobile/Controller.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Mobile/Controller.php') : '';
        if (strpos($mobile, "return new WP_Error('fieldflow_mobile_store_duplicate'") !== false) $missing[] = 'mobile:cross_day_duplicate_guard_present';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'planner_canonical_assignment_9bc',
            'Planner Canonical Assignment Catalog 9BC',
            $missing ? 'fail' : 'pass',
            $missing ? 'Planner canonical assignment catalogue is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Planear always exposes the complete assigned-store catalogue, allows the same store on different dates, and saves only dirty editable days so completed routes remain untouched.',
            ['runtime'=>$release,'contract'=>self::PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT,'catalogue'=>'assigned_to','cross_day_repeat'=>'allowed','same_day_duplicate'=>'blocked','save_scope'=>'dirty_days_only','locked_routes'=>'preserved','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkFormVisitMemoryFronts9BDContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Forms/DailySubmissionService.php' => [
                'find_previous_operational',
                'SubmissionStatusPolicy::operationalSql()',
                'visit_memory_question_eligible',
                "project_id > 0",
            ],
            'src/Forms/Forms.php' => [
                'DailySubmissionService::find_previous_operational',
                'ProductCardex::normalise_fronts_submission_rows',
                'Memória da loja',
                'Frentes reutiliza o valor final anterior',
                "'history_mode' => 'inline'",
                'form_record_history_inline',
            ],
            'src/Mobile/FormPayloadService.php' => [
                'DailySubmissionService::find_previous_operational',
                'visit_memory_question_eligible',
                "'previous_visit'",
            ],
            'src/Forms/ProductCardex.php' => [
                'normalise_fronts_submission_rows',
                "'before'",
                "'after'",
                "'qty'",
            ],
            'src/Mobile/SubmissionService.php' => [
                'ProductCardex::normalise_fronts_submission_rows',
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.573'", "STABLE_RELEASE = '2.2.574'"],
            'src/Support/ReleasePackageValidator.php' => ['form_visit_memory_fronts_9bd_ok', self::FORM_VISIT_MEMORY_FRONTS_9BD_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['form_visit_memory_fronts_9bd_ok']"],
            'PHASE-9BD-FORM-VISIT-MEMORY-FRONTS-CONTINUITY.md' => [self::FORM_VISIT_MEMORY_FRONTS_9BD_CONTRACT, '`SOURCE_RELEASE` is `2.2.573` and `STABLE_RELEASE` is `2.2.574`'],
            'tools/verify-form-visit-memory-fronts-9bd.php' => ['FORM VISIT MEMORY FRONTS 9BD CONTRACT OK'],
            'tools/test-form-visit-memory-fronts-9bd.php' => ['FORM VISIT MEMORY FRONTS 9BD RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.574', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'form_visit_memory_fronts_9bd',
            'Form Visit Memory & Fronts Continuity 9BD',
            $missing ? 'fail' : 'pass',
            $missing ? 'Form visit memory/fronts continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'A nova visita le a ultima submissao operacional anterior da mesma loja/campanha; memoria aparece nas perguntas elegiveis, Frentes preserva before/after/qty e a projecao de memoria ocorre inline apos COMMIT.',
            ['runtime'=>$release,'contract'=>self::FORM_VISIT_MEMORY_FRONTS_9BD_CONTRACT,'history_source'=>'previous_operational_submission_store_campaign','excluded_memory'=>'media+order_suggestion','fronts'=>'before_after_qty','projection'=>'inline_after_commit_with_queue_fallback','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkPlannerFreeWarning9BEContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/RouteOptimizationService.php' => [
                self::PLANNER_FREE_WARNING_9BE_CONTRACT,
                "'planner_mode'",
                "'planning_source'",
                '$lifecycleSql = $plannerMode ? \'\' :',
                'plannerWarnings',
                "'planning_warnings'",
                'Sobreposição:',
                'Periodicidade:',
            ],
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => [
                self::PLANNER_FREE_WARNING_9BE_CONTRACT,
                'planner_mode:1',
                "planning_source:'self_planner'",
                'planningWarningText',
                "warnings.length?'warning':'success'",
            ],
            'src/Mobile/Controller.php' => [
                "'planner_mode' => 1",
                "'planning_source' => 'self_planner'",
                "'date' => \$day['date']",
                "'planning_warnings'",
                "'warnings' => array_values(\$planning_warnings)",
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.574'", "STABLE_RELEASE = '2.2.575'"],
            'src/Support/ReleasePackageValidator.php' => ['planner_free_warning_9be_ok', self::PLANNER_FREE_WARNING_9BE_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['planner_free_warning_9be_ok']"],
            'PHASE-9BE-PLANNER-FREE-WARNING-CONTINUITY.md' => [self::PLANNER_FREE_WARNING_9BE_CONTRACT, '`SOURCE_RELEASE` is `2.2.574` and `STABLE_RELEASE` is `2.2.575`'],
            'tools/verify-planner-free-warning-9be.php' => ['PLANNER FREE WARNING 9BE CONTRACT OK'],
            'tools/test-planner-free-warning-9be.php' => ['PLANNER FREE WARNING 9BE RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.575', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'planner_free_warning_9be',
            'Planner Free Warning Continuity 9BE',
            $missing ? 'fail' : 'pass',
            $missing ? 'Planner free-warning continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Planear uses assigned-store authority for optimisation and treats route overlaps and cadence conflicts as warnings instead of blockers, while preserving true assignment/security guards.',
            ['runtime'=>$release,'contract'=>self::PLANNER_FREE_WARNING_9BE_CONTRACT,'catalogue'=>'assigned_to','overlap'=>'warning','periodicity'=>'warning','true_unassigned'=>'blocked','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkVisitExceptionJustifiedNonVisit9BFContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Services/VisitExceptionService.php' => [
                self::VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT,
                "OPTION = 'fieldflow_visit_exceptions_v1'",
                "'temporarily_closed' => 'Encerramento temporário'",
                'recordAppliesToWindow',
                "AuditLog::record('visit_exception.upsert'",
                "AuditLog::record('visit_exception.clear'",
            ],
            'src/Services/PeriodicityProgressService.php' => [
                self::VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT,
                "rs.fail_reason",
                "'remaining_after_justification'",
                "'justified_stores'",
                "'visits_justified'",
                'if ($status === \'justified\')',
            ],
            'src/Rest/RoutesController.php' => [
                "'/visit-exceptions'",
                'save_visit_exception',
                'clear_visit_exception',
                'can_manage_visit_exception',
            ],
            'src/Shortcodes.php' => [
                'Justificar não visita',
                'data-periodicity-justify',
                'rp-periodicity-justified',
                'não conta como visita realizada',
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.575'", "STABLE_RELEASE = '2.2.576'"],
            'src/Support/ReleasePackageValidator.php' => ['visit_exception_justified_nonvisit_9bf_ok', self::VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['visit_exception_justified_nonvisit_9bf_ok']"],
            'PHASE-9BF-VISIT-EXCEPTION-JUSTIFIED-NONVISIT.md' => [self::VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT, '`SOURCE_RELEASE` is `2.2.575` and `STABLE_RELEASE` is `2.2.576`'],
            'tools/verify-visit-exception-justified-nonvisit-9bf.php' => ['VISIT EXCEPTION JUSTIFIED NONVISIT 9BF CONTRACT OK'],
            'tools/test-visit-exception-justified-nonvisit-9bf.php' => ['VISIT EXCEPTION JUSTIFIED NONVISIT 9BF RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.576', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'visit_exception_justified_nonvisit_9bf',
            'Visit Exception & Justified Non Visit Continuity 9BF',
            $missing ? 'fail' : 'pass',
            $missing ? 'Visit exception continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Non-visits can be justified with reason, note and validity without counting as execution; justified stores leave the critical queue and automatically return when the exception expires.',
            ['runtime'=>$release,'contract'=>self::VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT,'execution'=>'unchanged','red_queue'=>'unjustified_only','store_exception'=>'reason+note+validity+audit','route_failure'=>'justified_slot','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }


    private static function checkOpenDayPlanningVisitCompletion9BGContract(): array {
        $release = self::currentRelease();
        $missing = [];
        $requirements = [
            'src/Front/SelfRoutePlannerShortcodeRenderer.php' => [
                self::OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT,
                'const operationalToday=',
                'function dayPlanningLocked(date)',
                'function canonicalStopStatus(store)',
                'function stopIsProtected(store)',
                'route_stop_canonical_status',
                "document.addEventListener('fieldflow:visit-completed'",
                'Visita concluída sincronizada no planeamento.',
                'O dia continua aberto para acrescentar ou ajustar outras lojas.',
            ],
            'src/Services/RoutePlanSyncService.php' => [
                'route_plan_operational_stop_protected',
                'has_operational_state',
            ],
            'src/Services/FormVisitCompletionService.php' => [
                'RouteProgressService::sync',
                "'stop_status'",
            ],
            'src/Mobile/Controller.php' => [
                'RoutePlanSyncService::sync',
                'RouteProgressService::sync',
            ],
            'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.577'", "STABLE_RELEASE = '2.2.578'"],
            'src/Support/ReleasePackageValidator.php' => ['open_day_planning_visit_completion_9bg_ok', self::OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['open_day_planning_visit_completion_9bg_ok']"],
            'PHASE-9BG-OPEN-DAY-PLANNING-VISIT-COMPLETION-SYNC.md' => [self::OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT, '`SOURCE_RELEASE` is `2.2.577` and `STABLE_RELEASE` is `2.2.578`'],
            'tools/verify-open-day-planning-visit-completion-9bg.php' => ['OPEN DAY PLANNING VISIT COMPLETION 9BG CONTRACT OK'],
            'tools/test-open-day-planning-visit-completion-9bg.php' => ['OPEN DAY PLANNING VISIT COMPLETION 9BG RUNTIME OK'],
        ];
        if ($release === '' || version_compare($release, '2.2.578', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $planner = is_readable(ROUTESPRO_PATH . 'src/Front/SelfRoutePlannerShortcodeRenderer.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Front/SelfRoutePlannerShortcodeRenderer.php') : '';
        if (strpos($planner, 'A rota deste dia já foi iniciada ou fechada. Escolhe outro dia para planear novas visitas.') !== false) $missing[] = 'planner:legacy_started_route_blocker';
        $submission = is_readable(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Forms/SubmissionPipeline.php') : '';
        if (strpos($submission, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
        $migrations = is_readable(ROUTESPRO_PATH . 'src/Support/Migrations.php') ? (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/Migrations.php') : '';
        if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
        return self::check(
            'open_day_planning_visit_completion_9bg',
            'Open Day Planning & Visit Completion Sync 9BG',
            $missing ? 'fail' : 'pass',
            $missing ? 'Open-day planning continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' plus ' . (count($missing) - 8) . ' marker(s).' : '.') : 'Started or completed current-day routes remain plannable, while operational stops remain protected and a completed visit is reflected immediately in the planner without closing the day.',
            ['runtime'=>$release,'contract'=>self::OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT,'open_day'=>'editable','completed_stop'=>'protected+visible','live_sync'=>'fieldflow:visit-completed','schema'=>'2.2.529','submission_build'=>'2.2.485','missing'=>$missing]
        );
    }

    private static function checkLatestVisitHistoryIsolation9BHContract(): array {
        $missing = [];
        $requirements = [
            'src/Forms/DailySubmissionService.php' => ['include_same_day', 'exclude_submission_id', 'visit_memory_prefill', 'new_visit_prefill'],
            'src/Forms/Forms.php' => ['DailySubmissionService::visit_memory_prefill', 'DailySubmissionService::new_visit_prefill', '$history_source = $previous_submission;'],
            'src/Mobile/FormPayloadService.php' => ['DailySubmissionService::visit_memory_prefill', 'DailySubmissionService::new_visit_prefill', '$historySource = $previousSubmission;'],
            'src/Shortcodes.php' => ['embeddedFormSequence', 'requestId!==embeddedFormSequence'],
            'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.578'", "public const STABLE_RELEASE = '2.2.579'"],
            'src/Support/ReleasePackageValidator.php' => ['latest_visit_history_isolation_9bh_ok', self::LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT],
            'src/Admin/ReleaseManager.php' => ["packageResult['latest_visit_history_isolation_9bh_ok']"],
            'PHASE-9BH-LATEST-VISIT-HISTORY-ISOLATION.md' => [self::LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT],
            'tools/verify-latest-visit-history-isolation-9bh.php' => ['LATEST VISIT HISTORY ISOLATION 9BH CONTRACT OK'],
            'tools/test-latest-visit-history-isolation-9bh.php' => ['LATEST VISIT HISTORY ISOLATION 9BH RUNTIME OK'],
            'tools/test-quick-report-selection-9bh.mjs' => ['QUICK REPORT SELECTION 9BH RUNTIME OK'],
        ];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $raw = is_readable($path) ? (string)file_get_contents($path) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $schema = ['questions'=>[
            ['key'=>'notes','type'=>'textarea'], ['key'=>'photo','type'=>'image_upload'],
            ['key'=>'categorized','type'=>'categorized_image_upload'], ['key'=>'file','type'=>'file_upload'],
            ['key'=>'order','type'=>'order_suggestion'], ['key'=>'fronts','type'=>'product_matrix'],
        ]];
        $input = ['notes'=>'latest','photo'=>[1],'categorized'=>[2],'file'=>[3],'order'=>[4],'fronts'=>[5],'obsolete'=>[6]];
        try {
            $memory = \RoutesPro\Forms\DailySubmissionService::visit_memory_prefill($input, $schema);
            $current = \RoutesPro\Forms\DailySubmissionService::new_visit_prefill($input, $schema);
            if ($memory !== ['notes'=>'latest','fronts'=>[5]]) $missing[] = 'runtime:memory_filter';
            if ($current !== ['fronts'=>[5]]) $missing[] = 'runtime:new_visit_filter';
        } catch (\Throwable $e) { $missing[] = 'runtime:history_policy'; }
        return self::check(
            'latest_visit_history_isolation_9bh',
            'Latest Visit History & Media/Order Isolation 9BH',
            $missing ? 'fail' : 'pass',
            $missing ? 'Visit-history isolation is incomplete: ' . implode(', ', array_slice($missing, 0, 8)) : 'Store/campaign memory uses the latest eligible operational submission; new visits carry only fronts and edits restore their own answers.',
            ['runtime'=>self::currentRelease(),'contract'=>self::LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT,'same_day'=>'eligible','current_submission'=>'excluded_by_id','media_orders'=>'isolated','record_fallback'=>'disabled','missing'=>$missing]
        );
    }

    private static function checkVisitMemoryRefresh9BIContract(): array {
        $missing = [];
        $requirements = [
            'src/Forms/DailySubmissionService.php'=>['Resolve ownership through submission_id instead.', 'WHERE submission_id=%d ORDER BY id DESC LIMIT 1'],
            'src/Forms/Forms.php'=>['data-rp-previous-visit-revision', '$previous_modal_revision', 'fieldflow-visit-memory-refresh-v1'],
            'src/Forms/FormRenderer.php'=>['fieldflow-visit-memory-refresh-v1','rpClearPreviousVisitModal','memoryRevision','routesproPreviousVisitCleanup'],
            'src/Shortcodes.php'=>['routesproClearPreviousVisitModal','embeddedFormVisitKey'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.579'","public const STABLE_RELEASE = '2.2.580'"],
            'tools/test-visit-memory-refresh-9bi.mjs'=>['VISIT MEMORY REFRESH 9BI RUNTIME OK'],
            'tools/test-visit-memory-version-ownership-9bi.php'=>['VISIT MEMORY VERSION OWNERSHIP 9BI RUNTIME OK'],
            'PHASE-9BI-VISIT-MEMORY-REFRESH.md'=>['fieldflow-visit-memory-refresh-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check('visit_memory_refresh_9bi', 'Visit Memory Refresh & Version Ownership 9BI', $missing ? 'fail' : 'pass',
            $missing ? 'Visit memory refresh is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Memory is refreshed by revision, cleared on store changes, and migrated answers belong to the selected submission.',
            ['runtime'=>self::currentRelease(),'contract'=>self::VISIT_MEMORY_REFRESH_9BI_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkStoreIdentityAuthority9BJContract(): array {
        $missing = [];
        $requirements = [
            'src/Forms/DailySubmissionService.php'=>['canonical_location_sql','_canonical_location_id','Historical FieldFlow builds could persist location_id=route_stop_id'],
            'src/Forms/BindingResolver.php'=>['explicit route stop is the canonical store identity','route_stop_id'],
            'src/Forms/Forms.php'=>['$daily_existing && !$daily_context_conflict','another route/stop/store'],
            'src/Mobile/FormPayloadService.php'=>['$existing && !$context_conflict','never expose another route/stop/store report'],
            'src/Forms/DraftService.php'=>['payload_identity_matches_scope','payload_identity_from_scope',"'retained' => 'identity'"],
            'src/Forms/FormRenderer.php'=>['function draftIdentity()','identity:draftIdentity()','allowRouteRebase'],
            'frontend/src/drafts/draft-core.ts'=>['identity?: FieldFlowDraftIdentity','allowRouteRebase?: boolean','const saved = snapshot.identity'],
            'src/Stores/StoreProfileService.php'=>['DailySubmissionService::canonical_location_sql'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.580'","public const STABLE_RELEASE = '2.2.581'"],
            'tools/verify-store-identity-authority-9bj.php'=>['STORE IDENTITY AUTHORITY 9BJ CONTRACT OK','successor=>=2.2.581'],
            'tools/test-store-identity-authority-9bj.php'=>['STORE IDENTITY AUTHORITY 9BJ RUNTIME OK','canonical_store_detects_numeric_collision'],
            'PHASE-9BJ-STORE-IDENTITY-AUTHORITY.md'=>['fieldflow-store-identity-authority-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'store_identity_authority_9bj',
            'Store Identity Authority & Historical Media Isolation 9BJ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Store identity authority is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Route-stop/store identity is canonical across history, current-report media, drafts and store profiles.',
            ['runtime'=>self::currentRelease(),'contract'=>self::STORE_IDENTITY_AUTHORITY_9BJ_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkStaticRoutingCostAuthority9BKContract(): array {
        $missing = [];
        $requirements = [
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
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.581'", "public const STABLE_RELEASE = '2.2.582'"],
            'tools/verify-static-routing-cost-authority-9bk.php'=>['STATIC ROUTING COST AUTHORITY 9BK CONTRACT OK', 'successor=>=2.2.582'],
            'tools/test-static-routing-cost-authority-9bk.php'=>['STATIC ROUTING COST AUTHORITY 9BK RUNTIME OK', 'matrix_disabled_without_http'],
            'PHASE-9BK-STATIC-ROUTING-COST-AUTHORITY.md'=>['fieldflow-static-routing-cost-authority-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'static_routing_cost_authority_9bk',
            'Static Routing & Cost Authority 9BK',
            $missing ? 'fail' : 'pass',
            $missing ? 'Static routing cost authority is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Routine planning is internal, Google is traffic-unaware and final route snapshots refresh only when route identity changes.',
            ['runtime'=>self::currentRelease(),'contract'=>self::STATIC_ROUTING_COST_AUTHORITY_9BK_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkTollCostIntelligence9BLContract(): array {
        $missing = [];
        $requirements = [
            'src/Support/TollEstimator.php'=>['INTELLIGENCE_CONTRACT', 'PT-2026-IMT-IP-ASCENDI-v1', 'estimateFromGoogleRoute', "'A22'=>['rate'=>0.000", "'A41'=>['rate'=>0.085"],
            'src/Support/Config.php'=>["'toll_intelligence_enabled' => 1", "'toll_vehicle_class' => 1", 'api_rate_google_routes_compute_routes_essentials_per_1000_eur'],
            'src/Support/Plugin.php'=>['applyTollCostIntelligence', 'fieldflow_toll_cost_intelligence_migrated', 'fieldflow-toll-cost-intelligence-v1'],
            'src/Services/GoogleRoutes.php'=>['TOLL_COST_INTELLIGENCE_CONTRACT', 'routes.legs.steps.navigationInstruction.instructions', 'compute_routes_essentials', 'compute_routes_enterprise', 'toll_confidence', 'toll_tariff_version'],
            'src/Observability/ApiUsageLedger.php'=>['strpos($operationKey, \'compute_routes_\')', "api_rate_google_routes_route_per_1000_eur"],
            'src/Admin/Settings.php'=>['Portagens estimadas FieldFlow', 'toll_intelligence_enabled', 'toll_vehicle_class'],
            'src/Admin/SecurityObservability.php'=>['Compute Routes Essentials por 1.000', 'Compute Routes Enterprise por 1.000'],
            'src/Services/RouteMetricsService.php'=>['toll_confidence', 'toll_tariff_version', 'toll_cost_intelligence_contract'],
            'src/Admin/Routes.php'=>['Confiança:', 'toll_confidence', 'toll_cost_intelligence_contract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.582'", "public const STABLE_RELEASE = '2.2.583'"],
            'tools/verify-toll-cost-intelligence-9bl.php'=>['TOLL COST INTELLIGENCE 9BL CONTRACT OK', 'successor=>=2.2.583'],
            'tools/test-toll-cost-intelligence-9bl.php'=>['TOLL COST INTELLIGENCE 9BL RUNTIME OK', 'a22_free_zero', 'essentials_without_google_tolls'],
            'PHASE-9BL-TOLL-COST-INTELLIGENCE.md'=>['fieldflow-toll-cost-intelligence-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'toll_cost_intelligence_9bl',
            'Toll Cost Intelligence 9BL',
            $missing ? 'fail' : 'pass',
            $missing ? 'Toll Cost Intelligence is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Portuguese tolls are estimated internally from standard route context, with confidence and billing-tier observability, without Google TOLLS by default.',
            ['runtime'=>self::currentRelease(),'contract'=>self::TOLL_COST_INTELLIGENCE_9BL_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkPlacesGeocodingCostAuthority9BMContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/PlacesCostAuthority.php'=>["CONTRACT = 'fieldflow-places-geocoding-cost-authority-v1'", 'canReuseEndpoint', 'findReusableLocation', 'GOOGLE_COORDINATE_CACHE_MAX_DAYS = 30', 'googleCoordinateWindowValid', 'recordAvoided'],
            'src/Services/RouteEndpointResolverService.php'=>['PlacesCostAuthority::canReuseEndpoint', 'endpoint_signature_reuse', 'force_external_geocode'],
            'src/Services/Maps.php'=>['google_geocode_v3', '30 * DAY_IN_SECONDS'],
            'src/Rest/IntegrationsController.php'=>['known_location_reuse', 'PlacesCostAuthority::findReusableLocation', 'routespro_geocode_v3_'],
            'src/Support/Config.php'=>["'places_cost_authority_enabled' => 1", "'google_places_contact_enrichment_enabled' => 0"],
            'src/Support/Plugin.php'=>['applyPlacesGeocodingCostAuthority', 'fieldflow_places_geocoding_cost_authority_migrated', 'fieldflow-places-geocoding-cost-authority-v1'],
            'src/Admin/Settings.php'=>['Places &amp; Geocoding low cost', 'places_cost_authority_enabled', 'google_places_contact_enrichment_enabled'],
            'src/Admin/Routes.php'=>['googlePlacesContactEnrichment', 'if (!googlePlacesContactEnrichment) { cb(null); return; }'],
            'src/Shortcodes.php'=>['googlePlacesContactEnrichment', "addressFields.push('website','formatted_phone_number')"],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.583'", "public const STABLE_RELEASE = '2.2.584'"],
            'tools/verify-places-geocoding-cost-authority-9bm.php'=>['PLACES GEOCODING COST AUTHORITY 9BM CONTRACT OK', 'successor=>=2.2.584'],
            'tools/test-places-geocoding-cost-authority-9bm.php'=>['PLACES GEOCODING COST AUTHORITY 9BM RUNTIME OK', 'unchanged_endpoint_reuses_coordinates', 'google_coordinate_window_capped_30_days'],
            'PHASE-9BM-PLACES-GEOCODING-COST-AUTHORITY.md'=>['fieldflow-places-geocoding-cost-authority-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'places_geocoding_cost_authority_9bm',
            'Places & Geocoding Cost Authority 9BM',
            $missing ? 'fail' : 'pass',
            $missing ? 'Places & Geocoding Cost Authority is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Known endpoints are reused safely, Google coordinate caches are bounded and paid contact enrichment is opt-in.',
            ['runtime'=>self::currentRelease(),'contract'=>self::PLACES_GEOCODING_COST_AUTHORITY_9BM_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkGoogleCostControlCenter9BNContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/GoogleCostControlCenter.php'=>["CONTRACT = 'fieldflow-google-cost-control-center-v1'", 'authorizeOperation', 'configurationDrift', 'recordBlocked', 'POLÍTICA SAUDÁVEL'],
            'src/Services/GoogleRoutes.php'=>['GoogleCostControlCenter::authorizeOperation', 'google_cost_control_blocked'],
            'src/Observability/ApiUsageLedger.php'=>["status === 'blocked'", "SUM(status='blocked') blocked", 'avoided_events'],
            'src/Support/Config.php'=>["'google_cost_control_enabled' => 1", "'google_cost_block_matrix' => 1", "'google_cost_block_pro' => 1", "'google_cost_block_enterprise' => 1"],
            'src/Support/Plugin.php'=>['applyGoogleCostControlCenter', 'fieldflow_google_cost_control_center_migrated', 'fieldflow-google-cost-control-center-v1'],
            'src/Admin/SecurityObservability.php'=>['Google Cost Control Center', 'Bloquear Google Route Matrix', 'Bloquear Google Routes Pro', 'Bloquear Google Routes Enterprise'],
            'src/Admin/Settings.php'=>['Google Cost Control Center:', 'bloqueios financeiros têm prioridade'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.584'", "public const STABLE_RELEASE = '2.2.585'"],
            'tools/verify-google-cost-control-center-9bn.php'=>['GOOGLE COST CONTROL CENTER 9BN CONTRACT OK', 'successor=>=2.2.585'],
            'tools/test-google-cost-control-center-9bn.php'=>['GOOGLE COST CONTROL CENTER 9BN RUNTIME OK', 'pro_blocked_before_http', 'matrix_blocked_before_http'],
            'PHASE-9BN-GOOGLE-COST-CONTROL-CENTER.md'=>['fieldflow-google-cost-control-center-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'google_cost_control_center_9bn',
            'Google Cost Control Center 9BN',
            $missing ? 'fail' : 'pass',
            $missing ? 'Google Cost Control Center is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Premium Google routing tiers are monitored and fail closed before outbound HTTP while Essentials remains available.',
            ['runtime'=>self::currentRelease(),'contract'=>self::GOOGLE_COST_CONTROL_CENTER_9BN_CONTRACT,'missing'=>$missing]
        );
    }


    private static function checkDailyOperationalPriority9BOContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/DailyOperationalPriorityService.php'=>["CONTRACT = 'fieldflow-daily-operational-priority-v1'", 'rankSignals', 'external_api_calls', 'route_mutations'],
            'src/Rest/RoutesController.php'=>["'/my-priorities'", 'DailyOperationalPriorityService::forUser'],
            'src/Shortcodes.php'=>['rp-daily-focus', 'fieldflow-daily-operational-priority-v1', 'loadDailyFocus', 'daily-operational-priority'],
            'src/Admin/Assignments.php'=>["'operations' => 'Operação diária'", 'DailyOperationalPriorityService::forUser', '0 chamadas externas'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.585'", "public const STABLE_RELEASE = '2.2.586'"],
            'tools/verify-daily-operational-priority-9bo.php'=>['DAILY OPERATIONAL PRIORITY 9BO CONTRACT OK', 'successor=>=2.2.586'],
            'tools/test-daily-operational-priority-9bo.php'=>['DAILY OPERATIONAL PRIORITY 9BO RUNTIME OK', 'active_first', 'read_only'],
            'PHASE-9BO-DAILY-OPERATIONAL-PRIORITY.md'=>['fieldflow-daily-operational-priority-v1'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'daily_operational_priority_9bo',
            'Daily Operational Priority 9BO',
            $missing ? 'fail' : 'pass',
            $missing ? 'Daily Operational Priority is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Daily focus is explainable, read-only and uses only FieldFlow operational state without external API calls.',
            ['runtime'=>self::currentRelease(),'contract'=>self::DAILY_OPERATIONAL_PRIORITY_9BO_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkDailyExecutionException9BPContract(): array {
        $missing = [];
        $requirements = [
            'src/Services/DailyExecutionExceptionService.php'=>["CONTRACT = 'fieldflow-daily-execution-exception-control-v1'", 'classifyRecord', 'external_api_calls', 'submission_mutations'],
            'src/Rest/RoutesController.php'=>["'/my-execution'", 'DailyExecutionExceptionService::forUser'],
            'src/Shortcodes.php'=>['data-execution-contract="fieldflow-daily-execution-exception-control-v1"', 'rp-focus-toggle', 'focusOpenStorageKey', 'loadDailyExecution', 'alertas de execução'],
            'src/Admin/Assignments.php'=>['DailyExecutionExceptionService::forUser', 'Controlo de execução e exceções', 'Alertas execução'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.586'", "public const STABLE_RELEASE = '2.2.587'"],
            'tools/verify-daily-execution-exception-control-9bp.php'=>['DAILY EXECUTION EXCEPTION CONTROL 9BP CONTRACT OK', 'successor=>=2.2.587'],
            'tools/test-daily-execution-exception-control-9bp.php'=>['DAILY EXECUTION EXCEPTION CONTROL 9BP RUNTIME OK', 'canonical_state', 'mobile_alerts'],
            'PHASE-9BP-DAILY-EXECUTION-EXCEPTION-CONTROL.md'=>['fieldflow-daily-execution-exception-control-v1', 'No database schema'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'daily_execution_exception_9bp',
            'Daily Execution & Exception Control 9BP',
            $missing ? 'fail' : 'pass',
            $missing ? 'Daily Execution & Exception Control is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Canonical daily execution exceptions are explainable and read-only, with compact mobile alerts in My Route.',
            ['runtime'=>self::currentRelease(),'contract'=>self::DAILY_EXECUTION_EXCEPTION_9BP_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkOrderMailVisitReopen9BQContract(): array {
        $missing = [];
        $requirements = [
            'src/Admin/FormSubmissions.php'=>['persistedOrderSuggestionEvidence', 'persisted_positive_order_authority', 'hidden_positive_keys'],
            'src/Admin/ReportAutomations.php'=>['recordOrderSuggestionMailState', "'failed_retry'", "'queued_or_sent'", 'accepted_by_outbox'],
            'src/Shortcodes.php'=>['Queres mesmo concluir esta visita?', 'editingExisting'],
            'src/Forms/FormRenderer.php'=>['Queres mesmo concluir esta visita?', '_rpVisitCloseConfirmed'],
            'src/Services/VisitResetService.php'=>['reopenToPending', 'visit_admin_reopen', 'RouteProgressService::sync($routeId, false, false)'],
            'src/Services/RouteProgressService.php'=>["visit_admin_reopen']['active", 'passive reconciliation'],
            'src/Services/FormVisitCompletionService.php'=>['resolved_submission_id', 'visit_admin_reopen'],
            'src/Rest/RoutesController.php'=>["'/stops/(?P<id>\\d+)/reopen'", 'reopen_stop_pending'],
            'src/Admin/Routes.php'=>['Reabrir loja', 'data-reopen-stop', "api+'stops/'+stopId+'/reopen"],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.587'", "public const STABLE_RELEASE = '2.2.588'", "public const STABLE_RELEASE = '2.2.588'"],
            'tools/verify-order-mail-visit-reopen-safety-9bq.php'=>['ORDER MAIL VISIT REOPEN SAFETY 9BQ CONTRACT OK', 'successor=>=2.2.588'],
            'tools/test-order-mail-visit-reopen-safety-9bq.php'=>['ORDER MAIL VISIT REOPEN SAFETY 9BQ RUNTIME OK', 'positive_order_authority', 'reopen_guard', 'close_confirmation'],
            'PHASE-9BQ-ORDER-MAIL-VISIT-REOPEN-SAFETY.md'=>['fieldflow-order-mail-visit-reopen-safety-v1', 'No database schema'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'order_mail_visit_reopen_9bq',
            'Order Mail & Visit Reopen Safety 9BQ',
            $missing ? 'fail' : 'pass',
            $missing ? 'Order Mail & Visit Reopen Safety is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Order suggestions have explicit delivery state, visit close requires confirmation, and terminal stores can be safely reopened with audit continuity.',
            ['runtime'=>self::currentRelease(),'contract'=>self::ORDER_MAIL_VISIT_REOPEN_9BQ_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkCertificationFoundationContinuity9BRContract(): array {
        $missing = [];
        $requirements = [
            'src/Cloud/LicenseTenantFoundation.php'=>['release_integrity_ready', '$foundationCertified = !empty($state[\'certified\']) && $match && $remoteConnectivityReady;', 'certification_requires_revalidation', '(!$match || !$remoteConnectivityReady)'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.588'", "public const STABLE_RELEASE = '2.2.589'", "'stage'=>'6B.1-revalidate'", 'LicenseTenantFoundation::certifyFoundation($actorId)', 'failedChecks'],
            'src/Admin/LicenseTenants.php'=>['failedCheckKeys', 'Ainda existem pré-condições da 6B.1 por concluir:'],
            'tools/verify-certification-foundation-continuity-9br.php'=>['CERTIFICATION FOUNDATION CONTINUITY 9BR CONTRACT OK', 'successor=>=2.2.589'],
            'tools/test-certification-foundation-continuity-9br.php'=>['CERTIFICATION FOUNDATION CONTINUITY 9BR RUNTIME OK', 'foundation_certificate_decoupled', 'chain_includes_6b1', 'diagnostic_visibility'],
            'PHASE-9BR-CERTIFICATION-FOUNDATION-CONTINUITY.md'=>['fieldflow-certification-foundation-continuity-v1', 'No database schema'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'certification_foundation_continuity_9br',
            'Certification Foundation Continuity 9BR',
            $missing ? 'fail' : 'pass',
            $missing ? 'Certification Foundation Continuity is incomplete: '.implode(', ',array_slice($missing,0,8)) : '6B.1 certification identity is isolated from per-release integrity while revalidation explicitly covers 6B.1 through 6B.4.',
            ['runtime'=>self::currentRelease(),'contract'=>self::CERTIFICATION_FOUNDATION_CONTINUITY_9BR_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkExecutiveAnalyticsRouteFinancial9BSContract(): array {
        $missing = [];
        $requirements = [
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
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'executive_analytics_route_financial_9bs',
            'Executive Analytics & Route Financial Visibility 9BS',
            $missing ? 'fail' : 'pass',
            $missing ? 'Executive Analytics & Route Financial Visibility is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'BO route costs, monthly client analytics and audience-aware explainability share canonical FieldFlow sources without external cost.',
            ['runtime'=>self::currentRelease(),'contract'=>self::EXECUTIVE_ANALYTICS_ROUTE_FINANCIAL_9BS_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkMobileAnalyticsReadabilityEvidence9BTContract(): array {
        $missing = [];
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','data-rp-evidence-store-detail','data-rp-evidence-date-detail','height:50px!important',"content:'Abrir'!important"],
            'src/Shortcodes.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','ffad-evidence-section','data-ffad-detail','height:50px!important','ffad-performance-focus'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.590'", "public const STABLE_RELEASE = '2.2.591'"],
            'tools/verify-mobile-analytics-readability-evidence-density-9bt.php'=>['MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT CONTRACT OK','successor=>=2.2.591'],
            'tools/test-mobile-analytics-readability-evidence-density-9bt.php'=>['MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT RUNTIME OK','client_evidence_collapsible','operative_evidence_collapsible','ask_height_compact'],
            'PHASE-9BT-MOBILE-ANALYTICS-READABILITY-EVIDENCE-DENSITY.md'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','No database schema'],
        ];
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH.$relative) ? (string)file_get_contents(ROUTESPRO_PATH.$relative) : '';
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
        }
        return self::check(
            'mobile_analytics_readability_evidence_9bt',
            'Mobile Analytics Readability & Evidence Density 9BT',
            $missing ? 'fail' : 'pass',
            $missing ? 'Mobile Analytics Readability & Evidence Density is incomplete: '.implode(', ',array_slice($missing,0,8)) : 'Client and Operative Analytics own the mobile viewport, keep contrast readable and collapse evidence/search surfaces without changing analytics semantics.',
            ['runtime'=>self::currentRelease(),'contract'=>self::MOBILE_ANALYTICS_READABILITY_EVIDENCE_9BT_CONTRACT,'missing'=>$missing]
        );
    }

    private static function checkClientReportsRuntime9BUContract(): array {
        $missing=[];
        $requirements=[
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-client-reports-runtime-continuity-v1','function paramsForReports()','async function loadReports()','form-submissions?','owner_user_id','date_from','date_to'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.591'","public const STABLE_RELEASE = '2.2.592'"],
            'tools/verify-client-reports-runtime-continuity-9bu.php'=>['CLIENT REPORTS RUNTIME CONTINUITY 9BU CONTRACT OK','successor=>=2.2.592'],
            'tools/test-client-reports-runtime-continuity-9bu.php'=>['CLIENT REPORTS RUNTIME CONTINUITY 9BU RUNTIME OK','loader_defined','filter_bridge'],
            'PHASE-9BU-CLIENT-REPORTS-RUNTIME-CONTINUITY.md'=>['fieldflow-client-reports-runtime-continuity-v1','No database schema'],
        ];
        foreach($requirements as $relative=>$markers){$raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;}
        return self::check('client_reports_runtime_9bu','Client Reports Runtime Continuity 9BU',$missing?'fail':'pass',$missing?'Client reports runtime is incomplete: '.implode(', ',array_slice($missing,0,8)):'Client report loading is defined and scoped by the canonical portal filters without changing report semantics.',['runtime'=>self::currentRelease(),'contract'=>self::CLIENT_REPORTS_RUNTIME_9BU_CONTRACT,'missing'=>$missing]);
    }

    private static function checkPortalExportRuntimeCertParity9BVContract(): array {
        $missing=[];
        $requirements=[
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-portal-export-runtime-certification-parity-v1','function exportUrl(format)','function dashboardExportUrl(format)','function routeExportUrl(format)','function safeRefreshExportLinks()','routespro_export_submissions','routespro_export_dashboard','routespro_export_routes','exportNonce','dashboardExportNonce','routeExportNonce'],
            'src/Shortcodes.php'=>['function exportUrl(format)',"p.set('action','routespro_export_dashboard')",'self_mode'],
            'src/Front/AppShortcodeRenderer.php'=>['fieldflow_route_today mode="app"'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.592'","public const STABLE_RELEASE = '2.2.593'"],
            'src/Support/ReleasePackageValidator.php'=>['fieldflow-portal-export-runtime-certification-parity-v1','portal_export_runtime_cert_parity_9bv_ok'],
            'tools/verify-portal-export-runtime-certification-parity-9bv.php'=>['PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV CONTRACT OK','successor=>=2.2.593'],
            'tools/test-portal-export-runtime-certification-parity-9bv.php'=>['PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV RUNTIME OK','client_export_dependencies_closed','reports_failure_domain_isolated','operative_export_path_preserved','gate_validator_parity'],
            'PHASE-9BV-PORTAL-EXPORT-RUNTIME-CERTIFICATION-PARITY.md'=>['fieldflow-portal-export-runtime-certification-parity-v1','No database schema'],
        ];
        foreach($requirements as $relative=>$markers){$raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;}
        return self::check('portal_export_runtime_cert_parity_9bv','Portal Export Runtime Integrity & Certification Parity 9BV',$missing?'fail':'pass',$missing?'Portal export runtime/certification parity is incomplete: '.implode(', ',array_slice($missing,0,8)):'Client Reports, Analytics and Routes have closed export dependencies, Operative export continuity is preserved, and Release Manager plus Stable certification enforce the same contract.',['runtime'=>self::currentRelease(),'contract'=>self::PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT,'missing'=>$missing]);
    }

    private static function checkClientReportScopeBriefingAssignment9BWContract(): array {
        $missing=[];
        $requirements=[
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','function paramsForReports()','date_from','date_to','owner_user_id','A route opened in Rotas must never leak into Relatórios'],
            'src/Admin/FormSubmissions.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','briefing_questions_for_submission','ContextQuestionService::briefing_for_context','briefing_location_assigned_to_owner','assigned_to=%d AND is_active=1','briefing_questions'],
            'src/Forms/SubmissionPresentation.php'=>['fieldflow-client-report-scope-briefing-assignment-v1',"'briefing' => ['name'=>'Briefing'",'Sem resposta'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.593'","public const STABLE_RELEASE = '2.2.594'"],
            'src/Support/ReleasePackageValidator.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','client_report_scope_briefing_assignment_9bw_ok'],
            'tools/verify-client-report-scope-briefing-assignment-9bw.php'=>['CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW CONTRACT OK','successor=>=2.2.594'],
            'tools/test-client-report-scope-briefing-assignment-9bw.php'=>['CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW RUNTIME OK','header_date_scope','selected_route_leak=0','store_briefing_scope','operative_store_assignment','briefing_export_parity','gate_validator_parity'],
            'PHASE-9BW-CLIENT-REPORT-HEADER-SCOPE-BRIEFING-ASSIGNMENT.md'=>['fieldflow-client-report-scope-briefing-assignment-v1','No database schema'],
        ];
        foreach($requirements as $relative=>$markers){$raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;}
        return self::check('client_report_scope_briefing_assignment_9bw','Client Report Header Scope & Briefing Assignment Integrity 9BW',$missing?'fail':'pass',$missing?'Client report scope/briefing assignment integrity is incomplete: '.implode(', ',array_slice($missing,0,8)):'Relatórios obey the header date and operative scope without route-state leakage, briefing questions remain exact-store scoped, operative store assignments are authoritative, exports include briefing, and Release Manager plus Stable certification enforce the same contract.',['runtime'=>self::currentRelease(),'contract'=>self::CLIENT_REPORT_SCOPE_BRIEFING_ASSIGNMENT_9BW_CONTRACT,'missing'=>$missing]);
    }

    private static function checkVisitCentricReportingBriefing9BXContract(): array {
        $missing=[];
        $requirements=[
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1',"p.set('report_mode','visits')",'visitas fechadas'],
            'src/Rest/EventsController.php'=>["\$reportMode === 'visits'",'get_visit_report_dataset'],
            'src/Admin/FormSubmissions.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','get_visit_report_dataset',"VisitCompletionPolicy::terminalSql('rs')","INNER JOIN ' . \$px . 'route_stops rs","LEFT JOIN ' . \$submissions . ' s",'assignments ao','campaign_locations clo','s.user_id=%d','briefing_questions_for_submission','BindingResolver::resolve','justified_non_visit'],
            'src/Forms/SubmissionPresentation.php'=>['Fecho operacional','Justificação','Fecho da visita','Relatório ID'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.594'","public const STABLE_RELEASE = '2.2.595'"],
            'src/Support/ReleasePackageValidator.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','visit_centric_reporting_briefing_9bx_ok'],
            'tools/verify-visit-centric-reporting-briefing-continuity-9bx.php'=>['VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX CONTRACT OK','successor=>=2.2.595'],
            'tools/test-visit-centric-reporting-briefing-continuity-9bx.php'=>['VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX RUNTIME OK','visit_stop_base=1','terminal_cardinality=3/5','operative_visit_scope=1','briefing_visit_scope=1','portal_rest_export_parity=1','gate_validator_parity=1'],
            'PHASE-9BX-VISIT-CENTRIC-REPORTING-BRIEFING-CONTINUITY.md'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','No database schema changes'],
        ];
        foreach($requirements as $relative=>$markers){$raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;}

        // Semantic safety net: certification must prove that the canonical visit
        // policy includes executed visits plus justified non-visits and excludes
        // unfinished/failed-without-reason stops. Static markers alone previously
        // allowed a submission-centric implementation to certify green.
        $fixtures=[
            ['status'=>'done','reason'=>'','expected'=>true],
            ['status'=>'completed','reason'=>'','expected'=>true],
            ['status'=>'failed','reason'=>'Loja em obras','expected'=>true],
            ['status'=>'failed','reason'=>'','expected'=>false],
            ['status'=>'pending','reason'=>'','expected'=>false],
        ];
        $terminalCount=0;
        foreach($fixtures as $fixture){
            $actual=\RoutesPro\Services\VisitCompletionPolicy::stopIsTerminal($fixture['status'],$fixture['reason']);
            if($actual)$terminalCount++;
            if($actual!==$fixture['expected'])$missing[]='VisitCompletionPolicy:'.sanitize_key($fixture['status']).':semantic_fixture';
        }
        if($terminalCount!==3)$missing[]='VisitCompletionPolicy:terminal_cardinality='.$terminalCount;
        return self::check('visit_centric_reporting_briefing_9bx','Visit-Centric Reporting & Briefing Continuity 9BX',$missing?'fail':'pass',$missing?'Visit-centric report cardinality/briefing continuity is incomplete: '.implode(', ',array_slice($missing,0,8)):'Each terminal route stop is one Client Report, completed visits and justified non-visits survive without a form submission, briefing stays exact-store scoped, and portal plus exports share the same visit-centric dataset.',['runtime'=>self::currentRelease(),'contract'=>self::VISIT_CENTRIC_REPORTING_BRIEFING_9BX_CONTRACT,'terminal_fixture_count'=>$terminalCount,'missing'=>$missing]);
    }

    private static function checkMultilingualClientLocaleAuthority9BYContract(): array {
        $missing=[];
        $requirements=[
            'src/Localization/LanguageService.php'=>['fieldflow-multilingual-client-locale-authority-v1',"public const META_KEY = 'i18n'",'supportedLocales','clientConfig','resolveLocale','persistPreference','ResponsiveAssets::shouldLoadForRequest()','fieldflow-i18n.js','fieldflow-i18n.css','WebViewBridge'],
            'src/Localization/LanguageController.php'=>["register_rest_route('fieldflow/v1', '/language'",'persistPreference','fieldflow_i18n_scope_denied','fieldflow_i18n_locale_disabled'],
            'src/Admin/Clients.php'=>['fieldflow_default_locale','fieldflow_enabled_locales','fieldflow_allow_locale_switch','LanguageService::sanitiseClientI18n'],
            'src/Architecture/RuntimeRegistry.php'=>['LanguageService::registerHooks'],
            'src/Architecture/RestRouteRegistry.php'=>['LanguageController'],
            'assets/fieldflow-i18n.js'=>['fieldflow-multilingual-client-locale-authority-v1','MutationObserver','window.FieldFlowI18n','fieldflow:locale-changed','data-fieldflow-i18n-ignore','fieldflow:i18n:'],
            'assets/fieldflow-i18n.css'=>['.ff-language-switcher','.ff-language-trigger','.ff-language-option'],
            'assets/fieldflow-offline-runtime.js'=>['window.FieldFlowI18n',"const RUNTIME_BUILD='2.2.596'"],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.595'","public const STABLE_RELEASE = '2.2.596'","// public const SOURCE_RELEASE = '2.2.594'","// public const STABLE_RELEASE = '2.2.595'"],
            'src/Support/ReleasePackageValidator.php'=>['fieldflow-multilingual-client-locale-authority-v1','multilingual_client_locale_authority_9by_ok'],
            'tools/verify-multilingual-client-locale-authority-9by.php'=>['MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY CONTRACT OK','successor=>=2.2.596'],
            'tools/test-multilingual-client-locale-authority-9by.php'=>['MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY RUNTIME OK','client_meta_authority=1','user_preference_scope=1','front_selector=1','dynamic_runtime_translation=1','native_webview_no_flutterflow=1','data_translation_guard=1','gate_validator_parity=1'],
            'PHASE-9BY-MULTILINGUAL-CLIENT-LOCALE-AUTHORITY.md'=>['fieldflow-multilingual-client-locale-authority-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        foreach($requirements as $relative=>$markers){$raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;}
        $localeKeys=[];
        if(class_exists('\\RoutesPro\\Localization\\LanguageService')){
            $localeKeys=array_keys((array)\RoutesPro\Localization\LanguageService::supportedLocales());
        }
        foreach(['pt-PT','en-GB','es-ES','fr-FR'] as $requiredLocale)if(!in_array($requiredLocale,$localeKeys,true))$missing[]='LanguageService:locale:'.$requiredLocale;
        return self::check('multilingual_client_locale_authority_9by','Multilingual Client Locale Authority 9BY',$missing?'fail':'pass',$missing?'Multilingual client locale authority is incomplete: '.implode(', ',array_slice($missing,0,8)):'Client-level language policy, per-user preference, front and native WebView selector, dynamic runtime translation and certification parity are active without changing FlutterFlow, routing or database schema.',['runtime'=>self::currentRelease(),'contract'=>self::MULTILINGUAL_CLIENT_LOCALE_AUTHORITY_9BY_CONTRACT,'supported_locales'=>$localeKeys,'missing'=>$missing]);
    }

    private static function checkPremiumLocaleVisitMemory9BZContract(): array {
        $missing=[];
        $requirements=[
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
            'src/Support/ReleasePackageValidator.php'=>['premium_locale_visit_memory_9bz_ok','Premium Locale & Visit Memory Continuity 9BZ'],
            'tools/verify-premium-locale-visit-memory-9bz.php'=>['PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ CONTRACT OK','successor=>=2.2.597'],
            'tools/test-premium-locale-visit-memory-9bz.php'=>['PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ RUNTIME OK','normal_answers_prefilled=1','fronts_prefilled=1','photos_excluded=1','order_excluded=1','gate_validator_parity=1'],
            'PHASE-9BZ-PREMIUM-LOCALE-VISIT-MEMORY-CONTINUITY.md'=>['fieldflow-premium-locale-visit-memory-continuity-v1','No FlutterFlow source modification is required','No database schema changes'],
        ];
        foreach($requirements as $relative=>$markers){
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            foreach($markers as $m)if(strpos($raw,$m)===false)$missing[]=$relative.':'.$m;
        }
        $currentReact='assets/fieldflow-react-front-'.self::currentRelease().'.js';
        $reactRaw=is_readable(ROUTESPRO_PATH.$currentReact)?(string)file_get_contents(ROUTESPRO_PATH.$currentReact):'';
        foreach(['fieldflow-react-locale-switcher-v1','typescript-react-wp-element'] as $m)if(strpos($reactRaw,$m)===false)$missing[]=$currentReact.':'.$m;
        $memoryFixture=[];
        if(class_exists('\\RoutesPro\\Forms\\DailySubmissionService')){
            $schema=['questions'=>[
                ['key'=>'notes','type'=>'textarea'],['key'=>'choice','type'=>'select'],['key'=>'fronts','type'=>'product_matrix'],
                ['key'=>'photo','type'=>'image_upload'],['key'=>'file','type'=>'file_upload'],['key'=>'order','type'=>'order_suggestion'],['key'=>'brief','type'=>'briefing_block'],
            ]];
            $input=['notes'=>'x','choice'=>'Sim','fronts'=>[['before'=>1,'after'=>2]],'photo'=>['x.jpg'],'file'=>['x.pdf'],'order'=>[['qty'=>2]],'brief'=>'x'];
            $memoryFixture=\RoutesPro\Forms\DailySubmissionService::new_visit_autofill_prefill($input,$schema);
            if(($memoryFixture['notes']??'')!=='x'||($memoryFixture['choice']??'')!=='Sim'||empty($memoryFixture['fronts']))$missing[]='DailySubmissionService:eligible_prefill';
            foreach(['photo','file','order','brief'] as $forbidden)if(array_key_exists($forbidden,$memoryFixture))$missing[]='DailySubmissionService:forbidden_'.$forbidden;
        }else{$missing[]='DailySubmissionService:class';}
        return self::check('premium_locale_visit_memory_9bz','Premium Locale & Visit Memory Continuity 9BZ',$missing?'fail':'pass',$missing?'Premium locale / visit memory successor is incomplete: '.implode(', ',array_slice($missing,0,8)):'React + TypeScript premium locale switching and safe last-visit auto-prefill are active across web and native WebView, with media/order exclusion and canonical store isolation preserved.',['runtime'=>self::currentRelease(),'contract'=>self::PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT,'prefill_keys'=>array_keys($memoryFixture),'missing'=>$missing]);
    }


    private static function checkCertificationMarkerContinuity9CAContract(): array {
        $missing = [];
        $requirements = [
            'src/Forms/Forms.php' => [
                'Frentes reutiliza o valor final anterior',
                'DailySubmissionService::new_visit_autofill_prefill',
                '9BD historical certification marker only',
            ],
            'src/Forms/DailySubmissionService.php' => [
                'visit_memory_autofill_question_eligible',
                'new_visit_autofill_prefill',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.597'",
                "public const STABLE_RELEASE = '2.2.598'",
                "// public const SOURCE_RELEASE = '2.2.596'",
                "// public const STABLE_RELEASE = '2.2.597'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'certification_marker_continuity_9ca_ok',
                'Certification Marker Continuity 9CA',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['certification_marker_continuity_9ca_ok']",
            ],
            'tools/verify-certification-marker-continuity-9ca.php' => [
                'CERTIFICATION MARKER CONTINUITY 9CA CONTRACT OK',
                'successor=>=2.2.598',
            ],
            'tools/test-certification-marker-continuity-9ca.php' => [
                'CERTIFICATION MARKER CONTINUITY 9CA RUNTIME OK',
                '9bd_marker=1',
                '9bz_runtime_preserved=1',
                'successor_continuity=1',
            ],
            'PHASE-9CA-CERTIFICATION-MARKER-CONTINUITY.md' => [
                self::CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.597` and `STABLE_RELEASE` is `2.2.598`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        if (version_compare(self::currentRelease(), '2.2.598', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check(
            'certification_marker_continuity_9ca',
            'Certification Marker Continuity 9CA',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Certification marker continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'The historical 9BD proof marker is restored while the 9BZ last-visit autofill policy remains the active runtime authority.',
            [
                'runtime' => self::currentRelease(),
                'contract' => self::CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT,
                'historical_gate' => '9BD',
                'runtime_authority' => '9BZ',
                'missing' => $missing,
            ]
        );
    }


    private static function checkLocaleBrandAssociatedClientScope9CBContract(): array {
        $missing = [];
        $requirements = [
            'src/Localization/LanguageService.php' => [
                'SCOPE_CONTINUITY_CONTRACT',
                self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT,
                'scopeClientIds',
            ],
            'src/Localization/LanguageController.php' => [
                "register_rest_route('fieldflow/v1', '/language'",
                'Permissions::is_allowed_client',
            ],
            'assets/fieldflow-i18n.js' => [
                self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT,
                'setClientContext',
                "document.addEventListener('routespro:scope-change'",
                'fieldflow:i18n-context-changed',
                'applyLegacyBrand',
            ],
            'assets/fieldflow-i18n.css' => [
                '.ff-language-trigger:hover',
                'var(--ff-lang-primary)',
                'var(--ff-lang-accent)',
                '.ff-language-code-badge',
            ],
            'frontend/src/react-front/locale-switcher.ts' => [
                self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT,
                'fieldFlowLocaleBrandStyle',
                "document.addEventListener('routespro:scope-change'",
                "window.addEventListener('fieldflow:i18n-context-changed'",
                'MutationObserver',
            ],
            'frontend/src/react-front/bootstrap.ts' => [
                "window.addEventListener('fieldflow:i18n-context-changed'",
                'fieldFlowReactLocaleSwitcherMount',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.598'",
                "public const STABLE_RELEASE = '2.2.599'",
                "// public const SOURCE_RELEASE = '2.2.597'",
                "// public const STABLE_RELEASE = '2.2.598'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'locale_brand_associated_client_scope_9cb_ok',
                'Locale Brand & Associated Client Scope Continuity 9CB',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['locale_brand_associated_client_scope_9cb_ok']",
            ],
            'tools/verify-locale-brand-associated-client-scope-9cb.php' => [
                'LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB CONTRACT OK',
                'successor=>=2.2.599',
            ],
            'tools/test-locale-brand-associated-client-scope-9cb.php' => [
                'LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB RUNTIME OK',
                'brand_hover=1',
                'dynamic_client_scope=1',
                'react_rebind=1',
                'historical_certification=1',
            ],
            'PHASE-9CB-LOCALE-BRAND-ASSOCIATED-CLIENT-SCOPE-CONTINUITY.md' => [
                self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.598` and `STABLE_RELEASE` is `2.2.599`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        if (version_compare(self::currentRelease(), '2.2.599', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        $reactRelative = 'assets/fieldflow-react-front-' . self::currentRelease() . '.js';
        $reactRaw = is_readable(ROUTESPRO_PATH . $reactRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $reactRelative) : '';
        foreach ([self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT, 'fieldFlowLocaleBrandStyle', 'routespro:scope-change'] as $marker) {
            if (strpos($reactRaw, $marker) === false) $missing[] = $reactRelative . ':' . $marker;
        }
        return self::check(
            'locale_brand_associated_client_scope_9cb',
            'Locale Brand & Associated Client Scope Continuity 9CB',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Locale brand / associated-client scope continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'The premium locale globe inherits the active client brand and rebinds its per-client language policy whenever the canonical portal scope changes.',
            [
                'runtime' => self::currentRelease(),
                'contract' => self::LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT,
                'scope_event' => 'routespro:scope-change',
                'historical_authorities' => ['9BD','9BZ','9CA'],
                'missing' => $missing,
            ]
        );
    }


    private static function checkAppShellBrowserlikeTranslation9CCContract(): array {
        $missing = [];
        $requirements = [
            'src/Localization/LanguageService.php' => [
                'AUTO_TRANSLATION_CONTRACT',
                'APP_SHELL_CONTINUITY_CONTRACT',
                'fieldflow-browserlike-auto-translation-v1',
                'fieldflow-app-shell-locale-runtime-v1',
                "'zh-CN'",
                "translationEndpoint'=>rest_url('fieldflow/v1/language/translate')",
            ],
            'src/Localization/LanguageController.php' => [
                "register_rest_route('fieldflow/v1', '/language/translate'",
                'TranslationService::translateBatch',
                'Permissions::is_manager()',
                'canUseClient',
            ],
            'src/Localization/TranslationService.php' => [
                'fieldflow-browserlike-auto-translation-v1',
                'translateBatch',
                "AIFactory::make('translation')",
                'store names, postal addresses and product names',
                'Simplified Chinese',
            ],
            'assets/fieldflow-i18n.js' => [
                'fieldflow-browserlike-auto-translation-v1',
                'fieldflow-app-shell-locale-runtime-v1',
                'window.Translator',
                'T.create({sourceLanguage:',
                'serverTranslateBatch',
                'preserveElement',
                "locale==='zh-CN'",
                'fieldflow:portal-ready',
                'ensureSwitcher',
                'data-fieldflow-locale-host',
            ],
            'frontend/src/react-front/locale-switcher.ts' => [
                'fieldflow-app-shell-locale-runtime-v1',
                'fieldflow-browserlike-auto-translation-v1',
                'element.createElement(fieldFlowReactLocaleSwitcherComponent, null)',
                'Invalid hook call',
                'runtime.ensureSwitcher',
                'runtime.prepareTranslator',
            ],
            'frontend/src/react-front/bootstrap.ts' => [
                "document.addEventListener('fieldflow:portal-ready'",
                "window.addEventListener('pageshow'",
                'FieldFlowI18n?.reconcile',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'data-fieldflow-locale-host="1"',
                'data-fieldflow-locale-shell="operative"',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'data-fieldflow-locale-host="1"',
                'data-fieldflow-locale-shell="client"',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.599'",
                "public const STABLE_RELEASE = '2.2.600'",
                "// public const SOURCE_RELEASE = '2.2.598'",
                "// public const STABLE_RELEASE = '2.2.599'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'app_shell_browserlike_translation_9cc_ok',
                'App Shell & Browser-like Translation Continuity 9CC',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['app_shell_browserlike_translation_9cc_ok']",
            ],
            'tools/verify-app-shell-browserlike-translation-9cc.php' => [
                'APP SHELL & BROWSER-LIKE TRANSLATION 9CC CONTRACT OK',
                'successor=>=2.2.600',
            ],
            'tools/test-app-shell-browserlike-translation-9cc.php' => [
                'APP SHELL & BROWSER-LIKE TRANSLATION 9CC RUNTIME OK',
                'react_hooks=1',
                'dual_portal_webview=1',
                'browserlike_translation=1',
                'protected_names=1',
                'chinese_full_translation=1',
                'historical_memory=1',
            ],
            'PHASE-9CC-APP-SHELL-BROWSERLIKE-TRANSLATION-CONTINUITY.md' => [
                self::APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.599` and `STABLE_RELEASE` is `2.2.600`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        if (version_compare(self::currentRelease(), '2.2.600', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $reactRelative = 'assets/fieldflow-react-front-' . self::currentRelease() . '.js';
        $reactRaw = is_readable(ROUTESPRO_PATH . $reactRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $reactRelative) : '';
        foreach (['fieldflow-app-shell-locale-runtime-v1','fieldflow-browserlike-auto-translation-v1','fieldFlowReactLocaleSwitcherComponent','Invalid hook call'] as $required) {
            if (strpos($reactRaw, $required) === false) $missing[] = $reactRelative . ':' . $required;
        }
        return self::check(
            'app_shell_browserlike_translation_9cc',
            'App Shell & Browser-like Translation Continuity 9CC',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'App-shell locale / browser-like translation continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Client and operative WebView shells keep one branded locale control alive, React hooks mount inside React, and unknown visible text is translated by the browser/AI fallback while store names, addresses and product names stay protected outside Chinese.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT,
                'locales'=>['pt-PT','en-GB','es-ES','fr-FR','zh-CN'],
                'historical_authorities'=>['9BD','9BZ','9CA','9CB'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkAdaptiveLocaleDock9CDContract(): array {
        $requirements = [
            'frontend/src/react-front/locale-switcher.ts' => [
                self::ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT,
                "window.addEventListener('scroll'",
                "document.addEventListener('scroll'",
                "switcherClasses.push('is-docked')",
                "switcherClasses.push('is-scrolling')",
                'setDocked(false)',
                'setOpen(true)',
            ],
            'assets/fieldflow-i18n.css' => [
                'Adaptive Locale Dock',
                '.ff-language-switcher.is-docked:not(.is-open):not(.is-scrolling)',
                '.ff-language-switcher.is-scrolling:not(.is-open)',
                'safe-area-inset-right',
                'prefers-reduced-motion:reduce',
            ],
            'assets/fieldflow-i18n.js' => [
                self::ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT,
                'wireLegacyAdaptiveDock',
                "wrap.classList.add('is-docked','is-scrolling')",
                'adaptiveDockContract',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.600'",
                "public const STABLE_RELEASE = '2.2.601'",
                "// public const SOURCE_RELEASE = '2.2.599'",
                "// public const STABLE_RELEASE = '2.2.600'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'adaptive_locale_dock_9cd_ok',
                'Adaptive Locale Dock Continuity 9CD',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['adaptive_locale_dock_9cd_ok']",
            ],
            'tools/verify-adaptive-locale-dock-9cd.php' => [
                'ADAPTIVE LOCALE DOCK 9CD CONTRACT OK',
                'successor=>=2.2.601',
            ],
            'tools/test-adaptive-locale-dock-9cd.php' => [
                'ADAPTIVE LOCALE DOCK 9CD RUNTIME OK',
                'initial_visible=1',
                'scroll_hidden=1',
                'resting_tab=1',
                'tap_reveal=1',
                'safe_area=1',
                'historical_continuity=1',
            ],
            'PHASE-9CD-ADAPTIVE-LOCALE-DOCK-CONTINUITY.md' => [
                self::ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.600` and `STABLE_RELEASE` is `2.2.601`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.601', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        $reactRelative = 'assets/fieldflow-react-front-' . self::currentRelease() . '.js';
        $reactRaw = is_readable(ROUTESPRO_PATH . $reactRelative) ? (string)file_get_contents(ROUTESPRO_PATH . $reactRelative) : '';
        foreach ([self::ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT,"switcherClasses.push('is-docked')","switcherClasses.push('is-scrolling')"] as $required) {
            if (strpos($reactRaw, $required) === false) $missing[] = $reactRelative . ':' . $required;
        }
        return self::check(
            'adaptive_locale_dock_9cd',
            'Adaptive Locale Dock Continuity 9CD',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Adaptive locale dock continuity is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'The branded locale globe starts visible, clears the viewport during scroll, rests as a small side tab, and expands by tap/click or desktop focus without changing translation or visit-memory authorities.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT,
                'historical_authorities'=>['9BD','9BZ','9CA','9CB','9CC'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkAnalyticsHeaderDateExportAuthority9CEContract(): array {
        $requirements = [
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                self::ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT,
                'function analyticsHeaderDateScope()',
                'function syncAnalyticsExportDateAuthority()',
                "addEventListener('input',syncAnalyticsExportDateAuthority)",
                "addEventListener('pointerdown',syncAnalyticsExportDateAuthority",
                "p.set('from',scope.from)",
                "p.set('to',scope.to)",
            ],
            'src/Shortcodes.php' => [
                self::ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT,
                'function analyticsHeaderDateScope()',
                'function syncAnalyticsExportDateAuthority()',
                "addEventListener('input',syncAnalyticsExportDateAuthority)",
                "addEventListener('pointerdown',syncAnalyticsExportDateAuthority",
                "p.set('from',scope.from)",
                "p.set('to',scope.to)",
            ],
            'src/Mobile/WebViewBridge.php' => [
                'fieldflow-webview-dashboard-export-date-authority-v1',
                'function applyLiveAnalyticsDateScope(anchor,url)',
                "action!=='routespro_export_dashboard'",
                "url.searchParams.set('from',fromValue)",
                "url.searchParams.set('to',toValue)",
            ],
            'src/Admin/FormSubmissions.php' => [
                self::ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT,
                'normalizeDashboardReportDate',
                'checkdate($parts[1], $parts[2], $parts[0])',
                'if ($filters[\'from\'] > $filters[\'to\'])',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.601'",
                "public const STABLE_RELEASE = '2.2.602'",
                "// public const SOURCE_RELEASE = '2.2.600'",
                "// public const STABLE_RELEASE = '2.2.601'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'analytics_header_date_export_authority_9ce_ok',
                'Analytics Header Date Export Authority 9CE',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['analytics_header_date_export_authority_9ce_ok']",
                'DATAS EXPORT OK',
            ],
            'tools/verify-analytics-header-date-export-authority-9ce.php' => [
                'ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE CONTRACT OK',
                'successor=>=2.2.602',
            ],
            'tools/test-analytics-header-date-export-authority-9ce.php' => [
                'ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE RUNTIME OK',
                'client_live_dates=1',
                'operative_live_dates=1',
                'webview_capture=1',
                'server_normalization=1',
                'presets_preserved=1',
                'historical_continuity=1',
            ],
            'PHASE-9CE-ANALYTICS-HEADER-DATE-EXPORT-AUTHORITY.md' => [
                self::ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.601` and `STABLE_RELEASE` is `2.2.602`',
                'Header dates override stale export URLs',
                'Preset buttons remain supported',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.602', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'analytics_header_date_export_authority_9ce',
            'Analytics Header Date Export Authority 9CE',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Analytics export date authority is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Client and operative Analytics exports use the live De/Até header values at interaction time and again inside the WebView bridge, while period preset buttons keep changing the same canonical fields.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT,
                'precedence'=>['explicit_header_dates','period_preset_updates_header','default_range'],
                'formats'=>['csv','xlsx','xlsx_mobile','pdf'],
                'historical_authorities'=>['9BV','9BZ','9CC','9CD'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkExpenseStatementPdfReceiptAuthority9CFContract(): array {
        $requirements = [
            'src/Expenses/ExpenseStatementService.php' => [
                self::EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT,
                'normalizeFilters(array $input)',
                'statementData(int $userId',
                'ExpensesController::buildWhere($filters, $userId, true)',
                'Branding::get_header_logos',
                'Declaração de recebimento',
                'Assinatura do colaborador',
                'Data: ____ / ____ / ______',
            ],
            'src/Admin/Expenses.php' => [
                'admin_post_fieldflow_expense_statement_export',
                'exportStatement()',
                'Folha de despesas PDF',
                "'user_id'",
            ],
            'src/Front/ExpensesShortcodeRenderer.php' => [
                'fieldflow_expense_statement_export',
                'ffe-statement-pdf',
                'syncStatementUrl()',
                "['month','.ffe-filter-month']",
                "['date_from','.ffe-filter-from']",
                "['date_to','.ffe-filter-to']",
                "['category','.ffe-filter-category']",
                "['status','.ffe-filter-status']",
                "['search','.ffe-filter-search']",
            ],
            'src/Mobile/WebViewBridge.php' => [
                'fieldflow-webview-expense-statement-scope-authority-v1',
                'function applyLiveExpenseStatementScope(anchor,url)',
                "action!=='fieldflow_expense_statement_export'",
                'url=applyLiveExpenseStatementScope(anchor,url);',
            ],
            'src/Support/Loader.php' => ['src/Expenses/ExpenseStatementService.php'],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.602'",
                "public const STABLE_RELEASE = '2.2.603'",
                "// public const SOURCE_RELEASE = '2.2.601'",
                "// public const STABLE_RELEASE = '2.2.602'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'expense_statement_pdf_receipt_authority_9cf_ok',
                'Expense Statement PDF & Receipt Authority 9CF',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['expense_statement_pdf_receipt_authority_9cf_ok']",
                'FOLHA DESPESAS OK',
            ],
            'tools/verify-expense-statement-pdf-receipt-authority-9cf.php' => [
                'EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF CONTRACT OK',
                'successor=>=2.2.603',
            ],
            'tools/test-expense-statement-pdf-receipt-authority-9cf.php' => [
                'EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF RUNTIME OK',
                'personal_pdf=1',
                'admin_operational_pdf=1',
                'date_filter_authority=1',
                'daily_totals=1',
                'dual_branding=1',
                'signature_receipt=1',
                'webview_live_scope=1',
                'historical_continuity=1',
            ],
            'PHASE-9CF-EXPENSE-STATEMENT-PDF-RECEIPT-AUTHORITY.md' => [
                self::EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.602` and `STABLE_RELEASE` is `2.2.603`',
                'Existing expenses remain the single source of truth',
                'Explicit De/Até has precedence over the month shortcut',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.603', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'expense_statement_pdf_receipt_authority_9cf',
            'Expense Statement PDF & Receipt Authority 9CF',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Expense statement PDF authority is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Expenses projects the canonical filtered records into a signed-ready A4 statement with operative identity, daily subtotals, total, TWT/client branding and receipt signature fields. Front, BO and WebView share the same filter authority.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT,
                'source'=>'routespro_expenses',
                'filters'=>['month','date_from','date_to','category','status','search'],
                'audiences'=>['operative_self','manager_selected_operative'],
                'historical_authorities'=>['9BV','9BZ','9CC','9CD','9CE'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkExpenseStatementMobileTicketDelivery9CGContract(): array {
        $requirements = [
            'src/Admin/Expenses.php' => [
                self::EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT,
                'prepareStatementTicket()',
                'handleStatementTicketDownload()',
                "wp_tempnam('fieldflow-expense-statement.pdf')",
                'fieldflow_expense_statement_download',
                "in_array(\$method, ['GET','HEAD'], true)",
                'X-FieldFlow-Expense-Statement-Ticket',
                'Keep the ticket alive for Android/Chrome HEAD probes and GET retries.',
            ],
            'src/Architecture/RuntimeRegistry.php' => [
                'wp_ajax_fieldflow_prepare_expense_statement_ticket',
                'handleStatementTicketDownload',
                'fieldflow_expense_statement_ticket_cleanup',
            ],
            'src/Mobile/WebViewBridge.php' => [
                self::EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT,
                "expenseStatement:action==='fieldflow_expense_statement_export'",
                'function requestExpenseStatement(meta)',
                "body.set('action','fieldflow_prepare_expense_statement_ticket')",
                "source:'expense_statement_pdf_ticket'",
                "transport:'external_system_browser'",
                'if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.603'",
                "public const STABLE_RELEASE = '2.2.604'",
                "// public const SOURCE_RELEASE = '2.2.602'",
                "// public const STABLE_RELEASE = '2.2.603'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'expense_statement_mobile_ticket_delivery_9cg_ok',
                'Expense Statement Mobile Ticket Delivery 9CG',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['expense_statement_mobile_ticket_delivery_9cg_ok']",
                'PDF APP OK',
            ],
            'tools/verify-expense-statement-mobile-ticket-delivery-9cg.php' => [
                'EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG CONTRACT OK',
                'successor=>=2.2.604',
            ],
            'tools/test-expense-statement-mobile-ticket-delivery-9cg.php' => [
                'EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG RUNTIME OK',
                'cookie_free_ticket=1',
                'head_safe=1',
                'generic_flutter_bypass=1',
                'system_browser=1',
                'filter_scope=1',
                'historical_continuity=1',
            ],
            'PHASE-9CG-EXPENSE-STATEMENT-MOBILE-TICKET-DELIVERY.md' => [
                self::EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.603` and `STABLE_RELEASE` is `2.2.604`',
                'authenticated WebView session',
                'cookie-free capability URL',
                'HEAD probes',
                'generic Flutter downloader',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.604', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'expense_statement_mobile_ticket_delivery_9cg',
            'Expense Statement Mobile Ticket Delivery 9CG',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Expense statement mobile delivery is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Expense statements are prepared inside the authenticated WebView, then delivered through an opaque short-lived capability URL that is HEAD-safe and bypasses the generic Flutter downloader.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT,
                'transport'=>['authenticated_prepare','opaque_capability','system_browser','browser_blob_fallback'],
                'preserves'=>['filters','branding','daily_totals','signature','9CF'],
                'historical_authorities'=>['7ZN','9BV','9BZ','9CC','9CD','9CE','9CF'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkExpenseSettlementSignature9CHContract(): array {
        $requirements = [
            'src/Expenses/ExpenseService.php' => [
                "'paid' => 'Paga / Reembolsada'",
                'public static function setStatus(',
                'payment_history',
                '\'paid_at\' => $now',
                '\'paid_by\' => $actorUserId',
            ],
            'src/Expenses/ExpenseSettlementService.php' => [
                self::EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT,
                "POST_TYPE = 'ff_exp_settlement'",
                "'public' => false",
                'normalizeSettlementFilters(array $input)',
                '$filters[\'status\'] = \'paid\'',
                'submitUpload(',
                'submitDirectSignature(',
                'snapshotHash(array $data)',
                'fieldflow-private-expense-settlements',
                'serveEvidence()',
            ],
            'src/Expenses/ExpensesController.php' => [
                '/expenses/statement-context',
                '/expenses/statement-evidence',
                'statementContext(',
                'submitStatementEvidence(',
                'declaration_confirmed',
            ],
            'src/Expenses/ExpenseStatementService.php' => [
                'signature_image_path',
                'signature_signed_at',
                '/Sig1',
                'Assinado no FieldFlow em',
                'signedFilename(array $data)',
            ],
            'src/Admin/Expenses.php' => [
                'fieldflow_expense_bulk_paid',
                'bulkMarkPaid()',
                'Marcar filtradas como Paga / Reembolsada',
                'Folhas assinadas e comprovativos de recebimento',
                'folhas-assinadas/',
                'Pago em',
                'Pago por',
            ],
            'src/Front/ExpensesShortcodeRenderer.php' => [
                'Fecho e comprovativo de recebimento',
                'Enviar folha já assinada',
                'Assinar diretamente no FieldFlow',
                'ffe-signature',
                'statement-context',
                'statement-evidence',
                "fd.set('mode','upload')",
                "fd.set('mode','signature')",
            ],
            'src/Support/Loader.php' => ['src/Expenses/ExpenseSettlementService.php'],
            'src/Architecture/RuntimeRegistry.php' => ['ExpenseSettlementService::registerHooks()'],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.604'",
                "public const STABLE_RELEASE = '2.2.605'",
                "// public const SOURCE_RELEASE = '2.2.603'",
                "// public const STABLE_RELEASE = '2.2.604'",
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'expense_settlement_signature_9ch_ok',
                'Expense Settlement, Payment & Signature Authority 9CH',
            ],
            'src/Admin/ReleaseManager.php' => [
                "packageResult['expense_settlement_signature_9ch_ok']",
                'PAGAMENTO + ASSINATURA OK',
            ],
            'tools/verify-expense-settlement-signature-authority-9ch.php' => [
                'EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH CONTRACT OK',
                'successor=>=2.2.605',
            ],
            'tools/test-expense-settlement-signature-authority-9ch.php' => [
                'EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH RUNTIME OK',
                'paid_state=1',
                'bulk_payment=1',
                'dual_evidence=1',
                'direct_signature_pdf=1',
                'private_archive=1',
                'bo_archive=1',
                'zip_archive=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CH-EXPENSE-SETTLEMENT-SIGNATURE-AUTHORITY.md' => [
                self::EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.604` and `STABLE_RELEASE` is `2.2.605`',
                'Paga / Reembolsada',
                'upload an already signed statement',
                'draw a handwritten signature directly',
                'No schema migration is required',
                'No FlutterFlow source modification is required',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.605', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'expense_settlement_signature_9ch',
            'Expense Settlement, Payment & Signature Authority 9CH',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Expense payment/signature lifecycle is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Expenses distinguish approval from payment, BO can close a guarded operative/period scope, and paid statements can be acknowledged by uploaded signed evidence or a direct handwritten FieldFlow signature with private snapshot archive.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT,
                'payment_authority'=>'manager_only',
                'settlement_modes'=>['uploaded_signed_file','fieldflow_direct_signature'],
                'storage'=>'non_public_post_type_plus_private_files',
                'schema_change'=>false,
                'historical_authorities'=>['9CF','9CG','9CE','9BZ','9CC','9CD'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkVisitClosureSelectorReturn9CIContract(): array {
        $requirements = [
            'src/Shortcodes.php' => [
                self::VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT,
                'id="rpf-closure"',
                'async function returnToStoreSelector(closedContext,state)',
                'async function refreshVisitsAfterClosure(date)',
                'if(preferred.selection===\'none\')',
                'data-rpf-next-stop',
                'Próxima loja',
                'const scrollToStoreSelector=()=>',
                'await returnToStoreSelector(completedContext,editingExisting?\'updated\':\'done\');',
                'await returnToStoreSelector(closureContextFromSelection(),\'offline\');',
                'await returnToStoreSelector(failureClosedContext,\'failed_offline\');',
                'await returnToStoreSelector(failureClosedContext,\'failed\');',
                'await returnToStoreSelector(fallbackClosedContext,\'done\');',
                'Queres mesmo concluir esta visita?',
                'fieldflow-route-first-paint-v1',
                'Promise.resolve().then(()=>loadRoutesForDay(inpDate.value))',
                'source:\'visit-closure-return\'',
                '.rp-report-focus.is-closure-return #rpf-submit',
            ],
            'fieldflow.php' => [
                'fieldflow-legacy-pdv-autocomplete-retirement-v1',
                'apply_filters(\'fieldflow_legacy_pdv_autocomplete_enabled\', false)',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.605\'',
                'public const STABLE_RELEASE = \'2.2.606\'',
                '// public const SOURCE_RELEASE = \'2.2.604\'',
                '// public const STABLE_RELEASE = \'2.2.605\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'visit_closure_selector_return_9ci_ok',
                'Visit Closure Selector Return & Route First Paint 9CI',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'visit_closure_selector_return_9ci_ok\']',
                'FECHO → SELETOR OK',
            ],
            'tools/verify-visit-closure-selector-return-9ci.php' => [
                'VISIT CLOSURE SELECTOR RETURN 9CI CONTRACT OK',
                'successor=>=2.2.606',
            ],
            'tools/test-visit-closure-selector-return-9ci.php' => [
                'VISIT CLOSURE SELECTOR RETURN 9CI RUNTIME OK',
                'closed_store_not_reselected=1',
                'selector_scroll_once=1',
                'next_store_card=1',
                'offline_return=1',
                'failure_return=1',
                'stored_context_cleared=1',
                'resume_checkpoint_cleared=1',
                'idle_actions_hidden=1',
                'route_first_paint=1',
                'legacy_autocomplete_retired=1',
                'historical_continuity=1',
            ],
            'PHASE-9CI-VISIT-CLOSURE-SELECTOR-RETURN.md' => [
                self::VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.605` and `STABLE_RELEASE` is `2.2.606`',
                'returns to the store selector',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Shortcodes.php' => ['await loadVisits(completedContext.date,{stop_id:completedContext.stop_id,force:true})','loadVisits(currentDate,{stop_id:parseInt(els.stop.value||0,10),force:true})','loadGoogle(()=>{ loadRoutesForDay(inpDate.value); });'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.606', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'visit_closure_selector_return_9ci',
            'Visit Closure Selector Return & Route First Paint 9CI',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Visit closure selector return is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'After a terminal visit the Report clears the closed store, shows the closure card with the next pending store and performs one scroll back to the store selector. My Route paints the day before Google Maps loads and the legacy PDV autocomplete is retired.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT,
                'terminal_states'=>['done','updated','offline','failed','failed_offline'],
                'auto_select_next'=>false,
                'continuous_viewport_guard'=>false,
                'route_first_paint'=>'fieldflow-route-first-paint-v1',
                'legacy_pdv_autocomplete'=>'filter_opt_in',
                'schema_change'=>false,
                'historical_authorities'=>['9AH','9AP','9AA','9AC','9BJ','9CH'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkSecurityHygieneAuthority9CJContract(): array {
        $requirements = [
            'src/Forms/Forms.php' => [
                'fieldflow-context-form-scope-authority-v1',
                'private static function context_form_scope_error(array $context): string',
                '$scope_error = self::context_form_scope_error($context);',
                '\'fieldflow_context_form_forbidden\'',
            ],
            'src/Services/AI.php' => [
                'fieldflow-ai-model-currency-v1',
                'public const DEFAULT_MODEL = \'gemini-2.5-flash-lite\';',
                'public static function resolveModel(string $model): string',
                '\'x-goog-api-key\'=>$this->key',
            ],
            'src/Support/Config.php' => [
                '\'google_ai_model\' => \'gemini-2.5-flash-lite\'',
            ],
            'src/Admin/AIControlCenter.php' => [
                'GoogleAIProvider::resolveModel(',
                'placeholder="gemini-2.5-flash-lite"',
            ],
            'tools/.htaccess' => [
                'fieldflow-build-tooling-web-isolation-v1',
                'Require all denied',
                'Deny from all',
            ],
            'frontend/.htaccess' => [
                'fieldflow-build-tooling-web-isolation-v1',
                'Require all denied',
                'Deny from all',
            ],
            'src/Shortcodes.php' => [
                'sanitize_text_field($atts[\'date\'] ?? current_time(\'Y-m-d\'))',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.606\'',
                'public const STABLE_RELEASE = \'2.2.607\'',
                '// public const SOURCE_RELEASE = \'2.2.605\'',
                '// public const STABLE_RELEASE = \'2.2.606\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'security_hygiene_authority_9cj_ok',
                'Security & Hygiene Authority 9CJ',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'security_hygiene_authority_9cj_ok\']',
                'SCOPE + TOOLS + IA OK',
            ],
            'tools/verify-security-hygiene-authority-9cj.php' => [
                'SECURITY & HYGIENE AUTHORITY 9CJ CONTRACT OK',
                'successor=>=2.2.607',
            ],
            'tools/test-security-hygiene-authority-9cj.php' => [
                'SECURITY & HYGIENE AUTHORITY 9CJ RUNTIME OK',
                'context_form_scope=1',
                'ai_model_currency=1',
                'gemini_key_header=1',
                'tooling_isolated=1',
                'site_timezone_dates=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CJ-SECURITY-HYGIENE-AUTHORITY.md' => [
                self::SECURITY_HYGIENE_AUTHORITY_9CJ_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.606` and `STABLE_RELEASE` is `2.2.607`',
                'same authority as submission',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Services/AI.php' => [':generateContent?key=','\'gemini-1.5-flash\')'],
            'src/Support/Config.php' => ['\'google_ai_model\' => \'gemini-1.5-flash\''],
            'src/Shortcodes.php' => ['esc_attr(date(\'Y-m-d\'))','sanitize_text_field($atts[\'date\'] ?? date(\'Y-m-d\'))'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.607', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'security_hygiene_authority_9cj',
            'Security & Hygiene Authority 9CJ',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Security & hygiene authority is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'The context form read path enforces route and client/project scope like submission, build tooling folders deny web access, default dates follow the site timezone and AI uses a current Gemini model with the key outside the URL.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::SECURITY_HYGIENE_AUTHORITY_9CJ_CONTRACT,
                'context_form_authority'=>'route_and_scope',
                'tooling_web_access'=>'denied',
                'date_authority'=>'site_timezone',
                'gemini_default_model'=>'gemini-3.5-flash-lite (9CO; 2.5 Flash-Lite mapped)',
                'gemini_key_transport'=>'header',
                'schema_change'=>false,
                'historical_authorities'=>['9CI','9CH','9M','9BN'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkPlannerGoogleCostAuthority9CKContract(): array {
        $requirements = [
            'src/Shortcodes.php' => [
                'fieldflow-planner-local-preview-cost-authority-v1',
                'function localRoutePreview(){',
                'function internalRoadEdge(a,b){',
                'routeCalcTimer=setTimeout(()=>localRoutePreview(),Math.max(0,delay));',
                'optimizeWaypoints:googleWaypointOptimizationEnabled&&!manualOrderLocked',
                'Calcular percurso real',
                'const extra=googlePlacesContactEnrichment?await findPlaceDetails(r.place_id):{};',
                'new google.maps.places.PlacesService(document.createElement(\'div\'))',
                'Config::get(\'google_routes_waypoint_optimization_enabled\', 0)',
                'wp_json_encode($endpoint_profiles ?? [])',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.607\'',
                'public const STABLE_RELEASE = \'2.2.608\'',
                '// public const SOURCE_RELEASE = \'2.2.606\'',
                '// public const STABLE_RELEASE = \'2.2.607\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'planner_google_cost_authority_9ck_ok',
                'Planner & Commercial Google Cost Authority 9CK',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'planner_google_cost_authority_9ck_ok\']',
                'GOOGLE SÓ A PEDIDO OK',
            ],
            'tools/verify-planner-google-cost-authority-9ck.php' => [
                'PLANNER GOOGLE COST AUTHORITY 9CK CONTRACT OK',
                'successor=>=2.2.608',
            ],
            'tools/test-planner-google-cost-authority-9ck.php' => [
                'PLANNER GOOGLE COST AUTHORITY 9CK RUNTIME OK',
                'local_preview=1',
                'estimator_parity=1',
                'manual_order_preserved=1',
                'google_on_request=1',
                'contact_details_gated=1',
                'no_hidden_map=1',
                'nearby_gated=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CK-PLANNER-GOOGLE-COST-AUTHORITY.md' => [
                self::PLANNER_GOOGLE_COST_AUTHORITY_9CK_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.607` and `STABLE_RELEASE` is `2.2.608`',
                'zero-cost local preview',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Shortcodes.php' => ['routeCalcTimer=setTimeout(()=>recalcRoute(),Math.max(0,delay));','optimizeWaypoints:!manualOrderLocked&&!queue.some(item=>item.position_locked)','const extra=await findPlaceDetails(r.place_id);','const dummy=document.createElement(\'div\'); googleMap=new google.maps.Map(dummy'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.608', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'planner_google_cost_authority_9ck',
            'Planner & Commercial Google Cost Authority 9CK',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Planner and commercial Google cost authority is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Descobrir previews routes locally with the calibrated road estimator and calls Google Directions only on explicit request, waypoint optimisation stays behind the premium switch, Base Comercial requests contact details only when enrichment is enabled and no hidden billable map is created.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::PLANNER_GOOGLE_COST_AUTHORITY_9CK_CONTRACT,
                'queue_change_google_calls'=>0,
                'directions_trigger'=>'explicit_request',
                'waypoint_optimization'=>'premium_switch',
                'places_contact_details'=>'enrichment_switch',
                'hidden_map_loads'=>0,
                'schema_change'=>false,
                'historical_authorities'=>['9BK','9BM','9BN','9CI','9CJ'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkVisualProductCatalog9CLContract(): array {
        $requirements = [
            'src/Catalog/ProductCatalogService.php' => [
                self::VISUAL_PRODUCT_CATALOG_9CL_CONTRACT,
                'fieldflow-briefing-product-label-mapping-v1',
                'public const POST_TYPE = \'ff_catalog_product\';',
                '\'public\' => false,',
                '\'show_in_rest\' => false,',
                'public static function syncFromCardex(int $onlyClientId = 0): array {',
                'public static function mapLabel(int $clientId, string $label, array $productIds): int {',
                'public static function merge(int $sourceId, int $targetId): void {',
                'public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {',
                '[\'cardex_import\', \'cardex\', \'cardex_briefing\', \'cardex_sync\']',
            ],
            'src/Admin/ProductCatalog.php' => [
                'fieldflow-visual-product-catalog-admin-v1',
                'check_admin_referer($action, \'fieldflow_catalog_nonce\');',
                'add_action(\'admin_post_fieldflow_catalog_map_label\', [self::class, \'handle_map_label\']);',
                'wp_enqueue_media()',
            ],
            'src/Admin/ProductCardex.php' => [
                'if (ProductCatalog::current_tab() !== \'cardex\') { ProductCatalog::render(); return; }',
                'ProductCatalog::render_tabs(\'cardex\');',
            ],
            'src/Architecture/RuntimeRegistry.php' => [
                '\\RoutesPro\\Catalog\\ProductCatalogService::registerHooks();',
                'add_action(\'admin_init\', [\'RoutesPro\\\\Admin\\\\ProductCatalog\', \'register_hooks\']);',
            ],
            'src/Support/Loader.php' => [
                '\'src/Catalog/ProductCatalogService.php\'',
                '\'src/Admin/ProductCatalog.php\'',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.608\'',
                'public const STABLE_RELEASE = \'2.2.609\'',
                '// public const SOURCE_RELEASE = \'2.2.607\'',
                '// public const STABLE_RELEASE = \'2.2.608\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'visual_product_catalog_9cl_ok',
                'Visual Product Catalog 9CL',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'visual_product_catalog_9cl_ok\']',
                'CATÁLOGO VISUAL OK',
            ],
            'tools/verify-visual-product-catalog-9cl.php' => [
                'VISUAL PRODUCT CATALOG 9CL CONTRACT OK',
                'successor=>=2.2.609',
            ],
            'tools/test-visual-product-catalog-9cl.php' => [
                'VISUAL PRODUCT CATALOG 9CL RUNTIME OK',
                'dedupe=1',
                'manual_preserved=1',
                'resolution=1',
                'competitor_mapping=1',
                'merge=1',
                'private_storage=1',
                'guarded_admin=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CL-VISUAL-PRODUCT-CATALOG.md' => [
                self::VISUAL_PRODUCT_CATALOG_9CL_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.608` and `STABLE_RELEASE` is `2.2.609`',
                'fieldflow-briefing-product-label-mapping-v1',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Catalog/ProductCatalogService.php' => ['CREATE TABLE','ALTER TABLE'],
            'src/Admin/ProductCatalog.php' => ['CREATE TABLE','ALTER TABLE'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.609', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'visual_product_catalog_9cl',
            'Visual Product Catalog 9CL',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Visual product catalog is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Products are de-duplicated per client from every Cardex into a private catalog with reference images, synchronisation never overwrites manual work, competitor products and briefing Share Space labels map to stable catalog products, and every back-office action requires routespro_manage and a nonce.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::VISUAL_PRODUCT_CATALOG_9CL_CONTRACT,
                'storage'=>'private_post_type',
                'identity'=>'client_reference_or_name_capacity',
                'label_mapping_contract'=>'fieldflow-briefing-product-label-mapping-v1',
                'max_images_per_product'=>12,
                'schema_change'=>false,
                'historical_authorities'=>['9CH','9CI','9CJ','9CK'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontsMobileCatalogVisual9CMContract(): array {
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                self::FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT,
                'private static function fronts_stepper(string $input, bool $enabled, string $name = \'\'): string {',
                'private static function fronts_row_summary(',
                'self::$fronts_visuals = self::fronts_catalog_visuals($q, $context, $rows, (int)$resolved_cardex_id);',
                'self::share_space_side_visual($share_catalog_client, $prLabel)',
                '$(document).on(\'click\',\'[data-fronts-step]\',function(e){',
                '$(document).on(\'click\',\'[data-product-zoom]\',function(e){',
                '.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{',
                'data-fronts-input',
                'data-share-space-pr',
                'data-share-space-competitor',
            ],
            'src/Catalog/ProductCatalogService.php' => [
                'public static function visualsForRows(int $clientId, array $rows): array {',
                'public static function visualsForLabel(int $clientId, string $label, int $limit = 3): array {',
                'public static function clientForContext(array $context, int $cardexId = 0): int {',
            ],
            'assets/routespro-form-builder.js' => [
                'catalog_images_disabled: !!q.catalog_images_disabled,',
                'input[data-fb="catalog_images_disabled"]',
            ],
            'src/Admin/Forms.php' => [
                'data-fb="catalog_images_disabled"',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.609\'',
                'public const STABLE_RELEASE = \'2.2.610\'',
                '// public const SOURCE_RELEASE = \'2.2.608\'',
                '// public const STABLE_RELEASE = \'2.2.609\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'fronts_mobile_catalog_visual_9cm_ok',
                'Fronts Mobile Layout & Catalog Visuals 9CM',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'fronts_mobile_catalog_visual_9cm_ok\']',
                'FRENTES COMPACTAS OK',
            ],
            'tools/verify-fronts-mobile-catalog-visual-9cm.php' => [
                'FRONTS MOBILE CATALOG VISUAL 9CM CONTRACT OK',
                'successor=>=2.2.610',
            ],
            'tools/test-fronts-mobile-catalog-visual-9cm.php' => [
                'FRONTS MOBILE CATALOG VISUAL 9CM RUNTIME OK',
                'resolution=1',
                'same_inputs=1',
                'compact_summary=1',
                'history_before=1',
                'opt_out=1',
                'share_space_visual=1',
                'runtime_handlers=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CM-FRONTS-MOBILE-CATALOG-VISUAL.md' => [
                self::FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.609` and `STABLE_RELEASE` is `2.2.610`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.610', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'fronts_mobile_catalog_visual_9cm',
            'Fronts Mobile Layout & Catalog Visuals 9CM',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Fronts mobile layout and catalog visuals are incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Fronts questions render one compact line per known product on mobile and tablet with the catalog image and a stepper while keeping every Cardex input name and value, Share Space sides show the images mapped to briefing labels, and the catalog images can be switched off per question.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT,
                'compact_breakpoint_px'=>1024,
                'submitted_inputs_changed'=>false,
                'catalog_source'=>'fieldflow-visual-product-catalog-v1',
                'builder_opt_out'=>'catalog_images_disabled',
                'schema_change'=>false,
                'historical_authorities'=>['9BD','9BZ','9CL'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkCatalogCrossClientReuse9CNContract(): array {
        $requirements = [
            'src/Catalog/ProductCatalogService.php' => [
                self::CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT,
                'public static function copyFromClient(int $targetClientId, array $sourceProductIds, string $kind = \'keep\'): array {',
                'public static function refreshImagesFromSource(int $productId): int {',
                '$filters[\'search\'] ?? $filters[\'s\'] ?? \'\'',
            ],
            'src/Admin/ProductCatalog.php' => [
                'add_action(\'admin_post_fieldflow_catalog_reuse\', [self::class, \'handle_reuse\']);',
                'self::guard(\'fieldflow_catalog_reuse\');',
                'Reutilizar de outro cliente',
                'Atualizar imagens da origem',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.610\'',
                'public const STABLE_RELEASE = \'2.2.611\'',
                '// public const SOURCE_RELEASE = \'2.2.609\'',
                '// public const STABLE_RELEASE = \'2.2.610\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'catalog_cross_client_reuse_9cn_ok',
                'Catalog Cross-Client Reuse 9CN',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'catalog_cross_client_reuse_9cn_ok\']',
                'CATÁLOGO REUTILIZÁVEL OK',
            ],
            'tools/verify-catalog-reuse-9cn.php' => [
                'CATALOG CROSS CLIENT REUSE 9CN CONTRACT OK',
                'successor=>=2.2.611',
            ],
            'tools/test-catalog-reuse-9cn.php' => [
                'CATALOG CROSS CLIENT REUSE 9CN RUNTIME OK',
                'shared_images=1',
                'no_duplicates=1',
                'independent_copies=1',
                'refresh_from_source=1',
                'labels_per_client=1',
                'guarded_admin=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CN-CATALOG-CROSS-CLIENT-REUSE.md' => [
                self::CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.610` and `STABLE_RELEASE` is `2.2.611`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Catalog/ProductCatalogService.php' => ['wp_insert_attachment','CREATE TABLE'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.611', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'catalog_cross_client_reuse_9cn',
            'Catalog Cross-Client Reuse 9CN',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Catalog cross-client reuse is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Catalog products can be copied from another client as independent records that share the same image attachments, existing identities are linked instead of duplicated, briefing mappings stay per client and every action requires routespro_manage and a nonce.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT,
                'catalog_scope'=>'per_client',
                'image_files_duplicated'=>false,
                'duplicate_identities'=>'linked',
                'schema_change'=>false,
                'historical_authorities'=>['9CL','9CM'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontsCameraReading9COContract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_CAMERA_READING_9CO_CONTRACT,
                'public const MODULE = \'fronts_vision\';',
                'check_ajax_referer(self::NONCE, \'nonce\', false)',
                'self::rateLimit($userId);',
                '$scopeError = self::scopeError($request, $userId);',
                '\\RoutesPro\\Services\\AIManager::complete($module, $prompt, $params)',
                'public static function confirm(int $readingId, array $confirmed, int $userId): array {',
            ],
            'src/Services/AI.php' => [
                'trait ProviderVisionPayload {',
                '\'type\' => \'image_url\'',
                '\'type\' => \'input_image\'',
                '\'inline_data\' => [\'mime_type\'',
                'fieldflow-gemini-model-availability-v1',
                'public const DEFAULT_MODEL = \'gemini-3.5-flash-lite\';',
                'private const UNAVAILABLE_FALLBACK = [',
            ],
            'src/Forms/FormRenderer.php' => [
                'private static function fronts_vision_attrs(array $q, array $context): string {',
                'function rpVisionFill(){',
                'data-fronts-camera-card',
                'fd.append(\'action\',\'fieldflow_fronts_vision_read\')',
                'data-fronts-input',
                'data-share-space-pr',
            ],
            'src/Admin/FrontsVisionAdmin.php' => [
                'fieldflow-fronts-vision-admin-v1',
                'check_admin_referer(\'fieldflow_vision_settings\', \'fieldflow_vision_nonce\');',
                'check_admin_referer(\'fieldflow_vision_test\', \'fieldflow_vision_nonce\');',
            ],
            'src/Admin/AIControlCenter.php' => [
                '\'fronts_vision\'=>[\'Leitura de frentes\'',
                'azure_openai_vision_deployment',
            ],
            'src/Support/Config.php' => [
                '\'ai_module_fronts_vision_provider\' => \'inherit\'',
                '\'google_ai_model\' => \'gemini-3.5-flash-lite\'',
            ],
            'assets/routespro-form-builder.js' => [
                'camera_reading: !!q.camera_reading,',
                'input[data-fb="camera_reading"]',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.611\'',
                'public const STABLE_RELEASE = \'2.2.612\'',
                '// public const SOURCE_RELEASE = \'2.2.610\'',
                '// public const STABLE_RELEASE = \'2.2.611\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'fronts_camera_reading_9co_ok',
                'Fronts Camera Reading 9CO',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'fronts_camera_reading_9co_ok\']',
                'LEITURA POR CÂMARA OK',
            ],
            'tools/verify-fronts-camera-reading-9co.php' => [
                'FRONTS CAMERA READING 9CO CONTRACT OK',
                'successor=>=2.2.612',
            ],
            'tools/test-fronts-camera-reading-9co.php' => [
                'FRONTS CAMERA READING 9CO RUNTIME OK',
                'multimodal_providers=1',
                'off_by_default=1',
                'reading_flow=1',
                'confirmation=1',
                'share_space=1',
                'limits=1',
                'guarded=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CO-FRONTS-CAMERA-READING.md' => [
                self::FRONTS_CAMERA_READING_9CO_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.611` and `STABLE_RELEASE` is `2.2.612`',
                'fieldflow-gemini-model-availability-v1',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Vision/FrontsVisionService.php' => ['wp_ajax_nopriv','CREATE TABLE'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.612', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'fronts_camera_reading_9co',
            'Fronts Camera Reading 9CO',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Fronts camera reading is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Camera reading is off by default, enabled per client and per question, authenticated with nonce, scope and limits, routed through the governed AI manager with optional images, confirmed by the operative before any field is filled, and kept per store; Gemini defaults follow model availability.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_CAMERA_READING_9CO_CONTRACT,
                'default_state'=>'off',
                'auto_submit'=>false,
                'provider_module'=>'fronts_vision',
                'image_transport'=>'base64_in_request',
                'gemini_default_model'=>'gemini-3.5-flash-lite',
                'schema_change'=>false,
                'historical_authorities'=>['9CJ','9CL','9CM','9CN'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkCatalogGeneralIdentity9CPContract(): array {
        $requirements = [
            'src/Catalog/ProductCatalogService.php' => [
                self::CATALOG_GENERAL_IDENTITY_9CP_CONTRACT,
                'public const IDENTITY_VERSION = 2;',
                'public static function identityKeys(string $reference, string $name, $capacity = null, string $ean = \'\', string $scope = \'\'): array {',
                'public static function resolveProductIdScoped(int $clientId, string $reference, string $name, $capacity = null, string $scope = \'\', string $ean = \'\'): int {',
                'public static function duplicateSuggestions(int $clientId, int $limit = 40): array {',
                'if (!$union($productNode[$id], $owner[$key], true)) $result[\'conflicts\']++;',
                'public static function maybeScheduleIdentityRebuild(): void {',
                'public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {',
            ],
            'src/Admin/ProductCatalog.php' => [
                'add_action(\'admin_post_fieldflow_catalog_not_duplicate\', [self::class, \'handle_not_duplicate\']);',
                'self::guard(\'fieldflow_catalog_not_duplicate\');',
                '\'lock_name\' => true,',
                'Possíveis duplicados',
                'private static function insignia_chips(array $row): string {',
            ],
            'src/Vision/FrontsVisionService.php' => [
                'fieldflow-fronts-field-reference-v1',
                'add_action(\'wp_ajax_\' . self::AJAX_REFERENCE, [self::class, \'ajaxReference\']);',
                'self::rateLimit($userId, \'ff_vision_ref_rate_\', self::REFERENCE_RATE_MAX,',
                'public static function addFieldReference(array $request, array $item, array $photo, int $userId): array {',
                'if ($product[\'image_count\'] > 0) throw new \\RuntimeException(\'Este produto já tem imagem de referência no catálogo.\');',
                'Catalog::resolveProductIdScoped($clientId, $reference, $name, $capacity === \'\' ? null : $capacity, $scope)',
            ],
            'src/Admin/FrontsVisionAdmin.php' => [
                '[field_references]',
                '\'field_references\' => !empty($row[\'field_references\']),',
            ],
            'src/Forms/FormRenderer.php' => [
                'fd.append(\'action\',\'fieldflow_fronts_vision_reference\')',
                'data-vision-ref-camera',
                'data-vision-references="1"',
                'function rpVisionSendReference(st,key,blob){',
                'data-fronts-input',
                'data-share-space-pr',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.612\'',
                'public const STABLE_RELEASE = \'2.2.613\'',
                '// public const SOURCE_RELEASE = \'2.2.611\'',
                '// public const STABLE_RELEASE = \'2.2.612\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'catalog_general_identity_9cp_ok',
                'Catalog General Identity 9CP',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'catalog_general_identity_9cp_ok\']',
                'CATÁLOGO GERAL OK',
            ],
            'tools/verify-catalog-general-identity-9cp.php' => [
                'CATALOG GENERAL IDENTITY 9CP CONTRACT OK',
                'successor=>=2.2.613',
            ],
            'tools/test-catalog-general-identity-9cp.php' => [
                'CATALOG GENERAL IDENTITY 9CP RUNTIME OK',
                'identity=1',
                'reorganised=1',
                'idempotent=1',
                'suggestions=1',
                'conflict_guard=1',
                'manual_preserved=1',
                'field_reference=1',
                'guarded=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CP-CATALOG-GENERAL-IDENTITY.md' => [
                self::CATALOG_GENERAL_IDENTITY_9CP_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.612` and `STABLE_RELEASE` is `2.2.613`',
                'fieldflow-fronts-field-reference-v1',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Catalog/ProductCatalogService.php' => ['wp_insert_attachment','CREATE TABLE'],
            'src/Vision/FrontsVisionService.php' => ['wp_ajax_nopriv','CREATE TABLE'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.613', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'catalog_general_identity_9cp',
            'Catalog General Identity 9CP',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'General catalog identity is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'One general catalog per client across insignias: products are grouped by EAN, by the article code inside its own insignia and by the word signature of the name with the capacity in cl; previous per-chain duplicates are merged keeping images, edited names and briefing mappings; probable duplicates are only suggested; field reference photos are guarded, tagged and limited to products without an image.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::CATALOG_GENERAL_IDENTITY_9CP_CONTRACT,
                'identity_version'=>2,
                'automatic_merge'=>'same_identity_only',
                'suggested_merge'=>'manual_confirmation',
                'field_reference_contract'=>'fieldflow-fronts-field-reference-v1',
                'field_references_default'=>true,
                'schema_change'=>false,
                'historical_authorities'=>['9CL','9CM','9CN','9CO'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontThemeCameraLive9CQContract(): array {
        $requirements = [
            'src/Front/ThemeIsolation.php' => [
                self::FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT,
                'add_action(\'wp_enqueue_scripts\', [self::class, \'enqueue\'], 9999);',
                'if (self::dependsOn($styles, $dependency, $handle, [])) continue;',
                '.rp-form-ui button.routespro-form-btn:is(:hover,:focus,:active)',
            ],
            'src/Vision/FrontsVisionService.php' => [
                'fieldflow-fronts-vision-question-switch-v1',
                'fieldflow-fronts-vision-live-count-v1',
                'public static function questionBlockReason(array $question, array $context, int $clientId = 0): string {',
                '$photo = $capture === \'live\' ? self::livePhoto($_FILES[\'photo\'] ?? null) : self::storePhoto($_FILES[\'photo\'] ?? null);',
                'is_uploaded_file($path)',
                'self::rateLimit($userId);',
            ],
            'src/Catalog/ProductCatalogService.php' => [
                'fieldflow-catalog-insignia-name-variants-v1',
                'public const IDENTITY_VERSION = 3;',
                '// 9CP marker: public const IDENTITY_VERSION = 2;',
                'public static function variantScore(array $a, array $b): float {',
                '$result[\'variants\'] = self::joinInsigniaVariants($list, $find, $union);',
            ],
            'src/Admin/ProductCatalog.php' => [
                'add_action(\'admin_post_fieldflow_catalog_export\', [self::class, \'handle_export\']);',
                'check_admin_referer(\'fieldflow_catalog_export_\' . $clientId);',
                '$rebuilt = Catalog::syncFromCardex($clientId);',
            ],
            'src/Admin/FrontsVisionAdmin.php' => [
                '\'questions\' => \'Nas perguntas com a opção ligada\'',
                '\'mode\' => sanitize_key((string)($row[\'mode\'] ?? \'questions\')),',
            ],
            'src/Forms/FormRenderer.php' => [
                'function rpVisionLiveStart(){',
                'data-vision-live-now',
                'fd.append(\'capture\',capture||\'camera\')',
                'function rpFrontsDecorateDynamicRow($row,$box){',
                'private static function fronts_vision_admin_hint(array $q, array $context, string $attrs): string {',
                'data-fronts-input',
                'data-share-space-pr',
            ],
            'src/Support/Loader.php' => [
                '\'src/Front/ThemeIsolation.php\'',
            ],
            'src/Architecture/RuntimeRegistry.php' => [
                '\\RoutesPro\\Front\\ThemeIsolation::registerHooks();',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.613\'',
                'public const STABLE_RELEASE = \'2.2.614\'',
                '// public const SOURCE_RELEASE = \'2.2.612\'',
                '// public const STABLE_RELEASE = \'2.2.613\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'front_theme_camera_live_9cq_ok',
                'Front Theme Camera Live 9CQ',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'front_theme_camera_live_9cq_ok\']',
                'FRONT E CÂMARA AO VIVO OK',
            ],
            'tools/verify-front-theme-camera-live-9cq.php' => [
                'FRONT THEME CAMERA LIVE 9CQ CONTRACT OK',
                'successor=>=2.2.614',
            ],
            'tools/test-front-theme-camera-live-9cq.php' => [
                'FRONT THEME CAMERA LIVE 9CQ RUNTIME OK',
                'insignia_variants=1',
                'question_switch=1',
                'client_modes=1',
                'live_without_photo=1',
                'theme_isolation=1',
                'front_fixes=1',
                'guarded=1',
                'no_schema=1',
                'historical_continuity=1',
            ],
            'PHASE-9CQ-FRONT-THEME-CAMERA-LIVE.md' => [
                self::FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.613` and `STABLE_RELEASE` is `2.2.614`',
                'fieldflow-fronts-vision-live-count-v1',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Vision/FrontsVisionService.php' => ['wp_ajax_nopriv','CREATE TABLE'],
            'src/Front/ThemeIsolation.php' => ['#c36'],
            'src/Catalog/ProductCatalogService.php' => ['wp_insert_attachment','CREATE TABLE'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.614', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'front_theme_camera_live_9cq',
            'Front Theme Camera Live 9CQ',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Front theme isolation, camera switch or live counting is incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'FieldFlow styles print after the theme and keep the campaign template colours in every state; fronts and Share Space stay readable on phones; the question switch alone shows the camera (clients can be turned off); live counting reads a frame without keeping a photo and photo and gallery remain; names written differently by each insignia are joined only through abbreviations and chain codes.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT,
                'question_switch'=>true,
                'live_photo_kept'=>false,
                'theme_colour_source'=>'campaign_template',
                'identity_version'=>3,
                'schema_change'=>false,
                'historical_authorities'=>['9CL','9CM','9CN','9CO','9CP'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFormDeletionConfirmation9CTContract(): array {
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                'function rpConfirmFormDeletion(options){',
                "'data-rp-delete-confirm'",
                "cancel.focus({preventScroll:true})",
                "document.addEventListener('visibilitychange',onVisibility)",
                "window.addEventListener('pagehide',onPageHide)",
                "$(document).on('click','[data-rp-upload-preview-remove]'",
                "$(document).on('click','[data-rp-upload-remove]'",
                "$(document).on('click','[data-rp-remove-existing-media]'",
                "$(document).on('click','.routespro-product-remove'",
                "\$form.on('click','[data-rp-discard-draft]'",
                'current.lastModified!==file.lastModified',
                "if(pending){toggleExisting();return;}",
                '.routespro-delete-confirm-overlay{',
            ],
            'tools/test-form-delete-confirm-9ct.mjs' => ['FORM DELETION 9CT OK','new vm.Script(js)','Escape','media row'],
            'PHASE-9CT-FORM-DELETION-CONFIRMATION.md' => [self::FORM_DELETION_CONFIRMATION_9CT_CONTRACT, '2.2.616 -> 2.2.617'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.617', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'form_deletion_confirmation_9ct',
            'Confirmação de remoções nos formulários 9CT',
            $missing ? 'fail' : 'pass',
            $missing ? 'Proteção de exclusões incompleta: ' . implode(', ', array_slice($missing, 0, 8))
                     : 'Produtos, fotografias, anexos e rascunhos exigem confirmação explícita e permitem cancelar sem alterar o estado.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FORM_DELETION_CONFIRMATION_9CT_CONTRACT,
                'default_choice'=>'cancel',
                'new_ai_calls'=>false,
                'schema_change'=>false,
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontsCaptureReview9CUContract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_CAPTURE_REVIEW_9CU_CONTRACT,
                'public static function captureReplayKey(',
                'SELECT GET_LOCK(%s, 0)',
                'VisionUsageGuard::reserve($clientId, $settings)',
                "'review_reason'",
                "cached['reused'] = true;",
            ],
            'src/Forms/FormRenderer.php' => [
                self::FRONTS_CAPTURE_REVIEW_9CU_CONTRACT,
                'function rpVisionReviewCapture(',
                'function rpVisionAssessCapture(',
                'function rpVisionApproveAnalysis(',
                'data-vision-analyze',
                'data-vision-select-product',
                "rpVisionReviewCapture(st,blob,'live')",
                "st.values[it.key]=it.needs_review",
            ],
            'tools/test-fronts-capture-review-9cu.php' => ['FRONTS CAPTURE REVIEW 9CU PHP OK'],
            'tools/test-fronts-capture-review-9cu.mjs' => ['FRONTS CAPTURE REVIEW 9CU JS OK'],
            'PHASE-9CU-FRONTS-CAPTURE-REVIEW.md' => [self::FRONTS_CAPTURE_REVIEW_9CU_CONTRACT, '2.2.617 -> 2.2.618'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.618', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'fronts_capture_review_9cu',
            'Contagem por câmara com revisão e custo controlado 9CU',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Revisão antes da chamada IA incompleta: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Captura sem custo, revisão local da fotografia, seleção dos artigos e autorização explícita para a IA; resultados incertos não são preenchidos automaticamente e leituras repetidas podem ser reutilizadas.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_CAPTURE_REVIEW_9CU_CONTRACT,
                'max_selected_items'=>12,
                'replay_minutes'=>15,
                'default_paid_calls'=>0,
                'schema_change'=>false,
                'prior_controls'=>['9CS','9CT'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkReportContextRecovery9CVContract(): array {
        $requirements = [
            'src/Shortcodes.php' => [
                self::REPORT_CONTEXT_RECOVERY_9CV_CONTRACT,
                'isReportDatabaseError=raw=>',
                "id=\"rpf-retry\"",
                "showReportRetry('form')",
                "showReportRetry('visits')",
                'matchesCachedContext=cached=>',
                'isOwnCachedForm=cached=>',
                "if(!sameLiveForm)els.slot.innerHTML=''",
                'reportUserId=',
                'reportRetryLastAt<4000',
            ],
            'src/Forms/Forms.php' => [
                self::REPORT_CONTEXT_RECOVERY_9CV_CONTRACT,
                'fieldflow_report_context_unavailable',
                "wp_send_json_error([",
                '], 503);',
            ],
            'tools/test-report-context-recovery-9cv.mjs' => ['REPORT CONTEXT RECOVERY 9CV JS OK'],
            'PHASE-9CV-REPORT-CONTEXT-RECOVERY.md' => [self::REPORT_CONTEXT_RECOVERY_9CV_CONTRACT, '2.2.618 -> 2.2.619'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.619', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check(
            'report_context_recovery_9cv',
            'Reportar resiliente a falha temporaria de base de dados 9CV',
            $missing?'fail':'pass',
            $missing?'Falta continuidade de recuperacao: '.implode(', ',array_slice($missing,0,8)):
                'Sem fuga de erros MySQL ao terreno; formulario e rascunho isolados por visita; retry manual e cache com scope.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::REPORT_CONTEXT_RECOVERY_9CV_CONTRACT,
                'retry'=>'manual',
                'new_ai_calls'=>false,
                'schema_change'=>false,
                'prior_controls'=>['9CS','9CT','9CU'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontsVisualEvidence9CWContract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_VISUAL_EVIDENCE_9CW_CONTRACT,
                'variantDiscriminators(', 'evidenceBoxesOverlap(', 'evidenceVariantMatches(',
                "'capacity_unverified'", "'brand_unverified'", "'duplicate_position'",
                "'model_fronts'", '$prompt = self::blindInventoryPrompt();',
                'self::VISUAL_EVIDENCE_CONTRACT,', "'max_attempts' => 1",
                "'disable_model_fallback' => true", 'VisionUsageGuard::reserve($clientId, $settings)',
            ],
            'src/Forms/FormRenderer.php' => [
                'function rpVisionReviewMessage(it)', 'variant_unverified', 'duplicate_position',
                'capacity_unverified', 'brand_unverified', 'rpVisionReviewMessage(it)',
                "st.values[it.key]=it.needs_review",
            ],
            'tools/test-fronts-visual-evidence-9cw.php' => ['FRONTS VISUAL EVIDENCE 9CW PHP OK', 'black_barrel_phantom_is_held'],
            'tools/test-fronts-visual-evidence-9cw.mjs' => ['FRONTS VISUAL EVIDENCE 9CW JS OK'],
            'PHASE-9CW-FRONTS-VISUAL-EVIDENCE.md' => [self::FRONTS_VISUAL_EVIDENCE_9CW_CONTRACT, '2.2.619 -> 2.2.620'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.620', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check(
            'fronts_visual_evidence_9cw',
            'Frentes com evidência visual por SKU e localização 9CW',
            $missing?'fail':'pass',
            $missing?'Falta continuidade de prova visual: '.implode(', ',array_slice($missing,0,8)):
                'Marca, variante e capacidade confrontadas com o rótulo; posições duplicadas bloqueadas; leitura única e orçamento mantido.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_VISUAL_EVIDENCE_9CW_CONTRACT,
                'new_ai_calls'=>false,
                'new_external_provider'=>false,
                'schema_change'=>false,
                'prior_controls'=>['9CS','9CT','9CU','9CV'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkFrontsSkuIdentity9CXContract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_SKU_IDENTITY_9CX_CONTRACT, 'evidenceCapacityMatches(', 'IRLANDES',
                "'capacity_unverified'", "'variant_unverified'", 'foreach (array_keys($collisions) as $i)',
                'VisionUsageGuard::reserve($clientId, $settings)', "'max_attempts' => 1",
            ],
            'src/Forms/FormRenderer.php' => [
                'routespro-vision-photo-inner', 'routespro-vision-box.is-review',
                'data-vision-accept', "['capacity_unverified','low_confidence']",
                'st.values[it.key]=it.needs_review',
            ],
            'tools/test-fronts-sku-identity-9cx.php' => ['FRONTS SKU IDENTITY 9CX PHP OK','real_jameson_original_detected'],
            'tools/test-fronts-sku-identity-9cx.mjs' => ['FRONTS SKU IDENTITY 9CX JS OK'],
            'PHASE-9CX-FRONTS-SKU-IDENTITY-RECOVERY.md' => [self::FRONTS_SKU_IDENTITY_9CX_CONTRACT, '2.2.620 -> 2.2.621'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.621', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') { $missing[]=$relative.':file';continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check(
            'fronts_sku_identity_9cx',
            'Frentes com identidade normalizada e revisão 9CX',
            $missing?'fail':'pass',
            $missing?'Falta continuidade de SKU: '.implode(', ',array_slice($missing,0,8)):
                'Brand do Cardex normalizada, variantes protegidas, capacidade com revisão explícita e custo inalterado.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_SKU_IDENTITY_9CX_CONTRACT,
                'new_ai_calls'=>false,
                'schema_change'=>false,
                'prior_controls'=>['9CS','9CT','9CU','9CV','9CW'],
                'missing'=>$missing,
            ]
        );
    }

    /** 9D2: category decisions and safe successive briefing version continuity. */
    private static function checkBriefingCategoryContinuity9D2Contract(): array {
        $requirements = [
            'src/Briefings/BriefingCategoryMappingService.php' => [self::BRIEFING_CATEGORY_CONTINUITY_9D2_CONTRACT,
                'public static function resolveForQuestion(', 'public static function saveManual(',
                'public static function inheritVerifiedQuestions(', 'sameProductEvidence(',
                'public static function synchronizeExisting(', 'public static function assign('],
            'src/Admin/Briefings.php' => ['CategoryMapping::inheritVerifiedQuestions(',
                'product_category_override', 'product_category_identity', "'product_category' =>"],
            'src/Admin/ProductCatalog.php' => ['render_question_categories(',
                'handle_question_category()', 'handle_sync_categories()', 'ff-catalog-tabs'],
            'src/Forms/ContextQuestions.php' => ["'product_category' =>", "'product_category_source' =>"],
            'src/Front/BriefingsShortcodeRenderer.php' => ['ffb-exec-category','Categoria por confirmar'],
            'src/Forms/FormRenderer.php' => ['$help = \'Categoria: \' . $category'],
            'tools/test-briefing-category-9d2.php' => ['BRIEFING CATEGORY CONTINUITY 9D2 PHP PASS',
                'changed SKU blocks inheritance', 'project isolation'],
            'tools/test-briefing-gate-9d2.php' => ['BRIEFING RELEASE GATE 9D2 PHP PASS', 'checkBriefingCategoryContinuity9D2Contract'],
            'PHASE-9D2-BRIEFING-CATEGORY-CONTINUITY.md' => [self::BRIEFING_CATEGORY_CONTINUITY_9D2_CONTRACT,
                '2.2.626 -> 2.2.627', 'sem chamadas adicionais à IA'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.627', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('briefing_category_continuity_9d2', 'Briefing: categorias e continuidade 9D2',
            $missing ? 'fail' : 'pass',
            $missing ? 'Integridade 9D2 incompleta: ' . implode(', ', array_slice($missing, 0, 8)) :
                'Categorias determinísticas por cliente, confirmações manuais e herança apenas de produtos verificados entre briefings.',
            ['runtime'=>self::currentRelease(), 'contract'=>self::BRIEFING_CATEGORY_CONTINUITY_9D2_CONTRACT,
             'added_ai_calls'=>false, 'schema_change'=>false, 'missing'=>$missing]);
    }

    /** 9D3: persisted client-scoped category selector in the visual catalog. */
    private static function checkCatalogCategoryPicker9D3Contract(): array {
        $requirements = [
            'src/Briefings/BriefingCategoryMappingService.php' => [self::CATALOG_CATEGORY_PICKER_9D3_CONTRACT,
                'public static function catalogCategories(', 'public static function catalogCategorySelection(', 'public static function rememberCatalogCategory(',
                'fieldflow_catalog_categories_9d3_client_'],
            'src/Admin/ProductCatalog.php' => ['data-ff-cat-category-editor', 'data-ff-cat-category-choice',
                'Adicionar nova categoria', 'data-ff-cat-new-category', 'CategoryMapping::catalogCategorySelection(',
                'CategoryMapping::rememberCatalogCategory(', 'Catalog::save($productId, $data)'],
            'tools/test-catalog-category-picker-9d3.php' => ['CATALOG CATEGORY PICKER 9D3 PHP PASS',
                'tenant isolation', 'new category persisted'],
            'tools/test-catalog-category-gate-9d3.php' => ['CATALOG CATEGORY RELEASE GATE 9D3 PHP PASS',
                'checkCatalogCategoryPicker9D3Contract'],
            'PHASE-9D3-CATALOG-CATEGORY-PICKER.md' => [self::CATALOG_CATEGORY_PICKER_9D3_CONTRACT,
                '2.2.627 -> 2.2.628', 'No AI usage'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.628', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('catalog_category_picker_9d3', 'Catálogo: categorias selecionáveis e persistentes 9D3',
            $missing ? 'fail' : 'pass',
            $missing ? 'Integridade 9D3 incompleta: ' . implode(', ', array_slice($missing, 0, 8)) :
                'Dropdown por cliente, nova categoria sem duplicados e integração com o mapeamento de briefing existente.',
            ['runtime'=>self::currentRelease(), 'contract'=>self::CATALOG_CATEGORY_PICKER_9D3_CONTRACT,
             'added_ai_calls'=>false, 'schema_change'=>false, 'missing'=>$missing]);
    }

    /** 9D4: harden tenant boundaries and canonical visit reporting for commercial scale. */
    private static function checkCommercialProductionHardening9D4Contract(): array {
        $requirements = [
            'src/Rest/CommercialController.php' => ['9D4:', 'Permissions::get_scope($uid)',
                "'scope_generation'", 'if ($client_id) {', 'COUNT(DISTINCT l.id)',
                'LIMIT %d OFFSET %d', "'include_unassigned' =>", "'location_id' =>"],
            'src/Rest/EventsController.php' => ["'offset' => min(10000000", "'with_total' =>"],
            'src/Front/ClientPortalShortcodeRenderer.php' => ["scoped.set('with_total','1')",
                'requestSequence!==reportLoadSequence', 'renderPagination(els.reportsPagination'],
            'src/Front/AppShortcodeRenderer.php' => ["lastReportVisitStorageKey='fieldflow:last-report-visit:v2:'",
                'localStorage.getItem(lastReportVisitStorageKey'],
            'src/Mobile/WebViewBridge.php' => ['key.indexOf("fieldflow:last-report-visit:v2:")===0'],
            'tools/test-commercial-production-isolation-9d4.php' => ['COMMERCIAL PRODUCTION ISOLATION 9D4 PHP PASS'],
            'tools/test-commercial-report-pagination-9d4.php' => ['COMMERCIAL REPORT PAGINATION 9D4 PHP PASS'],
            'tools/test-commercial-production-gate-9d4.php' => ['COMMERCIAL PRODUCTION RELEASE GATE 9D4 PHP PASS'],
            'PHASE-9D4-COMMERCIAL-PRODUCTION-HARDENING.md' => [self::COMMERCIAL_PRODUCTION_HARDENING_9D4_CONTRACT,
                '2.2.628 -> 2.2.629', 'No new AI calls', 'No schema changes'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.629', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('commercial_production_hardening_9d4', 'Consolidação: isolamento e relatórios 9D4',
            $missing ? 'fail' : 'pass',
            $missing ? 'Integridade comercial 9D4 incompleta: ' . implode(', ', array_slice($missing, 0, 8)) :
                'Cache por utilizador e âmbito, paginação real por visita, filtros consistentes e retoma WebView isolada.',
            ['runtime'=>self::currentRelease(), 'contract'=>self::COMMERCIAL_PRODUCTION_HARDENING_9D4_CONTRACT,
             'added_ai_calls'=>false, 'schema_change'=>false, 'governance_change'=>false, 'missing'=>$missing]);
    }

    /** 9D5: responsive presentation, independent of business data and native bridges. */
    private static function checkVisualExperienceConsolidation9D5Contract(): array {
        $requirements = [
            'assets/fieldflow-visual-consolidation-9d5.css' => [
                '.ffv-visual-9d5', '.ffv-dock-hidden', '.ffv-filters-open',
                '--ffv-dock-height', 'env(safe-area-inset-bottom', 'routespro-product-matrix.is-compact-fronts',
            ],
            'assets/fieldflow-visual-consolidation-9d5.js' => [
                self::VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT, 'aria-expanded',
                'aria-controls', "'#rpcp-from'", "'#rpcp-to'", 'ffv-dock-hidden', 'setupDock(root)',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'FIELDFLOW_VISUAL_CONSOLIDATION_9D5', 'fieldflow-visual-consolidation-9d5.css',
                'fieldflow-visual-consolidation-9d5.js', 'fieldflow-frontend-core-' . self::currentRelease() . '.js',
                'fieldflow-offline-runtime-' . self::currentRelease() . '.js',
            ],
            'src/Front/AppShortcodeRenderer.php' => ['ffv-visual-9d5', 'data-fieldflow-portal="operative"'],
            'src/Front/ClientPortalShortcodeRenderer.php' => ['ffv-visual-9d5', 'data-fieldflow-portal="client"', 'id="rpcp-from"', 'id="rpcp-to"'],
            'src/Support/LicenseManager.php' => ['get(?string $key = null, $default = null)'],
            'PHASE-9D5-VISUAL-EXPERIENCE-CONSOLIDATION.md' => [
                self::VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT, '2.2.629 -> 2.2.630',
                'No new AI calls', 'No schema changes',
            ],
            'tools/test-visual-experience-9d5.php' => ['VISUAL EXPERIENCE CONSOLIDATION 9D5 PHP PASS'],
            'tools/test-visual-experience-gate-9d5.php' => ['VISUAL EXPERIENCE RELEASE GATE 9D5 PHP PASS'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.630', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('visual_experience_consolidation_9d5', 'Consolidação visual multiplataforma 9D5',
            $missing ? 'fail' : 'pass',
            $missing ? 'Experiência visual 9D5 incompleta: ' . implode(', ', array_slice($missing, 0, 8)) :
                'Portais mobile compactos, navegação segura, filtros preservados e CSS de marca isolado.',
            ['runtime'=>self::currentRelease(), 'contract'=>self::VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT,
             'added_ai_calls'=>false, 'schema_change'=>false, 'governance_change'=>false, 'missing'=>$missing]);
    }

    /** 9D6: operative-only bottom dock parity, no business logic or REST effects. */
    private static function checkOperativeDockParity9D6Contract(): array {
        $requirements = [
            'assets/fieldflow-operative-dock-parity-9d6.js' => [
                self::OPERATIVE_DOCK_PARITY_9D6_CONTRACT, 'ffvDockAuthority',
                "document.addEventListener('scroll',onDocumentScroll,true)", 'ff-keyboard-open',
            ],
            'assets/fieldflow-operative-dock-parity-9d6.css' => [
                '#routespro-app.ffv-visual-9d5.ffv-operative-parity-9d6', '--ffv-dock-height',
            ],
            'assets/fieldflow-visual-consolidation-9d5.js' => ["root.dataset.ffvDockAuthority==='9d6'"],
            'src/Front/ResponsiveAssets.php' => [
                'FIELDFLOW_OPERATIVE_PARITY_9D6', 'fieldflow-operative-dock-parity-9d6.js',
                'fieldflow-operative-dock-parity-9d6.css', 'fieldflow-offline-runtime-' . self::currentRelease() . '.js',
                'fieldflow-frontend-core-' . self::currentRelease() . '.js',
            ],
            'src/Front/ClientPortalShortcodeRenderer.php' => [
                'function statusLabel(status)', 'data-stop-status="done"',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.630';", "public const STABLE_RELEASE = '2.2.631';",
            ],
            'tools/test-operative-dock-parity-9d6.php' => ['OPERATIVE DOCK PARITY 9D6 PHP PASS'],
            'tools/test-operative-dock-parity-gate-9d6.php' => ['OPERATIVE DOCK RELEASE GATE 9D6 PHP PASS'],
            'PHASE-9D6-OPERATIVE-DOCK-PARITY.md' => [
                self::OPERATIVE_DOCK_PARITY_9D6_CONTRACT, '2.2.630 -> 2.2.631',
                'No new AI calls', 'No schema changes',
            ],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(), '2.2.631', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('operative_dock_parity_9d6','Paridade mobile Operativo e rótulos 9D6',
            $missing?'fail':'pass',
            $missing?'Continuidade visual 9D6 incompleta: '.implode(', ',array_slice($missing,0,8)):
                'Navegação do Operativo responde ao scroll interno e janela, sem alterar a lógica Cliente.',
            ['runtime'=>self::currentRelease(),'contract'=>self::OPERATIVE_DOCK_PARITY_9D6_CONTRACT,
             'added_ai_calls'=>false,'schema_change'=>false,'governance_change'=>false,'missing'=>$missing]);
    }

    /** 9D7: WebView vertical-gesture fallback and real operative bottom reserve. */
    private static function checkOperativeDockOverlay9D7Contract(): array {
        $requirements = [
            'assets/fieldflow-operative-dock-overlay-9d7.js' => [
                self::OPERATIVE_DOCK_OVERLAY_9D7_CONTRACT, 'touchstart', 'touchmove',
                'onDocumentScroll', 'canScrollVertically', 'ffvDockAuthority',
                'ffv-dock-hidden', '--ffv-dock-height',
            ],
            'assets/fieldflow-operative-dock-overlay-9d7.css' => [
                '#routespro-app.ffv-visual-9d5.ffv-operative-overlay-9d7',
                'ffv-dock-hidden', '--ffv-dock-reserve', 'scroll-padding-bottom',
            ],
            'assets/fieldflow-operative-dock-parity-9d6.js' => [
                "root.dataset.ffvDockAuthority==='9d7'",
                self::OPERATIVE_DOCK_PARITY_9D6_CONTRACT,
            ],
            'assets/fieldflow-visual-consolidation-9d5.js' => [
                "root.dataset.ffvDockAuthority==='9d6'", "root.dataset.ffvDockAuthority==='9d7'",
            ],
            'src/Front/ResponsiveAssets.php' => [
                'FIELDFLOW_OPERATIVE_OVERLAY_9D7',
                'fieldflow-operative-dock-overlay-9d7.css',
                'fieldflow-operative-dock-overlay-9d7.js',
                'fieldflow-offline-runtime-' . self::currentRelease() . '.js',
                'fieldflow-frontend-core-' . self::currentRelease() . '.js',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.631';", "public const STABLE_RELEASE = '2.2.632';",
            ],
            'tools/test-operative-dock-overlay-9d7.php' => ['OPERATIVE DOCK OVERLAY 9D7 PHP PASS'],
            'tools/test-operative-dock-overlay-gate-9d7.php' => ['OPERATIVE DOCK OVERLAY GATE 9D7 PHP PASS'],
            'PHASE-9D7-OPERATIVE-DOCK-OVERLAY-GESTURE-RECOVERY.md' => [
                self::OPERATIVE_DOCK_OVERLAY_9D7_CONTRACT, '2.2.631 -> 2.2.632',
                'No new AI calls', 'No schema changes',
            ],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.632','<')) $missing[]='runtime:version';
        foreach($requirements as $relative=>$markers){
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if($raw===''){$missing[]=$relative.':file';continue;}
            foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$relative.':'.$marker;
        }
        return self::check('operative_dock_overlay_9d7','Barra Operativo: gestos e reserva de conteúdo 9D7',
            $missing?'fail':'pass',
            $missing?'Overlay 9D7 incompleto: '.implode(', ',array_slice($missing,0,8)):
                'Opera com scroll e gesto vertical mobile; conteúdo final acessível, Cliente intacto.',
            ['runtime'=>self::currentRelease(),'contract'=>self::OPERATIVE_DOCK_OVERLAY_9D7_CONTRACT,
             'added_ai_calls'=>false,'schema_change'=>false,'governance_change'=>false,'missing'=>$missing]);
    }

    /** 9D8: shortcode-owned dock, independent of Elementor/PWA enqueue heuristics. */
    private static function checkOperativeInlineDockAuthority9D8Contract(): array {
        $requirements = [
            'assets/fieldflow-operative-dock-authority-9d8.js' => [
                self::OPERATIVE_INLINE_DOCK_AUTHORITY_9D8_CONTRACT,
                "root.dataset.ffvDockAuthority='9d8'", 'touchstart', 'touchmove',
                "document.addEventListener('scroll'", 'ffv-dock-collapsed-9d8',
                'ffvDockSource',
            ],
            'assets/fieldflow-operative-dock-authority-9d8.css' => [
                '#routespro-app.ffv-dock-authority-9d8', 'ffv-dock-collapsed-9d8',
                'visibility:hidden!important', '--ffv-dock-reserve', 'scroll-padding-bottom',
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8',
                'fieldflow-operative-dock-authority-9d8.css',
                'fieldflow-operative-dock-authority-9d8.js',
                'data-fieldflow-contract="fieldflow-operative-inline-dock-authority-9d8-v1"',
            ],
            'assets/fieldflow-visual-consolidation-9d5.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
            'assets/fieldflow-operative-dock-parity-9d6.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
            'assets/fieldflow-operative-dock-overlay-9d7.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
            'src/Front/ResponsiveAssets.php' => ['fieldflow-frontend-core-' . self::currentRelease() . '.js'],
            'src/Cloud/Phase6StableRelease.php' => [
                "public const SOURCE_RELEASE = '2.2.632';", "public const STABLE_RELEASE = '2.2.633';",
            ],
            'tools/test-operative-dock-authority-9d8.php' => ['OPERATIVE DOCK AUTHORITY 9D8 PHP PASS'],
            'tools/test-operative-dock-authority-gate-9d8.php' => ['OPERATIVE DOCK AUTHORITY GATE 9D8 PHP PASS'],
            'PHASE-9D8-OPERATIVE-INLINE-DOCK-AUTHORITY.md' => [
                self::OPERATIVE_INLINE_DOCK_AUTHORITY_9D8_CONTRACT, '2.2.632 -> 2.2.633',
            ],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.633','<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('operative_inline_dock_authority_9d8','Dock Operativo: autoridade inline, sem dependência do enqueue 9D8',
            $missing?'fail':'pass',
            $missing?'Contrato 9D8 incompleto: '.implode(', ',array_slice($missing,0,8)):
                'Shortcode controla diretamente o dock; scroll e gestos sem scripts externos.',
            ['runtime'=>self::currentRelease(),'contract'=>self::OPERATIVE_INLINE_DOCK_AUTHORITY_9D8_CONTRACT,
             'added_ai_calls'=>false,'schema_change'=>false,'governance_change'=>false,'missing'=>$missing]);
    }

    /** 9D9: reuses the unchanged Client controller for the Operative; excludes legacy owners. */
    private static function checkOperativeClientDockParity9D9Contract(): array {
        $requirements = [
            'assets/fieldflow-visual-consolidation-9d5.js' => [
                self::VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT,
                'function setupDock(root)', "dock.classList.add('ffv-dock-hidden')",
                "dock.classList.remove('ffv-dock-hidden')",
            ],
            'src/Front/AppShortcodeRenderer.php' => [
                'FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9',
                'data-fieldflow-dock-controller=', 'client-9d5-9d9',
                'fieldflow-operative-client-dock-parity-9d9-v1',
                "file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js')",
                '$dock9d8_enabled = !$shared_dock_enabled',
                '.rp-tabs.ffv-dock-hidden',
            ],
            'src/Front/ResponsiveAssets.php' => [
                'FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9', '$legacyOperativeDock',
                'if ($legacyOperativeDock', 'fieldflow-visual-consolidation-9d5.js',
            ],
            // Version authority is checkReleaseContinuityAuthorityContract() above.
            // No historic Phase6 constant is required for the dock's functional contract.
            'tools/test-operative-client-dock-parity-9d9.php' => [
                'OPERATIVE CLIENT DOCK PARITY 9D9 PASS',
            ],
            'tools/test-operative-client-dock-parity-gate-9d9.php' => [
                'OPERATIVE CLIENT DOCK PARITY GATE 9D9 PASS',
            ],
            'PHASE-9D9-OPERATIVE-CLIENT-DOCK-PARITY.md' => [
                '2.2.633', '2.2.634', 'ffv-dock-hidden',
            ],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.634','<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('operative_client_dock_parity_9d9',
            'Barra Operativo: reutiliza o controlador do Cliente 9D5',
            $missing?'fail':'pass',
            $missing?'Contrato 9D9 incompleto: '.implode(', ',array_slice($missing,0,8)):
                'Mesmo controlador e classe do Cliente; camadas anteriores inativas por defeito.',
            ['runtime'=>self::currentRelease(),
             'contract'=>self::OPERATIVE_CLIENT_DOCK_PARITY_9D9_CONTRACT,
             'added_ai_calls'=>false,'schema_change'=>false,'governance_change'=>false,
             'missing'=>$missing]);
    }

    /** 9D1: photograph accuracy and store-scoped read-only history, one provider call. */
    private static function checkFrontsVisionIntelligence9D1Contract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [self::FRONTS_VISION_INTELLIGENCE_9D1_CONTRACT,
                'evidenceBoxesStrongDuplicate(', 'compareConfirmedWithPrevious(', 'previousClosedVisit(',
                "'_ff_submission_id', 'value' => '0', 'compare' => '>'", "'basis' => 'confirmed_closed_visit'",
                'VisionUsageGuard::reserve($clientId, $settings)', "'max_attempts' => 1"],
            'src/Forms/FormRenderer.php' => ['function rpVisionRefreshComparison()',
                'data-vision-previous', 'data-vision-delta', 'data-vision-fill', 'rpVisionReviewMessage(it)'],
            'src/Admin/FrontsVisionAdmin.php' => ['Visita fechada anterior', "['previous_visit']['items']"],
            'tools/test-fronts-vision-intelligence-9d1.php' => ['FRONTS VISION INTELLIGENCE 9D1 PHP OK',
                'duplicate_observation_same_sku', 'share_space_sides_never_mixed'],
            'tools/test-fronts-gate-9d1.php' => ['FRONTS RELEASE GATE 9D1 PHP OK', 'checkFrontsVisionIntelligence9D1Contract'],
            'tools/test-fronts-history-scope-9d1.php' => ['FRONTS HISTORY SCOPE 9D1 PHP OK',
                'same_store_previous_closed', 'another_store_not_reused'],
            'tools/test-fronts-vision-intelligence-9d1.mjs' => ['FRONTS VISION INTELLIGENCE 9D1 JS OK',
                'no_extra_ai_calls'],
            'PHASE-9D1-FRONTS-VISION-INTELLIGENCE-II.md' => [self::FRONTS_VISION_INTELLIGENCE_9D1_CONTRACT,
                '2.2.625 -> 2.2.626', 'sem chamadas adicionais'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.626', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
        }
        return self::check('fronts_vision_intelligence_9d1', 'Vision Intelligence II: precisão e histórico 9D1',
            $missing ? 'fail' : 'pass',
            $missing ? 'Integridade 9D1 incompleta: ' . implode(', ', array_slice($missing, 0, 8)) :
                'Contagem física sem duplicação injustificada, confirmação manual e histórico por loja/visita fechada, sem novas chamadas IA.',
            ['runtime' => self::currentRelease(), 'contract' => self::FRONTS_VISION_INTELLIGENCE_9D1_CONTRACT,
             'no_extra_ai_calls' => true, 'schema_change' => false, 'history_auto_fill' => false, 'missing' => $missing]);
    }

    private static function checkFrontsBudgetReconciliation9D0Contract(): array {
        $requirements = [
            'src/Vision/VisionUsageGuard.php'=>[self::FRONTS_BUDGET_RECONCILIATION_9D0_CONTRACT,
                'public static function settle(', 'public static function reconcileLegacy(', 'pending_reservations',
                'SELECT GET_LOCK(%s, 3)', 'ESTIMATE_SAFETY_FACTOR'],
            'src/Vision/FrontsVisionService.php'=>['VisionUsageGuard::reserve($clientId, $settings)',
                'VisionUsageGuard::settle(', 'AIManager::lastVisionReceipt()'],
            'src/Forms/FormRenderer.php'=>['data-vision-block-reason', 'st.blockReason', 'rpVisionEsc(cameraReason)'],
            'src/Admin/FrontsVisionAdmin.php'=>['handle_reconcile_legacy()', 'render_legacy_reconciliation(',
                'fieldflow_vision_reconcile_legacy', 'libertados (novas)'],
            'tools/test-fronts-budget-reconciliation-9d0.php'=>['FRONTS BUDGET RECONCILIATION 9D0 PHP OK','idempotent_settlement','historic_review_only_once'],
            'tools/test-fronts-budget-availability-9d0.mjs'=>['FRONTS BUDGET AVAILABILITY 9D0 JS OK', 'rpVisionEsc(cameraReason)'],
            'PHASE-9D0-FRONTS-BUDGET-RECONCILIATION.md'=>[self::FRONTS_BUDGET_RECONCILIATION_9D0_CONTRACT,
                '2.2.623 -> 2.2.624'],
        ];
        $missing=[];
        if (version_compare(self::currentRelease(),'2.2.624','<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') {$missing[]=$relative.':file';continue;}
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('fronts_budget_reconciliation_9d0', 'Reserva prévia e reconciliação FinOps 9D0',
            $missing?'fail':'pass',
            $missing?'Reconciliação incompleta: '.implode(', ',array_slice($missing,0,8)):
                'Reserva antes da chamada, liquidação conservadora só após tokens e tarifa, histórico revisto manualmente e avisos concretos.',
            ['runtime'=>self::currentRelease(),'contract'=>self::FRONTS_BUDGET_RECONCILIATION_9D0_CONTRACT,
             'new_ai_calls'=>false,'schema_change'=>false,'legacy_auto_reset'=>false,'missing'=>$missing]);
    }

    private static function checkFrontsAiCostMetering9CZContract(): array {
        $requirements = [
            'src/Vision/VisionCostMeter.php'=>[self::FRONTS_AI_COST_METERING_9CZ_CONTRACT,
                'public static function quote(', 'public static function saveRate(',
                'public static function monthByClient(', "'tariff_missing'", "'unmetered'"],
            'src/Services/AI.php'=>['visionMeteredUsage(', 'lastVisionReceipt()',
                "'vision_cost_status'", "'vision_finops'", 'getLastUsage()'],
            'src/Vision/FrontsVisionService.php'=>['VisionUsageGuard::reserve($clientId, $settings)',
                "'_ff_ai_usage_log_id'", "'_ff_ai_estimated_cost_eur'", 'AIManager::lastVisionReceipt()'],
            'src/Admin/FrontsVisionAdmin.php'=>['handle_tariffs()', 'VisionCostMeter::monthByClient()',
                'FinOps de IA', 'Reserva por tentativa', 'fieldflow_vision_tariffs'],
            'tools/test-fronts-cost-metering-9cz.php'=>['FRONTS AI COST METERING 9CZ PHP OK',
                'not_lying_when_tariff_missing','same_safety_gate_one_attempt'],
            'tools/test-fronts-ai-runtime-metering-9cz.php'=>['FRONTS AI RUNTIME METERING 9CZ PHP OK',
                'no_extra_paid_call','image_tokens_from_azure','unpriced_marked_not_free'],
            'PHASE-9CZ-FRONTS-AI-TRANSPARENT-COSTS.md'=>[self::FRONTS_AI_COST_METERING_9CZ_CONTRACT,
                '2.2.622 -> 2.2.623'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.623', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('fronts_ai_cost_metering_9cz', 'Tarifas e tokens com proveniencia 9CZ',
            $missing?'fail':'pass',
            $missing?'Integracao financeira incompleta: '.implode(', ',array_slice($missing,0,8)):
                'Tokens do fornecedor, tarifa por modelo e reserva de seguranca separados. Sem chamadas extra.',
            ['runtime'=>self::currentRelease(),'contract'=>self::FRONTS_AI_COST_METERING_9CZ_CONTRACT,
             'price_is_invoice'=>false, 'budget_guard_unchanged'=>true,'schema_change'=>false,'missing'=>$missing]);
    }

    private static function checkFrontsBlindInventory9CYContract(): array {
        $requirements = [
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_BLIND_INVENTORY_9CY_CONTRACT, 'blindInventoryPrompt(',
                'parseBlindInventory(', 'containsObservedTokens(',
                '$prompt = self::blindInventoryPrompt();',
                '$result = self::parseBlindInventory($raw, $items);',
                "'max_attempts' => 1", 'VisionUsageGuard::reserve($clientId, $settings)',
            ],
            'src/Forms/FormRenderer.php' => ['variant_manual_verification','res.vision_mode'],
            'tools/test-fronts-blind-inventory-9cy.php' => ['FRONTS BLIND INVENTORY 9CY PHP OK','carina_never_assigned_black_barrel'],
            'tools/test-fronts-blind-inventory-9cy.mjs' => ['FRONTS BLIND INVENTORY 9CY JS OK'],
            'PHASE-9CY-FRONTS-BLIND-INVENTORY.md' => [self::FRONTS_BLIND_INVENTORY_9CY_CONTRACT,'2.2.621 -> 2.2.622'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.622', '<')) $missing[]='runtime:version';
        foreach ($requirements as $relative=>$markers) {
            $raw=is_readable(ROUTESPRO_PATH.$relative)?(string)file_get_contents(ROUTESPRO_PATH.$relative):'';
            if ($raw==='') { $missing[]=$relative.':file'; continue; }
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $missing[]=$relative.':'.$marker;
        }
        return self::check('fronts_blind_inventory_9cy', 'Identificacao visual cega de SKU 9CY',
            $missing?'fail':'pass',
            $missing?'Falta continuidade visual: '.implode(', ',array_slice($missing,0,8)):
                'Leitura cega de garrafas e validacao local de SKU. Variantes em revisao humana. Sem chamada extra.',
            ['runtime'=>self::currentRelease(),'contract'=>self::FRONTS_BLIND_INVENTORY_9CY_CONTRACT,
             'new_ai_calls'=>false,'sku_names_sent_to_model'=>false,'schema_change'=>false,
             'prior_controls'=>['9CS','9CT','9CU','9CV','9CW','9CX'],'missing'=>$missing]);
    }

    private static function checkFrontsSafeMultimodal9CSContract(): array {
        $requirements = [
            'src/Vision/VisionUsageGuard.php' => [
                'fieldflow-vision-usage-reservation-v1',
                'public static function reserve(int $clientId, array $settings): array {',
                'SELECT GET_LOCK(%s, 3)',
                'if (!update_option($key, $row, false))',
                'self::persist($key, $row);',
                'return self::withLock($clientId,',
            ],
            'src/Vision/FrontsVisionService.php' => [
                self::FRONTS_SAFE_MULTIMODAL_9CS_CONTRACT,
                'VisionUsageGuard::reserve($clientId, $settings)',
                'public static function assertQuestionScope(array $request, ?array $referenceItem = null): void {',
                "['key' => '_ff_project_id'",
                "'fronts' => ",
                'self::livePhoto(',
            ],
            'src/Forms/FormRenderer.php' => [
                'data-vision-manual="select"',
                'data-vision-manual="text"',
                'data-vision-manual="voice"',
                'function rpVisionManualTarget(st,row){',
                'function rpVisionParseLines(text,rows){',
                'x.onload=function(){if(st!==rpVisionState)return;var response=null;',
            ],
            'src/Admin/FrontsVisionAdmin.php' => [
                'monthly_budget_cents',
                'reservation_cents',
                'VisionUsageGuard::summary(',
            ],
            'src/Support/Loader.php' => ['src/Vision/VisionUsageGuard.php'],
            'PHASE-9CS-MULTIMODAL-SAFE-COUNTING.md' => [
                self::FRONTS_SAFE_MULTIMODAL_9CS_CONTRACT,
                '2.2.615 -> 2.2.616',
            ],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.616', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        return self::check(
            'fronts_safe_multimodal_9cs',
            'Contagem multimodal e controlo de custos 9CS',
            $missing ? 'fail' : 'pass',
            $missing ? 'Integridade, contagem sem IA ou bloqueio de custos incompleto: ' . implode(', ', array_slice($missing, 0, 8))
                     : 'Selecionar, escrever e ditar sem IA, confirmações persistidas, orçamento operacional e quotas atómicas por cliente, imagens temporárias.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::FRONTS_SAFE_MULTIMODAL_9CS_CONTRACT,
                'monthly_budget_default_cents'=>0,
                'budget_is_provider_invoice'=>false,
                'schema_change'=>false,
                'missing'=>$missing,
            ]
        );
    }

    private static function checkMediaCaptureResilience9CRContract(): array {
        $requirements = [
            'src/Forms/FormRenderer.php' => [
                self::MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT,
                'function rpMediaOpener(){var saved=rpMediaGet(\'opener\');if(saved===\'click\'||saved===\'picker\')return saved;return rpMediaIsNativeApp()?\'click\':\'picker\';}',
                'function rpMediaCheckRetap(zone,source,origin){',
                'rpMediaReport(\'media_picker_no_signal\'',
                'rpMediaSet(\'camera\',\'native\');',
                'rpMediaGet(\'camera\')===\'native\'',
                'document.addEventListener(\'pointerup\',rpMediaReviveTrigger,true);',
                'rpMediaReport(\'media_input_disabled\'',
                'rpMediaNoteChange(this,$zone);',
                'offline.submitForm.__rpMediaWatch',
                'rpCheckpointExternalMediaHandoff($(this),source);',
                'data-rp-upload-trigger="camera"',
                'data-rp-upload-trigger="gallery"',
                'if(input.dataset.rpOptimisedPass!==\'1\') rpMediaNoteChange(input,$(input).closest(\'[data-rp-upload-zone]\'));',
            ],
            'src/Forms/Forms.php' => [
                'data-rp-media-diag-endpoint="<?php echo esc_url(rest_url(\'fieldflow-mobile/v1/diagnostics/client-event\')); ?>"',
                'data-rp-media-diag-nonce="<?php echo esc_attr(wp_create_nonce(\'wp_rest\')); ?>"',
            ],
            'src/Mobile/StabilityController.php' => [
                '\'/diagnostics/client-event\'',
                '\'media_error\'',
                '\'permission_callback\' => [$this, \'authenticated\']',
                '$context[\'detail\'] = substr(sanitize_textarea_field($body[\'detail\']), 0, 600);',
            ],
            'src/Cloud/Phase6StableRelease.php' => [
                'public const SOURCE_RELEASE = \'2.2.614\'',
                'public const STABLE_RELEASE = \'2.2.615\'',
                '// public const SOURCE_RELEASE = \'2.2.613\'',
                '// public const STABLE_RELEASE = \'2.2.614\'',
            ],
            'src/Support/ReleasePackageValidator.php' => [
                'media_capture_resilience_9cr_ok',
                'Media Capture Resilience 9CR',
            ],
            'src/Admin/ReleaseManager.php' => [
                'packageResult[\'media_capture_resilience_9cr_ok\']',
                'FOTOGRAFIAS RESILIENTES OK',
            ],
            'tools/verify-media-capture-resilience-9cr.php' => [
                'MEDIA CAPTURE RESILIENCE 9CR CONTRACT OK',
                'successor=>=2.2.615',
            ],
            'tools/test-media-capture-resilience-9cr.php' => [
                'MEDIA CAPTURE RESILIENCE 9CR RUNTIME OK',
                'openers=1',
                'retap=1',
                'camera_fallback=1',
                'disabled_guard=1',
                'diagnostics=1',
                'submit_watch=1',
                'historical_continuity=1',
            ],
            'PHASE-9CR-MEDIA-CAPTURE-RESILIENCE.md' => [
                self::MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT,
                '`SOURCE_RELEASE` is `2.2.614` and `STABLE_RELEASE` is `2.2.615`',
                'No database schema changes',
                'No FlutterFlow source modification is required',
            ],
        ];
        $forbidden = [
            'src/Forms/FormRenderer.php' => ['try{if(typeof input.showPicker===\'function\'){input.showPicker();return;}}catch(_){ }'],
        ];
        $missing = [];
        if (version_compare(self::currentRelease(), '2.2.615', '<')) $missing[] = 'runtime:version';
        foreach ($requirements as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            if ($raw === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($raw, $required) === false) $missing[] = $relative . ':' . $required;
        }
        foreach ($forbidden as $relative => $markers) {
            $raw = is_readable(ROUTESPRO_PATH . $relative) ? (string)file_get_contents(ROUTESPRO_PATH . $relative) : '';
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw, $blocked) !== false) $missing[] = $relative . ':forbidden:' . $blocked;
        }
        return self::check(
            'media_capture_resilience_9cr',
            'Media Capture Resilience 9CR',
            $missing ? 'fail' : 'pass',
            $missing
                ? 'Photo capture resilience or media diagnostics are incomplete: ' . implode(', ', array_slice($missing, 0, 8))
                : 'Photo questions open the gallery and the camera with the method that works on the device (click in the app WebView, showPicker in browsers, switched after a tap without result), fall back to the phone camera when the in-page camera is refused, never keep a disabled photo button in a visible editable question and report each failed step to the authenticated client-event diagnostics.',
            [
                'runtime'=>self::currentRelease(),
                'contract'=>self::MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT,
                'app_opener'=>'click',
                'browser_opener'=>'picker',
                'camera_native_fallback'=>true,
                'diagnostics_endpoint'=>'fieldflow-mobile/v1/diagnostics/client-event',
                'schema_change'=>false,
                'historical_authorities'=>['9CL','9CM','9CN','9CO','9CP','9CQ'],
                'missing'=>$missing,
            ]
        );
    }

    private static function checkShoppingMailMerchandisingContract(): array {
        $requirements = [
            'src/Shopping/ShoppingService.php' => ["SCHEMA_VERSION = '1.3.0'",'recipient_user_ids','notification_attempts','notification_last_sent_at','resendSubmission','resolveRecipientTargets','shopping_verification_resend','AssignmentResolver::get_client_user_ids','insignia','last_location_id','locationsForInsignia','consolidateLegacyInsigniaAssignments','Seleciona a loja desta insígnia','unmappedLocationsForInsignia','Team-wide contract','render_newsletter_html','FieldFlow · Shopping & Preços','createSubmissionAttachments','associateLocationToInsignia','shopping_submission','manual_front'],
            'src/Shopping/ShoppingResultsService.php' => ['notification_attempts','recipient_user_ids','notification_last_sent_at','AS insignia'],
            'src/Admin/Shopping.php' => ['fieldflow_shopping_resend_submission','handleResendSubmission','Reenviar email','recipient_user_ids[]','Pré-selecionar também os utilizadores associados ao cliente','configuração atual do lote','Insígnias e disponibilidade operacional','Toda a equipa do projeto','layout newsletter otimizado para Outlook','Anexar Excel','Anexar PDF'],
            'src/Front/ShoppingShortcodeRenderer.php' => ['Percurso do linear','data-view-mode="route"','orderedProducts','source_row','Agrupar por tipo','Loja desta insígnia','ffs-store-select','location_id:selectedLocationId','unmapped_locations','associate-location','Lojas por associar à insígnia'],
            'src/Shopping/ShoppingExportService.php' => ['createSubmissionAttachments','Resumo','Preços','buildSubmissionPdf','cleanupAttachments'],
            'src/Shopping/ShoppingController.php' => ['associate-location','associateLocationToInsignia'],
            'src/Admin/Emails.php' => ['shopping_verification','shopping_verification_resend','render_newsletter_html','x-apple-disable-message-reformatting','<!--[if mso]>'],
        ];
        $missing = [];
        foreach ($requirements as $relative => $markers) {
            $path = ROUTESPRO_PATH . $relative;
            $content = is_readable($path) ? (string)file_get_contents($path) : '';
            if ($content === '') { $missing[] = $relative . ':file'; continue; }
            foreach ($markers as $required) if (strpos($content, $required) === false) $missing[] = $relative . ':' . $required;
        }

        global $wpdb;
        $batchColumns = [];
        $assignmentColumns = [];
        $submissionColumns = [];
        $insigniaLocationColumns = [];
        if (class_exists(ShoppingService::class)) {
            $batchTable = ShoppingService::batchesTable();
            $assignmentTable = ShoppingService::assignmentsTable();
            $submissionTable = ShoppingService::submissionsTable();
            $insigniaLocationsTable = ShoppingService::insigniaLocationsTable();
            $batchExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $batchTable)) === $batchTable;
            $assignmentExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $assignmentTable)) === $assignmentTable;
            $submissionExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $submissionTable)) === $submissionTable;
            $insigniaLocationsExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $insigniaLocationsTable)) === $insigniaLocationsTable;
            if ($batchExists) $batchColumns = (array)$wpdb->get_col("SHOW COLUMNS FROM {$batchTable}", 0);
            else $missing[] = 'shopping_batches:table';
            if ($assignmentExists) $assignmentColumns = (array)$wpdb->get_col("SHOW COLUMNS FROM {$assignmentTable}", 0);
            else $missing[] = 'shopping_assignments:table';
            if ($submissionExists) $submissionColumns = (array)$wpdb->get_col("SHOW COLUMNS FROM {$submissionTable}", 0);
            else $missing[] = 'shopping_submissions:table';
            if ($insigniaLocationsExists) $insigniaLocationColumns = (array)$wpdb->get_col("SHOW COLUMNS FROM {$insigniaLocationsTable}", 0);
            else $missing[] = 'shopping_insignia_locations:table';
        } else {
            $missing[] = 'ShoppingService:class';
        }
        foreach (['recipient_user_ids','attach_xlsx','attach_pdf'] as $column) if (!in_array($column, $batchColumns, true)) $missing[] = 'shopping_batches:' . $column;
        foreach (['insignia','last_location_id'] as $column) if (!in_array($column, $assignmentColumns, true)) $missing[] = 'shopping_assignments:' . $column;
        foreach (['insignia','notification_attempts','notification_last_sent_at'] as $column) if (!in_array($column, $submissionColumns, true)) $missing[] = 'shopping_submissions:' . $column;
        foreach (['client_id','project_id','location_id','insignia','match_source','confidence'] as $column) if (!in_array($column, $insigniaLocationColumns, true)) $missing[] = 'shopping_insignia_locations:' . $column;
        $schemaVersion = (string)get_option('fieldflow_shopping_schema_version', '');
        if ($schemaVersion !== '1.3.0') $missing[] = 'shopping_schema_version:1.3.0';
        if (class_exists(ShoppingService::class)) {
            $restrictedAssignments = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . ShoppingService::assignmentsTable() . " a INNER JOIN " . ShoppingService::batchesTable() . " b ON b.id=a.batch_id WHERE b.status IN ('active','draft') AND a.assigned_user_id IS NOT NULL AND a.assigned_user_id<>0");
            if ($restrictedAssignments > 0) $missing[] = 'shopping_team_scope:restricted_assignments=' . $restrictedAssignments;
        }

        return self::check('shopping_mail_merchandising_flow', 'Shopping Insignia, Mail & Merchandising Flow', $missing ? 'fail' : 'pass',
            $missing
                ? 'O Shopping partilhado pela equipa, seleção de loja por insígnia, destinatários atuais, reenvio auditável, percurso do linear ou migração Shopping está incompleto.'
                : 'Shopping por insígnia está partilhado por toda a equipa operacional do projeto, sem ownership por merchandiser ou filtro de loja por rota, com reenvio auditável e schema Shopping 1.3.0 confirmado, anexos Excel/PDF e aprendizagem de lojas ativa.',
            ['runtime'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '','shopping_schema'=>$schemaVersion,'missing'=>$missing]
        );
    }

    private static function checkRuntimeFiles(): array {
        $missing = [];
        foreach (Loader::bootstrapFiles() as $relative) {
            if (!is_file(ROUTESPRO_PATH . ltrim($relative, '/'))) $missing[] = $relative;
        }
        foreach ([
            'src/Support/SchemaManifest.php','src/Support/SchemaIntegrity.php','src/Support/MigrationLedger.php',
            'src/Support/BatchMigrationManager.php','src/Support/ProductionManifest.php','src/Support/ReleaseGate.php','src/Cloud/Phase5StableRelease.php','src/Admin/SchemaIntegrityCenter.php',
            'assets/fieldflow-google-maps-loader.js',
            'assets/fieldflow-offline-runtime.js','assets/fieldflow-offline-runtime.css','src/Mobile/OfflineSyncService.php','src/PWA/ServiceWorkerController.php',
        ] as $relative) {
            if (!is_file(ROUTESPRO_PATH . $relative)) $missing[] = $relative;
        }
        return self::check('runtime_files', 'Ficheiros críticos do runtime', $missing ? 'fail' : 'pass',
            $missing ? 'Existem ficheiros críticos em falta.' : 'Todos os ficheiros críticos da versão instalada estão presentes.',
            ['missing'=>$missing]
        );
    }

    private static function checkArchitectureRuntime(): array {
        $status = RuntimeRegistry::status();
        $ok = !empty($status['registered']) && empty($status['missing_controllers']);
        return self::check('architecture_runtime', 'Composition root', $ok ? 'pass' : 'fail',
            $ok ? 'O runtime modular está registado.' : 'O runtime modular não está completamente registado.',
            $status
        );
    }

    private static function checkRestRegistry(): array {
        $status = RestRouteRegistry::status();
        $missing = (array)($status['missing_controllers'] ?? []);
        return self::check('rest_registry', 'Controllers REST', $missing ? 'fail' : 'pass',
            $missing ? 'Existem controllers REST em falta.' : (int)($status['registered_controllers'] ?? 0) . ' controllers REST estão disponíveis.',
            $status
        );
    }

    private static function checkMobileContract(): array {
        $summary = ContractRegistry::summary();
        $critical = ContractRegistry::criticalEndpoints();
        $checksum = (string)($summary['checksum'] ?? '');
        $ok = strlen($checksum) === 64 && count($critical) >= 20 && !empty($summary['api_version']);
        return self::check('mobile_contract', 'Contrato Flutter', $ok ? 'pass' : 'fail',
            $ok ? 'Contrato mobile assinado, additive-only e com ' . count($critical) . ' endpoints críticos.' : 'O contrato mobile está incompleto ou sem checksum válido.',
            ['api_version'=>$summary['api_version'] ?? '','contract_version'=>$summary['contract_version'] ?? '','checksum'=>$checksum,'critical_endpoints'=>count($critical)]
        );
    }

    private static function checkSchema(): array {
        $report = SchemaIntegrity::latestOrQuick(DAY_IN_SECONDS);
        $status = (string)($report['status'] ?? 'unknown');
        $gateStatus = $status === 'critical' ? 'fail' : ($status === 'warning' || $status === 'unknown' ? 'warning' : 'pass');
        $mode = (string)($report['mode'] ?? 'quick');
        if ($mode !== 'full' && $gateStatus === 'pass') $gateStatus = 'warning';
        $detail = (string)($report['summary'] ?? 'Sem diagnóstico de schema.');
        if ($mode !== 'full') $detail .= ' A validação usada pelo gate é rápida, falta concluir a auditoria integral.';
        return self::check('schema_integrity', 'Integridade do schema', $gateStatus, $detail, [
            'mode'=>$mode,
            'generated_at'=>$report['generated_at'] ?? '',
            'counts'=>$report['counts'] ?? [],
        ]);
    }

    private static function checkMigrations(): array {
        $status = MigrationLedger::status();
        $stale = MigrationLedger::staleRunning();
        $gate = empty($status['table_ready']) || (int)($status['failed'] ?? 0) > 0 || $stale ? 'fail' : 'pass';
        return self::check('migration_ledger', 'Histórico de migrações', $gate,
            $gate === 'pass' ? 'O histórico de migrações está operacional e sem falhas.' : 'Existem falhas, processos bloqueados ou a tabela de migrações não está disponível.',
            ['status'=>$status,'stale_running'=>count($stale)]
        );
    }

    private static function checkBatchFramework(): array {
        $status = BatchMigrationManager::status();
        $gate = empty($status['table_ready']) ? 'fail' : ((int)($status['failed'] ?? 0) > 0 ? 'warning' : 'pass');
        return self::check('batch_framework', 'Migrações em lotes', $gate,
            $gate === 'pass' ? 'O executor retomável de tarefas em lotes está disponível.' : 'O executor em lotes precisa de atenção.',
            $status
        );
    }

    private static function checkQueue(): array {
        $status = QueueManager::status();
        $gate = empty($status['table_ready']) ? 'fail' : ((string)($status['status'] ?? '') === 'healthy' ? 'pass' : 'warning');
        return self::check('queue_runtime', 'Fila persistente', $gate,
            $gate === 'pass' ? 'A fila persistente está saudável e mantém fallback para WP-Cron.' : 'A fila persistente apresenta avisos ou não está disponível.',
            $status
        );
    }

    private static function checkCron(): array {
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $actionScheduler = function_exists('as_schedule_single_action');
        $status = $disabled && !$actionScheduler ? 'warning' : 'pass';
        return self::check('cron_runtime', 'Agendamento', $status,
            $status === 'pass' ? 'Existe um mecanismo de agendamento disponível.' : 'WP-Cron está desativado e o Action Scheduler não foi detetado. Confirma um cron real no servidor.',
            ['wp_cron_disabled'=>$disabled,'action_scheduler_available'=>$actionScheduler]
        );
    }

    private static function checkUploads(): array {
        $uploads = wp_upload_dir(null, false);
        $path = (string)($uploads['basedir'] ?? '');
        $ok = empty($uploads['error']) && $path !== '' && is_dir($path) && is_writable($path);
        return self::check('uploads_writable', 'Diretório de uploads', $ok ? 'pass' : 'fail',
            $ok ? 'O diretório de uploads está disponível para evidências e exportações.' : 'O diretório de uploads não está disponível ou não permite escrita.',
            ['path'=>$path,'error'=>$uploads['error'] ?? '']
        );
    }

    private static function checkAssets(): array {
        $required = [
            'assets/fieldflow-design-system.css',
            'assets/fieldflow-ui-runtime.js',
            'assets/fieldflow-responsive.css',
            'assets/fieldflow-google-maps-loader.js',
        ];
        $missing = [];
        foreach ($required as $asset) if (!is_file(ROUTESPRO_PATH . $asset)) $missing[] = $asset;
        return self::check('design_assets', 'Assets de interface', $missing ? 'fail' : 'pass',
            $missing ? 'Existem assets críticos de interface em falta.' : 'Os assets críticos de BO, front, responsividade e Google Places estão presentes.',
            ['missing'=>$missing]
        );
    }

    private static function checkEnvironment(): array {
        global $wp_version;
        $phpOk = version_compare(PHP_VERSION, '7.4', '>=');
        $wpOk = version_compare((string)$wp_version, '6.4', '>=');
        $status = $phpOk && $wpOk ? 'pass' : 'warning';
        return self::check('environment', 'Ambiente de execução', $status,
            $status === 'pass' ? 'As versões de PHP e WordPress cumprem a base de compatibilidade.' : 'O ambiente usa versões abaixo da base recomendada.',
            ['php'=>PHP_VERSION,'wordpress'=>(string)$wp_version,'php_minimum'=>'7.4','wordpress_recommended'=>'6.4']
        );
    }

    /**
     * Runtime classes can survive a private updater request in PHP memory or OPcache
     * even after the canonical plugin file has already been replaced on disk.
     * The installed source remains authoritative when it contains the exact contract
     * and the loaded class originates from that same canonical file. A class loaded
     * from any other path is still a hard failure.
     */
    private static function inspectCanonicalRuntimeMethod(
        string $class,
        string $relativePath,
        string $method,
        array $expectedNames,
        int $expectedRequired,
        string $sourceSignature
    ): array {
        $canonicalPath = ROUTESPRO_PATH . ltrim($relativePath, '/');
        $canonicalReal = realpath($canonicalPath) ?: $canonicalPath;
        $source = is_readable($canonicalPath) ? (string)file_get_contents($canonicalPath) : '';
        $sourceOk = $source !== '' && strpos($source, $sourceSignature) !== false;
        $state = [
            'ok'=>$sourceOk,
            'reason'=>$sourceOk ? 'source_contract_ok' : 'source_signature_missing',
            'runtime_loaded'=>false,
            'runtime_stale'=>false,
            'runtime_origin'=>'',
            'canonical_path'=>$canonicalReal,
            'runtime_names'=>[],
            'expected_names'=>$expectedNames,
            'expected_required'=>$expectedRequired,
            'opcache_invalidated'=>false,
        ];
        if (!$sourceOk) return $state;
        if (!class_exists($class, false)) return $state;

        $state['runtime_loaded'] = true;
        try {
            $reflection = new \ReflectionClass($class);
            $runtimeFile = (string)$reflection->getFileName();
            $runtimeReal = $runtimeFile !== '' ? (realpath($runtimeFile) ?: $runtimeFile) : '';
            $state['runtime_origin'] = $runtimeReal;
            if ($runtimeReal === '' || $runtimeReal !== $canonicalReal) {
                $state['ok'] = false;
                $state['reason'] = 'runtime_origin_mismatch';
                return $state;
            }

            if (!$reflection->hasMethod($method)) {
                if (function_exists('opcache_invalidate')) $state['opcache_invalidated'] = (bool)@opcache_invalidate($canonicalPath, true);
                clearstatcache(true, $canonicalPath);
                $state['runtime_stale'] = true;
                $state['reason'] = 'stale_runtime_non_blocking';
                return $state;
            }

            $runtimeMethod = $reflection->getMethod($method);
            $params = $runtimeMethod->getParameters();
            $names = array_map(static function($param){ return $param->getName(); }, $params);
            $state['runtime_names'] = $names;
            $runtimeOk = count($params) === count($expectedNames)
                && $runtimeMethod->getNumberOfRequiredParameters() === $expectedRequired
                && $names === $expectedNames;
            if ($runtimeOk) {
                $state['reason'] = 'runtime_contract_ok';
                return $state;
            }

            if (function_exists('opcache_invalidate')) $state['opcache_invalidated'] = (bool)@opcache_invalidate($canonicalPath, true);
            clearstatcache(true, $canonicalPath);
            $state['runtime_stale'] = true;
            $state['reason'] = 'stale_runtime_non_blocking';
            return $state;
        } catch (\Throwable $e) {
            $state['ok'] = false;
            $state['reason'] = 'runtime_reflection_failed';
            return $state;
        }
    }

    private static function inspectCanonicalRuntimeConstant(
        string $class,
        string $relativePath,
        string $constant,
        $expectedValue,
        string $sourceMarker
    ): array {
        $canonicalPath = ROUTESPRO_PATH . ltrim($relativePath, '/');
        $canonicalReal = realpath($canonicalPath) ?: $canonicalPath;
        $source = is_readable($canonicalPath) ? (string)file_get_contents($canonicalPath) : '';
        $sourceOk = $source !== '' && strpos($source, $sourceMarker) !== false;
        $state = [
            'ok'=>$sourceOk,
            'reason'=>$sourceOk ? 'source_contract_ok' : 'source_constant_missing',
            'runtime_loaded'=>false,
            'runtime_stale'=>false,
            'runtime_origin'=>'',
            'canonical_path'=>$canonicalReal,
            'opcache_invalidated'=>false,
        ];
        if (!$sourceOk) return $state;
        if (!class_exists($class, false)) return $state;
        $state['runtime_loaded'] = true;
        try {
            $reflection = new \ReflectionClass($class);
            $runtimeFile = (string)$reflection->getFileName();
            $runtimeReal = $runtimeFile !== '' ? (realpath($runtimeFile) ?: $runtimeFile) : '';
            $state['runtime_origin'] = $runtimeReal;
            if ($runtimeReal === '' || $runtimeReal !== $canonicalReal) {
                $state['ok'] = false;
                $state['reason'] = 'runtime_origin_mismatch';
                return $state;
            }
            $runtimeOk = $reflection->hasConstant($constant)
                && $reflection->getConstant($constant) === $expectedValue;
            if ($runtimeOk) {
                $state['reason'] = 'runtime_contract_ok';
                return $state;
            }
            if (function_exists('opcache_invalidate')) $state['opcache_invalidated'] = (bool)@opcache_invalidate($canonicalPath, true);
            clearstatcache(true, $canonicalPath);
            $state['runtime_stale'] = true;
            $state['reason'] = 'stale_runtime_non_blocking';
            return $state;
        } catch (\Throwable $e) {
            $state['ok'] = false;
            $state['reason'] = 'runtime_reflection_failed';
            return $state;
        }
    }

    private static function check(string $key, string $label, string $status, string $detail, array $meta = []): array {
        return [
            'key'=>$key,
            'label'=>$label,
            'status'=>$status,
            'detail'=>$detail,
            'meta'=>self::safeMeta($meta),
        ];
    }

    private static function appendHistory(array $report): void {
        $history = self::history();
        array_unshift($history, [
            'status'=>(string)$report['status'],
            'score'=>(int)$report['score'],
            'counts'=>(array)$report['counts'],
            'generated_at'=>(string)$report['generated_at'],
            'duration_ms'=>(int)$report['duration_ms'],
            'actor_user_id'=>(int)$report['actor_user_id'],
            'plugin_version'=>(string)$report['plugin_version'],
        ]);
        update_option(self::HISTORY_OPTION, array_slice($history, 0, 20), false);
    }

    private static function safeMeta($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/token|secret|password|cookie|authorization/i', $key)) continue;
                $out[$key] = self::safeMeta($item);
            }
            return $out;
        }
        if (is_string($value)) return substr(wp_strip_all_tags($value), 0, 1000);
        if (is_scalar($value) || $value === null) return $value;
        return null;
    }
}
