<?php
namespace RoutesPro\Mobile;

use WP_Error;
use RoutesPro\Forms\BindingResolver;
use RoutesPro\Forms\BriefingFormBridge;
use RoutesPro\Forms\ContextQuestions;
use RoutesPro\Forms\ConditionalFlow;
use RoutesPro\Forms\DailySubmissionService;
use RoutesPro\Forms\EvidenceFlow;
use RoutesPro\Forms\Forms;
use RoutesPro\Forms\ProductCardex;
use RoutesPro\Forms\RecordService;
use RoutesPro\Support\Permissions;
use RoutesPro\Support\Theme;

if (!defined('ABSPATH')) exit;

class FormPayloadService {
    public static function for_stop(int $stop_id, ?int $user_id = null, bool $includeStoreProfile = true) {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        $px = $wpdb->prefix . 'routespro_';
        $stop = $wpdb->get_row($wpdb->prepare(
            "SELECT rs.*, r.client_id, r.project_id, r.date AS route_date, r.owner_user_id, l.name AS location_name, l.address, l.city, l.postal_code, l.lat AS location_lat, l.lng AS location_lng FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.id=%d LIMIT 1",
            $stop_id
        ), ARRAY_A);
        if (!$stop) return new WP_Error('fieldflow_mobile_stop_not_found', 'Stop not found.', ['status' => 404]);
        if (!Permissions::can_access_route((int)$stop['route_id'], $user_id)) {
            return new WP_Error('fieldflow_mobile_forbidden', 'You do not have access to this route.', ['status' => 403]);
        }

        $context = BindingResolver::get_context([
            'client_id' => (int)$stop['client_id'],
            'project_id' => (int)$stop['project_id'],
            'route_id' => (int)$stop['route_id'],
            'stop_id' => $stop_id,
            'location_id' => (int)$stop['location_id'],
            'date' => (string)$stop['route_date'],
        ]);
        $binding = BindingResolver::resolve($context);
        if (!$binding || empty($binding['form_id'])) {
            return new WP_Error('fieldflow_mobile_form_not_bound', 'No active form is bound to this stop.', ['status' => 404]);
        }

        return self::resolved((int)$binding['form_id'], $context, $binding, $user_id, $stop, $includeStoreProfile);
    }

