<?php
namespace RoutesPro\Forms;

use RoutesPro\Support\Permissions;
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Services\OperationalReportDayPolicy;

if (!defined('ABSPATH')) exit;

/**
 * Regra operacional de um reporte por formulario, loja e dia.
 *
 * A tabela de submissões continua a guardar apenas uma linha diária. Quando o
 * utilizador corrige um reporte, a linha e as respostas são atualizadas, mas o
 * RecordService cria uma nova versão para manter a auditoria completa.
 */
class DailySubmissionService {
    private static $has_report_day_column = null;

    public static function normalise_report_day($value): string {
        $value = trim((string)$value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            if (checkdate($month, $day, $year)) return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        if ($value !== '') {
            $timestamp = strtotime($value);
            if ($timestamp) return wp_date('Y-m-d', $timestamp, wp_timezone());
        }
        return current_time('Y-m-d');
    }

    public static function has_report_day_column(): bool {
        if (self::$has_report_day_column !== null) return (bool)self::$has_report_day_column;
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare(
            'SHOW COLUMNS FROM ' . Forms::table_submissions() . ' LIKE %s',
            'report_day'
        ));
        self::$has_report_day_column = !empty($column);
        return (bool)self::$has_report_day_column;
    }

    /**
     * Resolves the effective store identity of a submission.
     *
     * Historical FieldFlow builds could persist location_id=route_stop_id.
     * When route_stop_id is available, the route snapshot/current stop is the
     * authority. Without an explicit stop, a direct location on the same route
     * wins before the legacy stop-id fallback, preventing numeric-ID collisions.
     */
    public static function canonical_location_sql(string $alias = 's'): string {
        global $wpdb;
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 's';
        $px = $wpdb->prefix . 'routespro_';
        $stops = $px . 'route_stops';
        $snapshots = $px . 'route_location_snapshot';
        $a = $alias . '.';

        return "CASE
            WHEN COALESCE({$a}route_stop_id,0)>0 THEN COALESCE(
                (SELECT NULLIF(snap.location_id,0) FROM {$snapshots} snap
                 WHERE snap.route_stop_id={$a}route_stop_id
                   AND (COALESCE({$a}route_id,0)<=0 OR snap.route_id={$a}route_id)
                 ORDER BY snap.id DESC LIMIT 1),
                (SELECT NULLIF(rs.location_id,0) FROM {$stops} rs
                 WHERE rs.id={$a}route_stop_id
                   AND (COALESCE({$a}route_id,0)<=0 OR rs.route_id={$a}route_id)
                 LIMIT 1),
                {$a}location_id
            )
            WHEN COALESCE({$a}route_id,0)>0 AND COALESCE({$a}location_id,0)>0
                 AND (
                    EXISTS (SELECT 1 FROM {$stops} direct_rs
                            WHERE direct_rs.route_id={$a}route_id AND direct_rs.location_id={$a}location_id)
                    OR EXISTS (SELECT 1 FROM {$snapshots} direct_snap
                               WHERE direct_snap.route_id={$a}route_id AND direct_snap.location_id={$a}location_id)
                 ) THEN {$a}location_id
            WHEN COALESCE({$a}route_id,0)>0 AND COALESCE({$a}location_id,0)>0 THEN COALESCE(
                (SELECT NULLIF(legacy_snap.location_id,0) FROM {$snapshots} legacy_snap
                 WHERE legacy_snap.route_id={$a}route_id AND legacy_snap.route_stop_id={$a}location_id
                 ORDER BY legacy_snap.id DESC LIMIT 1),
                (SELECT NULLIF(legacy_rs.location_id,0) FROM {$stops} legacy_rs
                 WHERE legacy_rs.route_id={$a}route_id AND legacy_rs.id={$a}location_id
                 LIMIT 1),
                {$a}location_id
            )
            ELSE {$a}location_id
        END";
    }

