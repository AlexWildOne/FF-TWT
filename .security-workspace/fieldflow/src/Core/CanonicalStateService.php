<?php
namespace RoutesPro\Core;

use RoutesPro\Services\SubmissionStatusPolicy;

if (!defined('ABSPATH')) exit;

class CanonicalStateService {
    public static function visit(int $routeStopId): array {
        global $wpdb;
        $routeStopId = absint($routeStopId);
        $px = $wpdb->prefix . 'routespro_';
        $stop = $routeStopId > 0 ? $wpdb->get_row($wpdb->prepare(
            "SELECT rs.*, r.client_id, r.project_id, r.date AS route_date, r.owner_user_id, r.status AS route_legacy_status
             FROM {$px}route_stops rs
             INNER JOIN {$px}routes r ON r.id=rs.route_id
             WHERE rs.id=%d LIMIT 1",
            $routeStopId
        ), ARRAY_A) : null;
        if (!$stop) {
            return DataContract::state('visit', [
                'route_stop_id' => $routeStopId,
                'status' => 'missing',
                'integrity' => 'critical',
                'issues' => ['route_stop_missing'],
            ]);
        }

        $routeId = absint($stop['route_id'] ?? 0);
        $locationId = absint($stop['location_id'] ?? 0);
        $submission = self::latestSubmissionForStop($routeStopId, $routeId, $locationId, (string)($stop['route_date'] ?? ''));
        $legacyStatus = sanitize_key((string)($stop['status'] ?? 'pending'));
        $hasSubmission = !empty($submission['id']);
        $canonicalStatus = 'pending';
        if ($legacyStatus === 'failed') {
            $canonicalStatus = 'failed';
        } elseif (in_array($legacyStatus, ['done','completed'], true) || $hasSubmission) {
            $canonicalStatus = 'completed';
        } elseif (!empty($stop['arrived_at']) || in_array($legacyStatus, ['in_progress','active','started'], true)) {
            $canonicalStatus = 'in_progress';
        }

        $issues = [];
        if ($hasSubmission) {
            if (!empty($submission['route_id']) && (int)$submission['route_id'] !== $routeId) $issues[] = 'submission_route_mismatch';
            if (!empty($submission['route_stop_id']) && (int)$submission['route_stop_id'] !== $routeStopId) $issues[] = 'submission_stop_mismatch';
            if (!empty($submission['location_id']) && (int)$submission['location_id'] !== $locationId) $issues[] = 'submission_location_mismatch';
            if (!in_array($legacyStatus, ['done','completed'], true)) $issues[] = 'submission_not_reflected_in_stop';
        }

        $expectedForm = self::expectedFormBinding([
            'client_id' => absint($stop['client_id'] ?? 0),
            'project_id' => absint($stop['project_id'] ?? 0),
            'route_id' => $routeId,
            'route_stop_id' => $routeStopId,
            'location_id' => $locationId,
        ]);
        if ($canonicalStatus === 'completed' && $expectedForm && !$hasSubmission) $issues[] = 'completed_without_required_submission';

        $evidence = $hasSubmission ? self::evidenceState((int)$submission['id'], (int)($submission['form_id'] ?? 0)) : [
            'required' => 0,
            'answered' => 0,
            'complete' => $expectedForm ? false : true,
            'missing_keys' => [],
        ];
        if ($hasSubmission && !$evidence['complete']) $issues[] = 'required_evidence_missing';

        $integrity = 'valid';
        if (array_intersect($issues, ['submission_route_mismatch','submission_stop_mismatch','submission_location_mismatch'])) $integrity = 'critical';
        elseif ($issues) $integrity = 'warning';

        return DataContract::state('visit', [
            'route_stop_id' => $routeStopId,
            'route_id' => $routeId,
            'location_id' => $locationId,
            'client_id' => absint($stop['client_id'] ?? 0),
            'project_id' => absint($stop['project_id'] ?? 0),
            'owner_user_id' => absint($stop['owner_user_id'] ?? 0),
            'route_date' => (string)($stop['route_date'] ?? ''),
            'status' => $canonicalStatus,
            'legacy_status' => $legacyStatus,
            'form_expected' => (bool)$expectedForm,
            'expected_form_id' => absint($expectedForm['form_id'] ?? 0),
            'form_submitted' => $hasSubmission,
            'submission_id' => absint($submission['id'] ?? 0),
            'submitted_at' => (string)($submission['submitted_at'] ?? ''),
            'evidence' => $evidence,
            'integrity' => $integrity,
            'issues' => array_values(array_unique($issues)),
            'timestamps' => [
                'arrived_at' => $stop['arrived_at'] ?? null,
                'departed_at' => $stop['departed_at'] ?? null,
            ],
        ]);
    }

    public static function route(int $routeId): array {
        global $wpdb;
        $routeId = absint($routeId);
        $px = $wpdb->prefix . 'routespro_';
        $route = $routeId > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) : null;
        if (!$route) {
            return DataContract::state('route', [
                'route_id' => $routeId,
                'status' => 'missing',
                'integrity' => 'critical',
                'issues' => ['route_missing'],
            ]);
        }

