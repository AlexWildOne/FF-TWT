<?php
namespace RoutesPro\Briefings;

use RoutesPro\Services\AIManager;
use RoutesPro\Support\Config;
use RoutesPro\Support\Features;

if (!defined('ABSPATH')) exit;

class BriefingAIService {
    private const MAX_PROMPT_CHARS = 28000;
    private const MAX_MAPPING_ALIASES = 500;
    private const STORE_DICTIONARY_VERSION = '2026-07-14-v1';
    private const CRON_HOOK = 'fieldflow_briefing_ai_process';
    private const JOB_TRANSIENT_PREFIX = 'fieldflow_briefing_ai_job_';


    public static function registerHooks(): void {
        add_action(self::CRON_HOOK, [self::class, 'processQueued'], 10, 1);
    }

    public static function queue(int $briefingId): bool {
        if (!$briefingId || !self::available()) return false;
        $status = self::jobStatus($briefingId);
        $args = [$briefingId];
        if (in_array((string)($status['status'] ?? ''), ['queued','running'], true)) {
            // Recupera filas órfãs, por exemplo quando o evento cron foi limpo pelo alojamento.
            if (($status['status'] ?? '') === 'queued' && !wp_next_scheduled(self::CRON_HOOK, $args)) {
                wp_schedule_single_event(time() + 1, self::CRON_HOOK, $args);
            }
            if (function_exists('spawn_cron')) spawn_cron(time());
            return true;
        }
        set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, [
            'status' => 'queued',
            'queued_at' => current_time('mysql'),
            'queued_ts' => time(),
            'message' => 'Análise colocada em fila.',
        ], 6 * HOUR_IN_SECONDS);
        if (!wp_next_scheduled(self::CRON_HOOK, $args)) {
            wp_schedule_single_event(time() + 1, self::CRON_HOOK, $args);
        }
        if (function_exists('spawn_cron')) spawn_cron(time());
        return true;
    }

    public static function jobStatus(int $briefingId): array {
        $status = get_transient(self::JOB_TRANSIENT_PREFIX . $briefingId);
        if (!is_array($status)) return [];
        $state = sanitize_key((string)($status['status'] ?? ''));
        if ($state === 'running') {
            $startedAt = absint($status['started_ts'] ?? 0) ?: (strtotime((string)($status['started_at'] ?? '')) ?: 0);
            if ($startedAt && (time() - $startedAt) > 8 * MINUTE_IN_SECONDS) {
                delete_transient(self::JOB_TRANSIENT_PREFIX . 'lock_' . $briefingId);
                $status = [
                    'status' => 'error',
                    'finished_at' => current_time('mysql'),
                    'message' => 'A análise foi interrompida pelo alojamento antes de terminar. Podes voltar a executá-la sem bloquear o backoffice.',
                ];
                set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, $status, HOUR_IN_SECONDS);
            }
        } elseif ($state === 'queued') {
            $args = [$briefingId];
            $queuedAt = absint($status['queued_ts'] ?? 0) ?: (strtotime((string)($status['queued_at'] ?? '')) ?: 0);
            if ($queuedAt && (time() - $queuedAt) > 5 * MINUTE_IN_SECONDS) {
                $next = wp_next_scheduled(self::CRON_HOOK, $args);
                if ($next) wp_unschedule_event($next, self::CRON_HOOK, $args);
                $status = [
                    'status' => 'error',
                    'finished_at' => current_time('mysql'),
                    'message' => 'O WP Cron do alojamento não iniciou a análise. Confirma o cron/loopback do WordPress e volta a executar.',
                ];
                set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, $status, HOUR_IN_SECONDS);
            } else {
                $next = wp_next_scheduled(self::CRON_HOOK, $args);
                if (!$next || $next < (time() - MINUTE_IN_SECONDS)) {
                    if ($next) wp_unschedule_event($next, self::CRON_HOOK, $args);
                    wp_schedule_single_event(time() + 1, self::CRON_HOOK, $args);
                }
                if (function_exists('spawn_cron')) spawn_cron(time());
            }
        }
        return $status;
    }

    public static function processQueued(int $briefingId): void {
        if (!$briefingId) return;
        $lockKey = self::JOB_TRANSIENT_PREFIX . 'lock_' . $briefingId;
        $lockStarted = absint(get_transient($lockKey));
        if ($lockStarted && (time() - $lockStarted) < 8 * MINUTE_IN_SECONDS) return;
        if ($lockStarted) delete_transient($lockKey);
        set_transient($lockKey, time(), 10 * MINUTE_IN_SECONDS);
        set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, [
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'started_ts' => time(),
            'message' => 'Análise em processamento.',
        ], 6 * HOUR_IN_SECONDS);
        try {
            if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
            if (function_exists('set_time_limit')) @set_time_limit(240);
            self::analyze($briefingId, true);
            set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, [
                'status' => 'complete',
                'finished_at' => current_time('mysql'),
                'message' => 'Análise concluída.',
            ], 10 * MINUTE_IN_SECONDS);
        } catch (\Throwable $e) {
            set_transient(self::JOB_TRANSIENT_PREFIX . $briefingId, [
                'status' => 'error',
                'finished_at' => current_time('mysql'),
                'message' => sanitize_text_field(substr($e->getMessage(), 0, 600)),
            ], HOUR_IN_SECONDS);
        } finally {
            delete_transient($lockKey);
        }
    }

    public static function storeAbbreviationDictionary(): array {
        return [
            'CNT' => 'Continente',
            'CBD' => 'Continente Bom Dia',
            'MDL' => 'Continente Modelo',
            'PD' => 'Pingo Doce',
            'ITM / ITMI' => 'Intermarché',
            'ECI' => 'El Corte Inglés',
        ];
    }

    public static function available(): bool {
        if (!Features::enabled('ai_tools')) return false;
        return AIManager::available('briefings');
    }

    public static function providerLabel(): string {
        return AIManager::providerLabel('briefings');
    }

    public static function executionQuestionSuggestions(int $briefingId): array {
        $detail = BriefingService::adminDetail($briefingId);
        if (!$detail) return [];
        $analysis = self::analysis($briefingId);
        $suggestions = is_array($analysis['execution_question_suggestions'] ?? null) ? $analysis['execution_question_suggestions'] : [];
        if (!$suggestions) $suggestions = self::deterministicQuestionSuggestions($detail);
        return self::ensureStructuredShareSpace($suggestions, $detail);
    }

    public static function analysis(int $briefingId): array {
        $briefing = BriefingService::get($briefingId);
        if (!$briefing) return [];
        $summary = self::decodeJson($briefing['import_summary_json'] ?? '');
        return is_array($summary['ai_copilot'] ?? null) ? $summary['ai_copilot'] : [];
    }

    public static function markStale(int $briefingId, string $reason = 'content_changed'): void {
        $analysis = self::analysis($briefingId);
        if (!$analysis) return;
        $analysis['stale'] = true;
        $analysis['stale_reason'] = sanitize_key($reason) ?: 'content_changed';
        self::store($briefingId, $analysis);
    }

    public static function analyze(int $briefingId, bool $force = false): array {
        if (!self::available()) {
            throw new \RuntimeException('A IA não está configurada nas definições do FieldFlow.');
        }
        $existing = self::analysis($briefingId);
        if (!$force && $existing && empty($existing['stale'])) return $existing;

        $detail = BriefingService::adminDetail($briefingId);
        if (empty($detail['briefing'])) throw new \RuntimeException('Briefing não encontrado.');
        $previous = self::previousDetail($detail['briefing']);
        $facts = self::buildFacts($detail, $previous);
        $promptFacts = $facts;
        // O diagnóstico local percorre todas as lojas. O prompt principal recebe apenas uma amostra
        // para manter a análise rápida e compatível com limites de contexto do fornecedor.
        $promptFacts['mapping_candidates'] = array_slice($facts['mapping_candidates'] ?? [], 0, 12);
        $prompt = self::buildPrompt($promptFacts);
        if (strlen($prompt) > self::MAX_PROMPT_CHARS) {
            $promptFacts['mapping_candidates'] = array_slice($promptFacts['mapping_candidates'] ?? [], 0, 20);
            $promptFacts['section_samples'] = array_slice($promptFacts['section_samples'] ?? [], 0, 20);
            $promptFacts['rule_changes']['items'] = array_slice($promptFacts['rule_changes']['items'] ?? [], 0, 18);
            $prompt = self::buildPrompt($promptFacts);
        }
        if (strlen($prompt) > self::MAX_PROMPT_CHARS) {
            $promptFacts['mapping_candidates'] = array_slice($promptFacts['mapping_candidates'] ?? [], 0, 10);
            $promptFacts['section_samples'] = array_slice($promptFacts['section_samples'] ?? [], 0, 12);
            $promptFacts['rule_changes']['items'] = array_slice($promptFacts['rule_changes']['items'] ?? [], 0, 10);
            $prompt = self::buildPrompt($promptFacts);
        }

        $raw = AIManager::complete('briefings', $prompt, [
            'task' => 'briefing_ai_copilot',
            'feature_key' => 'briefing_ai_copilot',
            'request_kind' => 'analysis',
            'client_id' => absint($detail['briefing']['client_id'] ?? 0),
            'project_id' => absint($detail['briefing']['project_id'] ?? 0),
            'context' => $facts,
            'temperature' => 0.1,
            'max_tokens' => 2600,
            'timeout' => 35,
            'max_attempts' => 2,
        ]);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('A IA não devolveu uma análise válida.');
        }

        $decoded = self::parseJsonResponse($raw);
        $analysis = self::sanitizeAnalysis($decoded, $facts);
        $analysis['provider'] = self::providerLabel();
        $analysis['generated_at'] = current_time('mysql');
        $analysis['stale'] = false;
        $analysis['source_hash'] = (string)($detail['briefing']['source_hash'] ?? '');
        $analysis['previous_briefing_id'] = (int)($previous['briefing']['id'] ?? 0);
        $analysis['mapping_candidates_count'] = count($facts['mapping_candidates'] ?? []);
        $analysis['store_dictionary_version'] = self::STORE_DICTIONARY_VERSION;
        $storeMap = is_array($facts['store_mapping'] ?? null) ? $facts['store_mapping'] : [];
        $analysis['store_detection'] = [
            'total_names' => (int)($storeMap['total'] ?? 0),
            'mapped' => (int)($storeMap['mapped'] ?? 0),
            'pending' => (int)($storeMap['unmapped'] ?? 0),
            'scanned' => (int)($storeMap['unmapped'] ?? 0),
            'with_candidates' => count($facts['mapping_candidates'] ?? []),
            'without_candidates' => max(0, (int)($storeMap['unmapped'] ?? 0) - count($facts['mapping_candidates'] ?? [])),
            'suggestions' => count($analysis['store_suggestions'] ?? []),
            'engine' => 'FieldFlow full-store scan + AI Copilot',
        ];
        self::store($briefingId, $analysis);
        return $analysis;
    }

    public static function applyHighConfidenceMappings(int $briefingId, int $threshold = 85): int {
        $threshold = max(50, min(100, $threshold));
        $analysis = self::analysis($briefingId);
        $suggestions = is_array($analysis['store_suggestions'] ?? null) ? $analysis['store_suggestions'] : [];
        if (!$suggestions) return 0;

        $mapping = [];
        foreach ($suggestions as $suggestion) {
            $aliasId = absint($suggestion['alias_id'] ?? 0);
            $locationId = absint($suggestion['location_id'] ?? 0);
            $confidence = (int)($suggestion['confidence'] ?? 0);
            if ($aliasId && $locationId && $confidence >= $threshold) {
                $mapping[$aliasId] = $locationId;
            }
        }
        if (!$mapping) return 0;
        $saved = BriefingService::saveMappings($briefingId, $mapping);
        self::markStale($briefingId, 'mapping_changed');
        return $saved;
    }

    public static function frontPayload(array $analysis, array $retailerKeys, bool $clientMode): array {
        if (!$analysis) return [];
        $retailerSummaries = is_array($analysis['retailer_summaries'] ?? null) ? $analysis['retailer_summaries'] : [];
        $selected = [];
        if ($clientMode || !$retailerKeys) {
            $selected = $retailerSummaries;
        } else {
            foreach ($retailerKeys as $key) {
                $key = sanitize_key((string)$key);
                if ($key && !empty($retailerSummaries[$key])) $selected[$key] = $retailerSummaries[$key];
            }
        }
        $baseSummary = $clientMode
            ? (string)($analysis['client_summary'] ?? $analysis['executive_summary'] ?? '')
            : (string)($analysis['team_summary'] ?? $analysis['executive_summary'] ?? '');
        $operational = array_values(array_filter(array_merge([$baseSummary], array_values($selected))));

        $audit = is_array($analysis['audit'] ?? null) ? $analysis['audit'] : [];
        $issues = is_array($audit['issues'] ?? null) ? $audit['issues'] : [];
        $safeIssues = [];
        foreach (array_slice($issues, 0, 12) as $issue) {
            if (!is_array($issue)) continue;
            $internalText = strtolower((string)($issue['title'] ?? '') . ' ' . (string)($issue['detail'] ?? ''));
            if (strpos($internalText, 'por associar') !== false || strpos($internalText, 'fieldflow') !== false || strpos($internalText, 'secções vazias') !== false) continue;
            $safeIssues[] = [
                'severity' => sanitize_key((string)($issue['severity'] ?? 'info')),
                'title' => sanitize_text_field((string)($issue['title'] ?? 'Alerta')),
                'detail' => sanitize_textarea_field((string)($issue['detail'] ?? '')),
            ];
        }

        $changes = is_array($analysis['changes'] ?? null) ? $analysis['changes'] : [];
        return [
            'available' => true,
            'provider' => sanitize_text_field((string)($analysis['provider'] ?? 'IA FieldFlow')),
            'generated_at' => $analysis['generated_at'] ?? null,
            'stale' => !empty($analysis['stale']),
            'summary' => $baseSummary,
            'operational_summary' => $operational,
            'retailer_summaries' => $selected,
            'audit' => [
                'score' => max(0, min(100, (int)($audit['score'] ?? 0))),
                'status' => sanitize_key((string)($audit['status'] ?? 'warning')),
                'issues' => $safeIssues,
            ],
            'changes' => [
                'headline' => sanitize_text_field((string)($changes['headline'] ?? '')),
                'items' => array_values(array_slice(is_array($changes['items'] ?? null) ? $changes['items'] : [], 0, 12)),
            ],
        ];
    }

    private static function buildFacts(array $detail, array $previous): array {
        $briefing = $detail['briefing'];
        $sections = $detail['sections'] ?? [];
        $items = $detail['items'] ?? [];
        $aliases = $detail['aliases'] ?? [];
        $counts = [];
        $retailerCounts = [];
        $sectionSamples = [];

        foreach ($sections as $section) {
            $type = sanitize_key((string)($section['section_type'] ?? 'information')) ?: 'information';
            $retailer = sanitize_key((string)($section['retailer_key'] ?? 'all')) ?: 'all';
            $contentItems = is_array($section['content']['items'] ?? null) ? $section['content']['items'] : [];
            $count = count($contentItems) ?: (!empty($section['content']['text']) ? 1 : 0);
            $counts[$type] = ($counts[$type] ?? 0) + $count;
            $retailerCounts[$retailer] = ($retailerCounts[$retailer] ?? 0) + $count;
            $sample = [];
            $sampleLimit = $type === 'share_space' ? 12 : 5;
            foreach (array_slice($contentItems, 0, $sampleLimit) as $item) {
                if (is_string($item)) $sample[] = self::clip($item, 180);
                elseif (is_array($item)) $sample[] = self::compactItem($item);
            }
            if (!$sample && !empty($section['content']['text'])) $sample[] = self::clip((string)$section['content']['text'], 400);
            $sectionSamples[] = [
                'section_id' => (int)($section['id'] ?? 0),
                'retailer' => $retailer,
                'type' => $type,
                'title' => (string)($section['title'] ?? ''),
                'count' => $count,
                'sample' => $sample,
            ];
        }

        $storeItemCounts = [];
        $storeItemSamples = [];
        foreach ($items as $item) {
            $retailer = sanitize_key((string)($item['retailer_key'] ?? 'all')) ?: 'all';
            $type = sanitize_key((string)($item['item_type'] ?? 'information')) ?: 'information';
            $key = $retailer . '|' . $type;
            $storeItemCounts[$key] = ($storeItemCounts[$key] ?? 0) + 1;
            if (count($storeItemSamples[$key] ?? []) < 8) {
                $storeItemSamples[$key][] = [
                    'external_store_name' => self::clip((string)($item['external_store_name'] ?? ''), 120),
                    'location_name' => self::clip((string)($item['location_name'] ?? ''), 120),
                    'title' => self::clip((string)($item['title'] ?? ''), 120),
                    'valid_from' => (string)($item['valid_from'] ?? ''),
                    'valid_to' => (string)($item['valid_to'] ?? ''),
                    'details' => is_array($item['details'] ?? null) ? array_slice($item['details'], 0, 8, true) : [],
                ];
            }
        }
        $summary = self::decodeJson($briefing['import_summary_json'] ?? '');
        $mapped = 0;
        foreach ($aliases as $alias) if (!empty($alias['location_id'])) $mapped++;
        $audit = self::deterministicAudit($detail);
        $changes = self::deterministicChanges($detail, $previous);
        $locations = BriefingService::locationsForQuestionScope((int)$briefing['client_id'], (int)$briefing['project_id']);
        $mappingCandidates = self::mappingCandidates($aliases, $locations);

        return [
            'briefing' => [
                'id' => (int)$briefing['id'],
                'title' => (string)$briefing['title'],
                'year' => (int)$briefing['year_no'],
                'week' => (int)$briefing['week_no'],
                'version' => (int)$briefing['version_no'],
                'valid_from' => (string)$briefing['valid_from'],
                'valid_to' => (string)$briefing['valid_to'],
                'source_filename' => (string)$briefing['source_filename'],
                'template' => $summary['template'] ?? 'unknown',
            ],
            'source_sheets' => array_slice(is_array($summary['source_sheets'] ?? null) ? $summary['source_sheets'] : [], 0, 30),
            'counts_by_type' => $counts,
            'counts_by_retailer' => $retailerCounts,
            'section_samples' => array_slice($sectionSamples, 0, 35),
            'store_items_count' => count($items),
            'store_items_by_retailer_type' => $storeItemCounts,
            'store_item_samples' => $storeItemSamples,
            'store_mapping' => [
                'total' => count($aliases),
                'mapped' => $mapped,
                'unmapped' => max(0, count($aliases) - $mapped),
            ],
            'rule_audit' => $audit,
            'previous' => [
                'id' => (int)($previous['briefing']['id'] ?? 0),
                'week' => (int)($previous['briefing']['week_no'] ?? 0),
                'year' => (int)($previous['briefing']['year_no'] ?? 0),
                'version' => (int)($previous['briefing']['version_no'] ?? 0),
            ],
            'rule_changes' => $changes,
            'mapping_candidates' => $mappingCandidates,
            'store_abbreviations' => self::storeAbbreviationDictionary(),
        ];
    }

    private static function buildPrompt(array $facts): string {
        $schema = [
            'detected_structure' => [
                'template' => 'pernod_weekly ou generic_weekly',
                'confidence' => 0,
                'week' => 0,
                'year' => 0,
                'retailers' => [],
                'notes' => [],
            ],
            'executive_summary' => 'Resumo executivo curto.',
            'team_summary' => 'Resumo operacional para a equipa de terreno.',
            'client_summary' => 'Resumo para o cliente, sem informação técnica interna.',
            'retailer_summaries' => [
                'sonae' => '',
                'pingo_doce' => '',
                'auchan' => '',
                'intermarche' => '',
                'eci' => '',
            ],
            'audit' => [
                'score' => 0,
                'status' => 'good, warning ou critical',
                'issues' => [[
                    'severity' => 'info, warning ou critical',
                    'title' => '',
                    'detail' => '',
                    'action' => '',
                ]],
            ],
            'changes' => [
                'headline' => '',
                'items' => [[
                    'type' => 'new, removed ou changed',
                    'label' => '',
                    'detail' => '',
                ]],
            ],
            'store_suggestions' => [[
                'alias_id' => 0,
                'location_id' => 0,
                'confidence' => 0,
                'reason' => '',
            ]],
            'execution_question_suggestions' => [[
                'label' => 'Pergunta operacional curta e verificável',
                'question_type' => 'radio, text, textarea, number, percent, share_space_matrix, evidence_check, image_upload, categorized_image_upload ou file_upload',
                'options' => ['Sim', 'Não', 'Não aplicável'],
                'help_text' => 'Contexto curto para a equipa de terreno',
                'briefing_section_id' => 0,
                'required' => false,
                'reason' => 'Por que razão esta pergunta mede a execução do briefing',
            ]],
        ];

        return "És o Briefing AI Copilot do FieldFlow. Analisa dados já extraídos de um briefing semanal de execução em loja.\n\n"
            . "Regras obrigatórias:\n"
            . "1. Responde apenas com JSON válido, sem markdown e sem texto antes ou depois.\n"
            . "2. Usa português de Portugal.\n"
            . "3. Não inventes produtos, preços, lojas, datas ou objetivos.\n"
            . "4. Para associações de lojas, escolhe apenas location_id presentes nos candidatos do respetivo alias_id. Se não houver confiança suficiente, omite a sugestão.\n"
            . "5. Usa a auditoria por regras como base, podendo acrescentar incoerências suportadas pelos dados.\n"
            . "6. Nas alterações semanais, usa apenas as diferenças fornecidas.\n"
            . "7. Mantém os resumos curtos, acionáveis e adequados a telemóvel.\n"
            . "8. No matching de lojas, usa o dicionário como pista positiva: CNT é Continente, CBD é Continente Bom Dia e MDL é Continente Modelo. Mantém o matching flexível e não excluas candidatos apenas por ordem diferente das palavras, nomenclatura antiga ou ausência do prefixo completo.\n"
            . "9. Gera entre 5 e 20 sugestões de perguntas curtas, objetivas e verificáveis para medir a execução do briefing. Liga cada pergunta à section_id correta quando existir e evita duplicados ou perguntas vagas.\n"
            . "10. Quando estiveres a validar conformidade ou implementação física com resposta Sim/Não, prefere evidence_check. Este tipo mostra Sim, Não e Não aplicável, permite fotografias quando a resposta é Sim e permite um motivo opcional quando a resposta é Não. A resposta Sim/Não/Não aplicável é o dado principal; não transformes automaticamente foto ou motivo em campos obrigatórios. Usa image_upload apenas quando a pergunta for exclusivamente uma fotografia sem decisão de conformidade.\n"
            . "11. Quando uma única pergunta precisar de recolher fotografias separadas para várias categorias, usa categorized_image_upload e coloca os nomes das categorias em options. Exemplos: Topos, Expositores, Arcas e Displays.\n"
            . "12. Para Share Space, cria uma única pergunta do tipo share_space_matrix por secção. A pergunta recolhe frentes do produto Pernod Ricard e frentes da concorrência, e o FieldFlow calcula automaticamente quota, diferença para o objetivo e estado. Não peças ao utilizador para escrever a percentagem manualmente.\n\n"
            . "Estrutura JSON esperada:\n" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nDados do briefing:\n" . wp_json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function sanitizeAnalysis(array $data, array $facts): array {
        $structure = is_array($data['detected_structure'] ?? null) ? $data['detected_structure'] : [];
        $audit = is_array($data['audit'] ?? null) ? $data['audit'] : [];
        $ruleAudit = is_array($facts['rule_audit'] ?? null) ? $facts['rule_audit'] : [];
        $issues = [];
        foreach (array_slice(is_array($audit['issues'] ?? null) ? $audit['issues'] : [], 0, 20) as $issue) {
            if (!is_array($issue)) continue;
            $severity = sanitize_key((string)($issue['severity'] ?? 'info'));
            if (!in_array($severity, ['info', 'warning', 'critical'], true)) $severity = 'info';
            $issues[] = [
                'severity' => $severity,
                'title' => sanitize_text_field((string)($issue['title'] ?? 'Alerta')),
                'detail' => sanitize_textarea_field((string)($issue['detail'] ?? '')),
                'action' => sanitize_textarea_field((string)($issue['action'] ?? '')),
            ];
        }
        if (!$issues) $issues = $ruleAudit['issues'] ?? [];

        $status = sanitize_key((string)($audit['status'] ?? $ruleAudit['status'] ?? 'warning'));
        if (!in_array($status, ['good', 'warning', 'critical'], true)) $status = 'warning';
        $score = isset($audit['score']) ? (int)$audit['score'] : (int)($ruleAudit['score'] ?? 0);

        $changesData = is_array($data['changes'] ?? null) ? $data['changes'] : [];
        $changeItems = [];
        foreach (array_slice(is_array($changesData['items'] ?? null) ? $changesData['items'] : [], 0, 20) as $item) {
            if (!is_array($item)) continue;
            $type = sanitize_key((string)($item['type'] ?? 'changed'));
            if (!in_array($type, ['new', 'removed', 'changed'], true)) $type = 'changed';
            $changeItems[] = [
                'type' => $type,
                'label' => sanitize_text_field((string)($item['label'] ?? 'Alteração')),
                'detail' => sanitize_textarea_field((string)($item['detail'] ?? '')),
            ];
        }
        if (!$changeItems) $changeItems = array_slice($facts['rule_changes']['items'] ?? [], 0, 20);

        $candidateMap = [];
        foreach ($facts['mapping_candidates'] ?? [] as $entry) {
            $aliasId = absint($entry['alias_id'] ?? 0);
            foreach ($entry['candidates'] ?? [] as $candidate) {
                $locationId = absint($candidate['location_id'] ?? 0);
                if ($aliasId && $locationId) $candidateMap[$aliasId][$locationId] = $candidate;
            }
        }
        $suggestions = [];
        foreach (array_slice(is_array($data['store_suggestions'] ?? null) ? $data['store_suggestions'] : [], 0, self::MAX_MAPPING_ALIASES) as $suggestion) {
            if (!is_array($suggestion)) continue;
            $aliasId = absint($suggestion['alias_id'] ?? 0);
            $locationId = absint($suggestion['location_id'] ?? 0);
            if (!$aliasId || !$locationId || empty($candidateMap[$aliasId][$locationId])) continue;
            $suggestions[] = [
                'alias_id' => $aliasId,
                'location_id' => $locationId,
                'location_label' => sanitize_text_field((string)($candidateMap[$aliasId][$locationId]['label'] ?? '')),
                'confidence' => max(0, min(100, (int)($suggestion['confidence'] ?? 0))),
                'reason' => sanitize_text_field((string)($suggestion['reason'] ?? 'Correspondência sugerida pela IA.')),
            ];
        }
        $suggestedAliases = [];
        foreach ($suggestions as $suggestion) {
            $aliasId = absint($suggestion['alias_id'] ?? 0);
            if ($aliasId) $suggestedAliases[$aliasId] = true;
        }
        // Completa a cobertura com o motor de correspondência local. Todas as lojas pendentes são
        // analisadas, mesmo quando o prompt principal só inclui uma amostra para a IA narrativa.
        foreach ($facts['mapping_candidates'] ?? [] as $entry) {
            $aliasId = absint($entry['alias_id'] ?? 0);
            if (!$aliasId || isset($suggestedAliases[$aliasId])) continue;
            $candidate = $entry['candidates'][0] ?? [];
            $second = $entry['candidates'][1] ?? [];
            $scoreCandidate = (int)($candidate['score'] ?? 0);
            $secondScore = (int)($second['score'] ?? 0);
            $gap = $scoreCandidate - $secondScore;
            if ($scoreCandidate < 88 || ($scoreCandidate < 94 && $gap < 12)) continue;
            $suggestions[] = [
                'alias_id' => $aliasId,
                'location_id' => absint($candidate['location_id'] ?? 0),
                'location_label' => sanitize_text_field((string)($candidate['label'] ?? '')),
                'confidence' => min(96, max(85, $scoreCandidate)),
                'reason' => 'Correspondência forte encontrada no varrimento completo das lojas FieldFlow.',
            ];
            $suggestedAliases[$aliasId] = true;
        }

        $validSectionIds = [];
        foreach ($facts['section_samples'] ?? [] as $sample) {
            $sid = absint($sample['section_id'] ?? 0);
            if ($sid) $validSectionIds[$sid] = true;
        }
        $executionSuggestions = [];
        foreach (array_slice(is_array($data['execution_question_suggestions'] ?? null) ? $data['execution_question_suggestions'] : [], 0, 30) as $item) {
            if (!is_array($item)) continue;
            $label = sanitize_text_field((string)($item['label'] ?? ''));
            if ($label === '') continue;
            $type = sanitize_key((string)($item['question_type'] ?? 'radio'));
            if (!in_array($type, ['radio','text','textarea','number','percent','share_space_matrix','evidence_check','image_upload','categorized_image_upload','file_upload'], true)) $type = 'radio';
            $sid = absint($item['briefing_section_id'] ?? 0);
            if ($sid && empty($validSectionIds[$sid])) $sid = 0;
            $opts = [];
            foreach ((array)($item['options'] ?? []) as $opt) {
                $opt = sanitize_text_field((string)$opt);
                if ($opt !== '') $opts[] = $opt;
            }
            if (in_array($type, ['radio','evidence_check'], true) && !$opts) $opts = ['Sim','Não','Não aplicável'];
            if ($type === 'categorized_image_upload' && !$opts) $opts = ['Expositores','Arcas','Displays','Topos'];
            $executionSuggestions[] = [
                'label' => $label,
                'question_type' => $type,
                'options' => array_values(array_unique($opts)),
                'help_text' => sanitize_text_field((string)($item['help_text'] ?? '')),
                'briefing_section_id' => $sid,
                'required' => !empty($item['required']),
                'reason' => sanitize_text_field((string)($item['reason'] ?? 'Sugestão gerada a partir do briefing.')),
                'question_config' => $type === 'share_space_matrix' ? ShareSpaceMatrix::sanitizeConfig($item['question_config'] ?? []) : [],
            ];
        }
        if (!$executionSuggestions) $executionSuggestions = self::deterministicQuestionSuggestions(['briefing'=>$facts['briefing'] ?? [], 'sections'=>array_map(static function($sample){ return ['id'=>$sample['section_id'] ?? 0,'section_type'=>$sample['type'] ?? '', 'title'=>$sample['title'] ?? '', 'content'=>['items'=>$sample['sample'] ?? []]]; }, $facts['section_samples'] ?? [])]);

        $retailerSummaries = [];
        foreach (BriefingService::retailerLabels() as $key => $label) {
            if ($key === 'all') continue;
            $value = (string)($data['retailer_summaries'][$key] ?? '');
            if ($value !== '') $retailerSummaries[$key] = sanitize_textarea_field($value);
        }

        return [
            'detected_structure' => [
                'template' => sanitize_key((string)($structure['template'] ?? $facts['briefing']['template'] ?? 'generic_weekly')),
                'confidence' => max(0, min(100, (int)($structure['confidence'] ?? 0))),
                'week' => max(1, min(53, (int)($structure['week'] ?? $facts['briefing']['week'] ?? 1))),
                'year' => max(2020, min(2100, (int)($structure['year'] ?? $facts['briefing']['year'] ?? date('Y')))),
                'retailers' => array_values(array_filter(array_map('sanitize_key', is_array($structure['retailers'] ?? null) ? $structure['retailers'] : array_keys($facts['counts_by_retailer'] ?? [])))),
                'notes' => array_values(array_filter(array_map('sanitize_text_field', array_slice(is_array($structure['notes'] ?? null) ? $structure['notes'] : [], 0, 8)))),
            ],
            'executive_summary' => sanitize_textarea_field((string)($data['executive_summary'] ?? '')),
            'team_summary' => sanitize_textarea_field((string)($data['team_summary'] ?? '')),
            'client_summary' => sanitize_textarea_field((string)($data['client_summary'] ?? '')),
            'retailer_summaries' => $retailerSummaries,
            'audit' => [
                'score' => max(0, min(100, $score)),
                'status' => $status,
                'issues' => $issues,
                'rule_score' => (int)($ruleAudit['score'] ?? 0),
            ],
            'changes' => [
                'headline' => sanitize_text_field((string)($changesData['headline'] ?? $facts['rule_changes']['headline'] ?? '')),
                'items' => $changeItems,
            ],
            'store_suggestions' => array_values(array_filter($suggestions, static fn($s) => !empty($s['alias_id']) && !empty($s['location_id']))),
            'execution_question_suggestions' => $executionSuggestions,
        ];
    }

    private static function ensureStructuredShareSpace(array $suggestions, array $detail): array {
        $sectionIds = [];
        $matrixSuggestions = [];
        foreach ((array)($detail['sections'] ?? []) as $section) {
            if (sanitize_key((string)($section['section_type'] ?? '')) !== 'share_space') continue;
            $sid = absint($section['id'] ?? 0);
            $items = is_array($section['content']['items'] ?? null) ? $section['content']['items'] : [];
            $config = ShareSpaceMatrix::configFromSection((array)($section['content'] ?? []), 'share_space', ['reference_week'=>(int)($section['content']['week'] ?? 0)]);
            if (!$sid || empty($config['rows'])) continue;
            $sectionIds[$sid] = true;
            $matrixSuggestions[] = [
                'label'=>'Comparativo de referências do briefing',
                'question_type'=>'share_space_matrix',
                'options'=>[],
                'question_config'=>$config,
                'help_text'=>'Indica os valores observados para as referências identificadas no briefing. O FieldFlow calcula as percentagens e o desvio face ao objetivo.',
                'briefing_section_id'=>$sid,
                'required'=>false,
                'reason'=>'Pergunta comparativa estruturada a partir das referências importadas do briefing.',
            ];
        }
        $clean = [];
        foreach ($suggestions as $item) {
            if (!is_array($item)) continue;
            $sid = absint($item['briefing_section_id'] ?? 0);
            $type = sanitize_key((string)($item['question_type'] ?? ''));
            if (!empty($sectionIds[$sid]) && in_array($type, ['percent','number','share_space_matrix'], true)) continue;
            $clean[] = $item;
        }
        return array_slice(array_merge($matrixSuggestions, $clean), 0, 24);
    }

    private static function deterministicQuestionSuggestions(array $detail): array {
        $out = [];
        foreach ((array)($detail['sections'] ?? []) as $section) {
            $type = sanitize_key((string)($section['section_type'] ?? ''));
            $sid = absint($section['id'] ?? 0);
            $items = is_array($section['content']['items'] ?? null) ? $section['content']['items'] : [];
            if ($type === 'share_space') {
                $config = ShareSpaceMatrix::configFromSection((array)($section['content'] ?? []), $type, ['reference_week'=>(int)($section['content']['week'] ?? 0)]);
                if (!empty($config['rows'])) {
                    $out[] = [
                        'label'=>'Comparativo de referências do briefing',
                        'question_type'=>'share_space_matrix',
                        'options'=>[],
                        'question_config'=>$config,
                        'help_text'=>'Indica os valores das duas referências. As percentagens e o desvio para o objetivo são calculados automaticamente.',
                        'briefing_section_id'=>$sid,
                        'required'=>false,
                        'reason'=>'Transforma as referências do Excel num comparativo objetivo, sem percentagens introduzidas manualmente.',
                    ];
                }
            } elseif ($type === 'promotions') {
                $out[] = ['label'=>'A promoção indicada no briefing está implementada e corretamente comunicada?','question_type'=>'evidence_check','options'=>['Sim','Não','Não aplicável'],'help_text'=>'Confirma preço, comunicação e presença do produto na loja. Se estiver conforme, podes juntar fotografias. Se não estiver, podes indicar o motivo.','briefing_section_id'=>$sid,'required'=>false,'reason'=>'Valida a implementação promocional com contexto e evidência no mesmo fluxo.'];
            } elseif ($type === 'vaps') {
                $out[] = ['label'=>'O VAP indicado no briefing está implementado nesta loja?','question_type'=>'evidence_check','options'=>['Sim','Não','Não aplicável'],'help_text'=>'Confirma a presença e execução do VAP aplicável. Se estiver conforme, podes juntar fotografias. Se não estiver, podes indicar o motivo.','briefing_section_id'=>$sid,'required'=>false,'reason'=>'Valida a execução do VAP semanal com resposta, evidência e motivo no mesmo fluxo.'];
            } elseif (in_array($type, ['spaces_summary','actions','focus'], true)) {
                $out[] = ['label'=>'A prioridade operacional indicada no briefing está implementada nesta loja?','question_type'=>($type === 'spaces_summary' || $type === 'focus') ? 'evidence_check' : 'radio','options'=>['Sim','Não','Não aplicável'],'help_text'=>($type === 'spaces_summary' || $type === 'focus') ? 'Confirma a execução. Se estiver conforme, podes juntar fotografias. Se não estiver, podes indicar o motivo.' : 'Responde apenas quando a instrução for aplicável à loja associada.','briefing_section_id'=>$sid,'required'=>false,'reason'=>'Transforma a prioridade semanal numa verificação objetiva.'];
            }
            if (count($out) >= 24) break;
        }
        if (!$out) $out[] = ['label'=>'As prioridades aplicáveis do briefing foram executadas nesta loja?','question_type'=>'radio','options'=>['Sim','Não','Não aplicável'],'help_text'=>'Confirma apenas o conteúdo aplicável à loja associada.','briefing_section_id'=>0,'required'=>false,'reason'=>'Verificação geral da execução do briefing.'];
        $seen=[]; $clean=[];
        foreach($out as $item){$k=sanitize_key($item['label']??''); if(!$k||isset($seen[$k]))continue; $seen[$k]=true; $clean[]=$item;}
        return array_slice($clean,0,24);
    }

    private static function deterministicAudit(array $detail): array {
        $issues = [];
        $penalty = 0;
        $unmapped = 0;
        foreach ($detail['aliases'] ?? [] as $alias) if (empty($alias['location_id'])) $unmapped++;
        if ($unmapped > 0) {
            $severity = $unmapped > 10 ? 'critical' : 'warning';
            $issues[] = [
                'severity' => $severity,
                'title' => $unmapped . ' lojas por associar',
                'detail' => 'Existem nomes do Excel que ainda não estão ligados a lojas FieldFlow.',
                'action' => 'Validar o mapa de lojas antes da publicação.',
            ];
            $penalty += min(35, 5 + $unmapped);
        }

        $promoMissingDates = 0;
        $promoPriceErrors = 0;
        $cardexMissingCodes = 0;
        $emptySections = 0;
        foreach ($detail['sections'] ?? [] as $section) {
            $contentItems = is_array($section['content']['items'] ?? null) ? $section['content']['items'] : [];
            if (!$contentItems && empty($section['content']['text'])) $emptySections++;
            if (($section['section_type'] ?? '') === 'promotions') {
                foreach ($contentItems as $item) {
                    if (!is_array($item)) continue;
                    if (empty($item['valid_from']) || empty($item['valid_to'])) $promoMissingDates++;
                    $regular = (float)($item['regular_price'] ?? 0);
                    $promo = (float)($item['promo_price'] ?? 0);
                    if ($regular > 0 && $promo > $regular) $promoPriceErrors++;
                }
            }
            if (($section['section_type'] ?? '') === 'cardex') {
                foreach ($contentItems as $item) {
                    if (is_array($item) && empty(trim((string)($item['code'] ?? '')))) $cardexMissingCodes++;
                }
            }
        }
        if ($promoPriceErrors) {
            $issues[] = ['severity' => 'critical', 'title' => 'Preços promocionais incoerentes', 'detail' => $promoPriceErrors . ' linhas têm preço promocional superior ao preço regular.', 'action' => 'Confirmar os valores no ficheiro original.'];
            $penalty += min(30, $promoPriceErrors * 8);
        }
        if ($promoMissingDates) {
            $issues[] = ['severity' => 'warning', 'title' => 'Promoções sem período completo', 'detail' => $promoMissingDates . ' promoções não têm data inicial ou final.', 'action' => 'Completar as datas antes da publicação.'];
            $penalty += min(15, $promoMissingDates * 2);
        }
        if ($cardexMissingCodes) {
            $issues[] = ['severity' => 'warning', 'title' => 'Produtos sem referência', 'detail' => $cardexMissingCodes . ' produtos do cardex não têm código ou referência.', 'action' => 'Validar se são produtos novos ou referências em falta.'];
            $penalty += min(12, $cardexMissingCodes);
        }
        if ($emptySections) {
            $issues[] = ['severity' => 'info', 'title' => 'Secções vazias', 'detail' => $emptySections . ' secções não têm conteúdo útil e serão ignoradas na app.', 'action' => 'Sem ação obrigatória.'];
            $penalty += min(5, $emptySections);
        }
        if (!$issues) {
            $issues[] = ['severity' => 'info', 'title' => 'Estrutura consistente', 'detail' => 'Não foram encontradas incoerências objetivas nas regras automáticas.', 'action' => 'Rever o resumo e publicar.'];
        }
        $score = max(0, 100 - $penalty);
        $status = $score >= 85 ? 'good' : ($score >= 60 ? 'warning' : 'critical');
        return ['score' => $score, 'status' => $status, 'issues' => $issues];
    }

    private static function deterministicChanges(array $detail, array $previous): array {
        if (empty($previous['briefing'])) {
            return ['headline' => 'Primeiro briefing disponível para comparação.', 'items' => []];
        }
        $currentMap = self::flattenDetail($detail);
        $previousMap = self::flattenDetail($previous);
        $items = [];
        foreach ($currentMap as $key => $row) {
            if (!isset($previousMap[$key])) {
                $items[] = ['type' => 'new', 'label' => $row['label'], 'detail' => 'Novo na semana atual.'];
            } elseif ($previousMap[$key]['hash'] !== $row['hash']) {
                $items[] = ['type' => 'changed', 'label' => $row['label'], 'detail' => 'Conteúdo, preço, validade ou instrução alterada.'];
            }
        }
        foreach ($previousMap as $key => $row) {
            if (!isset($currentMap[$key])) $items[] = ['type' => 'removed', 'label' => $row['label'], 'detail' => 'Deixou de constar no briefing atual.'];
        }
        $new = count(array_filter($items, static fn($i) => $i['type'] === 'new'));
        $changed = count(array_filter($items, static fn($i) => $i['type'] === 'changed'));
        $removed = count(array_filter($items, static fn($i) => $i['type'] === 'removed'));
        return [
            'headline' => 'Comparação com W' . (int)$previous['briefing']['week_no'] . ': ' . $new . ' novos, ' . $changed . ' alterados e ' . $removed . ' removidos.',
            'items' => array_slice($items, 0, 35),
        ];
    }

    private static function flattenDetail(array $detail): array {
        $flat = [];
        foreach ($detail['sections'] ?? [] as $section) {
            $retailer = (string)($section['retailer_key'] ?? 'all');
            $type = (string)($section['section_type'] ?? 'information');
            $items = is_array($section['content']['items'] ?? null) ? $section['content']['items'] : [];
            if (!$items && !empty($section['content']['text'])) $items = [(string)$section['content']['text']];
            foreach ($items as $index => $item) {
                $identity = is_array($item)
                    ? (string)($item['product'] ?? $item['name'] ?? $item['metric'] ?? $item['code'] ?? $item['title'] ?? $index)
                    : (string)$item;
                $key = 'section|' . $retailer . '|' . $type . '|' . BriefingService::normalizeStoreName($identity);
                if ($key === 'section|' . $retailer . '|' . $type . '|') $key .= $index;
                $flat[$key] = ['label' => trim($identity) ?: (string)($section['title'] ?? 'Item'), 'hash' => md5(wp_json_encode($item))];
            }
        }
        foreach ($detail['items'] ?? [] as $item) {
            $identity = implode('|', [
                (string)($item['retailer_key'] ?? 'all'),
                (string)($item['location_id'] ?? $item['external_store_name'] ?? ''),
                (string)($item['item_type'] ?? 'information'),
                (string)($item['title'] ?? ''),
            ]);
            $key = 'store|' . BriefingService::normalizeStoreName($identity);
            $flat[$key] = [
                'label' => trim((string)($item['external_store_name'] ?? '') . ' · ' . (string)($item['title'] ?? '')),
                'hash' => md5(wp_json_encode([$item['details'] ?? [], $item['valid_from'] ?? '', $item['valid_to'] ?? ''])),
            ];
        }
        return $flat;
    }

    private static function mappingCandidates(array $aliases, array $locations): array {
        $out = [];
        foreach ($aliases as $alias) {
            if (!empty($alias['location_id'])) continue;
            $ranked = [];
            foreach ($locations as $location) {
                $score = self::locationScore((string)($alias['external_name'] ?? ''), $location);
                if ($score < 20) continue;
                $ranked[] = [
                    'location_id' => (int)$location['id'],
                    'label' => trim((string)$location['name'] . (!empty($location['city']) ? ' · ' . $location['city'] : '')),
                    'score' => $score,
                ];
            }
            usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);
            if ($ranked) {
                $out[] = [
                    'alias_id' => (int)$alias['id'],
                    'retailer' => (string)$alias['retailer_key'],
                    'external_name' => (string)$alias['external_name'],
                    'candidates' => array_slice($ranked, 0, 5),
                ];
            }
        }
        return $out;
    }

    private static function locationScore(string $external, array $location): int {
        $variantsA = self::matchVariants($external);
        $variantsB = self::matchVariants(trim((string)($location['name'] ?? '') . ' ' . (string)($location['city'] ?? '')));
        $best = 0.0;
        foreach ($variantsA as $a) {
            foreach ($variantsB as $b) {
                if ($a === '' || $b === '') continue;
                similar_text($a, $b, $similarity);
                $tokensA = array_values(array_unique(array_filter(explode(' ', $a))));
                $tokensB = array_values(array_unique(array_filter(explode(' ', $b))));
                $intersection = count(array_intersect($tokensA, $tokensB));
                $tokenScore = max(count($tokensA), count($tokensB)) ? ($intersection / max(count($tokensA), count($tokensB))) * 100 : 0;
                $contains = (strpos($b, $a) !== false || strpos($a, $b) !== false) ? 96 : 0;
                $best = max($best, $similarity, $tokenScore, $contains);
            }
        }
        return (int)round($best);
    }

    /**
     * Mantém o matching flexível da 2.2.118 e acrescenta variantes de nomenclatura.
     * As abreviações ajudam, mas nunca bloqueiam candidatos por uma regra rígida.
     */
    private static function matchVariants(string $value): array {
        $raw = BriefingService::normalizeStoreName($value);
        if ($raw === '') return [];
        $variants = [$raw];
        $maps = [
            [
                '/\\bcnt\\b/' => 'continente',
                '/\\bcbd\\b/' => 'continente bom dia',
                '/\\bmdl\\b/' => 'modelo',
                '/\\bpd\\b/' => 'pingo doce',
                '/\\bitmi?\\b/' => 'intermarche',
                '/\\beci\\b/' => 'el corte ingles',
            ],
            [
                '/\\bcnt\\b/' => 'continente',
                '/\\bcbd\\b/' => 'continente bom dia',
                '/\\bmdl\\b/' => 'continente modelo',
                '/\\bpd\\b/' => 'pingo doce',
                '/\\bitmi?\\b/' => 'intermarche',
                '/\\beci\\b/' => 'el corte ingles',
            ],
        ];
        foreach ($maps as $replacements) {
            $normal = $raw;
            foreach ($replacements as $pattern => $replacement) $normal = preg_replace($pattern, $replacement, $normal);
            $normal = preg_replace('/^bom dia\\b/', 'continente bom dia', $normal);
            $normal = preg_replace('/^modelo continente\\b/', 'continente modelo', $normal);
            $normal = trim(preg_replace('/\\s+/', ' ', $normal));
            if ($normal !== '') $variants[] = $normal;
        }
        return array_values(array_unique($variants));
    }

    private static function normaliseMatch(string $value): string {
        $variants = self::matchVariants($value);
        return $variants[1] ?? ($variants[0] ?? '');
    }

    private static function previousDetail(array $briefing): array {
        global $wpdb;
        $table = BriefingService::table('briefings');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE client_id=%d AND project_id=%d AND id<>%d AND (year_no<%d OR (year_no=%d AND week_no<%d) OR (year_no=%d AND week_no=%d AND version_no<%d)) ORDER BY year_no DESC,week_no DESC,version_no DESC,id DESC LIMIT 1",
            (int)$briefing['client_id'], (int)$briefing['project_id'], (int)$briefing['id'],
            (int)$briefing['year_no'], (int)$briefing['year_no'], (int)$briefing['week_no'],
            (int)$briefing['year_no'], (int)$briefing['week_no'], (int)$briefing['version_no']
        ), ARRAY_A);
        return $row ? BriefingService::adminDetail((int)$row['id']) : [];
    }

    private static function store(int $briefingId, array $analysis): void {
        global $wpdb;
        $briefing = BriefingService::get($briefingId);
        if (!$briefing) return;
        $summary = self::decodeJson($briefing['import_summary_json'] ?? '');
        $summary['ai_copilot'] = $analysis;
        $wpdb->update(BriefingService::table('briefings'), [
            'import_summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql'),
        ], ['id' => $briefingId]);
    }

    private static function parseJsonResponse(string $raw): array {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }
        throw new \RuntimeException('A resposta da IA não continha JSON válido.');
    }

    private static function compactItem(array $item): array {
        $allowed = ['product', 'name', 'metric', 'code', 'title', 'valid_from', 'valid_to', 'regular_price', 'promo_price', 'discount', 'share', 'target', 'gap', 'reference', 'status', 'allocation', 'actual', 'pending', 'delta', 'category', 'brand'];
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $item)) continue;
            $value = $item[$key];
            if (is_scalar($value) && trim((string)$value) !== '') $out[$key] = is_string($value) ? self::clip($value, 160) : $value;
        }
        return $out;
    }

    private static function clip(string $value, int $length): string {
        $value = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)));
        if (function_exists('mb_strlen') && mb_strlen($value) > $length) return mb_substr($value, 0, $length - 1) . '…';
        return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
    }

    private static function decodeJson($json): array {
        if (is_array($json)) return $json;
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