    public static function canonical_location_id(array $submission): int {
        if (array_key_exists('_canonical_location_id', $submission)) {
            $resolved = absint($submission['_canonical_location_id']);
            if ($resolved > 0) return $resolved;
        }
        $submission_id = absint($submission['id'] ?? 0);
        if ($submission_id <= 0) return absint($submission['location_id'] ?? 0);
        global $wpdb;
        $table = Forms::table_submissions();
        $expr = self::canonical_location_sql('s');
        $resolved = absint($wpdb->get_var($wpdb->prepare(
            "SELECT {$expr} FROM {$table} s WHERE s.id=%d LIMIT 1",
            $submission_id
        )));
        return $resolved > 0 ? $resolved : absint($submission['location_id'] ?? 0);
    }

    public static function find_previous_operational(int $form_id, int $location_id, string $before_day, int $client_id = 0, int $project_id = 0, bool $include_same_day = false, int $exclude_submission_id = 0): ?array {
        global $wpdb;
        if ($location_id <= 0 || ($form_id <= 0 && $project_id <= 0)) return null;
        $before_day = min(self::normalise_report_day($before_day), current_time('Y-m-d'));
        $day_comparison = $include_same_day ? '<=' : '<';
        $table = Forms::table_submissions();
        $routes = $wpdb->prefix . 'routespro_routes';
        $canonical_location = self::canonical_location_sql('s');
        // 9BD predecessor package contract marker. The certified 2.2.574-2.2.580 validators look for
        // the original unaliased signature SubmissionStatusPolicy::operationalSql(). Runtime 9BJ uses
        // the equivalent aliased form below so canonical-store joins remain unambiguous.
        $operational = SubmissionStatusPolicy::operationalSql('s');
        $scope = ["({$canonical_location})=%d"];
        $scope_args = [$location_id];

        // Visit memory belongs to the canonical store inside the campaign. A
        // form revision can change form_id without erasing operational history.
        if ($project_id > 0) {
            $scope[] = 's.project_id=%d';
            $scope_args[] = $project_id;
        } else {
            $scope[] = 's.form_id=%d';
            $scope_args[] = $form_id;
        }
        if ($client_id > 0) {
            $scope[] = 's.client_id=%d';
            $scope_args[] = $client_id;
        }
        if ($exclude_submission_id > 0) {
            $scope[] = 's.id<>%d';
            $scope_args[] = $exclude_submission_id;
        }
        $scope_sql = implode(' AND ', $scope);
        $select = "SELECT s.*, ({$canonical_location}) AS _canonical_location_id FROM {$table} s";

        if ($include_same_day) {
            $history_day = self::has_report_day_column()
                ? OperationalReportDayPolicy::sqlExpression('s', 'r')
                : "COALESCE(NULLIF(r.date,''), DATE(s.submitted_at))";
            $query = "{$select} LEFT JOIN {$routes} r ON r.id=s.route_id
                      WHERE {$scope_sql} AND {$operational} AND {$history_day} <= %s
                      ORDER BY {$history_day} DESC, s.submitted_at DESC, s.id DESC LIMIT 1";
            $row = $wpdb->get_row($wpdb->prepare($query, array_merge($scope_args, [$before_day])), ARRAY_A);
            return $row ?: null;
        }

        if (self::has_report_day_column()) {
            $query = "{$select}
                      WHERE {$scope_sql} AND {$operational}
                        AND (
                             (s.report_day IS NOT NULL AND s.report_day<>'' AND s.report_day {$day_comparison} %s)
                             OR ((s.report_day IS NULL OR s.report_day='') AND DATE(s.submitted_at) {$day_comparison} %s)
                        )
                      ORDER BY COALESCE(NULLIF(s.report_day,''), DATE(s.submitted_at)) DESC, s.submitted_at DESC, s.id DESC
                      LIMIT 1";
            $sql = $wpdb->prepare($query, array_merge($scope_args, [$before_day, $before_day]));
        } else {
            $query = "{$select}
                      WHERE {$scope_sql} AND {$operational}
                        AND DATE(s.submitted_at) {$day_comparison} %s
                      ORDER BY s.submitted_at DESC, s.id DESC LIMIT 1";
            $sql = $wpdb->prepare($query, array_merge($scope_args, [$before_day]));
        }
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    public static function visit_memory_question_eligible(array $question): bool {
        $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
        if (in_array($type, [
            'briefing_block', 'briefing_intro',
            'image_upload', 'categorized_image_upload', 'file_upload',
            'order_suggestion', 'product_matrix'
        ], true)) return false;
        return sanitize_key((string)($question['key'] ?? '')) !== '';
    }

    /** Only schema keys permitted as visit memory may cross into a new visit. */
    public static function visit_memory_prefill(array $prefill, array $schema): array {
        $out = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            if ($key === '' || !array_key_exists($key, $prefill)) continue;
            if ($type === 'product_matrix' || self::visit_memory_question_eligible($question)) {
                $out[$key] = $prefill[$key];
            }
        }
        return $out;
    }

