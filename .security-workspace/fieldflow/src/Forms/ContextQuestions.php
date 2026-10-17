<?php
namespace RoutesPro\Forms;

use RoutesPro\Services\SubmissionStatusPolicy;

if (!defined('ABSPATH')) exit;

class ContextQuestions {
    private static $mappedBriefingLocations = [];
    private static $executionSnapshots = [];

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_report_context_questions';
    }

    private static function activeCampaignLocationScope(int $projectId): array {
        global $wpdb;
        if ($projectId <= 0) return ['explicit' => false, 'ids' => []];
        $table = $wpdb->prefix . 'routespro_campaign_locations';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return ['explicit' => false, 'ids' => []];
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE project_id=%d", $projectId));
        if ($total <= 0) return ['explicit' => false, 'ids' => []];
        $ids = array_values(array_unique(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM {$table} WHERE project_id=%d AND is_active=1 AND location_id>0",
            $projectId
        )) ?: []))));
        return ['explicit' => true, 'ids' => $ids];
    }

    public static function for_context(array $context): array {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];

        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        $locationId = absint($context['location_id'] ?? 0);
        $formId = absint($context['form_id'] ?? 0);
        $campaignLocationId = absint($context['campaign_location_id'] ?? 0);
        if (!$campaignLocationId && $projectId && $locationId) {
            $px = $wpdb->prefix . 'routespro_';
            $campaignLocationId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$px}campaign_locations WHERE project_id=%d AND location_id=%d AND is_active=1 LIMIT 1", $projectId, $locationId));
        }

        $hasFormId = (bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'form_id'));
        $hasValidity = (bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'valid_from'));
        $where = ['is_active = 1'];
        $args = [];
        if ($hasValidity) {
            $contextDate = sanitize_text_field((string)($context['date'] ?? $context['route_date'] ?? current_time('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contextDate)) $contextDate = current_time('Y-m-d');
            $where[] = "(valid_from IS NULL OR valid_from='0000-00-00' OR valid_from<=%s)";
            $args[] = $contextDate;
            $where[] = "(valid_to IS NULL OR valid_to='0000-00-00' OR valid_to>=%s)";
            $args[] = $contextDate;
        }
        foreach ([
            'client_id' => $clientId,
            'project_id' => $projectId,
            'location_id' => $locationId,
            'campaign_location_id' => $campaignLocationId,
        ] as $field => $value) {
            if ($value > 0) {
                $where[] = "({$field}=0 OR {$field}=%d)";
                $args[] = $value;
            } else {
                $where[] = "{$field}=0";
            }
        }
        if ($hasFormId) {
            if ($formId > 0) {
                $where[] = '(form_id=0 OR form_id=%d)';
                $args[] = $formId;
            } else {
                $where[] = 'form_id=0';
            }
        }

        $specificity = $hasFormId
            ? '((client_id>0) + (project_id>0) + (location_id>0) + (campaign_location_id>0) + (form_id>0))'
            : '((client_id>0) + (project_id>0) + (location_id>0) + (campaign_location_id>0))';
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY
            {$specificity} DESC,
            priority ASC,
            id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = sanitize_key($row['question_key'] ?? '');
            if (!$key) $key = 'ctxq_' . (int)($row['id'] ?? 0);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $row['question_key'] = $key;
            $out[] = $row;
        }
        return $out;
    }

    public static function regular_for_context(array $context): array {
        return array_values(array_filter(self::for_context($context), static function($row) {
            return sanitize_key((string)($row['source_type'] ?? 'manual')) !== 'briefing' && empty($row['briefing_id']);
        }));
    }

    public static function briefing_for_context(array $context): array {
        $locationId = absint($context['location_id'] ?? $context['store_id'] ?? 0);
        if ($locationId <= 0) return [];
        $projectId = absint($context['project_id'] ?? 0);
        $campaignScope = self::activeCampaignLocationScope($projectId);
        if (!empty($campaignScope['explicit']) && !in_array($locationId, (array)$campaignScope['ids'], true)) return [];

        return array_values(array_filter(self::for_context($context), static function($row) use ($locationId) {
            $isBriefing = sanitize_key((string)($row['source_type'] ?? '')) === 'briefing' || !empty($row['briefing_id']);
            if (!$isBriefing) return false;

            // A linha gravada continua a ser específica da loja da visita. Os modos
            // históricos exigem mapping no ficheiro; os novos modos *_active usam
            // a carteira ativa do projeto/campanha e podem incluir lojas não mapeadas.
            if (absint($row['location_id'] ?? 0) !== $locationId) return false;
            $briefingId = absint($row['briefing_id'] ?? 0);
            if ($briefingId <= 0) return false;
            if (self::briefingRowAllowsUnmapped((array)$row)) return true;
            return in_array($locationId, self::briefingMappedLocationIds($briefingId), true);
        }));
    }

    public static function inject_into_schema(array $schema, array $questions): array {
        if (!$questions) return $schema;
        if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = [];
        $existing = [];
        foreach ($schema['questions'] as $q) {
            if (is_array($q) && !empty($q['key'])) $existing[sanitize_key($q['key'])] = true;
        }
        $regularKeys = [];
        foreach ($questions as $row) {
            if (sanitize_key((string)($row['source_type'] ?? 'manual')) === 'briefing' || !empty($row['briefing_id'])) continue;
            $q = self::row_to_schema_question((array)$row);
            if (!$q || isset($existing[$q['key']])) continue;
            $schema['questions'][] = $q;
            $existing[$q['key']] = true;
            $regularKeys[] = $q['key'];
        }
        if ($regularKeys && !empty($schema['layout']) && is_array($schema['layout']) && ($schema['layout']['mode'] ?? '') === 'steps') {
            if (empty($schema['layout']['steps']) || !is_array($schema['layout']['steps'])) $schema['layout']['steps'] = [];
            $schema['layout']['steps'][] = [
                'title' => 'Perguntas da loja',
                'description' => 'Campos específicos configurados para este cliente, campanha, formulário ou local.',
                'fields' => $regularKeys,
                'source' => 'context',
            ];
        }
        return $schema;
    }

    public static function row_to_schema_question(array $row): array {
        $key = sanitize_key($row['question_key'] ?? ('ctxq_' . (int)($row['id'] ?? 0)));
        if (!$key) return [];
        $type = sanitize_key($row['question_type'] ?? 'select');
        $allowed = ['text','textarea','number','currency','percent','date','time','select','radio','checkbox','share_space_matrix','evidence_check','image_upload','categorized_image_upload','file_upload'];
        if (!in_array($type, $allowed, true)) $type = 'select';
        $options = [];
        $raw = (string)($row['question_options_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $options = array_values(array_filter(array_map('strval', $decoded), static fn($v) => trim($v) !== ''));
        }
        if (($type === 'select' || $type === 'radio') && !$options) $options = ['Sim', 'Não', 'Não aplicável'];
        if ($type === 'categorized_image_upload' && !$options) $options = ['Expositores', 'Arcas', 'Displays', 'Topos'];
        $questionConfig = [];
        if (!empty($row['question_config_json'])) {
            $questionConfig = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig((string)$row['question_config_json']);
        }
        $visibilityRules = json_decode((string)($row['visibility_rules_json'] ?? ''), true);
        if (!is_array($visibilityRules)) $visibilityRules = [];
        $targetScope = sanitize_key((string)($visibilityRules['target_scope'] ?? 'section')) ?: 'section';
        $targetScopeLabel = class_exists('\RoutesPro\Briefings\BriefingService')
            ? \RoutesPro\Briefings\BriefingService::questionScopeLabel($targetScope)
            : $targetScope;
        return [
            'key' => $key,
            'label' => sanitize_text_field((string)($row['question_label'] ?? $key)),
            'type' => $type,
            'required' => !empty($row['is_required']),
            'options' => $options,
            'question_config' => $questionConfig,
            'share_space_rows' => (array)($questionConfig['rows'] ?? []),
            'help_text' => sanitize_text_field((string)($row['help_text'] ?? '')),
            'product_category' => sanitize_text_field((string)($visibilityRules['product_category'] ?? '')),
            'product_category_source' => sanitize_key((string)($visibilityRules['category_source'] ?? '')),
            'source' => ((sanitize_key((string)($row['source_type'] ?? 'manual')) === 'briefing' || !empty($row['briefing_id'])) ? 'briefing_question' : 'context_question'),
            'context_question_id' => (int)($row['id'] ?? 0),
            'context_type' => sanitize_key((string)($row['context_type'] ?? 'custom')),
            'form_id' => (int)($row['form_id'] ?? 0),
            'source_type' => sanitize_key((string)($row['source_type'] ?? 'manual')) ?: 'manual',
            'briefing_id' => (int)($row['briefing_id'] ?? 0),
            'briefing_version_no' => (int)($row['briefing_version_no'] ?? 0),
            'briefing_section_id' => (int)($row['briefing_section_id'] ?? 0),
            'briefing_item_key' => sanitize_text_field((string)($row['briefing_item_key'] ?? '')),
            // 9DX: layout metadata only, answer key stays identical.
            'briefing_step_key' => sanitize_key((string)($visibilityRules['briefing_step_key'] ?? '')),
            'briefing_title' => sanitize_text_field((string)($row['briefing_title'] ?? '')),
            'target_scope' => $targetScope,
            'target_scope_label' => $targetScopeLabel,
            'valid_from' => sanitize_text_field((string)($row['valid_from'] ?? '')),
            'valid_to' => sanitize_text_field((string)($row['valid_to'] ?? '')),
            'analytics_enabled' => !empty($row['analytics_enabled']),
            'analytics_dashboard_id' => (int)($row['analytics_dashboard_id'] ?? 0),
            'analytics_metric_key' => sanitize_key((string)($row['analytics_metric_key'] ?? '')),
            'analytics_metric_label' => sanitize_text_field((string)($row['analytics_metric_label'] ?? '')),
            'analytics_chart_type' => sanitize_key((string)($row['analytics_chart_type'] ?? '')),
            'analytics_aggregation' => sanitize_key((string)($row['analytics_aggregation'] ?? '')),
            'analytics_dimension' => sanitize_key((string)($row['analytics_dimension'] ?? '')),
            'analytics_secondary_dimension' => sanitize_key((string)($row['analytics_secondary_dimension'] ?? '')),
            'analytics_sort_order' => (int)($row['analytics_sort_order'] ?? $row['priority'] ?? 10),
        ];
    }

    public static function for_briefing(int $briefingId, array $locationIds = [], bool $includeInactive = false): array {
        global $wpdb;
        $table = self::table();
        if (!$briefingId || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $hasBriefing = (bool)$wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'briefing_id'));
        if (!$hasBriefing) return [];
        $where = ['q.briefing_id=%d'];
        if (!$includeInactive) $where[] = 'q.is_active=1';
        $args = [$briefingId];
        $locationIds = array_values(array_unique(array_filter(array_map('absint', $locationIds))));
        $briefingTable = $wpdb->prefix . 'routespro_briefings';
        $projectId = (int)$wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$briefingTable} WHERE id=%d LIMIT 1", $briefingId));
        $campaignScope = self::activeCampaignLocationScope($projectId);
        if (!empty($campaignScope['explicit'])) {
            $activeIds = (array)$campaignScope['ids'];
            $locationIds = $locationIds ? array_values(array_intersect($locationIds, $activeIds)) : $activeIds;
            if (!$locationIds) return [];
        }

        // O âmbito pedido e a carteira ativa continuam a limitar a consulta. O mapping
        // só é obrigatório para os modos históricos; perguntas *_active podem atingir
        // lojas ativas que não constam do ficheiro semanal.
        if ($locationIds) {
            $where[] = 'q.location_id IN (' . implode(',', array_fill(0, count($locationIds), '%d')) . ')';
            $args = array_merge($args, $locationIds);
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT q.*,f.title AS form_title,l.name AS location_name FROM {$table} q LEFT JOIN {$wpdb->prefix}routespro_forms f ON f.id=q.form_id LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=q.location_id WHERE " . implode(' AND ', $where) . " ORDER BY q.priority ASC,q.id ASC",
            ...$args
        ), ARRAY_A) ?: [];
        if (!$rows) return [];

        $mappedIds = self::briefingMappedLocationIds($briefingId);
        $mappedSet = array_fill_keys(array_map('intval', $mappedIds), true);
        return array_values(array_filter($rows, static function(array $row) use ($mappedSet): bool {
            if (self::briefingRowAllowsUnmapped($row)) return true;
            $locationId = absint($row['location_id'] ?? 0);
            return $locationId > 0 && !empty($mappedSet[$locationId]);
        }));
    }

    public static function execution_stats(int $briefingId, array $locationIds = []): array {
        $snapshot = self::execution_snapshot($briefingId, $locationIds);
        return (array)($snapshot['stats'] ?? []);
    }

    public static function execution_states(int $briefingId, array $locationIds = []): array {
        $snapshot = self::execution_snapshot($briefingId, $locationIds);
        return (array)($snapshot['states'] ?? []);
    }

    private static function execution_snapshot(int $briefingId, array $locationIds = []): array {
        global $wpdb;
        $locationIds = array_values(array_unique(array_filter(array_map('absint', $locationIds))));
        sort($locationIds, SORT_NUMERIC);
        $cacheKey = $briefingId . '|' . implode(',', $locationIds);
        if (isset(self::$executionSnapshots[$cacheKey])) return self::$executionSnapshots[$cacheKey];

        $questions = self::for_briefing($briefingId, $locationIds);
        $keys = array_values(array_unique(array_filter(array_map(static fn($row) => sanitize_key((string)($row['question_key'] ?? '')), $questions))));
        $storeIds = array_values(array_unique(array_filter(array_map(static fn($row) => absint($row['location_id'] ?? 0), $questions))));
        $assignments = [];
        $states = [];
        foreach ($questions as $question) {
            $key = sanitize_key((string)($question['question_key'] ?? ''));
            $locationId = absint($question['location_id'] ?? 0);
            if ($key === '' || $locationId <= 0) continue;
            $assignmentKey = $key . '|' . $locationId;
            if (isset($assignments[$assignmentKey])) continue;
            $assignments[$assignmentKey] = [
                'question_key' => $key,
                'location_id' => $locationId,
                'location_name' => sanitize_text_field((string)($question['location_name'] ?? '')),
            ];
            if (!isset($states[$key])) {
                $states[$key] = [
                    'question_key' => $key,
                    'assignments' => 0,
                    'answered' => 0,
                    'implemented' => 0,
                    'not_implemented' => 0,
                    'not_applicable' => 0,
                    'other_answered' => 0,
                    'pending' => 0,
                    'state' => 'pending',
                    'location_states' => [],
                ];
            }
            $states[$key]['assignments']++;
        }

        $stats = [
            'questions' => count($keys),
            'assignments' => count($assignments),
            'mapped_stores' => count($storeIds),
            'answers' => 0,
            'submissions' => 0,
            'conforming' => 0,
            'non_conforming' => 0,
            'not_applicable' => 0,
            'pending' => count($assignments),
        ];
        if (!$keys || !$assignments) {
            return self::$executionSnapshots[$cacheKey] = ['stats' => $stats, 'states' => $states];
        }

        $answers = $wpdb->prefix . 'routespro_form_submission_answers';
        $submissions = $wpdb->prefix . 'routespro_form_submissions';
        $keyPlaceholders = implode(',', array_fill(0, count($keys), '%s'));
        $where = ["a.question_key IN ({$keyPlaceholders})"];
        $args = $keys;
        $where[] = 's.location_id IN (' . implode(',', array_fill(0, count($storeIds), '%d')) . ')';
        $args = array_merge($args, $storeIds);
        $where[] = SubmissionStatusPolicy::operationalSql('s');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id AS answer_id,a.question_key,a.value_text,a.value_number,a.value_json,a.submission_id,s.location_id,s.submitted_at
             FROM {$answers} a
             INNER JOIN {$submissions} s ON s.id=a.submission_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.submitted_at DESC,s.id DESC,a.id DESC",
            ...$args
        ), ARRAY_A) ?: [];

        $latest = [];
        foreach ($rows as $row) {
            $key = sanitize_key((string)($row['question_key'] ?? ''));
            $locationId = absint($row['location_id'] ?? 0);
            $assignmentKey = $key . '|' . $locationId;
            if (!isset($assignments[$assignmentKey]) || isset($latest[$assignmentKey])) continue;
            $latest[$assignmentKey] = $row;
        }

        $submissionIds = [];
        foreach ($assignments as $assignmentKey => $assignment) {
            $key = $assignment['question_key'];
            $row = $latest[$assignmentKey] ?? [];
            $value = self::answerDisplayValue($row);
            $classification = $row ? self::classifyExecutionAnswer($value) : 'pending';
            $locationState = [
                'location_id' => (int)$assignment['location_id'],
                'location_name' => (string)$assignment['location_name'],
                'state' => $classification,
                'answer' => $value,
                'submission_id' => absint($row['submission_id'] ?? 0),
                'submitted_at' => sanitize_text_field((string)($row['submitted_at'] ?? '')),
            ];
            $states[$key]['location_states'][] = $locationState;

            if ($classification === 'pending') {
                $states[$key]['pending']++;
                continue;
            }
            $states[$key]['answered']++;
            $stats['answers']++;
            $submissionId = absint($row['submission_id'] ?? 0);
            if ($submissionId) $submissionIds[$submissionId] = true;
            if ($classification === 'implemented') {
                $states[$key]['implemented']++;
                $stats['conforming']++;
            } elseif ($classification === 'not_implemented') {
                $states[$key]['not_implemented']++;
                $stats['non_conforming']++;
            } elseif ($classification === 'not_applicable') {
                $states[$key]['not_applicable']++;
                $stats['not_applicable']++;
            } else {
                $states[$key]['other_answered']++;
            }
        }

        foreach ($states as &$state) {
            $state['pending'] = max(0, (int)$state['assignments'] - (int)$state['answered']);
            if ((int)$state['not_implemented'] > 0) $state['state'] = 'attention';
            elseif ((int)$state['pending'] > 0 && (int)$state['answered'] > 0) $state['state'] = 'partial';
            elseif ((int)$state['pending'] > 0) $state['state'] = 'pending';
            elseif ((int)$state['not_applicable'] === (int)$state['assignments'] && (int)$state['assignments'] > 0) $state['state'] = 'not_applicable';
            elseif ((int)$state['implemented'] > 0) $state['state'] = 'implemented';
            else $state['state'] = 'answered';
        }
        unset($state);

        $stats['submissions'] = count($submissionIds);
        $stats['pending'] = max(0, count($assignments) - $stats['answers']);
        return self::$executionSnapshots[$cacheKey] = ['stats' => $stats, 'states' => $states];
    }

    private static function briefingRowAllowsUnmapped(array $row): bool {
        $rules = json_decode((string)($row['visibility_rules_json'] ?? ''), true);
        if (!is_array($rules)) $rules = [];
        $scope = sanitize_key((string)($rules['target_scope'] ?? 'section')) ?: 'section';
        if (class_exists('\RoutesPro\Briefings\BriefingService')) {
            return \RoutesPro\Briefings\BriefingService::questionScopeAllowsUnmapped($scope);
        }
        return in_array($scope, ['retailer_active','project_active','selected_active'], true);
    }

    private static function briefingMappedLocationIds(int $briefingId): array {
        global $wpdb;
        $briefingId = absint($briefingId);
        if ($briefingId <= 0) return [];
        if (isset(self::$mappedBriefingLocations[$briefingId])) return self::$mappedBriefingLocations[$briefingId];
        $items = $wpdb->prefix . 'routespro_briefing_store_items';
        $aliases = $wpdb->prefix . 'routespro_briefing_store_aliases';
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM {$items} WHERE briefing_id=%d AND location_id>0
             UNION
             SELECT a.location_id FROM {$aliases} a
             INNER JOIN {$items} si ON si.alias_id=a.id AND si.briefing_id=%d
             WHERE a.location_id>0",
            $briefingId,
            $briefingId
        )) ?: [];
        return self::$mappedBriefingLocations[$briefingId] = array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private static function answerDisplayValue(array $row): string {
        if (!$row) return '';
        $text = trim((string)($row['value_text'] ?? ''));
        if ($text !== '') return $text;
        if (isset($row['value_number']) && $row['value_number'] !== null && $row['value_number'] !== '') return (string)$row['value_number'];
        $json = trim((string)($row['value_json'] ?? ''));
        if ($json === '') return '';
        $decoded = json_decode($json, true);
        if (is_scalar($decoded)) return trim((string)$decoded);
        if (is_array($decoded)) {
            $flat = [];
            array_walk_recursive($decoded, static function($value) use (&$flat) {
                if (is_scalar($value) && trim((string)$value) !== '') $flat[] = trim((string)$value);
            });
            return implode(', ', array_slice($flat, 0, 12));
        }
        return '';
    }

    private static function classifyExecutionAnswer(string $value): string {
        $normal = trim($value);
        if ($normal === '') return 'pending';
        if (function_exists('remove_accents')) $normal = remove_accents($normal);
        $normal = function_exists('mb_strtolower') ? mb_strtolower($normal, 'UTF-8') : strtolower($normal);
        $normal = trim(preg_replace('/\\s+/u', ' ', $normal));
        if (in_array($normal, ['n/a','na','nao aplicavel','not applicable'], true)) return 'not_applicable';
        if (in_array($normal, ['sim','yes','true','1','conforme','implementado','implementada','feito','feita','ok'], true)) return 'implemented';
        if (in_array($normal, ['nao','no','false','0','nao conforme','nao implementado','nao implementada'], true)) return 'not_implemented';
        return 'answered';
    }

    public static function make_briefing_question_key(int $briefingId, int $sectionId, string $label, int $formId = 0): string {
        $slug = sanitize_key(substr(remove_accents($label), 0, 62));
        if (!$slug) $slug = 'pergunta';
        return sanitize_key('briefing_' . max(0, $briefingId) . '_' . ($sectionId ?: 'geral') . '_' . ($formId ?: 'todos') . '_' . $slug);
    }

    public static function make_question_key(string $contextType, string $label, int $locationId = 0): string {
        $base = sanitize_key($contextType ?: 'custom');
        $slug = sanitize_key(substr(remove_accents($label), 0, 70));
        if (!$slug) $slug = 'pergunta';
        return sanitize_key('ctx_' . $base . '_' . ($locationId ? $locationId . '_' : '') . $slug);
    }
}
