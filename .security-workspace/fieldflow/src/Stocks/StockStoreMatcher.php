<?php
namespace RoutesPro\Stocks;

use RoutesPro\Services\AIManager;
use RoutesPro\Support\Features;

if (!defined('ABSPATH')) exit;

class StockStoreMatcher {
    private const MAX_CANDIDATES = 3;
    private const AI_CHUNK_SIZE = 6;
    private const AI_REQUEST_TIMEOUT = 20;

    public static function aiAvailable(): bool {
        return Features::enabled('ai_tools') && AIManager::available('briefings');
    }

    public static function providerLabel(): string {
        return self::aiAvailable() ? AIManager::providerLabel('briefings') : 'Motor inteligente FieldFlow';
    }

    public static function generate(int $batchId, bool $useAI = true): array {
        $batch = StockService::getBatch($batchId);
        if (!$batch) throw new \RuntimeException('Lote de stock não encontrado.');

        $stores = StockService::unmatchedStores($batchId);
        $locations = array_values(StockService::locationIndex((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia'])['rows']);
        if (!$stores || !$locations) {
            self::save($batchId, [], [
                'generated_at' => current_time('mysql'),
                'engine' => 'fieldflow_hybrid_v2',
                'ai_used' => false,
                'provider' => '',
                'reviewed_by_ai' => 0,
                'message' => !$stores ? 'Todas as lojas já estão associadas.' : 'Não existem PDVs disponíveis no âmbito do lote.',
            ]);
            return ['suggestions'=>[], 'ai_used'=>false, 'reviewed_by_ai'=>0, 'message'=>!$stores ? 'Todas as lojas já estão associadas.' : 'Não existem PDVs disponíveis no âmbito do lote.'];
        }

        $suggestions = [];
        foreach ($stores as $store) {
            $candidates = self::rankCandidates($store, $locations, (string)$batch['insignia']);
            if (!$candidates) continue;
            $top = $candidates[0];
            $second = $candidates[1]['score'] ?? 0;
            $gap = max(0, (int)$top['score'] - (int)$second);
            $confidence = self::deterministicConfidence((int)$top['score'], $gap, $store, $top);
            $suggestions[(string)$store['source_store_key']] = [
                'source_store_key' => (string)$store['source_store_key'],
                'source_store_ref' => (string)$store['store_external_ref'],
                'source_store_name' => (string)$store['store_name'],
                'source_locality' => (string)$store['locality'],
                'location_id' => (int)$top['location_id'],
                'location_name' => (string)$top['name'],
                'location_ref' => (string)$top['external_ref'],
                'location_city' => (string)$top['city'],
                'confidence' => $confidence,
                'deterministic_score' => (int)$top['score'],
                'candidate_gap' => $gap,
                'reason' => self::deterministicReason($store, $top, $gap),
                'method' => 'smart_suggestion',
                'ai_reviewed' => false,
                'candidates' => $candidates,
            ];
        }

        $meta = [
            'generated_at' => current_time('mysql'),
            'engine' => 'fieldflow_hybrid_v2',
            'ai_used' => false,
            'provider' => '',
            'reviewed_by_ai' => 0,
            'ai_error' => '',
            'store_count' => count($stores),
            'suggestion_count' => count($suggestions),
            'ai_progress' => [],
        ];
        self::save($batchId, array_values($suggestions), $meta);

        $queued = 0;
        if ($useAI && self::aiAvailable() && $suggestions) {
            $progress = self::prepareAI($batchId, true);
            $queued = (int)($progress['total'] ?? 0);
        }

        return [
            'suggestions' => array_values($suggestions),
            'ai_used' => false,
            'reviewed_by_ai' => 0,
            'message' => $queued > 0
                ? sprintf('%d sugestões geradas. %d ficaram preparadas para validação faseada pela IA.', count($suggestions), $queued)
                : sprintf('%d sugestões geradas pelo motor inteligente.', count($suggestions)),
            'ai_error' => '',
        ];
    }

    public static function suggestions(int $batchId): array {
        $batch = StockService::getBatch($batchId);
        if (!$batch) return ['items'=>[], 'meta'=>[]];
        $payload = self::decodePayload((string)($batch['mapping_json'] ?? ''));
        $raw = is_array($payload['_stock_store_suggestions'] ?? null) ? $payload['_stock_store_suggestions'] : [];
        $meta = is_array($payload['_stock_store_suggestions_meta'] ?? null) ? $payload['_stock_store_suggestions_meta'] : [];
        $unmatched = [];
        foreach (StockService::unmatchedStores($batchId) as $store) $unmatched[(string)$store['source_store_key']] = true;
        $items = [];
        foreach ($raw as $item) {
            if (!is_array($item)) continue;
            $key = (string)($item['source_store_key'] ?? '');
            if ($key === '' || empty($unmatched[$key])) continue;
            $items[$key] = $item;
        }
        return ['items'=>$items, 'meta'=>$meta];
    }

    public static function apply(int $batchId, int $threshold = 90, array $sourceKeys = []): array {
        $batch = StockService::getBatch($batchId);
        if (!$batch) throw new \RuntimeException('Lote de stock não encontrado.');
        $threshold = max(70, min(99, $threshold));
        $data = self::suggestions($batchId);
        $suggestions = $data['items'];
        if ($sourceKeys) {
            $allowed = array_fill_keys(array_values(array_filter(array_map('sanitize_text_field', $sourceKeys))), true);
            $suggestions = array_filter($suggestions, static fn($item, $key) => isset($allowed[$key]), ARRAY_FILTER_USE_BOTH);
        }

        $locationIndex = StockService::locationIndex((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia']);
        $allowedLocations = array_fill_keys(array_map('intval', array_keys($locationIndex['rows'])), true);
        $usedLocations = self::matchedLocationIds($batchId);
        $applied = 0;
        $skipped = 0;

        uasort($suggestions, static fn($a, $b) => ((int)($b['confidence'] ?? 0)) <=> ((int)($a['confidence'] ?? 0)));
        foreach ($suggestions as $suggestion) {
            $confidence = (int)($suggestion['confidence'] ?? 0);
            $locationId = (int)($suggestion['location_id'] ?? 0);
            if ($confidence < $threshold || !$locationId || empty($allowedLocations[$locationId])
                || !StockService::sourceMatchesInsignia((string)($suggestion['source_store_name'] ?? ''), (string)$batch['insignia'])
                || !StockService::sourceMatchesLocation((string)($suggestion['source_store_name'] ?? ''), (array)($locationIndex['rows'][$locationId] ?? []), (string)$batch['insignia'])) { $skipped++; continue; }
            if (!empty($usedLocations[$locationId])) { $skipped++; continue; }
            StockService::saveMapping(
                (int)$batch['client_id'],
                (int)$batch['project_id'],
                (string)$batch['insignia'],
                (string)($suggestion['source_store_ref'] ?? ''),
                (string)($suggestion['source_store_name'] ?? ''),
                $locationId,
                $confidence,
                !empty($suggestion['ai_reviewed']) ? 'ai_suggestion' : 'smart_suggestion',
                false
            );
            $usedLocations[$locationId] = true;
            $applied++;
        }
        if ($applied > 0) StockService::refreshBatchStatsForScope((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia']);
        return ['applied'=>$applied, 'skipped'=>$skipped, 'threshold'=>$threshold];
    }

    public static function rankCandidates(array $store, array $locations, string $insignia = ''): array {
        $ranked = [];
        foreach ($locations as $location) {
            $scoreData = self::score($store, $location, $insignia);
            if ($scoreData['score'] < 32) continue;
            $ranked[] = [
                'location_id' => (int)($location['id'] ?? 0),
                'name' => (string)($location['name'] ?? ''),
                'external_ref' => (string)($location['external_ref'] ?? ''),
                'city' => (string)($location['city'] ?? ''),
                'address' => (string)($location['address'] ?? ''),
                'postal_code' => (string)($location['postal_code'] ?? ''),
                'score' => (int)$scoreData['score'],
                'signals' => $scoreData['signals'],
            ];
        }
        usort($ranked, static function($a, $b) {
            $cmp = ((int)$b['score']) <=> ((int)$a['score']);
            return $cmp ?: strnatcasecmp((string)$a['name'], (string)$b['name']);
        });
        return array_slice($ranked, 0, self::MAX_CANDIDATES);
    }

    private static function score(array $store, array $location, string $insignia = ''): array {
        $sourceName = self::compact((string)($store['store_name'] ?? ''));
        $candidateName = self::compact((string)($location['name'] ?? ''));
        $sourceRef = StockService::normaliseText((string)($store['store_external_ref'] ?? ''));
        $candidateRef = StockService::normaliseText((string)($location['external_ref'] ?? ''));
        $sourceLocality = StockService::normaliseText((string)($store['locality'] ?? ''));
        $candidateLocality = StockService::normaliseText(trim((string)($location['city'] ?? '') . ' ' . (string)($location['address'] ?? '') . ' ' . (string)($location['postal_code'] ?? '')));
        $signals = [];
        // 9DM: scoring and AI must not override a missing/mismatching PDV family.
        if ($insignia !== '' && (!StockService::sourceMatchesInsignia((string)($store['store_name'] ?? ''), $insignia)
            || !StockService::sourceMatchesLocation((string)($store['store_name'] ?? ''), $location, $insignia))) {
            return ['score'=>0, 'signals'=>['insignia em revisao']];
        }
        $brand = self::brandCompatibility($insignia, (string)($location['name'] ?? '') . ' ' . (string)($location['tags'] ?? '') . ' ' . (string)($location['meta_json'] ?? ''));
        // Uma insígnia explicitamente incompatível é um bloqueio absoluto.
        // Códigos de centro e localidades podem repetir-se entre cadeias e nunca devem ultrapassar esta regra.
        if ($brand === 'mismatch') return ['score'=>0, 'signals'=>['insígnia incompatível']];

        $nameSimilarity = self::similarity($sourceName, $candidateName);
        $tokenOverlap = self::tokenOverlap($sourceName, $candidateName);
        $score = (0.62 * $nameSimilarity) + (0.28 * $tokenOverlap);
        if ($brand === 'match') { $score += 22; $signals[] = 'insígnia coincidente'; }

        if ($sourceName !== '' && $sourceName === $candidateName) { $score = max($score, 94); $signals[] = 'nome normalizado exato'; }
        elseif ($sourceName !== '' && $candidateName !== '' && (strpos($sourceName, $candidateName) !== false || strpos($candidateName, $sourceName) !== false)) { $score += 8; $signals[] = 'nome contido'; }
        if ($nameSimilarity >= 88) $signals[] = 'nome muito semelhante';
        elseif ($nameSimilarity >= 72) $signals[] = 'nome semelhante';

        if ($sourceLocality !== '' && $candidateLocality !== '') {
            if (preg_match('/\b' . preg_quote($sourceLocality, '/') . '\b/', $candidateLocality)) { $score += 10; $signals[] = 'localidade coincidente'; }
            elseif (self::tokenOverlap($sourceLocality, $candidateLocality) >= 50) { $score += 5; $signals[] = 'localidade semelhante'; }
        }

        if ($sourceRef !== '' && $candidateRef !== '') {
            if ($sourceRef === $candidateRef) { $score = 100; $signals[] = 'referência exata'; }
            else {
                $sourceDigits = preg_replace('/\D+/', '', $sourceRef);
                $candidateDigits = preg_replace('/\D+/', '', $candidateRef);
                if ($sourceDigits !== '' && $candidateDigits !== '' && $sourceDigits === $candidateDigits) { $score = max($score, 98); $signals[] = 'código numérico exato'; }
                elseif (strlen($sourceDigits) >= 3 && strlen($candidateDigits) >= 3 && (substr($candidateDigits, -strlen($sourceDigits)) === $sourceDigits || substr($sourceDigits, -strlen($candidateDigits)) === $candidateDigits)) { $score += 18; $signals[] = 'código compatível'; }
            }
        }

        if ($sourceName === '' || $candidateName === '') $score -= 15;
        return ['score'=>max(0, min(100, (int)round($score))), 'signals'=>array_values(array_unique($signals))];
    }


    private static function brandCompatibility(string $insignia, string $candidate): string {
        $wanted = self::brandTokens($insignia);
        if (!$wanted) return 'unknown';
        $candidateTokens = self::brandTokens($candidate);
        if (!$candidateTokens) return 'unknown';
        if (array_intersect($wanted, $candidateTokens)) return 'match';
        $known = ['arcol','mcunha','continente','modelo','bomdia','auchan','jumbo','pingodoce','recheio','intermarche','minipreco','elcorteingles','mercadona','lidl','aldi','froiz','apolonia','makro','eclerc'];
        if (array_intersect($candidateTokens, $known)) return 'mismatch';
        return 'unknown';
    }

    private static function brandTokens(string $value): array {
        $value = StockService::normaliseText($value);
        $flat = str_replace(' ', '', $value);
        $out = [];
        $patterns = [
            'pingodoce' => ['PINGO DOCE','PINGODOCE','P DOCE','P. DOCE','PD'],
            'arcol' => ['ARCOL'],
            'mcunha' => ['M CUNHA','MCUNHA','M. CUNHA'],
            'continente' => ['CONTINENTE','CNT','SONAE','SONAE MC'],
            'modelo' => ['CONTINENTE MODELO','MODELO'],
            'bomdia' => ['CONTINENTE BOM DIA','BOM DIA','BOMDIA'],
            'auchan' => ['AUCHAN','JUMBO'],
            'recheio' => ['RECHEIO'],
            'intermarche' => ['INTERMARCHE'],
            'minipreco' => ['MINIPRECO'],
            'elcorteingles' => ['EL CORTE INGLES','ELCORTEINGLES'],
            'mercadona' => ['MERCADONA'],
            'lidl' => ['LIDL'],
            'aldi' => ['ALDI'],
            'froiz' => ['FROIZ'],
            'apolonia' => ['APOLONIA'],
            'makro' => ['MAKRO'],
            'eclerc' => ['E LECLERC','ELECLERC','LECLERC'],
        ];
        foreach ($patterns as $token => $aliases) {
            foreach ($aliases as $alias) {
                $alias = StockService::normaliseText($alias);
                $aliasFlat = str_replace(' ', '', $alias);
                $matched = strlen($alias) <= 3
                    ? (bool)preg_match('/(?:^|\s)' . preg_quote($alias, '/') . '(?:$|\s)/', $value)
                    : (strpos($value, $alias) !== false || strpos($flat, $aliasFlat) !== false);
                if ($matched) { $out[] = $token; break; }
            }
        }
        // Modelo e Bom Dia pertencem à família Continente, mas conservam o subtipo.
        if (in_array('modelo', $out, true) || in_array('bomdia', $out, true)) $out[] = 'continente';
        return array_values(array_unique($out));
    }

    private static function deterministicConfidence(int $score, int $gap, array $store, array $top): int {
        $confidence = $score;
        if ($gap >= 20) $confidence += 5;
        elseif ($gap >= 10) $confidence += 2;
        elseif ($gap <= 3) $confidence -= 8;
        if (in_array('referência exata', (array)($top['signals'] ?? []), true)) $confidence = 100;
        if (in_array('código numérico exato', (array)($top['signals'] ?? []), true)) $confidence = max($confidence, 98);
        return max(0, min(99, $confidence));
    }

    private static function deterministicReason(array $store, array $top, int $gap): string {
        $signals = array_values(array_filter((array)($top['signals'] ?? [])));
        $reason = $signals ? implode(', ', array_slice($signals, 0, 3)) : 'proximidade entre nome e localização';
        if ($gap >= 15) $reason .= ', vantagem clara sobre a alternativa seguinte';
        elseif ($gap <= 4) $reason .= ', existem alternativas próximas';
        return ucfirst($reason) . '.';
    }

    public static function prepareAI(int $batchId, bool $reset = false): array {
        if (!self::aiAvailable()) throw new \RuntimeException('A IA não está ativa para este módulo.');
        $data = self::suggestions($batchId);
        if (!$data['items']) {
            self::generate($batchId, false);
            $data = self::suggestions($batchId);
        }
        $suggestions = $data['items'];
        $meta = $data['meta'];
        $existing = is_array($meta['ai_progress'] ?? null) ? $meta['ai_progress'] : [];
        if (!$reset && !empty($existing['queue']) && in_array((string)($existing['status'] ?? ''), ['ready','running','paused'], true)) {
            return self::publicProgress($existing);
        }

        $queue = self::reviewableKeys($suggestions);
        $alreadyReviewed = count(array_filter($suggestions, static fn($item) => !empty($item['ai_reviewed'])));
        $progress = [
            'status' => $queue ? 'ready' : 'completed',
            'queue' => $queue,
            'offset' => 0,
            'total' => count($queue),
            'processed' => 0,
            'reviewed' => $alreadyReviewed,
            'errors' => [],
            'last_error' => '',
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'completed_at' => $queue ? '' : current_time('mysql'),
        ];
        $meta['ai_progress'] = $progress;
        $meta['ai_error'] = '';
        $meta['provider'] = self::providerLabel();
        $meta['reviewed_by_ai'] = $alreadyReviewed;
        $meta['ai_used'] = $alreadyReviewed > 0;
        self::save($batchId, array_values($suggestions), $meta);
        return self::publicProgress($progress);
    }

    public static function reviewNextAIChunk(int $batchId): array {
        if (!self::aiAvailable()) throw new \RuntimeException('A IA não está ativa para este módulo.');
        $data = self::suggestions($batchId);
        $suggestions = $data['items'];
        $meta = $data['meta'];
        $progress = is_array($meta['ai_progress'] ?? null) ? $meta['ai_progress'] : [];
        if (empty($progress['queue'])) {
            self::prepareAI($batchId, false);
            $data = self::suggestions($batchId);
            $suggestions = $data['items'];
            $meta = $data['meta'];
            $progress = is_array($meta['ai_progress'] ?? null) ? $meta['ai_progress'] : [];
        }

        $queue = array_values(array_filter(array_map('sanitize_text_field', (array)($progress['queue'] ?? []))));
        $offset = max(0, (int)($progress['offset'] ?? 0));
        $total = count($queue);
        if ($offset >= $total || !$queue) {
            $progress['status'] = 'completed';
            $progress['processed'] = $total;
            $progress['total'] = $total;
            $progress['completed_at'] = current_time('mysql');
            $progress['updated_at'] = current_time('mysql');
            $meta['ai_progress'] = $progress;
            $meta['ai_used'] = !empty($progress['reviewed']);
            $meta['reviewed_by_ai'] = (int)($progress['reviewed'] ?? 0);
            self::save($batchId, array_values($suggestions), $meta);
            return self::publicProgress($progress);
        }

        $keys = array_slice($queue, $offset, self::AI_CHUNK_SIZE);
        $chunk = [];
        foreach ($keys as $key) if (isset($suggestions[$key])) $chunk[$key] = $suggestions[$key];
        if (!$chunk) {
            $progress['offset'] = min($total, $offset + count($keys));
            $progress['processed'] = $progress['offset'];
            $progress['status'] = $progress['offset'] >= $total ? 'completed' : 'running';
            $progress['updated_at'] = current_time('mysql');
            $meta['ai_progress'] = $progress;
            self::save($batchId, array_values($suggestions), $meta);
            return self::publicProgress($progress);
        }

        $progress['status'] = 'running';
        $progress['last_error'] = '';
        $progress['updated_at'] = current_time('mysql');
        $meta['ai_progress'] = $progress;
        self::save($batchId, array_values($suggestions), $meta);

        try {
            $result = self::reviewChunk($suggestions, $chunk);
            $suggestions = $result['suggestions'];
            $reviewedNow = (int)$result['reviewed'];
            $progress['offset'] = min($total, $offset + count($keys));
            $progress['processed'] = $progress['offset'];
            $progress['reviewed'] = (int)($progress['reviewed'] ?? 0) + $reviewedNow;
            $progress['status'] = $progress['offset'] >= $total ? 'completed' : 'running';
            if ($progress['status'] === 'completed') $progress['completed_at'] = current_time('mysql');
            $progress['updated_at'] = current_time('mysql');
            $meta['ai_progress'] = $progress;
            $meta['ai_used'] = !empty($progress['reviewed']);
            $meta['provider'] = self::providerLabel();
            $meta['reviewed_by_ai'] = (int)$progress['reviewed'];
            $meta['ai_error'] = '';
            self::save($batchId, array_values($suggestions), $meta);
            return self::publicProgress($progress);
        } catch (\Throwable $e) {
            $message = sanitize_text_field($e->getMessage());
            $errors = array_values(array_filter((array)($progress['errors'] ?? [])));
            $errors[] = ['at'=>current_time('mysql'), 'offset'=>$offset, 'message'=>$message];
            $progress['errors'] = array_slice($errors, -8);
            $progress['last_error'] = $message;
            $progress['status'] = 'paused';
            $progress['updated_at'] = current_time('mysql');
            $meta['ai_progress'] = $progress;
            $meta['ai_error'] = $message;
            self::save($batchId, array_values($suggestions), $meta);
            $public = self::publicProgress($progress);
            $public['error'] = $message;
            return $public;
        }
    }

    private static function reviewableKeys(array $suggestions): array {
        $queue = [];
        foreach ($suggestions as $key => $item) {
            if (!empty($item['ai_reviewed'])) continue;
            $score = (int)($item['deterministic_score'] ?? 0);
            $gap = (int)($item['candidate_gap'] ?? 0);
            if ($score < 42) continue;
            if ($score >= 96 && $gap >= 15) continue;
            $queue[] = (string)$key;
        }
        return $queue;
    }

    private static function reviewChunk(array $suggestions, array $chunk): array {
        $payload = [];
        foreach ($chunk as $key => $item) {
            $payload[] = [
                'source_store_key' => (string)$key,
                'source' => [
                    'ref' => (string)$item['source_store_ref'],
                    'name' => (string)$item['source_store_name'],
                    'locality' => (string)$item['source_locality'],
                ],
                'candidates' => array_map(static fn($candidate) => [
                    'location_id' => (int)$candidate['location_id'],
                    'name' => (string)$candidate['name'],
                    'ref' => (string)$candidate['external_ref'],
                    'city' => (string)$candidate['city'],
                    'score' => (int)$candidate['score'],
                ], (array)$item['candidates']),
            ];
        }
        $raw = AIManager::complete('briefings', self::buildPrompt($payload), [
            'task' => 'stock_store_matching_chunk',
            'temperature' => 0,
            'max_tokens' => 1500,
            'timeout' => self::AI_REQUEST_TIMEOUT,
            'force_provider' => AIManager::providerName('briefings'),
        ]);
        $decoded = self::extractJson($raw);
        $reviewed = 0;
        foreach ((array)($decoded['suggestions'] ?? []) as $aiItem) {
            if (!is_array($aiItem)) continue;
            $key = (string)($aiItem['source_store_key'] ?? '');
            $locationId = (int)($aiItem['location_id'] ?? 0);
            if ($key === '' || !isset($chunk[$key]) || !isset($suggestions[$key]) || !$locationId) continue;
            $candidate = null;
            foreach ((array)$suggestions[$key]['candidates'] as $candidateRow) {
                if ((int)$candidateRow['location_id'] === $locationId) { $candidate = $candidateRow; break; }
            }
            if (!$candidate) continue;
            $aiConfidence = max(0, min(100, (int)($aiItem['confidence'] ?? 0)));
            if ($aiConfidence < 45) continue;
            $deterministic = (int)$candidate['score'];
            $finalConfidence = max(0, min(99, (int)round(($deterministic * 0.55) + ($aiConfidence * 0.45))));
            $suggestions[$key]['location_id'] = $locationId;
            $suggestions[$key]['location_name'] = (string)$candidate['name'];
            $suggestions[$key]['location_ref'] = (string)$candidate['external_ref'];
            $suggestions[$key]['location_city'] = (string)$candidate['city'];
            $suggestions[$key]['confidence'] = $finalConfidence;
            $suggestions[$key]['reason'] = sanitize_text_field((string)($aiItem['reason'] ?? 'Candidato validado pela IA dentro da lista segura do FieldFlow.'));
            $suggestions[$key]['method'] = 'ai_suggestion';
            $suggestions[$key]['ai_reviewed'] = true;
            $reviewed++;
        }
        return ['suggestions'=>$suggestions, 'reviewed'=>$reviewed];
    }

    private static function publicProgress(array $progress): array {
        $total = max(0, (int)($progress['total'] ?? count((array)($progress['queue'] ?? []))));
        $processed = max(0, min($total, (int)($progress['processed'] ?? $progress['offset'] ?? 0)));
        return [
            'status' => (string)($progress['status'] ?? 'ready'),
            'total' => $total,
            'processed' => $processed,
            'reviewed' => max(0, (int)($progress['reviewed'] ?? 0)),
            'percent' => $total > 0 ? (int)round(($processed / $total) * 100) : 100,
            'done' => $total === 0 || (string)($progress['status'] ?? '') === 'completed' || $processed >= $total,
            'last_error' => (string)($progress['last_error'] ?? ''),
        ];
    }

    private static function buildPrompt(array $payload): string {
        return "Atua como especialista em normalização de lojas de retalho em Portugal.\n"
            . "Para cada loja de origem, escolhe apenas um location_id existente na respetiva lista de candidatos, ou 0 quando nenhum candidato for suficientemente seguro.\n"
            . "Usa código da loja, nome, localidade e coerência geográfica. Não inventes lojas nem IDs. Um nome parecido noutra localidade não é uma correspondência segura.\n"
            . "A confiança deve ser de 0 a 100. Usa 90 ou mais apenas quando a correspondência é muito clara.\n"
            . "Devolve apenas JSON válido, sem markdown, neste formato: {\"suggestions\":[{\"source_store_key\":\"...\",\"location_id\":123,\"confidence\":92,\"reason\":\"...\"}]}\n\n"
            . wp_json_encode(['stores'=>$payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function extractJson(string $raw): array {
        $raw = trim($raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) throw new \RuntimeException('A IA não devolveu JSON válido para as sugestões de lojas.');
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (!is_array($decoded)) throw new \RuntimeException('A IA devolveu sugestões num formato inválido.');
        return $decoded;
    }

    private static function save(int $batchId, array $suggestions, array $meta): void {
        global $wpdb;
        $batch = StockService::getBatch($batchId);
        if (!$batch) return;
        $payload = self::decodePayload((string)($batch['mapping_json'] ?? ''));
        $payload['_stock_store_suggestions'] = $suggestions;
        $payload['_stock_store_suggestions_meta'] = $meta;
        $wpdb->update(StockService::batchTable(), [
            'mapping_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql'),
        ], ['id'=>$batchId]);
    }

    private static function decodePayload(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function matchedLocationIds(int $batchId): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT location_id FROM ' . StockService::itemTable() . ' WHERE batch_id=%d AND location_id>0', $batchId)) ?: [];
        return array_fill_keys(array_map('intval', $ids), true);
    }

    private static function compact(string $value): string {
        $value = StockService::normaliseText($value);
        $noise = ['PINGO','DOCE','CONTINENTE','MODELO','BOM','DIA','CNT','MDL','CBD','AUCHAN','INTERMARCHE','INTERMARCHÉ','HIPER','SUPERMERCADO','SUPER','LOJA','STORE','CENTRO','PDV'];
        $tokens = array_values(array_filter(explode(' ', $value), static fn($token) => $token !== '' && !in_array($token, $noise, true)));
        return implode(' ', $tokens);
    }

    private static function similarity(string $left, string $right): int {
        if ($left === '' || $right === '') return 0;
        if ($left === $right) return 100;
        similar_text($left, $right, $percent);
        return (int)round($percent);
    }

    private static function tokenOverlap(string $left, string $right): int {
        $a = array_values(array_unique(array_filter(explode(' ', $left))));
        $b = array_values(array_unique(array_filter(explode(' ', $right))));
        if (!$a || !$b) return 0;
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union ? (int)round(($intersection / $union) * 100) : 0;
    }
}
