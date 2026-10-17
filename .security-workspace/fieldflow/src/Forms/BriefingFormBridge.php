<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Expands a builder placeholder (briefing_block) into the approved briefing
 * execution questions that apply to the current associated store and active date.
 *
 * The placeholder is never submitted. Only the generated questions are stored,
 * using the same answer tables as every other FieldFlow form question.
 */
class BriefingFormBridge {
    public static function expand_blocks(array $schema, array $context): array {
        $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
        if (!$questions) return $schema;

        $blocks = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            if (sanitize_key((string)($question['type'] ?? '')) !== 'briefing_block') continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key) $blocks[$key] = $question;
        }
        if (!$blocks) return $schema;

        $rows = ContextQuestions::briefing_for_context($context);
        $metadata = self::briefing_metadata($rows);
        $usedRows = [];
        $replacementMap = [];
        $replacementStepMap = [];
        $expandedQuestions = [];

        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($type !== 'briefing_block') {
                $expandedQuestions[] = $question;
                continue;
            }

            $selected = self::select_rows_for_block($rows, $question, $usedRows, $metadata, $context);
            $replacementKeys = [];
            $blockStepGroups = [];
            $briefingSegments = [];
            if ($selected) {
                $grouped = [];
                foreach ($selected as $row) {
                    $bid = absint($row['briefing_id'] ?? 0);
                    if (!$bid) continue;
                    $grouped[$bid][] = $row;
                    $usedRows[(int)($row['id'] ?? 0)] = true;
                }
                foreach ($grouped as $briefingId => $groupRows) {
                    $meta = $metadata[$briefingId] ?? [];
                    $introKey = '';
                    $entries = [];
                    if (!array_key_exists('briefing_show_context', $question) || !empty($question['briefing_show_context'])) {
                        $introKey = sanitize_key($key . '_intro_' . $briefingId);
                        $expandedQuestions[] = [
                            'key' => $introKey,
                            'label' => sanitize_text_field((string)($question['label'] ?? 'Briefing desta semana')),
                            'type' => 'briefing_intro',
                            'required' => false,
                            'briefing_id' => $briefingId,
                            'briefing_title' => sanitize_text_field((string)($meta['title'] ?? ('Briefing #' . $briefingId))),
                            'briefing_week' => (int)($meta['week_no'] ?? 0),
                            'briefing_year' => (int)($meta['year_no'] ?? 0),
                            'valid_from' => sanitize_text_field((string)($meta['valid_from'] ?? '')),
                            'valid_to' => sanitize_text_field((string)($meta['valid_to'] ?? '')),
                            'help_text' => sanitize_text_field((string)($question['help_text'] ?? 'Responde apenas às perguntas atribuídas à loja desta visita.')),
                        ];
                        $replacementKeys[] = $introKey;
                    }
                    foreach ($groupRows as $row) {
                        $dynamic = ContextQuestions::row_to_schema_question($row);
                        if (!$dynamic) continue;
                        $dynamic['source'] = 'briefing_question';
                        $dynamic['briefing_block_key'] = $key;
                        $expandedQuestions[] = $dynamic;
                        $dynamicKey = sanitize_key((string)$dynamic['key']);
                        $replacementKeys[] = $dynamicKey;
                        $entries[] = ['key'=>$dynamicKey, 'briefing_step_key'=>sanitize_key((string)($dynamic['briefing_step_key'] ?? ''))];
                    }
                    $groups = self::stepGroupsForBriefing((int)$briefingId, $entries, $introKey);
                    $briefingSegments[] = [
                        'groups'=>$groups,
                        'keys'=>array_values(array_filter(array_merge($introKey !== '' ? [$introKey] : [], array_column($entries,'key')))),
                        'title'=>sanitize_text_field((string)($meta['title'] ?? 'Perguntas do briefing')),
                    ];
                    if ($groups) foreach ($groups as $groupStep) $blockStepGroups[] = $groupStep;
                }
            }
            $replacementMap[$key] = array_values(array_filter($replacementKeys));
            if ($blockStepGroups) {
                // If multiple published briefings share a block, keep the others visible.
                $allGroups = [];
                foreach ($briefingSegments as $segment) {
                    if ($segment['groups']) {
                        foreach ($segment['groups'] as $step) $allGroups[] = $step;
                    } elseif ($segment['keys']) {
                        $allGroups[] = ['title'=>$segment['title'], 'description'=>'', 'fields'=>$segment['keys'], 'source'=>'briefing_steps_9dx'];
                    }
                }
                $replacementStepMap[$key] = $allGroups;
            }
        }

        // Reescreve condições apontadas ao placeholder para as perguntas reais.
        foreach ($expandedQuestions as &$expandedQuestion) {
            if (!is_array($expandedQuestion) || empty($expandedQuestion['conditions']) || !is_array($expandedQuestion['conditions'])) continue;
            $conditions = $expandedQuestion['conditions'];
            $sourceRules = isset($conditions['rules']) && is_array($conditions['rules']) ? $conditions['rules'] : [];
            if (!$sourceRules && !empty($conditions['go_to'])) {
                $sourceRules = [['value' => $conditions['value'] ?? '', 'go_to' => $conditions['go_to']]];
            }
            $rewritten = [];
            foreach ($sourceRules as $rule) {
                if (!is_array($rule)) continue;
                $target = sanitize_key((string)($rule['go_to'] ?? ''));
                if ($target !== '' && array_key_exists($target, $replacementMap)) {
                    foreach ((array)$replacementMap[$target] as $replacementKey) {
                        $copy = $rule;
                        $copy['go_to'] = sanitize_key((string)$replacementKey);
                        if ($copy['go_to'] !== '') $rewritten[] = $copy;
                    }
                } else {
                    $rewritten[] = $rule;
                }
            }
            $expandedQuestion['conditions'] = [
                'enabled' => !empty($conditions['enabled']) && !empty($rewritten),
                'rules' => $rewritten,
            ];
        }
        unset($expandedQuestion);

        $schema['questions'] = $expandedQuestions;
        if (!empty($replacementStepMap)) {
            $layout = is_array($schema['layout'] ?? null) ? $schema['layout'] : [];
            $hadSteps = sanitize_key((string)($layout['mode'] ?? 'single')) === 'steps';
            $sourceSteps = $hadSteps && !empty($layout['steps']) && is_array($layout['steps'])
                ? $layout['steps']
                : [['title'=>$hadSteps ? 'Formulário' : 'Perguntas do formulário', 'description'=>'', 'fields'=>array_values(array_filter(array_map(
                    static fn($q) => is_array($q) ? sanitize_key((string)($q['key'] ?? '')) : '', $questions
                  )))]];
            $layout['steps'] = self::expandLayoutSteps($sourceSteps, $replacementMap, $replacementStepMap);
            $layout['mode'] = 'steps';
            if (!$hadSteps) $layout['show_progress'] = count($layout['steps']) > 1;
            if (!empty($layout['field_layout']) && is_array($layout['field_layout'])) {
                foreach ($replacementMap as $blockKey => $replacementKeys) {
                    $width = (int)($layout['field_layout'][$blockKey]['width'] ?? 100);
                    unset($layout['field_layout'][$blockKey]);
                    foreach ($replacementKeys as $replacementKey) $layout['field_layout'][$replacementKey] = ['width'=>$width ?: 100];
                }
            }
            $schema['layout'] = $layout;
        } elseif (!empty($schema['layout']) && is_array($schema['layout'])) {
            // No step selected for any question: byte-compatible legacy flow.
            $layout = $schema['layout'];
            if (!empty($layout['steps']) && is_array($layout['steps'])) {
                $newSteps = [];
                foreach ($layout['steps'] as $step) {
                    if (empty($step['fields']) || !is_array($step['fields'])) {
                        $newSteps[] = $step;
                        continue;
                    }
                    $newFields = [];
                    $containedBriefingBlock = false;
                    foreach ($step['fields'] as $fieldKey) {
                        $fieldKey = sanitize_key((string)$fieldKey);
                        if (array_key_exists($fieldKey, $replacementMap)) {
                            $containedBriefingBlock = true;
                            foreach ($replacementMap[$fieldKey] as $replacementKey) $newFields[] = $replacementKey;
                        } elseif ($fieldKey) {
                            $newFields[] = $fieldKey;
                        }
                    }
                    $step['fields'] = array_values(array_unique($newFields));
                    // Se o step existia apenas para o bloco de briefing e a loja nao tem
                    // perguntas atribuidas, remove-se tambem o step vazio do wizard.
                    if ($containedBriefingBlock && !$step['fields']) continue;
                    $newSteps[] = $step;
                }
                $layout['steps'] = array_values($newSteps);
            }
            if (!empty($layout['field_layout']) && is_array($layout['field_layout'])) {
                foreach ($replacementMap as $blockKey => $replacementKeys) {
                    $width = (int)($layout['field_layout'][$blockKey]['width'] ?? 100);
                    unset($layout['field_layout'][$blockKey]);
                    foreach ($replacementKeys as $replacementKey) $layout['field_layout'][$replacementKey] = ['width' => $width ?: 100];
                }
            }
            $schema['layout'] = $layout;
        }
        return $schema;
    }

    /**
     * Presentation-only groups. The question key is NEVER rewritten: submitted
     * answers, historic visits, evidence, conditional rules and analytics keep
     * the exact same identity as before 9DX.
     */
    private static function stepGroupsForBriefing(int $briefingId, array $entries, string $introKey): array {
        $definitions = \RoutesPro\Briefings\BriefingSteps::all($briefingId);
        if (!$definitions || !$entries) return [];
        $known = [];
        foreach ($definitions as $definition) $known[$definition['key']] = $definition;
        $assigned = [];
        $unassigned = [];
        foreach ($entries as $entry) {
            $questionKey = sanitize_key((string)($entry['key'] ?? ''));
            $stepKey = sanitize_key((string)($entry['briefing_step_key'] ?? ''));
            if ($questionKey === '') continue;
            if ($stepKey !== '' && isset($known[$stepKey])) {
                $assigned[$stepKey][] = $questionKey;
            } else {
                $unassigned[] = $questionKey;
            }
        }
        if (!$assigned) return []; // Opt-in. Existing questions remain unchanged.
        $groups = [];
        foreach ($definitions as $step) {
            $fields = array_values(array_unique($assigned[$step['key']] ?? []));
            if (!$fields) continue; // Never show a step for the wrong store.
            $groups[] = [
                'title'=>$step['title'], 'description'=>$step['description'],
                'fields'=>$fields, 'source'=>'briefing_steps_9dx',
                'briefing_step_key'=>$step['key'],
            ];
        }
        if ($unassigned) {
            $groups[] = [
                'title'=>'Outras perguntas do briefing', 'description'=>'',
                'fields'=>array_values(array_unique($unassigned)), 'source'=>'briefing_steps_9dx',
            ];
        }
        if ($introKey !== '' && $groups) array_unshift($groups[0]['fields'], $introKey);
        return $groups;
    }

    /**
     * Replaces a briefing placeholder at its original form position, without
     * stealing other fields or touching questions that have no step configured.
     */
    private static function expandLayoutSteps(array $sourceSteps, array $replacementMap, array $replacementStepMap): array {
        $result = [];
        $usedBlocks = [];
        foreach ($sourceSteps as $originalStep) {
            if (!is_array($originalStep)) continue;
            $fields = is_array($originalStep['fields'] ?? null) ? $originalStep['fields'] : [];
            $pending = [];
            $hasGroupedBlock = false;
            $hadPlaceholder = false;
            foreach ($fields as $field) {
                $field = sanitize_key((string)$field);
                if ($field === '') continue;
                if (!array_key_exists($field, $replacementMap)) {
                    $pending[] = $field;
                    continue;
                }
                $hadPlaceholder = true;
                if (empty($replacementStepMap[$field])) {
                    foreach ($replacementMap[$field] as $realKey) $pending[] = $realKey;
                    continue;
                }
                $hasGroupedBlock = true;
                $usedBlocks[$field] = true;
                if ($pending) {
                    $before = $originalStep;
                    $before['fields'] = array_values(array_unique($pending));
                    $result[] = $before;
                    $pending = [];
                }
                foreach ($replacementStepMap[$field] as $substep) {
                    if (!empty($substep['fields'])) $result[] = $substep;
                }
            }
            if ($pending) {
                $after = $originalStep;
                $after['fields'] = array_values(array_unique($pending));
                $result[] = $after;
            } elseif (!$hasGroupedBlock && !$hadPlaceholder && !$fields) {
                $result[] = $originalStep; // preserve existing empty manual steps
            }
        }
        // A legacy form may have omitted the placeholder from layout.steps.
        foreach ($replacementStepMap as $key => $groups) {
            if (isset($usedBlocks[$key])) continue;
            foreach ($groups as $group) if (!empty($group['fields'])) $result[] = $group;
        }
        return $result;
    }

    private static function select_rows_for_block(array $rows, array $block, array $usedRows, array $metadata, array $context): array {
        // O bloco de briefing e estritamente dependente da loja atualmente selecionada.
        // Nunca reutilizar perguntas de outra loja nem perguntas globais quando o contexto
        // da visita nao identifica uma loja concreta.
        $locationId = absint($context['location_id'] ?? $context['store_id'] ?? 0);
        if ($locationId <= 0) return [];
        $briefingId = absint($block['briefing_id'] ?? 0);
        $sectionId = absint($block['briefing_section_id'] ?? 0);
        $source = sanitize_key((string)($block['briefing_source'] ?? 'active_latest')) ?: 'active_latest';
        $onlyCurrentLocation = true;
        $limit = max(1, min(100, (int)($block['briefing_question_limit'] ?? 30)));

        $filtered = [];
        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId && isset($usedRows[$rowId])) continue;
            $rowBriefingId = absint($row['briefing_id'] ?? 0);
            if ($rowBriefingId && (($metadata[$rowBriefingId]['status'] ?? '') !== 'published')) continue;
            $rowLocationId = absint($row['location_id'] ?? 0);
            if ($onlyCurrentLocation && $rowLocationId !== $locationId) continue;
            if ($briefingId && absint($row['briefing_id'] ?? 0) !== $briefingId) continue;
            if ($sectionId && absint($row['briefing_section_id'] ?? 0) !== $sectionId) continue;
            $filtered[] = $row;
        }
        if (!$filtered) return [];

        if ($source === 'active_latest' && !$briefingId) {
            $ids = array_values(array_unique(array_map(static fn($row) => absint($row['briefing_id'] ?? 0), $filtered)));
            usort($ids, static function($a, $b) use ($metadata) {
                $ma = $metadata[$a] ?? [];
                $mb = $metadata[$b] ?? [];
                $ka = sprintf('%04d%02d%06d', (int)($ma['year_no'] ?? 0), (int)($ma['week_no'] ?? 0), $a);
                $kb = sprintf('%04d%02d%06d', (int)($mb['year_no'] ?? 0), (int)($mb['week_no'] ?? 0), $b);
                return strcmp($kb, $ka);
            });
            $latest = (int)($ids[0] ?? 0);
            $filtered = array_values(array_filter($filtered, static fn($row) => absint($row['briefing_id'] ?? 0) === $latest));
        }

        usort($filtered, static function($a, $b) {
            $pa = (int)($a['priority'] ?? 100);
            $pb = (int)($b['priority'] ?? 100);
            return $pa === $pb ? ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)) : ($pa <=> $pb);
        });
        return array_slice($filtered, 0, $limit);
    }

    private static function briefing_metadata(array $rows): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map(static fn($row) => absint($row['briefing_id'] ?? 0), $rows))));
        if (!$ids) return [];
        $table = $wpdb->prefix . 'routespro_briefings';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT id,title,year_no,week_no,version_no,valid_from,valid_to,status FROM {$table} WHERE id IN ({$placeholders})",
            ...$ids
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($records as $record) $out[(int)$record['id']] = $record;
        return $out;
    }
}