        $stopIds = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
            $routeId
        )) ?: [])));
        $counts = ['total' => count($stopIds), 'completed' => 0, 'in_progress' => 0, 'failed' => 0, 'pending' => 0];
        $issues = [];
        foreach ($stopIds as $stopId) {
            $contract = self::visit($stopId);
            $state = (array)($contract['data'] ?? []);
            $status = sanitize_key((string)($state['status'] ?? 'pending'));
            if (isset($counts[$status])) $counts[$status]++;
            else $counts['pending']++;
            foreach ((array)($state['issues'] ?? []) as $issue) {
                $issues[] = ['route_stop_id' => $stopId, 'code' => sanitize_key((string)$issue)];
            }
        }

        $legacyStatus = sanitize_key((string)($route['status'] ?? 'draft'));
        $canonicalStatus = $legacyStatus;
        if (!in_array($legacyStatus, ['cancelled','canceled'], true)) {
            if ($counts['total'] > 0 && ($counts['completed'] + $counts['failed']) >= $counts['total']) $canonicalStatus = 'completed';
            elseif ($counts['completed'] > 0 || $counts['failed'] > 0 || $counts['in_progress'] > 0) $canonicalStatus = 'in_progress';
            elseif ($counts['total'] > 0) $canonicalStatus = 'planned';
            else $canonicalStatus = 'draft';
        }
        if ($canonicalStatus !== $legacyStatus) $issues[] = ['route_id' => $routeId, 'code' => 'route_status_mismatch'];

        $integrity = 'valid';
        foreach ($issues as $issue) {
            if (strpos((string)($issue['code'] ?? ''), 'mismatch') !== false && (string)($issue['code'] ?? '') !== 'route_status_mismatch') {
                $integrity = 'critical';
                break;
            }
            $integrity = 'warning';
        }

        return DataContract::state('route', [
            'route_id' => $routeId,
            'client_id' => absint($route['client_id'] ?? 0),
            'project_id' => absint($route['project_id'] ?? 0),
            'owner_user_id' => absint($route['owner_user_id'] ?? 0),
            'date' => (string)($route['date'] ?? ''),
            'status' => $canonicalStatus,
            'legacy_status' => $legacyStatus,
            'counts' => $counts,
            'completion_rate' => $counts['total'] > 0 ? round(($counts['completed'] / $counts['total']) * 100, 1) : 0,
            'integrity' => $integrity,
            'issues' => $issues,
        ]);
    }

    public static function submission(int $submissionId): array {
        global $wpdb;
        $submissionId = absint($submissionId);
        $px = $wpdb->prefix . 'routespro_';
        $row = $submissionId > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}form_submissions WHERE id=%d LIMIT 1", $submissionId), ARRAY_A) : null;
        if (!$row) {
            return DataContract::state('submission', [
                'submission_id' => $submissionId,
                'status' => 'missing',
                'integrity' => 'critical',
                'issues' => ['submission_missing'],
            ]);
        }
        $issues = [];
        $routeId = absint($row['route_id'] ?? 0);
        $stopId = absint($row['route_stop_id'] ?? 0);
        $locationId = absint($row['location_id'] ?? 0);
        if ($stopId <= 0 && $routeId > 0 && $locationId > 0) $issues[] = 'route_stop_unlinked';
        $stop = $stopId > 0 ? $wpdb->get_row($wpdb->prepare("SELECT route_id,location_id FROM {$px}route_stops WHERE id=%d LIMIT 1", $stopId), ARRAY_A) : null;
        if ($stopId > 0 && !$stop) $issues[] = 'route_stop_missing';
        if ($stop) {
            if ($routeId > 0 && (int)$stop['route_id'] !== $routeId) $issues[] = 'submission_route_mismatch';
            if ($locationId > 0 && (int)$stop['location_id'] !== $locationId) $issues[] = 'submission_location_mismatch';
        }
        if ($routeId > 0 && !(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}routes WHERE id=%d", $routeId))) $issues[] = 'route_missing';
        if ($locationId > 0 && !(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}locations WHERE id=%d", $locationId))) $issues[] = 'location_missing';
        $evidence = self::evidenceState($submissionId, absint($row['form_id'] ?? 0));
        if (!$evidence['complete']) $issues[] = 'required_evidence_missing';
        $integrity = !$issues ? 'valid' : (array_intersect($issues, ['route_stop_missing','submission_route_mismatch','submission_location_mismatch','route_missing','location_missing']) ? 'critical' : 'warning');

        return DataContract::state('submission', [
            'submission_id' => $submissionId,
            'form_id' => absint($row['form_id'] ?? 0),
            'client_id' => absint($row['client_id'] ?? 0),
            'project_id' => absint($row['project_id'] ?? 0),
            'route_id' => $routeId,
            'route_stop_id' => $stopId,
            'location_id' => $locationId,
            'user_id' => absint($row['user_id'] ?? 0),
            'status' => sanitize_key((string)($row['status'] ?? 'submitted')),
            'report_day' => (string)($row['report_day'] ?? ''),
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'evidence' => $evidence,
            'integrity' => $integrity,
            'issues' => array_values(array_unique($issues)),
        ]);
    }

    private static function latestSubmissionForStop(int $stopId, int $routeId, int $locationId, string $routeDate): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $routeDate) ? $routeDate : current_time('Y-m-d');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$px}form_submissions
             WHERE " . SubmissionStatusPolicy::operationalSql() . "
               AND (
                    route_stop_id=%d
                    OR (
                        location_id=%d
                        AND route_id=%d
                        AND COALESCE(route_stop_id,0)=0
                        AND NOT EXISTS (
                            SELECT 1
                            FROM {$px}route_stops rs_same
                            WHERE rs_same.route_id=%d
                              AND rs_same.location_id=%d
                              AND rs_same.id<>%d
                        )
                    )
                    OR (
                        location_id=%d
                        AND COALESCE(route_id,0)=0
                        AND (report_day=%s OR (report_day IS NULL AND DATE(submitted_at)=%s))
                        AND NOT EXISTS (
                            SELECT 1
                            FROM {$px}route_stops rs_other
                            INNER JOIN {$px}routes r_other ON r_other.id=rs_other.route_id
                            WHERE rs_other.location_id=%d
                              AND r_other.date=%s
                              AND r_other.id<>%d
                        )
                    )
               )
             ORDER BY CASE WHEN route_stop_id=%d THEN 0 WHEN route_id=%d THEN 1 ELSE 2 END, id DESC
             LIMIT 1",
            $stopId, $locationId, $routeId, $routeId, $locationId, $stopId, $locationId, $routeDate, $routeDate, $locationId, $routeDate, $routeId, $stopId, $routeId
        ), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    private static function expectedFormBinding(array $context): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_form_bindings';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE is_active=1
               AND (client_id IS NULL OR client_id=0 OR client_id=%d)
               AND (project_id IS NULL OR project_id=0 OR project_id=%d)
               AND (route_id IS NULL OR route_id=0 OR route_id=%d)
               AND (stop_id IS NULL OR stop_id=0 OR stop_id=%d)
               AND (location_id IS NULL OR location_id=0 OR location_id=%d)
               AND mode IN ('route_and_form','form_only')
             ORDER BY
               (CASE WHEN stop_id=%d THEN 32 ELSE 0 END) +
               (CASE WHEN route_id=%d THEN 16 ELSE 0 END) +
               (CASE WHEN location_id=%d THEN 8 ELSE 0 END) +
               (CASE WHEN project_id=%d THEN 4 ELSE 0 END) +
               (CASE WHEN client_id=%d THEN 2 ELSE 0 END) DESC,
               priority ASC,id DESC LIMIT 1",
            absint($context['client_id'] ?? 0), absint($context['project_id'] ?? 0), absint($context['route_id'] ?? 0), absint($context['route_stop_id'] ?? 0), absint($context['location_id'] ?? 0),
            absint($context['route_stop_id'] ?? 0), absint($context['route_id'] ?? 0), absint($context['location_id'] ?? 0), absint($context['project_id'] ?? 0), absint($context['client_id'] ?? 0)
        ), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    private static function evidenceState(int $submissionId, int $formId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        if ($submissionId <= 0 || $formId <= 0) return ['required' => 0, 'answered' => 0, 'complete' => true, 'missing_keys' => []];
        $schemaRaw = $wpdb->get_var($wpdb->prepare("SELECT schema_json FROM {$px}forms WHERE id=%d LIMIT 1", $formId));
        $schema = is_string($schemaRaw) ? json_decode($schemaRaw, true) : [];
        $requiredKeys = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question) || empty($question['required'])) continue;
            $type = sanitize_key((string)($question['type'] ?? ''));
            if (!in_array($type, ['image_upload','categorized_image_upload','file_upload','signature'], true)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '') $requiredKeys[] = $key;
        }
        if (!$requiredKeys) return ['required' => 0, 'answered' => 0, 'complete' => true, 'missing_keys' => []];
        $placeholders = implode(',', array_fill(0, count($requiredKeys), '%s'));
        $args = array_merge([$submissionId], $requiredKeys);
        $sql = $wpdb->prepare(
            "SELECT question_key,value_text,value_json,value_number FROM {$px}form_submission_answers WHERE submission_id=%d AND question_key IN ({$placeholders})",
            ...$args
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $answered = [];
        foreach ($rows as $row) {
            $has = trim((string)($row['value_text'] ?? '')) !== '' || trim((string)($row['value_json'] ?? '')) !== '' || $row['value_number'] !== null;
            if ($has) $answered[] = sanitize_key((string)$row['question_key']);
        }
        $missing = array_values(array_diff(array_values(array_unique($requiredKeys)), array_values(array_unique($answered))));
        return [
            'required' => count(array_unique($requiredKeys)),
            'answered' => count(array_unique($answered)),
            'complete' => !$missing,
            'missing_keys' => $missing,
        ];
    }
}