    public static function resolved(int $form_id, array $context, ?array $binding = null, ?int $user_id = null, array $stop = [], bool $includeStoreProfile = true) {
        $user_id = $user_id ?: get_current_user_id();
        $form = Forms::get_form($form_id);
        if (!$form || (string)($form['status'] ?? '') !== 'active') {
            return new WP_Error('fieldflow_mobile_form_not_found', 'Form not found or inactive.', ['status' => 404]);
        }

        $context['form_id'] = $form_id;
        $context['route_stop_id'] = (int)($context['route_stop_id'] ?? $context['stop_id'] ?? 0);
        $context['stop_id'] = $context['route_stop_id'];
        $schema = Forms::decode_schema((string)($form['schema_json'] ?? ''));
        if (class_exists(ContextQuestions::class)) {
            $schema = ContextQuestions::inject_into_schema($schema, ContextQuestions::regular_for_context($context));
        }
        if (class_exists(BriefingFormBridge::class)) {
            $schema = BriefingFormBridge::expand_blocks($schema, $context);
        }
        if (class_exists(EvidenceFlow::class)) {
            $schema = EvidenceFlow::expand_schema($schema);
        }

        if (class_exists(ConditionalFlow::class)) {
            $schema = ConditionalFlow::normalise_schema($schema);
        }
        if (class_exists(ProductCardex::class)) {
            $schema = ProductCardex::synchronise_order_questions_with_fronts($schema, $context);
        }

        $report_day = DailySubmissionService::normalise_report_day(
            $context['route_date'] ?? $context['date'] ?? current_time('Y-m-d')
        );
        $existing = !empty($context['location_id'])
            ? DailySubmissionService::find_existing($form_id, (int)$context['location_id'], $report_day)
            : null;
        $context_conflict = $existing ? DailySubmissionService::edit_context_conflict($existing, $context) : null;
        // ProductCardex precisa de distinguir uma nova visita de uma edição do
        // reporte diário atual para fazer a rotação correta antes/atual.
        $context['_daily_existing'] = !empty($existing);

        $record_state = RecordService::get_record_state(
            $form_id,
            (int)($context['client_id'] ?? 0),
            (int)($context['project_id'] ?? 0),
            (int)($context['location_id'] ?? 0)
        );
        $recordPrefill = is_array($record_state['prefill'] ?? null) ? $record_state['prefill'] : [];
        $record = is_array($record_state['record'] ?? null) ? $record_state['record'] : null;
        $version = is_array($record_state['version'] ?? null) ? $record_state['version'] : null;
        if ($record) $recordPrefill = RecordService::prepare_prefill_for_schema((int)$record['id'], $schema);
        $recordPrefill = RecordService::without_historical_media($recordPrefill, $schema);

        $previousSubmission = !empty($context['location_id'])
            ? DailySubmissionService::find_previous_operational(
                $form_id,
                (int)$context['location_id'],
                $report_day,
                (int)($context['client_id'] ?? 0),
                (int)($context['project_id'] ?? 0),
                true,
                (int)($existing['id'] ?? 0)
            )
            : null;
        $historicalPrefill = $previousSubmission
            ? DailySubmissionService::submission_prefill((int)$previousSubmission['id'], $schema)
            : [];
        $historicalPrefill = DailySubmissionService::visit_memory_prefill($historicalPrefill, $schema);
        $historySource = $previousSubmission;
        // 9BZ: native/WebView receives the same safe auto-prefill as web.
        $prefill = // 9BH predecessor certification marker: DailySubmissionService::new_visit_prefill
        $prefill = DailySubmissionService::new_visit_autofill_prefill($historicalPrefill, $schema);
        if ($existing && !$context_conflict) {
            // 9BJ: never expose another route/stop/store report as current media.
            $prefill = DailySubmissionService::submission_prefill((int)$existing['id'], $schema);
        }

        $questions = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            if ($key !== '') $question['key'] = $key;
            $question['type'] = $type;
            if (in_array($type, ['image_upload', 'categorized_image_upload'], true)) $question['multiple'] = true;
            $previousVisitAvailable = !$existing
                && DailySubmissionService::visit_memory_question_eligible($question)
                && $key !== ''
                && array_key_exists($key, $historicalPrefill)
                && self::hasPreviousVisitValue($historicalPrefill[$key]);
            $question['resolved'] = [
                'value' => $key !== '' && array_key_exists($key, $prefill) ? $prefill[$key] : null,
                'available' => true,
                'previous_visit' => [
                    'available' => $previousVisitAvailable,
                    'value' => $previousVisitAvailable ? $historicalPrefill[$key] : null,
                    'submitted_at' => $previousVisitAvailable ? sanitize_text_field((string)($historySource['submitted_at'] ?? '')) : '',
                    'mode' => 'prefill',
                ],
            ];
            if (in_array($type, ['product_matrix', 'order_suggestion'], true) && class_exists(ProductCardex::class)) {
                $cardexAvailable = ProductCardex::question_is_available_for_context($question, $context);
                $available = $type === 'order_suggestion' ? true : $cardexAvailable;
                $cardex_id = ProductCardex::question_cardex_id($question, $context);
                // 9DS: native clients must display a binding warning instead
                // of silently removing the configured Frentes field.
                if (!$cardexAvailable) {
                    $available = true;
                    $question['resolved']['read_only'] = true;
                    $question['resolved']['binding_error'] = ProductCardex::question_binding_issue($question, $context);
                }
                $question['resolved']['available'] = $available;
                $question['resolved']['cardex_available'] = $cardexAvailable;
                $question['resolved']['cardex_id'] = $cardex_id;
                $question['resolved']['paired_fronts_key'] = sanitize_key((string)($question['_fieldflow_paired_fronts_key'] ?? ''));
                $question['resolved']['rows'] = $type === 'order_suggestion'
                    ? ProductCardex::order_question_rows($question, $context, $prefill[$key] ?? null)
                    : ($available ? ProductCardex::question_rows($question, $context, $prefill[$key] ?? null) : []);
                $question['resolved']['store_override'] = $cardex_id > 0 && !empty($context['location_id'])
                    ? ProductCardex::has_store_override($cardex_id, (int)$context['location_id'])
                    : false;
            }
            $questions[] = $question;
        }
        $schema['questions'] = $questions;
        $schemaHash = Forms::schema_hash($schema);
        $revision = substr($schemaHash, 0, 20);
        $definitionVersion = Forms::definition_version($form);
        $downloadedAt = current_time('mysql', true);
        $theme = class_exists(Theme::class)
            ? Theme::resolve((int)($context['client_id'] ?? 0), (int)($context['project_id'] ?? 0))
            : self::decode((string)($form['theme_json'] ?? ''));
        $themePayload = class_exists(Theme::class)
            ? array_merge($theme, Theme::js_payload($theme))
            : $theme;
        $conditionalFlow = class_exists(ConditionalFlow::class)
            ? ConditionalFlow::payload($schema, $prefill)
            : [];
        $storeProfile = null;
        if ($includeStoreProfile && !empty($context['location_id']) && class_exists('\\RoutesPro\\Stores\\StoreProfileService')) {
            $storeSnapshot = \RoutesPro\Stores\StoreProfileService::snapshot(
                (int)$context['location_id'],
                (int)($context['project_id'] ?? 0),
                (int)($context['route_id'] ?? 0),
                $user_id
            );
            if (!is_wp_error($storeSnapshot)) $storeProfile = $storeSnapshot;
        }

        return [
            'form' => [
                'id' => $form_id,
                'title' => (string)($form['title'] ?? ''),
                'settings' => self::decode((string)($form['settings_json'] ?? '')),
                'theme' => $themePayload,
                'schema' => $schema,
                'schema_revision' => $revision,
                'schema_hash' => $schemaHash,
                'definition_version' => $definitionVersion,
                'downloaded_at' => $downloadedAt,
                'conditional_flow' => $conditionalFlow,
            ],
            'binding' => [
                'id' => (int)($binding['id'] ?? 0),
                'mode' => (string)($binding['mode'] ?? ''),
            ],
            'context' => [
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
                'route_date' => (string)($context['route_date'] ?? $report_day),
                'report_day' => $report_day,
            ],
            'stop' => $stop,
            'store_profile' => $storeProfile,
            'daily_report' => [
                'exists' => (bool)$existing,
                'submission_id' => (int)($existing['id'] ?? 0),
                'can_edit' => $existing ? (DailySubmissionService::can_edit($existing, $user_id) && !$context_conflict) : false,
                'context_conflict' => $context_conflict,
                'original_context' => $existing ? DailySubmissionService::original_context($existing) : [],
            ],
            'record' => [
                'id' => (int)($record['id'] ?? 0),
                'version_id' => (int)($version['id'] ?? 0),
                'version_no' => (int)($version['version_no'] ?? 0),
            ],
            'prefill' => $prefill,
        ];
    }

    private static function hasPreviousVisitValue($value): bool {
        if (is_bool($value)) return true;
        if (is_scalar($value)) return trim((string)$value) !== '';
        if (!is_array($value)) return false;
        foreach ($value as $item) {
            if (self::hasPreviousVisitValue($item)) return true;
        }
        return false;
    }

    private static function decode(string $json): array {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