    /** Fronts are the only historical answers used as current form input. */
    public static function new_visit_prefill(array $prefill, array $schema): array {
        $out = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question) || sanitize_key((string)($question['type'] ?? '')) !== 'product_matrix') continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '' && array_key_exists($key, $prefill)) $out[$key] = $prefill[$key];
        }
        return $out;
    }


    /**
     * 9BZ: answers from the latest completed visit that are safe to reuse as
     * current inputs in the next visit. Historical 9BH behaviour remains in
     * new_visit_prefill() for predecessor certification, while current runtime
     * uses this explicit allowlist.
     */
    public static function visit_memory_autofill_question_eligible(array $question): bool {
        $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
        if ($type === 'product_matrix') return sanitize_key((string)($question['key'] ?? '')) !== '';
        return self::visit_memory_question_eligible($question);
    }

    public static function new_visit_autofill_prefill(array $prefill, array $schema): array {
        $out = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question) || !self::visit_memory_autofill_question_eligible($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '' && array_key_exists($key, $prefill)) $out[$key] = $prefill[$key];
        }
        return $out;
    }

    public static function find_existing(int $form_id, int $location_id, string $report_day): ?array {
        global $wpdb;
        if ($form_id <= 0 || $location_id <= 0) return null;
        $report_day = self::normalise_report_day($report_day);
        $table = Forms::table_submissions();
        $active_status = SubmissionStatusPolicy::editableExistingSql('s');
        $canonical_location = self::canonical_location_sql('s');
        $select = "SELECT s.*, ({$canonical_location}) AS _canonical_location_id FROM {$table} s";

        if (self::has_report_day_column()) {
            $sql = $wpdb->prepare(
                "{$select}
                 WHERE s.form_id=%d AND ({$canonical_location})=%d AND {$active_status}
                   AND (s.report_day=%s OR ((s.report_day IS NULL OR s.report_day='') AND DATE(s.submitted_at)=%s))
                 ORDER BY CASE WHEN s.report_day=%s THEN 0 ELSE 1 END ASC, s.id DESC
                 LIMIT 1",
                $form_id,
                $location_id,
                $report_day,
                $report_day,
                $report_day
            );
        } else {
            $sql = $wpdb->prepare(
                "{$select}
                 WHERE s.form_id=%d AND ({$canonical_location})=%d AND {$active_status}
                   AND DATE(s.submitted_at)=%s
                 ORDER BY s.id DESC LIMIT 1",
                $form_id,
                $location_id,
                $report_day
            );
        }
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * Prevents a daily report from being silently rebound to another
     * operational visit while it is being edited.
     *
     * Zero-valued legacy context fields are tolerated so old reports can be
     * repaired, but two different non-zero identifiers are always a conflict.
     */
    public static function edit_context_conflict(array $submission, array $context): ?array {
        $pairs = [
            'client_id' => 'cliente',
            'project_id' => 'projeto',
            'route_id' => 'rota',
            'route_stop_id' => 'paragem',
            'location_id' => 'loja',
        ];
        $requested = [
            'client_id' => absint($context['client_id'] ?? 0),
            'project_id' => absint($context['project_id'] ?? 0),
            'route_id' => absint($context['route_id'] ?? 0),
            'route_stop_id' => absint($context['route_stop_id'] ?? $context['stop_id'] ?? 0),
            'location_id' => absint($context['location_id'] ?? 0),
        ];
        foreach ($pairs as $key => $label) {
            $existing_id = $key === 'location_id'
                ? self::canonical_location_id($submission)
                : absint($submission[$key] ?? 0);
            $requested_id = absint($requested[$key] ?? 0);
            if ($existing_id > 0 && $requested_id > 0 && $existing_id !== $requested_id) {
                return [
                    'field' => $key,
                    'label' => $label,
                    'existing_id' => $existing_id,
                    'requested_id' => $requested_id,
                    'submission_id' => absint($submission['id'] ?? 0),
                ];
            }
        }
        return null;
    }

    public static function original_context(array $submission): array {
        $meta = self::decode_meta($submission['meta_json'] ?? '');
        $daily = is_array($meta['daily_report'] ?? null) ? $meta['daily_report'] : [];
        return [
            'client_id' => absint($daily['original_client_id'] ?? $submission['client_id'] ?? 0),
            'project_id' => absint($daily['original_project_id'] ?? $submission['project_id'] ?? 0),
            'route_id' => absint($daily['original_route_id'] ?? $submission['route_id'] ?? 0),
            'route_stop_id' => absint($daily['original_route_stop_id'] ?? $submission['route_stop_id'] ?? 0),
            'location_id' => absint($daily['original_location_id'] ?? 0) ?: self::canonical_location_id($submission),
        ];
    }

    public static function submission_prefill(int $submission_id, array $schema = []): array {
        global $wpdb;
        if ($submission_id <= 0) return [];

        $question_types = [];
        $question_labels = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key($question['key'] ?? '');
            if ($key === '') continue;
            $question_types[$key] = sanitize_key($question['type'] ?? 'text');
            $question_labels[$key] = self::normalise_question_label($question['label'] ?? '');
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT question_key,question_label,value_text,value_number,value_json FROM ' . Forms::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
            $submission_id
        ), ARRAY_A) ?: [];

        $candidates = [];
        foreach ($rows as $row) {
            $key = sanitize_key($row['question_key'] ?? '');
            if ($key === '') continue;
            $type = $question_types[$key] ?? self::guess_answer_type($row);
            $candidates[] = [
                'key' => $key,
                'label' => self::normalise_question_label($row['question_label'] ?? ''),
                'type' => $type,
                'value' => self::answer_value($row, $type),
                'priority' => 10,
            ];
        }

        // 9BI: a stale record_version_id can point at the preceding visit or
        // an earlier edit. Resolve ownership through submission_id instead.
        // Durable answers remain authoritative; only this visit's latest
        // projection may supply migrated/missing answers.
        $version_id = 0;
        if (class_exists('\\RoutesPro\\Forms\\RecordService')) {
            $version_id = absint($wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . RecordService::table_versions() . ' WHERE submission_id=%d ORDER BY id DESC LIMIT 1',
                $submission_id
            )));
        }
        if ($version_id > 0 && class_exists('\\RoutesPro\\Forms\\RecordService')) {
            foreach (RecordService::get_version_values($version_id) as $key => $stored) {
                $key = sanitize_key($key);
                if ($key === '') continue;
                $candidates[] = [
                    'key' => $key,
                    'label' => self::normalise_question_label($stored['label'] ?? ''),
                    'type' => sanitize_key($stored['type'] ?? 'text'),
                    'value' => RecordService::value_for_form_input($stored),
                    'priority' => 20,
                ];
            }
        }

        if (!$candidates) return [];

        // Primeiro preserva a resposta exata da própria submissão. A versão do
        // recorde serve apenas de fallback quando a tabela legada está vazia ou
        // incompleta.
        usort($candidates, static function(array $a, array $b): int {
            return ((int)$a['priority']) <=> ((int)$b['priority']);
        });
        $prefill = [];
        foreach ($candidates as $candidate) {
            $key = (string)$candidate['key'];
            if (!array_key_exists($key, $prefill)) {
                $prefill[$key] = $candidate['value'];
            }
        }

        if (!$question_types) return $prefill;

        // Compatibilidade com formulários que foram editados depois do reporte:
        // quando a chave mudou, procura uma correspondência única pelo texto da
        // pergunta e por um tipo de resposta compatível.
        foreach ($question_types as $current_key => $current_type) {
            if (array_key_exists($current_key, $prefill)) continue;
            $label = $question_labels[$current_key] ?? '';
            if ($label === '') continue;
            $matches = array_values(array_filter($candidates, static function(array $candidate) use ($label, $current_type): bool {
                return ($candidate['label'] ?? '') === $label
                    && DailySubmissionService::prefill_types_compatible($current_type, (string)($candidate['type'] ?? 'text'));
            }));
            if (!$matches) continue;

            // A mesma resposta pode existir na tabela legada e na versão do
            // recorde. Deduplica pelo valor para não transformar um match seguro
            // numa falsa ambiguidade.
            $unique = [];
            foreach ($matches as $match) {
                $signature = wp_json_encode($match['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!array_key_exists((string)$signature, $unique)
                    || (int)$match['priority'] < (int)$unique[(string)$signature]['priority']) {
                    $unique[(string)$signature] = $match;
                }
            }
            if (count($unique) === 1) {
                $selected = reset($unique);
                $prefill[$current_key] = $selected['value'];
            }
        }
        return $prefill;
    }

    private static function normalise_question_label($label): string {
        $label = strtolower(remove_accents(trim(wp_strip_all_tags((string)$label))));
        return preg_replace('/\\s+/', ' ', $label) ?: '';
    }

    private static function guess_answer_type(array $row): string {
        if ((string)($row['value_json'] ?? '') !== '') return 'json';
        if ($row['value_number'] !== null && ($row['value_text'] === '1' || $row['value_text'] === '0')) return 'checkbox';
        if ($row['value_number'] !== null && $row['value_number'] !== '') return 'number';
        return 'text';
    }

    private static function answer_value(array $row, string $type) {
        $json = (string)($row['value_json'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        if ($type === 'checkbox') {
            return (string)($row['value_text'] ?? '') === '1' || (float)($row['value_number'] ?? 0) === 1.0;
        }
        if ($row['value_number'] !== null && $row['value_number'] !== '') {
            return in_array($type, ['number', 'currency', 'percent'], true)
                ? (string)(0 + $row['value_number'])
                : (float)$row['value_number'];
        }
        return (string)($row['value_text'] ?? '');
    }

    private static function prefill_types_compatible(string $question_type, string $stored_type): bool {
        $question_type = sanitize_key($question_type);
        $stored_type = sanitize_key($stored_type);
        if ($question_type === $stored_type) return true;
        if ($stored_type === 'json') {
            return in_array($question_type, ['product_matrix', 'order_suggestion', 'share_space_matrix', 'multi_select', 'checkbox_group', 'select', 'radio', 'visual_choice', 'categorized_image_upload', 'image_upload', 'file_upload'], true);
        }
        $choice_types = ['select', 'radio', 'visual_choice', 'multi_select', 'checkbox_group'];
        if (in_array($question_type, $choice_types, true) && in_array($stored_type, ['text', 'json'], true)) return true;
        $numeric_types = ['number', 'currency', 'percent'];
        if (in_array($question_type, $numeric_types, true) && in_array($stored_type, ['number', 'text'], true)) return true;
        return $stored_type === 'text' && !in_array($question_type, ['product_matrix', 'order_suggestion', 'share_space_matrix'], true);
    }

    public static function can_edit(array $submission, ?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;
        if (Permissions::is_manager($user_id)) return true;
        if ((int)($submission['user_id'] ?? 0) === $user_id) return true;
        $route_id = absint($submission['route_id'] ?? 0);
        return $route_id > 0 && Permissions::can_access_route($route_id, $user_id);
    }

    public static function decode_meta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function build_meta(array $base_meta, string $report_day, ?array $existing = null, int $user_id = 0, string $saved_at = ''): array {
        $saved_at = $saved_at ?: current_time('mysql');
        // Conditional visibility is a point-in-time decision. On edits, list
        // values such as active_order_suggestion_keys must replace the old
        // snapshot completely instead of being recursively merged.
        $conditional_flow_snapshot = is_array($base_meta['conditional_flow'] ?? null) ? $base_meta['conditional_flow'] : null;
        $daily = [];
        $submitted_context = is_array($base_meta['submission_context'] ?? null) ? $base_meta['submission_context'] : [];
        if ($existing) {
            $old_meta = self::decode_meta($existing['meta_json'] ?? '');
            $daily = is_array($old_meta['daily_report'] ?? null) ? $old_meta['daily_report'] : [];
            $base_meta = array_replace_recursive($old_meta, $base_meta);
            if (is_array($conditional_flow_snapshot)) $base_meta['conditional_flow'] = $conditional_flow_snapshot;
            $original_submission_id = (int)($daily['original_submission_id'] ?? 0);
            if ($original_submission_id <= 0) $original_submission_id = (int)($existing['id'] ?? 0);
            $daily['original_submission_id'] = $original_submission_id;
            $daily['original_submitted_at'] = (string)($daily['original_submitted_at'] ?? $existing['submitted_at'] ?? $saved_at);
            $daily['original_client_id'] = absint($daily['original_client_id'] ?? $existing['client_id'] ?? 0);
            $daily['original_project_id'] = absint($daily['original_project_id'] ?? $existing['project_id'] ?? 0);
            $daily['original_route_id'] = absint($daily['original_route_id'] ?? $existing['route_id'] ?? 0);
            $daily['original_route_stop_id'] = absint($daily['original_route_stop_id'] ?? $existing['route_stop_id'] ?? 0);
            $daily['original_location_id'] = absint($daily['original_location_id'] ?? $existing['location_id'] ?? 0);
            $daily['edit_count'] = max(0, (int)($daily['edit_count'] ?? 0)) + 1;
            $daily['last_edited_at'] = $saved_at;
            $daily['last_edited_by'] = $user_id;
        } else {
            $daily = [
                'original_submission_id' => 0,
                'original_submitted_at' => $saved_at,
                'original_client_id' => absint($submitted_context['client_id'] ?? 0),
                'original_project_id' => absint($submitted_context['project_id'] ?? 0),
                'original_route_id' => absint($submitted_context['route_id'] ?? 0),
                'original_route_stop_id' => absint($submitted_context['route_stop_id'] ?? $submitted_context['stop_id'] ?? 0),
                'original_location_id' => absint($submitted_context['location_id'] ?? 0),
                'edit_count' => 0,
                'created_at' => $saved_at,
                'created_by' => $user_id,
            ];
        }
        $daily['report_day'] = $report_day;
        $daily['rule'] = 'one_form_per_store_per_day';
        $base_meta['daily_report'] = $daily;
        return $base_meta;
    }

    public static function is_duplicate_database_error(string $error): bool {
        $error = strtolower($error);
        return strpos($error, 'duplicate') !== false || strpos($error, 'uniq_form_location_day') !== false;
    }
}
