<?php
namespace RoutesPro\Vision;

use RoutesPro\Catalog\ProductCatalogService as Catalog;
use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * 9CO Fronts camera reading (pilot).
 *
 * The operative photographs a shelf from a fronts question (Cardex `product_matrix`
 * or briefing `share_space_matrix`). The photo and the reference images of the
 * products of that question (9CL catalog) go to the vision model configured for the
 * `fronts_vision` module of the AI Control Center (Azure OpenAI by default when it is
 * the configured provider, Gemini when chosen). The model returns fronts per product;
 * the operative confirms or corrects them before anything is written in the form.
 *
 * Nothing is ever submitted automatically. Each reading records detected and confirmed
 * values, attribution, provider and duration. Shelf photographs are discarded after
 * processing; catalog reference photographs remain intentionally persistent.
 * Off by default; enabled per client in Cardex e Catálogo → Leitura por câmara.
 *
 * 9CP fieldflow-fronts-field-reference-v1: a product without an image in the catalog can get a
 * reference photographed in the field from the camera sheet. The photo becomes an image of the
 * catalog product (tagged as field reference, visible and replaceable in the back office) and is
 * used by the next readings. Products are never duplicated: the Cardex row or the briefing label
 * is resolved through the general catalog identity before anything is created.
 */
final class FrontsVisionService {
    public const CONTRACT = 'fieldflow-fronts-vision-reading-v1';
    public const MODULE = 'fronts_vision';
    public const POST_TYPE = 'ff_fronts_reading';
    public const OPTION = 'fieldflow_fronts_vision_settings';
    public const AJAX_READ = 'fieldflow_fronts_vision_read';
    public const AJAX_CONFIRM = 'fieldflow_fronts_vision_confirm';
    public const AJAX_REFERENCE = 'fieldflow_fronts_vision_reference';
    public const REFERENCE_CONTRACT = 'fieldflow-fronts-field-reference-v1';
    /** 9CQ: the question switch alone shows the camera; the client can still turn it off. Live counting keeps no photo. */
    public const QUESTION_SWITCH_CONTRACT = 'fieldflow-fronts-vision-question-switch-v1';
    public const LIVE_CONTRACT = 'fieldflow-fronts-vision-live-count-v1';
    public const SAFETY_CONTRACT = 'fieldflow-fronts-safe-multimodal-9cs-v1';
    /** 9CU: consent before billable analysis, one-capture replay protection, conservative confidence review. */
    public const CAPTURE_REVIEW_CONTRACT = 'fieldflow-fronts-capture-review-9cu-v1';
    /** 9CW: photographic evidence, distinguishing variant labels and position deduplication. */
    public const VISUAL_EVIDENCE_CONTRACT = 'fieldflow-fronts-visual-evidence-9cw-v1';
    /** 9CX: canonical Cardex SKU identity and assisted review of uncertain capacity. */
    public const SKU_IDENTITY_CONTRACT = 'fieldflow-fronts-sku-identity-9cx-v1';
    /** Blind, inventory-first vision: no candidate product list exposed to AI. */
    public const BLIND_INVENTORY_CONTRACT = 'fieldflow-fronts-blind-inventory-9cy-v1';
    public const VISION_INTELLIGENCE_II_CONTRACT = 'fieldflow-fronts-vision-intelligence-9d1-v1';
    public const MODES = ['questions', 'on', 'off'];
    public const FIELD_REFERENCE_META = '_ff_field_reference';
    public const NONCE = 'fieldflow_fronts_vision';
    public const MAX_ITEMS = 30;
    private const MAX_PHOTO_BYTES = 12582912;
    private const PHOTO_MAX_SIDE = 1280;
    private const REFERENCE_MAX_SIDE = 384;
    private const RATE_WINDOW = 600;
    private const RATE_MAX = 15;
    private const REFERENCE_RATE_MAX = 30;

    /** Test seam: callable(string $module, string $prompt, array $params): string. */
    public static $aiCaller = null;

    public static function registerHooks(): void {
        add_action('init', [self::class, 'registerPostType'], 4);
        add_action('wp_ajax_' . self::AJAX_READ, [self::class, 'ajaxRead']);
        add_action('wp_ajax_' . self::AJAX_CONFIRM, [self::class, 'ajaxConfirm']);
        add_action('wp_ajax_' . self::AJAX_REFERENCE, [self::class, 'ajaxReference']);
        add_action('fieldflow_form_visit_completed', [self::class, 'linkSubmission'], 30, 3);
    }

    public static function registerPostType(): void {
        if (post_type_exists(self::POST_TYPE)) return;
        register_post_type(self::POST_TYPE, [
            'label' => 'Leituras de frentes por câmara',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public static function clientDefaults(): array {
        return ['enabled' => false, 'mode' => 'questions', 'share_space' => true, 'monthly_limit' => 300, 'daily_limit' => 8, 'monthly_budget_cents' => 0, 'reservation_cents' => 100, 'reference_images' => 3, 'field_references' => true];
    }

    public static function settings(): array {
        $raw = get_option(self::OPTION, []);
        return is_array($raw) ? $raw : [];
    }

    public static function clientSettings(int $clientId): array {
        $all = self::settings();
        $row = is_array($all['clients'][$clientId] ?? null) ? $all['clients'][$clientId] : [];
        $settings = array_merge(self::clientDefaults(), $row);
        // 9CQ modes: "questions" (default: questions with the switch on), "on" (also Share Space), "off".
        $mode = sanitize_key((string)($row['mode'] ?? ''));
        if (!in_array($mode, self::MODES, true)) $mode = !empty($row['enabled']) ? 'on' : 'questions';
        $settings['mode'] = $mode;
        $settings['enabled'] = $mode === 'on';
        $settings['share_space'] = !empty($settings['share_space']);
        $settings['monthly_limit'] = max(0, min(10000, (int)$settings['monthly_limit']));
        $settings['daily_limit'] = max(0, min(500, (int)$settings['daily_limit']));
        $settings['monthly_budget_cents'] = max(0, min(1000000, (int)$settings['monthly_budget_cents']));
        $settings['reservation_cents'] = max(1, min(10000, (int)$settings['reservation_cents']));
        $settings['reference_images'] = max(0, min(16, (int)$settings['reference_images']));
        $settings['field_references'] = !empty($settings['field_references']);
        return $settings;
    }

    public static function saveClientSettings(int $clientId, array $data): array {
        if ($clientId <= 0) throw new \RuntimeException('Cliente inválido.');
        $all = self::settings();
        $all['clients'] = is_array($all['clients'] ?? null) ? $all['clients'] : [];
        $mode = sanitize_key((string)($data['mode'] ?? ''));
        if (!in_array($mode, self::MODES, true)) $mode = !empty($data['enabled']) ? 'on' : 'questions';
        $all['clients'][$clientId] = [
            'mode' => $mode,
            'enabled' => $mode === 'on',
            'share_space' => !empty($data['share_space']),
            'monthly_limit' => max(0, min(10000, (int)($data['monthly_limit'] ?? 300))),
            'daily_limit' => max(0, min(500, (int)($data['daily_limit'] ?? 8))),
            'monthly_budget_cents' => max(0, min(1000000, (int)($data['monthly_budget_cents'] ?? 0))),
            'reservation_cents' => max(1, min(10000, (int)($data['reservation_cents'] ?? 100))),
            'reference_images' => max(0, min(16, (int)($data['reference_images'] ?? 3))),
            // Field references are on unless the back office turns them off.
            'field_references' => !array_key_exists('field_references', $data) || !empty($data['field_references']),
        ];
        update_option(self::OPTION, $all, false);
        return self::clientSettings($clientId);
    }

    public static function providerStatus(): array {
        $provider = Config::aiProviderForModule(self::MODULE);
        $ready = $provider !== 'none' && Config::aiProviderReady($provider);
        $model = '';
        if ($provider === 'azure') $model = self::azureVisionDeployment();
        elseif (class_exists('\\RoutesPro\\Services\\AIFactory')) $model = \RoutesPro\Services\AIFactory::modelLabel($provider);
        $label = class_exists('\\RoutesPro\\Services\\AIFactory') ? \RoutesPro\Services\AIFactory::providerLabel($provider) : $provider;
        return ['provider' => $provider, 'ready' => $ready, 'label' => $label, 'model' => $model, 'boxes' => in_array($provider, ['azure', 'google', 'openai'], true)];
    }

    public static function azureVisionDeployment(): string {
        $vision = trim((string)Config::get('azure_openai_vision_deployment', ''));
        return $vision !== '' ? $vision : trim((string)Config::get('azure_openai_deployment', ''));
    }

    public static function clientEnabled(int $clientId): bool {
        return $clientId > 0 && self::clientSettings($clientId)['enabled'];
    }

    /** Camera reading may run for this client (any mode except "off"). */
    public static function clientAllowed(int $clientId): bool {
        return $clientId > 0 && self::clientSettings($clientId)['mode'] !== 'off';
    }

    public static function fieldReferencesEnabled(int $clientId): bool {
        return self::clientAllowed($clientId) && self::clientSettings($clientId)['field_references'];
    }

    /** Whether the camera button is shown for a question in this visit context. */
    public static function questionEnabled(array $question, array $context, int $clientId = 0): bool {
        return self::questionBlockReason($question, $context, $clientId) === '';
    }

    /**
     * Why a question does not show the camera ('' when it does). The question switch in the form builder is
     * enough for fronts questions; Share Space needs the client set to "on". Shown to managers in the form.
     */
    public static function questionBlockReason(array $question, array $context, int $clientId = 0): string {
        $type = sanitize_key((string)($question['type'] ?? ''));
        if ($type === 'product_matrix' && empty($question['camera_reading'])) return 'question_off';
        if ($type !== 'product_matrix' && $type !== 'share_space_matrix') return 'type';
        $clientId = $clientId ?: (class_exists(Catalog::class) ? Catalog::clientForContext($context) : absint($context['client_id'] ?? 0));
        if ($clientId <= 0) return 'Não foi possível identificar o cliente desta visita.';
        $settings = self::clientSettings($clientId);
        if ($type === 'share_space_matrix' && ($settings['mode'] !== 'on' || !$settings['share_space'] || !empty($question['camera_reading_disabled']))) return 'share_off';
        if ($settings['mode'] === 'off') return 'A leitura por câmara está desligada para este cliente em Cardex e Catálogo → Leitura por câmara.';
        if (!self::providerStatus()['ready']) return 'O módulo "Leitura de frentes" não tem um fornecedor de IA pronto em Configuração de IA.';
        try { \RoutesPro\Vision\VisionUsageGuard::check($clientId, $settings); }
        catch (\RuntimeException $e) { return $e->getMessage(); }
        return '';
    }

    public static function monthCount(int $clientId): int {
        if ($clientId <= 0) return 0;
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'suppress_filters' => true,
            'date_query' => [['after' => current_time('Y-m-01 00:00:00'), 'inclusive' => true]],
            'meta_query' => [['key' => '_ff_client_id', 'value' => (string)$clientId]],
        ]);
        return (int)$query->found_posts;
    }

    // ------------------------------------------------------------------
    // Items, prompt and parsing
    // ------------------------------------------------------------------

    /**
     * Normalises the products of the reading. Product rows are resolved through the catalog
     * identity on the server; Share Space sides use the 9CL briefing label mapping.
     */
    public static function buildItems(int $clientId, string $type, array $raw): array {
        $items = [];
        $add = static function (array $item) use (&$items): void {
            if ($item['name'] === '') return;
            if (count($items) >= self::MAX_ITEMS) throw new \RuntimeException('Demasiados produtos para uma fotografia. Seleciona um grupo mais pequeno e tenta novamente sem custos.');
            $item['code'] = 'P' . (count($items) + 1);
            $items[] = $item;
        };
        if ($type === 'share_space_matrix') {
            foreach ($raw as $card) {
                if (!is_array($card)) continue;
                $cardKey = sanitize_key((string)($card['key'] ?? ''));
                if ($cardKey === '') continue;
                foreach (['pr' => (string)($card['pr_label'] ?? ''), 'competitor' => (string)($card['competitor_label'] ?? '')] as $side => $label) {
                    $label = trim(sanitize_text_field($label));
                    if ($label === '') continue;
                    $ids = $clientId > 0 ? Catalog::productIdsForLabel($clientId, $label) : [];
                    $index = $ids ? Catalog::clientIndex($clientId) : [];
                    $added = false;
                    foreach ($ids as $productId) {
                        $row = $index[$productId] ?? null;
                        if (!$row || empty($row['active'])) continue;
                        $add(['key' => $cardKey . ':' . $side . ':' . (int)$productId, 'card' => $cardKey, 'side' => $side, 'side_label' => $label, 'name' => (string)$row['name'], 'catalog_id' => (int)$productId, 'kind' => (string)$row['kind']]);
                        $added = true;
                    }
                    if (!$added) $add(['key' => $cardKey . ':' . $side . ':label', 'card' => $cardKey, 'side' => $side, 'side_label' => $label, 'name' => $label, 'catalog_id' => 0, 'kind' => $side === 'pr' ? 'own' : 'competitor']);
                }
            }
            return $items;
        }
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['key'] ?? ''));
            $name = trim(sanitize_text_field((string)($row['name'] ?? '')));
            if ($key === '' || $name === '') continue;
            $reference = sanitize_text_field((string)($row['ref'] ?? ''));
            $capacity = sanitize_text_field((string)($row['capacity'] ?? ''));
            // 9CP: the article code is read inside the insignia of the Cardex row.
            $scope = Catalog::scopeForCardexId(absint($row['cardex_id'] ?? 0));
            $catalogId = $clientId > 0 ? Catalog::resolveProductIdScoped($clientId, $reference, $name, $capacity === '' ? null : $capacity, $scope) : 0;
            $add(['key' => $key, 'name' => $name, 'ref' => $reference, 'capacity' => $capacity, 'catalog_id' => $catalogId, 'kind' => 'own']);
        }
        return $items;
    }

    /**
     * 9CW: Tokens which distinguish a variant from other SKUs of the same brand in
     * the selected list. These are *claims to verify*, never a visual classifier.
     * E.g. JAMESON BLACK BARREL 70CL versus JAMESON 70CL -> BLACK, BARREL.
     */
    public static function variantDiscriminators(array $items): array {
        $tokens = [];
        foreach ($items as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $name = function_exists('remove_accents') ? remove_accents($name) : $name;
            preg_match_all('/[\p{L}\p{N}]+/u', strtoupper($name), $parts);
            $tokens[(string)($item['code'] ?? '')] = array_values(array_filter($parts[0] ?? [], static function ($word) {
                return !preg_match('/^(?:\d+|\d+CL|\d+ML|\d+L|CL|ML|L|GARRAFA|BOTTLE|UN|UND|WHISKY|WHISKEY|GIN|VODKA|RUM|COGNAC|LICOR|LIQUEUR|BEBIDA|ESPIRITUOSA|GG|IRLANDES|IRLANDESA|IRLANDESAS|IRLANDESES|ESCOCES|ESCOCESA|ESCOCESAS|ESCOCESES|IRISH|SCOTCH|AGUARDENTE|DESTILADO|DESTILADA)$/', (string)$word);
            }));
        }
        $capacities = [];
        foreach ($items as $item) {
            $code = (string)($item['code'] ?? '');
            $cap = trim((string)($item['capacity'] ?? ''));
            // Cardex may supply 70, 70CL, 700ML or 1L. Canonicalise to centilitres.
            // A generic whisky-category prefix must never become the brand.
            $title = (string)($item['name'] ?? '');
            if ($cap === '') $cap = $title;
            $normalisedCap = '';
            if (preg_match('/(?<!\d)(\d{2,3})\s*CL\b/i', $cap, $match)) $normalisedCap = (string)(int)$match[1];
            elseif (preg_match('/(?<!\d)(\d{3,4})\s*ML\b/i', $cap, $match)) $normalisedCap = (string)((int)$match[1] / 10);
            elseif (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s*L\b/i', $cap, $match)) $normalisedCap = (string)((float)str_replace(',', '.', $match[1]) * 100);
            elseif (ctype_digit($cap)) $normalisedCap = (string)(int)$cap;
            $capacities[$code] = $normalisedCap;
        }
        $result = [];
        foreach ($items as $item) {
            $code = (string)($item['code'] ?? '');
            $words = $tokens[$code] ?? [];
            $brand = $words[0] ?? '';
            $otherWords = [];
            $siblings = 0;
            $capacityDistinction = false;
            if ($brand !== '') foreach ($items as $other) {
                $otherCode = (string)($other['code'] ?? '');
                if ($otherCode === $code || ($tokens[$otherCode][0] ?? '') !== $brand) continue;
                $siblings++;
                $otherWords = array_merge($otherWords, $tokens[$otherCode]);
                if (($capacities[$code] ?? '') !== '' && ($capacities[$otherCode] ?? '') !== ''
                    && $capacities[$code] !== $capacities[$otherCode]) $capacityDistinction = true;
            }
            $unique = $siblings ? array_values(array_unique(array_diff(array_slice($words, 1), $otherWords))) : [];
            $result[$code] = [
                'brand' => $brand, 'variant' => array_slice($unique, 0, 5), 'siblings' => $siblings,
                'capacity' => $capacities[$code] ?? '', 'capacity_required' => $capacityDistinction,
            ];
        }
        return $result;
    }

    public static function prompt(array $items, string $type, int $referenceCount, bool $withBoxes = false): string {
        $lines = [];
        $distinct = self::variantDiscriminators($items);
        foreach ($items as $item) {
            $line = $item['code'] . ': ' . $item['name'];
            if (!empty($item['ref'])) $line .= ' (ref. ' . $item['ref'] . ')';
            if (!empty($item['capacity'])) $line .= ' (capacidade: ' . $item['capacity'] . ' cl)';
            if (!empty($item['side_label'])) $line .= ' — lado "' . $item['side_label'] . '"' . ($item['kind'] === 'competitor' ? ', concorrência' : '');
            $line .= !empty($item['has_reference']) ? ' — tem imagem de referência' : ' — sem imagem de referência';
            $variant = $distinct[(string)$item['code']]['variant'] ?? [];
            if ($variant) $line .= ' — variante: exige leitura visual de ' . implode(' ', $variant);
            $discriminator = $distinct[(string)$item['code']] ?? [];
            if (!empty($discriminator['capacity_required'])) $line .= ' — capacidade distinta: exige leitura do valor ' . $discriminator['capacity'] . ' cl';
            $lines[] = '- ' . $line;
        }
        // Coordinates are requested from *all* vision providers, including Azure,
        // in the same paid call. A textual assertion is not independent evidence.
        return "Tarefa: auditar visualmente frentes de produto numa UNICA fotografia de prateleira.\n"
            . "Conta apenas garrafas fisicamente visíveis na primeira fila, não reflexos, sombras, caixas decorativas ou garrafas atrás.\n"
            . "Produtos POSSÍVEIS (não é uma lista do que está na fotografia):\n" . implode("\n", $lines) . "\n\n"
            . ($referenceCount ? "Seguem $referenceCount imagens de referência identificadas pelo código e, POR ÚLTIMO, a fotografia real da prateleira.\n" : "Segue a fotografia real da prateleira.\n")
            . "PROIBIDO inferir a presença de um SKU apenas porque outro da mesma marca aparece. Cada garrafa tem EXATAMENTE uma identidade.\n"
            . "Distingue rigorosamente marcas, variantes e capacidades. Um produto base não prova a presença de outras edições, sabores ou capacidades da mesma marca.\n"
            . "Para cada frente positiva, localiza a garrafa por uma caixa aproximada [ymin,xmin,ymax,xmax] (0..1000), em coordenadas da fotografia REAL. Uma caixa não pode ser utilizada em dois SKUs.\n"
            . "Transcreve em label_text apenas texto que consegues efetivamente LER no rótulo (não copies a lista de produtos). Em variant_text, só palavras distintivas efetivamente legíveis no produto. Em capacity_text, só a capacidade se estiver visível. Deixa os campos vazios se ilegíveis.\n"
            . "Se a variante distintiva não está legível e não existe evidência visual suficiente, OMITE esse SKU da resposta. Não inventes texto, localização nem confiança.\n"
            . "Não contes produto pela semelhança de cor ou forma com imagens de referência. Não deduzas capacidade pela lista do Cardex.\n"
            . "Para ausência claramente observável, podes indicar fronts 0 e confidence. Para ausência incerta, OMITE o SKU, nunca inventes zero.\n"
            . "fronts é inteiro >= 0; confidence é um número de 0 a 1, não uma certeza comprovada. Para positivos, boxes deve ter uma caixa por frente; para zeros, boxes [].\n"
            . "Responde numa única mensagem JSON, sem markdown, esquema: "
            . '{"items":[{"code":"P1","fronts":1,"confidence":0.92,"label_text":"texto visível","variant_text":"variante legível","capacity_text":"70 cl se visível","boxes":[[100,200,800,320]]}],"unidentified":0,"notes":""}';
    }

    /**
     * 9CY: Inventory-first vision. The model receives the shelf photo only.
     * The possible SKUs and their reference pictures stay in WordPress.
     * This prevents the model from copying an expected variant into the photo.
     */
    public static function blindInventoryPrompt(): string {
        return "Read the REAL shelf photo without any list of expected products. "
            . "First transcribe only what is literally visible on the labels of front-row bottles. "
            . "Do not guess a brand, variant, age, flavour or size. Exclude reflections, "
            . "background bottles, display boxes, glasses and decorative items.\n"
            . "For each physically separate bottle with a readable brand, output one observation. "
            . "visible_text must be an exact transcription from THAT bottle, never an inference. "
            . "visible_brand must appear word-for-word in visible_text. "
            . "Do not combine text from neighbouring bottles. Omit bottles with unreadable brands.\n"
            . "Provide one box [ymin,xmin,ymax,xmax] in 0..1000 image coordinates per bottle. "
            . "Each box must tightly cover the physical bottle from cap to base, excluding "
            . "the reflection and any shelf or cabinet underneath it. "
            . "No product codes, guesses, SKU assignments, reference names or descriptions.\n"
            . "Reply with JSON only: "
            . '{"observations":[{"visible_text":"words actually read","visible_brand":"brand actually read",'
            . '"visible_capacity":"","box":[100,200,700,330],"confidence":0.9,'
            . '"front_row":true}],"notes":""}';
    }

    private static function observationWords(string $raw): array {
        if (function_exists('remove_accents')) $raw = remove_accents($raw);
        preg_match_all('/[\p{L}\p{N}]+/u', strtoupper($raw), $matches);
        return array_values(array_unique($matches[0] ?? []));
    }

    private static function containsObservedTokens(array $words, array $needed): bool {
        if (!$needed) return false;
        foreach ($needed as $word) if (!in_array($word, $words, true)) return false;
        return true;
    }

    /**
     * 9D1: One physical bottle can only contribute once. De-duplicate model
     * observations by rectangle BEFORE accumulating SKU counts. When two
     * different products claim the same position, require manual review.
     * No SKU shortlist or reference picture is ever sent to the model.
     */
    public static function parseBlindInventory(string $raw, array $items): array {
        $raw = trim((string)preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $start = strpos($raw, '{'); $end = strrpos($raw, '}');
        $decoded = ($start !== false && $end !== false && $end > $start)
            ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
        if (!is_array($decoded) || !isset($decoded['observations']) || !is_array($decoded['observations'])) {
            throw new \RuntimeException('A IA nao devolveu observacoes da prateleira. Conta manualmente.');
        }
        $identities = self::variantDiscriminators($items);
        $observed = [];
        foreach (array_slice($decoded['observations'], 0, 45) as $observation) {
            if (!is_array($observation) || (isset($observation['front_row']) && !$observation['front_row'])
                || !empty($observation['is_reflection'])) continue;
            $label = trim(sanitize_text_field((string)($observation['visible_text'] ?? '')));
            $brandText = trim(sanitize_text_field((string)($observation['visible_brand'] ?? '')));
            $capacity = trim(sanitize_text_field((string)($observation['visible_capacity'] ?? '')));
            // Do not trust a separate capacity guess if no unit appears in the OCR text.
            if (!preg_match('/(?<!\d)\d+(?:[.,]\d+)?\s*(?:CL|ML|L)\b/i', $label)) $capacity = '';
            if ($label === '' || $brandText === '') continue;
            $label = substr($label, 0, 180); $brandText = substr($brandText, 0, 60);
            $words = self::observationWords($label);
            $brandWords = self::observationWords($brandText);
            if (!self::containsObservedTokens($words, $brandWords)) continue;
            $boxes = self::evidenceBoxes([$observation['box'] ?? $observation['bbox'] ?? null]);
            if (!$boxes) continue;
            $confidence = is_numeric($observation['confidence'] ?? null)
                ? max(0.0, min(1.0, (float)$observation['confidence'])) : 0.0;
            if ($confidence < 0.6) continue;

            $candidates = [];
            foreach ($items as $item) {
                $code = (string)($item['code'] ?? '');
                $identity = $identities[$code] ?? [];
                $brand = (string)($identity['brand'] ?? '');
                if ($brand === '' || !in_array($brand, $brandWords, true)
                    || !in_array($brand, $words, true)) continue;
                $variant = (array)($identity['variant'] ?? []);
                if ($variant && !self::containsObservedTokens($words, $variant)) continue;
                if (!$variant) {
                    $conflictingVariant = false;
                    foreach ($identities as $otherCode => $other) {
                        if ($otherCode === $code || ($other['brand'] ?? '') !== $brand) continue;
                        if (self::containsObservedTokens($words, (array)($other['variant'] ?? []))) {
                            $conflictingVariant = true; break;
                        }
                    }
                    if ($conflictingVariant) continue;
                }
                $expectedCap = (string)($identity['capacity'] ?? '');
                $capSeen = (bool)preg_match('/(?<!\d)\d+(?:[.,]\d+)?\s*(?:CL|ML|L)\b/i', $label);
                if ($capSeen && $expectedCap !== '' && !self::evidenceCapacityMatches($expectedCap, $label . ' ' . $capacity)) continue;
                $candidates[] = $code;
            }
            $provisional = false;
            if (count($candidates) > 1) {
                $withReference = [];
                foreach ($candidates as $code) foreach ($items as $item) {
                    if (($item['code'] ?? '') === $code && !empty($item['has_reference'])) $withReference[] = $code;
                }
                if (count($withReference) === 1) {
                    $candidates = $withReference;
                    $provisional = true;
                } else $candidates = []; // No automatic identity for ambiguous SKU.
            }
            $observed[] = [
                'code' => count($candidates) === 1 ? $candidates[0] : '',
                'box' => $boxes[0], 'label' => $label, 'capacity' => $capacity,
                'confidence' => $confidence, 'provisional' => $provisional,
            ];
        }
        $deduplicated = 0; $conflicts = []; $unique = [];
        foreach ($observed as $row) {
            $duplicateIndex = null;
            foreach ($unique as $i => $existing) {
                if (self::evidenceBoxesOverlap($row['box'], $existing['box'])) {
                    $duplicateIndex = $i; break;
                }
            }
            if ($duplicateIndex === null) { $unique[] = $row; continue; }
            $previous = $unique[$duplicateIndex];
            if ($previous['code'] === $row['code'] && self::evidenceBoxesStrongDuplicate($row['box'], $previous['box'])) {
                // Only NEAR-IDENTICAL rectangles are safe to collapse. Two adjacent
                // bottles with partially overlapping boxes must go to review.
                $deduplicated++;
                if ($row['confidence'] > $previous['confidence']) $unique[$duplicateIndex] = $row;
            } else {
                // A physical bottle cannot safely belong to two SKUs.
                // Keep a trace for manual review, never silently accept either claim.
                if ($previous['code'] !== '') $conflicts[$previous['code']] = true;
                if ($row['code'] !== '') $conflicts[$row['code']] = true;
                $unique[] = $row;
            }
        }
        $matched = []; $review = []; $unassigned = 0;
        foreach ($unique as $row) {
            if ($row['code'] === '') { $unassigned++; continue; }
            $code = $row['code'];
            $value = $matched[$code] ?? [
                'code' => $code, 'fronts' => 0, 'confidence' => 1.0,
                'label_text' => '', 'variant_text' => '', 'capacity_text' => '', 'boxes' => [],
            ];
            $value['fronts']++;
            $value['confidence'] = min($value['confidence'], $row['confidence']);
            $value['label_text'] .= ' ' . $row['label'];
            $value['capacity_text'] .= ' ' . $row['capacity'];
            $value['boxes'][] = $row['box'];
            $matched[$code] = $value;
            $variant = (array)($identities[$code]['variant'] ?? []);
            $height = $row['box'][2] - $row['box'][0];
            $width = max(1, $row['box'][3] - $row['box'][1]);
            if ($variant) $review[$code] = 'variant_manual_verification';
            elseif ($height / $width > 5.5) $review[$code] = 'box_unreliable';
            elseif ($row['provisional']) $review[$code] = 'capacity_unverified';
        }
        $result = self::parseResult((string)wp_json_encode(['items' => array_values($matched)]), $items);
        foreach ($result['items'] as &$entry) {
            $code = (string)$entry['code'];
            if (($entry['model_fronts'] ?? 0) <= 0) continue;
            $reason = !empty($conflicts[$code]) ? 'duplicate_position' : ($review[$code] ?? '');
            if ($reason === '') continue;
            $entry['fronts'] = null;
            $entry['needs_review'] = true;
            $entry['recognition_status'] = 'uncertain';
            $entry['review_reason'] = $reason;
            $entry['boxes'] = []; // Never draw a verified-green box for a disputed SKU.
        }
        unset($entry);
        $result['unidentified'] = $unassigned;
        $result['deduplicated'] = $deduplicated;
        $result['distinct_observations'] = count($unique);
        $result['notes'] = 'Contagem por garrafa fisica sem lista de produtos enviada a IA. Confirma marcas, variantes e capacidades.';
        $result['vision_mode'] = self::BLIND_INVENTORY_CONTRACT;
        return $result;
    }

    /** Accept only actual normalized rectangles, never clamp invalid coordinates into plausible positions. */
    private static function evidenceBoxes($raw): array {
        $boxes = [];
        foreach (array_slice(is_array($raw) ? $raw : [], 0, 60) as $box) {
            if (!is_array($box) || count($box) !== 4) continue;
            $values = array_values($box);
            if (count(array_filter($values, static function ($v) {
                return is_numeric($v) && is_finite((float)$v) && (float)$v >= 0 && (float)$v <= 1000;
            })) !== 4) continue;
            $b = array_map(static function ($n) { return (int)round((float)$n); }, $values);
            if ($b[2] - $b[0] < 12 || $b[3] - $b[1] < 12) continue;
            $boxes[] = $b;
        }
        return $boxes;
    }

    /** Conservative duplicate-object test: substantial IoU OR containment. */
    public static function evidenceBoxesOverlap(array $a, array $b): bool {
        if (count($a) !== 4 || count($b) !== 4) return false;
        $h = max(0, min($a[2], $b[2]) - max($a[0], $b[0]));
        $w = max(0, min($a[3], $b[3]) - max($a[1], $b[1]));
        $intersection = $h * $w;
        $areaA = max(1, ($a[2] - $a[0]) * ($a[3] - $a[1]));
        $areaB = max(1, ($b[2] - $b[0]) * ($b[3] - $b[1]));
        $union = max(1, $areaA + $areaB - $intersection);
        return $intersection / $union >= 0.35 || $intersection / min($areaA, $areaB) >= 0.68;
    }

    /** A high-overlap detector for repeated sightings of the SAME physical bottle. */
    private static function evidenceBoxesStrongDuplicate(array $a, array $b): bool {
        if (count($a) !== 4 || count($b) !== 4) return false;
        $height = max(0, min($a[2], $b[2]) - max($a[0], $b[0]));
        $width = max(0, min($a[3], $b[3]) - max($a[1], $b[1]));
        $intersection = $height * $width;
        $areaA = max(1, ($a[2] - $a[0]) * ($a[3] - $a[1]));
        $areaB = max(1, ($b[2] - $b[0]) * ($b[3] - $b[1]));
        return $intersection / max(1, $areaA + $areaB - $intersection) >= 0.72
            || $intersection / min($areaA, $areaB) >= 0.92;
    }

    /** Reject the model's bare brand assertion when the SKU needs variant evidence. */
    private static function evidenceVariantMatches(array $discriminator, string $label, string $variant): bool {
        $required = $discriminator['variant'] ?? [];
        if (!$required) return true;
        $text = function_exists('remove_accents') ? remove_accents($label . ' ' . $variant) : ($label . ' ' . $variant);
        preg_match_all('/[\p{L}\p{N}]+/u', strtoupper($text), $match);
        $seen = array_unique($match[0] ?? []);
        foreach ($required as $token) if (!in_array($token, $seen, true)) return false;
        return true;
    }

    /** 9CX: check a capacity seen on the physical label, never inferred from the SKU list. */
    private static function evidenceCapacityMatches(string $expectedCl, string $observed): bool {
        if ($expectedCl === '' || !is_numeric($expectedCl)) return false;
        $expected = (float)$expectedCl;
        if (!preg_match_all('/(?<!\d)(\d+(?:[.,]\d+)?)\s*(CL|ML|L)\b/iu', $observed, $matches, PREG_SET_ORDER)) return false;
        foreach ($matches as $match) {
            $value = (float)str_replace(',', '.', $match[1]);
            $actual = strtoupper($match[2]) === 'ML' ? $value / 10 : (strtoupper($match[2]) === 'L' ? $value * 100 : $value);
            if (abs($actual - $expected) < 0.001) return true;
        }
        return false;
    }

    /** Parse positive fronts only when physical positions and SKU evidence are present. */
    public static function parseResult(string $raw, array $items): array {
        $text = trim($raw);
        $text = (string)preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $start = strpos($text, '{'); $end = strrpos($text, '}');
        $decoded = ($start !== false && $end !== false && $end > $start) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        if (!is_array($decoded)) throw new \RuntimeException('A IA não devolveu uma leitura válida. Tenta outra fotografia ou conta à mão.');
        $byCode = []; $duplicateCodes = [];
        foreach ((array)($decoded['items'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $code = strtoupper(trim((string)($row['code'] ?? $row['id'] ?? '')));
            if ($code === '') continue;
            if (isset($byCode[$code])) $duplicateCodes[$code] = true;
            $byCode[$code] = $row;
        }
        $discriminators = self::variantDiscriminators($items);
        $out = [];
        foreach ($items as $item) {
            $code = (string)($item['code'] ?? '');
            $row = $byCode[$code] ?? [];
            $numeric = array_key_exists('fronts', $row) && is_numeric($row['fronts']) && is_finite((float)$row['fronts']);
            $number = $numeric ? (float)$row['fronts'] : -1;
            $hasValue = $numeric && $number >= 0 && $number <= 200 && floor($number) === $number;
            $modelFronts = $hasValue ? (int)$number : null;
            $confidence = isset($row['confidence']) && is_numeric($row['confidence']) && is_finite((float)$row['confidence']) ? (float)$row['confidence'] : 0.0;
            $confidence = max(0.0, min(1.0, $confidence));
            $boxes = self::evidenceBoxes($row['boxes'] ?? []);
            $label = trim(sanitize_text_field((string)($row['label_text'] ?? '')));
            $label = function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
            $variant = trim(sanitize_text_field((string)($row['variant_text'] ?? '')));
            $variant = function_exists('mb_substr') ? mb_substr($variant, 0, 120) : substr($variant, 0, 120);
            $capacityText = trim(sanitize_text_field((string)($row['capacity_text'] ?? '')));
            $capacityText = function_exists('mb_substr') ? mb_substr($capacityText, 0, 40) : substr($capacityText, 0, 40);
            $reason = '';
            if (isset($duplicateCodes[$code])) $reason = 'duplicate_code';
            elseif (!$hasValue) $reason = 'unrecognised';
            elseif ($modelFronts > 0) {
                $brand = (string)($discriminators[$code]['brand'] ?? '');
                $labelWords = (string)(function_exists('remove_accents') ? remove_accents($label) : $label);
                preg_match_all('/[\p{L}\p{N}]+/u', strtoupper($labelWords), $seenLabel);
                if (!$label || count($boxes) !== $modelFronts) $reason = 'no_visual_proof';
                elseif ($brand !== '' && !in_array($brand, $seenLabel[0] ?? [], true)) $reason = 'brand_unverified';
                elseif (!self::evidenceVariantMatches($discriminators[$code] ?? [], $label, $variant)) $reason = 'variant_unverified';
                elseif (!empty($discriminators[$code]['capacity_required']) && !self::evidenceCapacityMatches((string)$discriminators[$code]['capacity'], $label . ' ' . $capacityText)) $reason = 'capacity_unverified';
                elseif ($confidence < 0.85) $reason = 'low_confidence';
            } elseif ($confidence < 0.8) $reason = 'uncertain_zero';
            $out[] = [
                'key' => (string)($item['key'] ?? ''), 'code' => $code, 'name' => (string)($item['name'] ?? ''),
                'catalog_id' => (int)($item['catalog_id'] ?? 0), 'ref' => (string)($item['ref'] ?? ''), 'capacity' => (string)($item['capacity'] ?? ''), 'card' => (string)($item['card'] ?? ''),
                'side' => (string)($item['side'] ?? ''), 'side_label' => (string)($item['side_label'] ?? ''),
                'has_reference' => !empty($item['has_reference']),
                'fronts' => $reason === '' ? $modelFronts : ($modelFronts === 0 && $reason === 'uncertain_zero' ? 0 : null),
                // Audit trail only. Never populate the field with an unverified model claim.
                'model_fronts' => $modelFronts,
                'needs_review' => $reason !== '',
                'recognition_status' => !$hasValue ? 'unrecognised' : ($reason ? 'uncertain' : 'suggested'),
                'review_reason' => $reason, 'confidence' => round($confidence, 2),
                'found' => (bool)$row, 'boxes' => $boxes,
                'label_text' => $label, 'variant_text' => $variant, 'capacity_text' => $capacityText,
            ];
        }
        // 9CX: only independently plausible SKU identifications compete for a physical box.
        // A phantom variant rejected because BLACK BARREL is unreadable cannot veto a
        // legitimate JAMESON ORIGINAL on an overlapping box. Two verified claims for
        // the same position still block each other. Collect collisions before editing.
        $collisions = [];
        foreach ($out as $i => $entry) {
            if (($entry['model_fronts'] ?? 0) <= 0 || $entry['review_reason'] !== '') continue;
            foreach ($entry['boxes'] as $j => $a) {
                foreach ($entry['boxes'] as $k => $b) {
                    if ($k > $j && self::evidenceBoxesOverlap($a, $b)) $collisions[$i] = true;
                }
                foreach ($out as $otherIndex => $other) {
                    if ($otherIndex <= $i || ($other['model_fronts'] ?? 0) <= 0 || $other['review_reason'] !== '') continue;
                    foreach ($other['boxes'] as $b) if (self::evidenceBoxesOverlap($a, $b)) {
                        $collisions[$i] = true;
                        $collisions[$otherIndex] = true;
                    }
                }
            }
        }
        foreach (array_keys($collisions) as $i) {
            $out[$i]['fronts'] = null;
            $out[$i]['needs_review'] = true;
            $out[$i]['recognition_status'] = 'uncertain';
            $out[$i]['review_reason'] = 'duplicate_position';
        }
        return [
            'items' => $out,
            'unidentified' => max(0, min(500, (int)($decoded['unidentified'] ?? 0))),
            'notes' => trim(sanitize_text_field((string)($decoded['notes'] ?? ''))),
        ];
    }

    /** A stable product identifier within the same client, form and question. */
    private static function comparisonKey(array $row, string $type): string {
        $catalogId = absint($row['catalog_id'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        $normal = function_exists('remove_accents') ? remove_accents($name) : $name;
        $normal = strtoupper(trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normal)));
        if ($catalogId > 0) $identity = 'catalog:' . $catalogId;
        elseif ($normal !== '') $identity = 'name:' . $normal;
        else return '';
        if ($type === 'share_space_matrix') {
            $card = sanitize_key((string)($row['card'] ?? ''));
            $side = sanitize_key((string)($row['side'] ?? ''));
            if ($card === '' || !in_array($side, ['pr', 'competitor'], true)) return '';
            $identity = $card . ':' . $side . ':' . $identity;
        }
        return $identity;
    }

    /**
     * Pure comparison, based exclusively on manually confirmed readings.
     * Refuse duplicate identities on either side instead of mixing product rows.
     * The caller must validate the tenant, store, form, question and closed visit.
     */
    public static function compareConfirmedWithPrevious(array $current, array $previousDetected, array $previousConfirmed, string $type): array {
        $pastCountByKey = [];
        foreach ($previousConfirmed as $row) {
            if (!is_array($row) || !isset($row['key']) || !isset($row['fronts'])
                || !is_numeric($row['fronts']) || (float)$row['fronts'] < 0
                || (float)$row['fronts'] !== floor((float)$row['fronts'])) continue;
            $pastCountByKey[(string)$row['key']] = (int)$row['fronts'];
        }
        $old = []; $oldMultiplicity = [];
        foreach ((array)($previousDetected['items'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $id = self::comparisonKey($row, $type);
            if ($id === '') continue;
            $oldMultiplicity[$id] = ($oldMultiplicity[$id] ?? 0) + 1;
            if (array_key_exists((string)($row['key'] ?? ''), $pastCountByKey)) {
                $old[$id] = $pastCountByKey[(string)$row['key']];
            }
        }
        $newMultiplicity = [];
        foreach ($current as $row) {
            if (!is_array($row)) continue;
            $id = self::comparisonKey($row, $type);
            if ($id !== '') $newMultiplicity[$id] = ($newMultiplicity[$id] ?? 0) + 1;
        }
        $out = [];
        foreach ($current as $row) {
            if (!is_array($row) || !isset($row['key'])) continue;
            $id = self::comparisonKey($row, $type);
            if ($id !== '' && ($oldMultiplicity[$id] ?? 0) === 1
                && ($newMultiplicity[$id] ?? 0) === 1 && array_key_exists($id, $old)) {
                $out[(string)$row['key']] = $old[$id];
            }
        }
        return $out;
    }

    /** Last closed visit, exact tenant/store/form/question, never today's draft. */
    public static function previousClosedVisit(array $request, array $current, string $type): ?array {
        $client = absint($request['client_id'] ?? 0);
        $project = absint($request['project_id'] ?? 0);
        $store = absint($request['location_id'] ?? 0);
        $form = absint($request['form_id'] ?? 0);
        $question = sanitize_key((string)($request['question_key'] ?? ''));
        $day = (string)($request['date'] ?? '');
        if (!$client || !$project || !$store || !$form || !$question
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) return null;
        $meta = ['relation' => 'AND',
            ['key' => '_ff_client_id', 'value' => (string)$client],
            ['key' => '_ff_project_id', 'value' => (string)$project],
            ['key' => '_ff_location_id', 'value' => (string)$store],
            ['key' => '_ff_form_id', 'value' => (string)$form],
            ['key' => '_ff_question_key', 'value' => $question],
            ['key' => '_ff_question_type', 'value' => $type],
            ['key' => '_ff_status', 'value' => 'confirmed'],
            ['key' => '_ff_submission_id', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC'],
            ['key' => '_ff_date', 'value' => $day, 'compare' => '<', 'type' => 'DATE'],
        ];
        try {
            $ids = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish',
                'fields' => 'ids', 'numberposts' => 20, 'meta_key' => '_ff_date',
                'orderby' => 'meta_value', 'order' => 'DESC', // last CLOSED report date, never insertion timestamp
                'no_found_rows' => true, 'suppress_filters' => true, 'meta_query' => $meta]);
            foreach ((array)$ids as $id) {
                $id = absint($id);
                if (!$id) continue;
                // Recheck scope to avoid leaking previous-store or draft values.
                $same = (int)get_post_meta($id, '_ff_client_id', true) === $client
                    && (int)get_post_meta($id, '_ff_project_id', true) === $project
                    && (int)get_post_meta($id, '_ff_location_id', true) === $store
                    && (int)get_post_meta($id, '_ff_form_id', true) === $form
                    && sanitize_key((string)get_post_meta($id, '_ff_question_key', true)) === $question
                    && (string)get_post_meta($id, '_ff_question_type', true) === $type
                    && (string)get_post_meta($id, '_ff_status', true) === 'confirmed'
                    && (int)get_post_meta($id, '_ff_submission_id', true) > 0;
                $oldDate = (string)get_post_meta($id, '_ff_date', true);
                if (!$same || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $oldDate) || $oldDate >= $day) continue;
                $oldDetected = json_decode((string)get_post_meta($id, '_ff_detected', true), true);
                $oldConfirmed = json_decode((string)get_post_meta($id, '_ff_confirmed', true), true);
                if (!is_array($oldDetected) || !is_array($oldConfirmed)) continue;
                $counts = self::compareConfirmedWithPrevious($current, $oldDetected, $oldConfirmed, $type);
                if (!$counts) continue;
                return ['date' => $oldDate, 'submission_id' => (int)get_post_meta($id, '_ff_submission_id', true),
                    'items' => $counts, 'basis' => 'confirmed_closed_visit'];
            }
        } catch (\Throwable $e) { return null; } // Historical comparison never blocks a visit.
        return null;
    }

    // ------------------------------------------------------------------
    // Images
    // ------------------------------------------------------------------

    /** Reference images of the catalog products (first image of each), smallest stored size that still identifies the label. */
    public static function referenceImages(int $clientId, array &$items, int $max): array {
        $images = [];
        if ($max <= 0 || $clientId <= 0) return $images;
        $index = Catalog::clientIndex($clientId);
        foreach ($items as &$item) {
            if (count($images) >= $max) break;
            $attachmentId = (int)($index[(int)($item['catalog_id'] ?? 0)]['image_ids'][0] ?? 0);
            if ($attachmentId <= 0) continue;
            $payload = self::attachmentPayload($attachmentId);
            if (!$payload) continue;
            $item['has_reference'] = true;
            $images[] = ['mime' => $payload['mime'], 'data' => $payload['data'], 'label' => 'Imagem de referência ' . $item['code'] . ' — ' . $item['name'] . ':', 'detail' => 'low'];
        }
        unset($item);
        return $images;
    }

    private static function attachmentPayload(int $attachmentId): ?array {
        $path = '';
        if (function_exists('image_get_intermediate_size')) {
            $size = image_get_intermediate_size($attachmentId, 'medium');
            if (is_array($size) && !empty($size['path'])) {
                $uploads = wp_upload_dir();
                $candidate = trailingslashit((string)$uploads['basedir']) . ltrim((string)$size['path'], '/');
                if (is_readable($candidate)) $path = $candidate;
            }
        }
        if ($path === '') $path = (string)get_attached_file($attachmentId);
        if ($path === '' || !is_readable($path)) return null;
        return self::imagePayload($path, self::REFERENCE_MAX_SIDE, 80);
    }

    /** Base64 JPEG/PNG/WebP payload, downscaled when larger than $maxSide. */
    public static function imagePayload(string $path, int $maxSide, int $quality = 82): ?array {
        $info = @getimagesize($path);
        if (!$info || empty($info['mime']) || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
        $mime = (string)$info['mime'];
        $bytes = '';
        if ((int)$info[0] > $maxSide || (int)$info[1] > $maxSide) {
            if (!function_exists('wp_tempnam') && defined('ABSPATH') && is_readable(ABSPATH . 'wp-admin/includes/file.php')) require_once ABSPATH . 'wp-admin/includes/file.php';
            $editor = function_exists('wp_get_image_editor') ? wp_get_image_editor($path) : null;
            if ($editor && !is_wp_error($editor)) {
                $editor->resize($maxSide, $maxSide, false);
                $editor->set_quality($quality);
                $tmp = function_exists('wp_tempnam') ? wp_tempnam('ff-vision') : (string)tempnam(sys_get_temp_dir(), 'ffv');
                $saved = $editor->save($tmp . '.jpg', 'image/jpeg');
                if (!is_wp_error($saved) && !empty($saved['path']) && is_readable($saved['path'])) {
                    $bytes = (string)file_get_contents($saved['path']);
                    $mime = 'image/jpeg';
                    @unlink($saved['path']);
                }
                @unlink($tmp);
            }
        }
        if ($bytes === '') $bytes = (string)file_get_contents($path);
        if ($bytes === '') return null;
        return ['mime' => $mime, 'data' => base64_encode($bytes), 'bytes' => strlen($bytes)];
    }

    // ------------------------------------------------------------------
    // Reading flow
    // ------------------------------------------------------------------

    /**
     * @param array $request client_id, project_id, route_id, stop_id, location_id, form_id, date, question_key, question_type, scope_label, items[]
     * @param array $photo   ['path' => local file, 'url' => public url]
     */
    /**
     * One device capture, one paid attempt while the short replay cache exists.
     * Includes the exact visit, question, product list and uploaded photo bytes.
     * No photograph, audio or product identity is retained in the replay key.
     * Capture IDs are never an authorization primitive; scope is checked by ajaxRead.
     */
    public static function captureReplayKey(array $request, string $photoPath, int $userId): string {
        $captureId = (string)($request['capture_id'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{12,96}$/', $captureId) || $userId <= 0 || !is_readable($photoPath)) return '';
        $parts = [
            // 9CW: never reuse a 9CV model response that had no visual evidence.
            self::VISUAL_EVIDENCE_CONTRACT, self::BLIND_INVENTORY_CONTRACT,
            $userId, absint($request['client_id'] ?? 0), absint($request['project_id'] ?? 0),
            absint($request['location_id'] ?? 0), absint($request['form_id'] ?? 0),
            absint($request['route_id'] ?? 0), absint($request['stop_id'] ?? 0),
            (string)($request['date'] ?? ''), (string)($request['question_key'] ?? ''),
            (string)($request['question_type'] ?? ''), $captureId,
            hash('sha256', (string)wp_json_encode($request['items'] ?? [], JSON_UNESCAPED_UNICODE)),
            (string)hash_file('sha256', $photoPath),
        ];
        return 'ff_vision_replay_' . hash('sha256', implode('|', $parts));
    }

    public static function read(array $request, array $photo, int $userId): array {
        $clientId = absint($request['client_id'] ?? 0);
        if ($clientId <= 0) $clientId = Catalog::clientForContext($request);
        $requestType = sanitize_key((string)($request['question_type'] ?? 'product_matrix'));
        if (!self::clientAllowed($clientId) || ($requestType === 'share_space_matrix' && !self::clientEnabled($clientId))) throw new \RuntimeException('A leitura por câmara não está ligada para este cliente.');
        $status = self::providerStatus();
        if (!$status['ready']) throw new \RuntimeException('A leitura por câmara não tem um fornecedor de IA configurado.');
        $settings = self::clientSettings($clientId);
        $type = sanitize_key((string)($request['question_type'] ?? 'product_matrix'));
        if (!in_array($type, ['product_matrix', 'share_space_matrix'], true)) throw new \RuntimeException('Tipo de pergunta sem leitura por câmara.');
        $items = self::buildItems($clientId, $type, (array)($request['items'] ?? []));
        if (!$items) throw new \RuntimeException('Esta pergunta não tem produtos para ler.');
        // 9CY: preserve reference availability in UX, but never send reference
        // photos or candidate SKU names to the vision model.
        $catalog = Catalog::clientIndex($clientId);
        foreach ($items as &$item) {
            $id = (int)($item['catalog_id'] ?? 0);
            $item['has_reference'] = $id > 0 && !empty($catalog[$id]['image_ids']);
        }
        unset($item, $catalog);
        $shelf = self::imagePayload((string)($photo['path'] ?? ''), self::PHOTO_MAX_SIDE, 82);
        if (!$shelf) throw new \RuntimeException('Invalid shelf photograph.');
        $images = [['mime'=>$shelf['mime'], 'data'=>$shelf['data'],
            'label'=>'Real shelf photo:', 'detail'=>'high']];
        // 9CY predecessor Release Manager compatibility only. The 2.2.621
        // package validator expects the historical 9CW source marker below.
        // This legacy assignment is a COMMENT, never executed. In 9CY the
        // catalogue is intentionally NOT sent to Azure for SKU recognition.
        // $prompt = self::prompt($items, $type, count($references), true);
        $prompt = self::blindInventoryPrompt();
        $params = [
            'images' => $images,
            'json' => true,
            'temperature' => 0,
            'max_tokens' => min(2400, 1200 + 80 * count($items)),
            'max_attempts' => 1,
            'disable_model_fallback' => true,
            'force_provider' => $status['provider'],
            'timeout' => 90,
            'system_prompt' => 'És um auditor de merchandising rigoroso. Contas frentes de produtos em fotografias de prateleiras e respondes apenas com o JSON pedido.',
            'client_id' => $clientId,
            'project_id' => absint($request['project_id'] ?? 0),
            'task' => 'fronts_vision_reading',
            'feature_key' => 'fronts_vision',
            'request_kind' => 'field_camera',
        ];
        if ($status['provider'] === 'azure') $params['deployment'] = self::azureVisionDeployment();
        // Lock only identical capture and context (not the whole client). Concurrent duplicate
        // requests must not each make a paid provider call. Release on exceptions too.
        global $wpdb;
        $replayKey = self::captureReplayKey($request + ['client_id' => $clientId], (string)($photo['path'] ?? ''), $userId);
        $replayLocked = false;
        $lockName = '';
        if ($replayKey !== '') {
            if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
                throw new \RuntimeException('A proteção de reenvio da fotografia não está disponível. Conta manualmente.');
            }
            $lockName = 'ffv_replay_' . substr(hash('sha256', $replayKey), 0, 40);
            $replayLocked = (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lockName)) === '1';
            if (!$replayLocked) throw new \RuntimeException('Esta fotografia já está em análise. Não será iniciada uma segunda leitura paga.');
        }
        try {
            if ($replayKey !== '') {
                $cached = get_transient($replayKey);
                if (is_array($cached) && absint($cached['reading_id'] ?? 0) > 0) {
                    $post = get_post(absint($cached['reading_id']));
                    if ($post && $post->post_type === self::POST_TYPE
                        && (int)get_post_meta((int)$post->ID, '_ff_user_id', true) === $userId) {
                        $cached['reused'] = true;
                        $cached['budget'] = VisionUsageGuard::summary($clientId, $settings);
                        return $cached;
                    }
                }
            }
            // The budget must be checked after replay lookup but before any paid call.
            VisionUsageGuard::check($clientId, $settings);
            $started = microtime(true);
            $caller = self::$aiCaller ?: static function (string $module, string $prompt, array $params): string {
                return \RoutesPro\Services\AIManager::complete($module, $prompt, $params);
            };
            // Count before the network call, including any upstream timeout or error.
            // A transient or a plain SELECT is not a concurrency-safe spending cap.
            $budget = \RoutesPro\Vision\VisionUsageGuard::reserve($clientId, $settings);
            $raw = (string)$caller(self::MODULE, $prompt, $params);
            $duration = (int)round((microtime(true) - $started) * 1000);
            $receipt = self::$aiCaller === null && class_exists('\RoutesPro\Services\AIManager')
                ? \RoutesPro\Services\AIManager::lastVisionReceipt() : [];
            // 9D0: reserve was charged before Azure. Settle conservatively only
            // when the provider measured tokens with an explicitly saved tariff.
            // On any settlement failure the full reserve remains in the ledger.
            if (!empty($budget['reservation_id']) && $receipt
                && ($receipt['provider'] ?? '') === $status['provider']
                && ($receipt['model'] ?? '') === $status['model']) {
                try { $budget = VisionUsageGuard::settle($clientId, (string)$budget['reservation_id'], $receipt, $settings); }
                catch (\Throwable $settlementError) { $budget = VisionUsageGuard::summary($clientId, $settings); }
            }
            unset($budget['reservation_id']); // O identificador é interno, nunca enviado à app.
            $result = self::parseBlindInventory($raw, $items);
            // Read-only last closed store visit; no new AI call, never auto-fills fronts.
            $result['previous_visit'] = self::previousClosedVisit($request + ['client_id' => $clientId], $result['items'], $type);
            $readingId = self::store($request + ['client_id' => $clientId], $type, $result, $photo, $userId, $status, $duration, $receipt);
            $response = [
            'contract' => self::CONTRACT,
            'reading_id' => $readingId,
            'items' => $result['items'],
            'unidentified' => $result['unidentified'],
            'notes' => $result['notes'],
            'vision_mode' => self::BLIND_INVENTORY_CONTRACT,
            'previous_visit' => $result['previous_visit'],
            'deduplicated' => (int)($result['deduplicated'] ?? 0),
            'distinct_observations' => (int)($result['distinct_observations'] ?? 0),
            'provider' => $status['label'],
            'model' => $status['model'],
            'boxes' => $status['boxes'],
            'duration_ms' => $duration,
            'photo_url' => (string)($photo['url'] ?? ''),
            'month_count' => self::monthCount($clientId),
            'monthly_limit' => $settings['monthly_limit'],
            'budget' => $budget,
            'ai_usage' => $receipt,
            'reused' => false,
            ];
            if ($replayKey !== '') set_transient($replayKey, $response, 15 * 60);
            return $response;
        } finally {
            if ($replayLocked) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private static function store(array $request, string $type, array $result, array $photo, int $userId, array $status, int $duration, array $receipt = []): int {
        self::registerPostType();
        $locationId = absint($request['location_id'] ?? 0);
        $title = 'Leitura ' . sanitize_text_field((string)($request['scope_label'] ?? $request['question_key'] ?? '')) . ' · loja #' . $locationId;
        $readingId = (int)wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => wp_slash($title),
            'post_author' => $userId,
        ], true);
        if (!$readingId || is_wp_error($readingId)) throw new \RuntimeException('Não foi possível guardar a leitura.');
        $meta = [
            '_ff_client_id' => (string)absint($request['client_id'] ?? 0),
            '_ff_project_id' => (string)absint($request['project_id'] ?? 0),
            '_ff_location_id' => (string)$locationId,
            '_ff_route_id' => (string)absint($request['route_id'] ?? 0),
            '_ff_stop_id' => (string)absint($request['stop_id'] ?? 0),
            '_ff_form_id' => (string)absint($request['form_id'] ?? 0),
            '_ff_user_id' => (string)$userId,
            '_ff_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($request['date'] ?? '')) ? (string)$request['date'] : current_time('Y-m-d'),
            '_ff_question_key' => sanitize_key((string)($request['question_key'] ?? '')),
            '_ff_question_type' => $type,
            '_ff_scope_label' => sanitize_text_field((string)($request['scope_label'] ?? '')),
            '_ff_status' => 'detected',
            '_ff_provider' => (string)$status['provider'],
            '_ff_model' => (string)$status['model'],
            '_ff_duration_ms' => (string)$duration,
            '_ff_photo_url' => esc_url_raw((string)($photo['url'] ?? '')),
            '_ff_capture' => in_array((string)($photo['capture'] ?? ''), ['live', 'camera', 'gallery'], true) ? (string)$photo['capture'] : 'camera',
            '_ff_photo_path' => !empty($photo['live']) ? '' : (string)($photo['path'] ?? ''),
            '_ff_detected' => wp_json_encode($result, JSON_UNESCAPED_UNICODE),
            '_ff_submission_id' => '0',
            '_ff_ai_usage_log_id' => (string)absint($receipt['usage_log_id'] ?? 0),
            '_ff_ai_token_source' => sanitize_key((string)($receipt['token_source'] ?? 'unavailable')),
            '_ff_ai_cost_status' => sanitize_key((string)($receipt['status'] ?? 'unmetered')),
            '_ff_ai_input_tokens' => (string)max(0, (int)($receipt['input_tokens'] ?? 0)),
            '_ff_ai_output_tokens' => (string)max(0, (int)($receipt['output_tokens'] ?? 0)),
            '_ff_ai_estimated_cost_eur' => ($receipt['status'] ?? '') === 'priced'
                ? (string)(float)($receipt['estimated_cost_eur'] ?? 0) : '',
        ];
        foreach ($meta as $key => $value) update_post_meta($readingId, $key, wp_slash($value));
        return $readingId;
    }

    public static function confirm(int $readingId, array $confirmed, int $userId): array {
        $post = get_post($readingId);
        if (!$post || $post->post_type !== self::POST_TYPE) throw new \RuntimeException('Leitura não encontrada.');
        if ((int)get_post_meta($readingId, '_ff_user_id', true) !== $userId) throw new \RuntimeException('Esta leitura pertence a outro utilizador.');
        $detected = json_decode((string)get_post_meta($readingId, '_ff_detected', true), true);
        $detectedByKey = [];
        foreach ((array)($detected['items'] ?? []) as $item) {
            if (!is_array($item) || !isset($item['key'])) continue;
            $detectedByKey[(string)$item['key']] = is_numeric($item['fronts'] ?? null) ? (int)$item['fronts'] : null;
        }
        if (!$detectedByKey) throw new \RuntimeException('Leitura sem produtos.');
        $rowsByKey = [];
        foreach ($confirmed as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_text_field((string)($row['key'] ?? ''));
            if (!array_key_exists($key, $detectedByKey) || isset($rowsByKey[$key])) throw new \RuntimeException('Produto de confirmação inválido ou duplicado.');
            $value = $row['fronts'] ?? null;
            if (!is_numeric($value) || (string)$value === '' || (float)$value < 0 || (float)$value > 500 || (float)$value !== floor((float)$value)) {
                throw new \RuntimeException('Preenche explicitamente todas as quantidades, incluindo os produtos não reconhecidos.');
            }
            $rowsByKey[$key] = (int)$value;
        }
        if (count($rowsByKey) !== count($detectedByKey)) throw new \RuntimeException('Há produtos sem contagem confirmada.');
        $rows = []; $changed = 0;
        foreach ($detectedByKey as $key => $suggestion) {
            $value = $rowsByKey[$key];
            if ($suggestion === null || $value !== $suggestion) $changed++;
            $rows[] = ['key' => $key, 'fronts' => $value, 'detected' => $suggestion];
        }
        if ((string)get_post_meta($readingId, '_ff_status', true) === 'confirmed') {
            $existing = json_decode((string)get_post_meta($readingId, '_ff_confirmed', true), true);
            if ($existing !== $rows) throw new \RuntimeException('A leitura já foi confirmada com outros valores. Corrige diretamente o formulário.');
            return ['reading_id' => $readingId, 'confirmed' => count($rows), 'corrections' => $changed, 'already_confirmed' => true];
        }
        update_post_meta($readingId, '_ff_confirmed', wp_slash(wp_json_encode($rows, JSON_UNESCAPED_UNICODE)));
        update_post_meta($readingId, '_ff_corrections', (string)$changed);
        update_post_meta($readingId, '_ff_status', 'confirmed');
        update_post_meta($readingId, '_ff_confirmed_at', current_time('mysql'));
        return ['reading_id' => $readingId, 'confirmed' => count($rows), 'corrections' => $changed, 'already_confirmed' => false];
    }

    /** Link confirmed evidence only to the exact persisted submission identity. */
    public static function linkSubmission($submissionId = 0, $completion = [], $answers = []): int {
        global $wpdb;
        $submissionId = absint($submissionId);
        if ($submissionId <= 0 || !class_exists('\\RoutesPro\\Forms\\Forms')) return 0;
        $table = \RoutesPro\Forms\Forms::table_submissions();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id,client_id,project_id,location_id,form_id,route_id,route_stop_id,user_id,report_day,submitted_at FROM {$table} WHERE id=%d LIMIT 1",
            $submissionId
        ), ARRAY_A);
        if (!is_array($row) || absint($row['location_id'] ?? 0) <= 0 || absint($row['user_id'] ?? 0) <= 0) return 0;
        $day = (string)($row['report_day'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = substr((string)($row['submitted_at'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) return 0;
        $meta = ['relation' => 'AND',
            ['key' => '_ff_status', 'value' => 'confirmed'],
            ['key' => '_ff_submission_id', 'value' => '0'],
            ['key' => '_ff_client_id', 'value' => (string)absint($row['client_id'] ?? 0)],
            ['key' => '_ff_project_id', 'value' => (string)absint($row['project_id'] ?? 0)],
            ['key' => '_ff_location_id', 'value' => (string)absint($row['location_id'] ?? 0)],
            ['key' => '_ff_form_id', 'value' => (string)absint($row['form_id'] ?? 0)],
            ['key' => '_ff_user_id', 'value' => (string)absint($row['user_id'] ?? 0)],
            ['key' => '_ff_date', 'value' => $day],
            ['key' => '_ff_route_id', 'value' => (string)absint($row['route_id'] ?? 0)],
            ['key' => '_ff_stop_id', 'value' => (string)absint($row['route_stop_id'] ?? 0)],
        ];
        // Ambiguous incomplete context is intentionally not linked.
        if (absint($row['form_id'] ?? 0) <= 0 || absint($row['client_id'] ?? 0) <= 0) return 0;
        $ids = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish',
            'fields' => 'ids', 'numberposts' => 100, 'no_found_rows' => true,
            'suppress_filters' => true, 'meta_query' => $meta]);
        $linked = 0;
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id > 0 && update_post_meta($id, '_ff_submission_id', (string)$submissionId)) $linked++;
        }
        return $linked;
    }

    public static function history(array $filters = [], int $page = 1, int $perPage = 30): array {
        $meta = ['relation' => 'AND'];
        foreach (['client_id' => '_ff_client_id', 'location_id' => '_ff_location_id', 'user_id' => '_ff_user_id'] as $filter => $key) {
            $value = absint($filters[$filter] ?? 0);
            if ($value > 0) $meta[] = ['key' => $key, 'value' => (string)$value];
        }
        $status = sanitize_key((string)($filters['status'] ?? ''));
        if (in_array($status, ['detected', 'confirmed'], true)) $meta[] = ['key' => '_ff_status', 'value' => $status];
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => max(1, min(100, $perPage)), 'paged' => max(1, $page),
            'orderby' => 'date', 'order' => 'DESC', 'meta_query' => $meta, 'suppress_filters' => true,
        ]);
        $rows = [];
        foreach ($query->posts as $post) $rows[] = self::reading((int)$post->ID);
        return ['rows' => array_values(array_filter($rows)), 'total' => (int)$query->found_posts, 'pages' => (int)$query->max_num_pages, 'page' => max(1, $page)];
    }

    public static function reading(int $readingId): ?array {
        $post = get_post($readingId);
        if (!$post || $post->post_type !== self::POST_TYPE) return null;
        $get = static function (string $key) use ($readingId): string { return (string)get_post_meta($readingId, $key, true); };
        $detected = json_decode($get('_ff_detected'), true);
        $confirmed = json_decode($get('_ff_confirmed'), true);
        return [
            'id' => $readingId,
            'created_at' => (string)$post->post_date,
            'client_id' => absint($get('_ff_client_id')),
            'project_id' => absint($get('_ff_project_id')),
            'location_id' => absint($get('_ff_location_id')),
            'user_id' => absint($get('_ff_user_id')),
            'date' => $get('_ff_date'),
            'question_key' => $get('_ff_question_key'),
            'question_type' => $get('_ff_question_type'),
            'scope_label' => $get('_ff_scope_label'),
            'status' => $get('_ff_status') ?: 'detected',
            'provider' => $get('_ff_provider'),
            'model' => $get('_ff_model'),
            'duration_ms' => (int)$get('_ff_duration_ms'),
            'ai_usage_log_id' => absint($get('_ff_ai_usage_log_id')),
            'ai_cost_status' => $get('_ff_ai_cost_status') ?: 'legacy',
            'ai_input_tokens' => (int)$get('_ff_ai_input_tokens'),
            'ai_output_tokens' => (int)$get('_ff_ai_output_tokens'),
            'ai_estimated_cost_eur' => $get('_ff_ai_estimated_cost_eur') !== '' ? (float)$get('_ff_ai_estimated_cost_eur') : null,
            'photo_url' => $get('_ff_photo_url'),
            'capture' => $get('_ff_capture') ?: 'camera',
            'detected' => is_array($detected) ? $detected : ['items' => [], 'unidentified' => 0, 'notes' => ''],
            'confirmed' => is_array($confirmed) ? $confirmed : [],
            'corrections' => (int)$get('_ff_corrections'),
            'submission_id' => absint($get('_ff_submission_id')),
        ];
    }

    // ------------------------------------------------------------------
    // Field reference photos (9CP, fieldflow-fronts-field-reference-v1)
    // ------------------------------------------------------------------

    /**
     * Resolves the catalog product of a Cardex row or of a Share Space side without creating it.
     * Returns [product id (0 when it does not exist yet), data to create it].
     */
    public static function referenceTarget(int $clientId, string $type, array $item): array {
        if ($type === 'share_space_matrix') {
            $label = trim(sanitize_text_field((string)($item['label'] ?? '')));
            if ($label === '') throw new \RuntimeException('Rótulo do Share Space em falta.');
            $side = sanitize_key((string)($item['side'] ?? '')) === 'competitor' ? 'competitor' : 'pr';
            $index = Catalog::clientIndex($clientId);
            $mapped = array_values(array_filter(Catalog::productIdsForLabel($clientId, $label), static function ($id) use ($index) { return !empty($index[$id]['active']); }));
            foreach ($mapped as $productId) if (empty($index[$productId]['image_count'])) return [(int)$productId, null, $label];
            if ($mapped) return [(int)$mapped[0], null, $label];
            $existing = Catalog::findByKeys($clientId, Catalog::identityKeys('', $label, null));
            return [$existing, ['name' => $label, 'capacity_cl' => '', 'kind' => $side === 'competitor' ? 'competitor' : 'own'], $label];
        }
        $name = trim(sanitize_text_field((string)($item['name'] ?? '')));
        if ($name === '') throw new \RuntimeException('Produto em falta.');
        $reference = sanitize_text_field((string)($item['ref'] ?? ''));
        $capacity = Catalog::normalizeCapacity(sanitize_text_field((string)($item['capacity'] ?? '')), $name);
        $scope = Catalog::scopeForCardexId(absint($item['cardex_id'] ?? 0));
        $productId = Catalog::resolveProductIdScoped($clientId, $reference, $name, $capacity === '' ? null : $capacity, $scope);
        if (!$productId) $productId = Catalog::findByKeys($clientId, Catalog::identityKeys('', $name, $capacity === '' ? null : $capacity));
        return [$productId, ['name' => $name, 'capacity_cl' => $capacity, 'kind' => 'own'], ''];
    }

    /**
     * Adds a reference photographed in the field to the catalog product of a row or side.
     * Only for products without an image: the back office images are never replaced from the field.
     *
     * @param array $photo ['path' => stored file, 'url' => public url, 'mime' => image type]
     */
    public static function addFieldReference(array $request, array $item, array $photo, int $userId): array {
        $path = (string)($photo['path'] ?? '');
        try {
            $clientId = absint($request['client_id'] ?? 0);
            if ($clientId <= 0) $clientId = Catalog::clientForContext($request);
            if (!self::fieldReferencesEnabled($clientId)) throw new \RuntimeException('As fotografias de referência não estão ligadas para este cliente.');
            $type = sanitize_key((string)($request['question_type'] ?? 'product_matrix'));
            if (!in_array($type, ['product_matrix', 'share_space_matrix'], true)) throw new \RuntimeException('Tipo de pergunta sem leitura por câmara.');
            $info = $path !== '' ? @getimagesize($path) : false;
            if (!$info || !in_array((string)($info['mime'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) throw new \RuntimeException('A fotografia tem de ser JPEG, PNG ou WebP.');
            [$productId, $create, $label] = self::referenceTarget($clientId, $type, $item);
            if ($productId) {
                $product = Catalog::get($productId);
                if (!$product) throw new \RuntimeException('Produto não encontrado no catálogo.');
                if ($product['image_count'] > 0) throw new \RuntimeException('Este produto já tem imagem de referência no catálogo.');
            }
        } catch (\Throwable $e) {
            if ($path !== '' && is_file($path)) @unlink($path);
            throw $e;
        }
        $name = $productId ? (string)$product['name'] : (string)$create['name'];
        $attachmentId = self::createReferenceAttachment($path, (string)$info['mime'], $name, [
            'contract' => self::REFERENCE_CONTRACT,
            'user_id' => $userId,
            'client_id' => $clientId,
            'project_id' => absint($request['project_id'] ?? 0),
            'location_id' => absint($request['location_id'] ?? 0),
            'question_key' => sanitize_key((string)($request['question_key'] ?? '')),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($request['date'] ?? '')) ? (string)$request['date'] : current_time('Y-m-d'),
            'created_at' => current_time('mysql'),
        ]);
        $created = false;
        if (!$productId) {
            $projectId = absint($request['project_id'] ?? 0);
            $productId = Catalog::save(0, [
                'client_id' => $clientId,
                'name' => $create['name'],
                'capacity_cl' => $create['capacity_cl'],
                'kind' => $create['kind'],
                'origin' => 'field',
                'project_ids' => $projectId ? [$projectId] : [],
            ]);
            $created = true;
        }
        // A Share Space side photographed in the field stays linked to its briefing label.
        if ($label !== '') {
            $mapped = Catalog::productIdsForLabel($clientId, $label);
            if (!in_array((int)$productId, $mapped, true)) Catalog::mapLabel($clientId, $label, array_merge($mapped, [(int)$productId]));
        }
        $count = Catalog::addImage($productId, $attachmentId);
        $thumb = wp_get_attachment_image_src($attachmentId, 'thumbnail');
        $full = wp_get_attachment_image_src($attachmentId, 'large');
        return [
            'contract' => self::REFERENCE_CONTRACT,
            'product_id' => (int)$productId,
            'name' => $name,
            'created' => $created,
            'attachment_id' => (int)$attachmentId,
            'image_count' => $count,
            'thumb' => (string)($thumb[0] ?? $full[0] ?? (string)($photo['url'] ?? '')),
            'full' => (string)($full[0] ?? $thumb[0] ?? (string)($photo['url'] ?? '')),
        ];
    }

    /** Media library record of a field reference (sizes generated like any upload, tagged as field reference). */
    private static function createReferenceAttachment(string $path, string $mime, string $name, array $meta): int {
        if (!function_exists('wp_generate_attachment_metadata') && defined('ABSPATH') && is_readable(ABSPATH . 'wp-admin/includes/image.php')) require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => wp_slash(sanitize_text_field('Referência do terreno · ' . $name)),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $path, 0, true);
        if (!$attachmentId || is_wp_error($attachmentId)) {
            if (is_file($path)) @unlink($path);
            throw new \RuntimeException('Não foi possível guardar a fotografia de referência.');
        }
        $attachmentId = (int)$attachmentId;
        if (function_exists('wp_generate_attachment_metadata')) {
            $generated = wp_generate_attachment_metadata($attachmentId, $path);
            if (is_array($generated)) wp_update_attachment_metadata($attachmentId, $generated);
        }
        update_post_meta($attachmentId, self::FIELD_REFERENCE_META, wp_slash(wp_json_encode($meta, JSON_UNESCAPED_UNICODE)));
        return $attachmentId;
    }

    // ------------------------------------------------------------------
    // AJAX (front: web, app WebView and desktop share the same session)
    // ------------------------------------------------------------------

    public static function ajaxRead(): void {
        try {
            $userId = self::ajaxGuard();
            self::rateLimit($userId);
            $request = self::requestFromPost();
            $scopeError = self::scopeError($request, $userId);
            if ($scopeError !== '') self::fail($scopeError, 403);
            self::assertQuestionScope($request);
            $capture = sanitize_key((string)($_POST['capture'] ?? 'camera'));
            // 9CQ fieldflow-fronts-vision-live-count-v1: a frame of the live camera is read and discarded (no photo kept).
            // Historical 9CQ implementation marker (retired for 9CS privacy): $photo = $capture === 'live' ? self::livePhoto($_FILES['photo'] ?? null) : self::storePhoto($_FILES['photo'] ?? null);
            $photo = self::livePhoto($_FILES['photo'] ?? null); // Ephemeral for ALL captures, no public shelf-photo URL.
            $photo['capture'] = in_array($capture, ['live', 'camera', 'gallery'], true) ? $capture : 'camera';
            wp_send_json_success(self::read($request, $photo, $userId));
        } catch (\Throwable $e) {
            self::fail($e->getMessage(), 422);
        }
    }

    public static function ajaxConfirm(): void {
        try {
            $userId = self::ajaxGuard();
            $confirmed = json_decode(wp_unslash((string)($_POST['confirmed'] ?? '[]')), true);
            wp_send_json_success(self::confirm(absint($_POST['reading_id'] ?? 0), is_array($confirmed) ? $confirmed : [], $userId));
        } catch (\Throwable $e) {
            self::fail($e->getMessage(), 422);
        }
    }

    public static function ajaxReference(): void {
        try {
            $userId = self::ajaxGuard();
            self::rateLimit($userId, 'ff_vision_ref_rate_', self::REFERENCE_RATE_MAX, 'Demasiadas fotografias de referência seguidas. Espera uns minutos.');
            $request = self::requestFromPost();
            $scopeError = self::scopeError($request, $userId);
            if ($scopeError !== '') self::fail($scopeError, 403);
            $item = json_decode(wp_unslash((string)($_POST['item'] ?? '{}')), true);
            if (!is_array($item)) throw new \RuntimeException('Produto em falta.');
            self::assertQuestionScope($request, $item);
            $photo = self::storePhoto($_FILES['photo'] ?? null, 'ff-reference-');
            wp_send_json_success(self::addFieldReference($request, $item, $photo, $userId));
        } catch (\Throwable $e) {
            self::fail($e->getMessage(), 422);
        }
    }

    private static function ajaxGuard(): int {
        if (!is_user_logged_in()) self::fail('Sem sessão ativa. Volta a entrar no FieldFlow.', 401);
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) self::fail('A sessão do formulário expirou. Recarrega a visita e tenta de novo.', 403);
        return (int)get_current_user_id();
    }

    private static function fail(string $message, int $status): void {
        wp_send_json_error(['message' => $message, 'contract' => self::CONTRACT], $status);
    }

    private static function rateLimit(int $userId, string $prefix = 'ff_vision_rate_', int $max = self::RATE_MAX, string $message = 'Demasiadas leituras seguidas. Espera uns minutos ou conta à mão.'): void {
        $key = $prefix . $userId;
        $now = time();
        $hits = array_values(array_filter((array)get_transient($key), static function ($t) use ($now) { return (int)$t > $now - self::RATE_WINDOW; }));
        if (count($hits) >= $max) throw new \RuntimeException($message);
        $hits[] = $now;
        set_transient($key, $hits, self::RATE_WINDOW);
    }

    private static function requestFromPost(): array {
        $context = json_decode(wp_unslash((string)($_POST['context'] ?? '{}')), true);
        $items = json_decode(wp_unslash((string)($_POST['items'] ?? '[]')), true);
        $context = is_array($context) ? $context : [];
        return [
            'client_id' => absint($context['client_id'] ?? 0),
            'project_id' => absint($context['project_id'] ?? 0),
            'route_id' => absint($context['route_id'] ?? 0),
            'stop_id' => absint($context['stop_id'] ?? 0),
            'location_id' => absint($context['location_id'] ?? 0),
            'form_id' => absint($context['form_id'] ?? 0),
            'date' => sanitize_text_field((string)($context['date'] ?? '')),
            'question_key' => sanitize_key((string)($_POST['question_key'] ?? '')),
            'question_type' => sanitize_key((string)($_POST['question_type'] ?? '')),
            'scope_label' => sanitize_text_field(wp_unslash((string)($_POST['scope_label'] ?? ''))),
            'capture_id' => sanitize_text_field(wp_unslash((string)($_POST['capture_id'] ?? ''))),
            'items' => is_array($items) ? array_slice($items, 0, 80) : [],
        ];
    }

    public static function scopeError(array $request, int $userId): string {
        if ($userId <= 0) return 'Sem sessão ativa.';
        if (!class_exists('\\RoutesPro\\Support\\Permissions')) return '';
        $routeId = absint($request['route_id'] ?? 0);
        if ($routeId > 0 && !\RoutesPro\Support\Permissions::can_access_route($routeId, $userId)) return 'Sem acesso à rota desta visita.';
        $stopId = absint($request['stop_id'] ?? 0);
        $locationId = absint($request['location_id'] ?? 0);
        if ($stopId > 0) {
            global $wpdb;
            $stop = $wpdb->get_row($wpdb->prepare(
                'SELECT id,route_id,location_id FROM ' . $wpdb->prefix . 'routespro_route_stops WHERE id=%d LIMIT 1', $stopId
            ), ARRAY_A);
            if (!is_array($stop) || !$routeId || !$locationId || (int)$stop['route_id'] !== $routeId || (int)$stop['location_id'] !== $locationId) return 'A paragem não pertence à rota ou loja desta visita.';
        }
        $scope = \RoutesPro\Support\Permissions::assert_scope_or_error(absint($request['client_id'] ?? 0), absint($request['project_id'] ?? 0), $userId);
        return is_wp_error($scope) ? (string)$scope->get_error_message() : '';
    }

    /** Re-resolve the official form before trusting a client-supplied SKU or question. */
    public static function assertQuestionScope(array $request, ?array $referenceItem = null): void {
        $formId = absint($request['form_id'] ?? 0);
        $key = sanitize_key((string)($request['question_key'] ?? ''));
        $type = sanitize_key((string)($request['question_type'] ?? ''));
        if ($formId <= 0 || $key === '' || !in_array($type, ['product_matrix', 'share_space_matrix'], true)) {
            throw new \RuntimeException('Contexto da pergunta em falta. Reabre o formulário.');
        }
        $form = \RoutesPro\Forms\Forms::get_form($formId);
        if (!is_array($form) || ($form['status'] ?? '') !== 'active') throw new \RuntimeException('Formulário não está ativo.');
        $schema = \RoutesPro\Forms\SubmissionApplicationService::buildSchema($form, $request);
        $question = null;
        foreach ((array)($schema['questions'] ?? []) as $q) {
            if (!is_array($q)) continue;
            if (sanitize_key((string)($q['key'] ?? '')) === $key) { $question = $q; break; }
        }
        if (!is_array($question) || sanitize_key((string)($question['type'] ?? '')) !== $type) {
            throw new \RuntimeException('A pergunta mudou. Reabre o formulário.');
        }
        $clientId = absint($request['client_id'] ?? 0);
        if ($clientId <= 0 || self::questionBlockReason($question, $request, $clientId) !== '') {
            throw new \RuntimeException('Leitura por câmara não autorizada para esta pergunta ou cliente.');
        }
        if ($type === 'share_space_matrix') {
            $config = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig($question['question_config'] ?? ($question['share_space_rows'] ?? []));
            $allowed = [];
            foreach ((array)($config['rows'] ?? []) as $row) $allowed[(string)$row['metric_key']] = $row;
            if (!$allowed) throw new \RuntimeException('O comparativo ainda não está configurado.');
            if ($referenceItem !== null) {
                $label = trim((string)($referenceItem['label'] ?? ''));
                $ok = false;
                foreach ($allowed as $row) if (in_array($label, [(string)$row['pr_label'], (string)$row['competitor_label']], true)) $ok = true;
                if (!$ok) throw new \RuntimeException('Referência fora da pergunta.');
            } else {
                foreach ((array)($request['items'] ?? []) as $item) {
                    $card = $allowed[sanitize_key((string)($item['key'] ?? ''))] ?? null;
                    if (!$card || (string)($item['pr_label'] ?? '') !== (string)$card['pr_label'] || (string)($item['competitor_label'] ?? '') !== (string)$card['competitor_label']) {
                        throw new \RuntimeException('Comparativo não corresponde ao briefing da visita.');
                    }
                }
            }
            return;
        }
        $rows = \RoutesPro\Forms\ProductCardex::question_rows($question, $request, []);
        if (!is_array($rows) || !$rows) throw new \RuntimeException('O Cardex não tem produtos válidos nesta loja.');
        $matches = static function (array $submitted, array $official): bool {
            $name = trim(sanitize_text_field((string)($submitted['name'] ?? '')));
            $reference = trim(sanitize_text_field((string)($submitted['ref'] ?? '')));
            $capacity = trim(sanitize_text_field((string)($submitted['capacity'] ?? '')));
            return $name !== '' && $name === trim(sanitize_text_field((string)($official['name'] ?? $official['product_name'] ?? '')))
                && $reference === trim(sanitize_text_field((string)($official['ref'] ?? $official['reference'] ?? '')))
                && $capacity === trim(sanitize_text_field((string)($official['capacity_cl'] ?? $official['capacity'] ?? '')));
        };
        if ($referenceItem !== null) {
            $ok = false;
            foreach ($rows as $row) if (is_array($row) && $matches($referenceItem, $row)) { $ok = true; break; }
            if (!$ok) throw new \RuntimeException('Fotografia de referência fora do Cardex desta visita.');
            return;
        }
        $seen = [];
        foreach ((array)($request['items'] ?? []) as $submitted) {
            if (!is_array($submitted)) throw new \RuntimeException('Produto inválido.');
            $key = (string)($submitted['key'] ?? '');
            if (!preg_match('/^r(\d+)$/', $key, $m) || isset($seen[$key]) || !isset($rows[(int)$m[1]]) || !is_array($rows[(int)$m[1]]) || !$matches($submitted, $rows[(int)$m[1]])) {
                throw new \RuntimeException('A lista não corresponde ao Cardex oficial desta loja. Reabre o formulário.');
            }
            $seen[$key] = true;
        }
    }

    /** Frame of the live camera: validated and read from the temporary upload, never moved to the uploads. */
    private static function livePhoto($file): array {
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('A imagem da câmara não chegou ao servidor. Tenta de novo.');
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > self::MAX_PHOTO_BYTES) throw new \RuntimeException('A imagem da câmara é demasiado grande.');
        $path = (string)($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) throw new \RuntimeException('A imagem da câmara não chegou ao servidor. Tenta de novo.');
        $info = @getimagesize($path);
        if (!$info || !in_array((string)($info['mime'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) throw new \RuntimeException('A imagem da câmara tem de ser JPEG, PNG ou WebP.');
        return ['path' => $path, 'url' => '', 'mime' => (string)$info['mime'], 'live' => true];
    }

    /** Stores the shelf photo like the form photos (uploads, unguessable name). */
    private static function storePhoto($file, string $prefix = 'ff-vision-'): array {
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('A fotografia não chegou ao servidor. Tenta de novo.');
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > self::MAX_PHOTO_BYTES) throw new \RuntimeException('A fotografia é demasiado grande.');
        $info = @getimagesize((string)$file['tmp_name']);
        if (!$info || !in_array((string)($info['mime'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) throw new \RuntimeException('A fotografia tem de ser JPEG, PNG ou WebP.');
        if (!function_exists('wp_handle_upload')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(string)$info['mime']];
        $file['name'] = $prefix . wp_generate_password(24, false, false) . '.' . $extension;
        $stored = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'],
        ]);
        if (!is_array($stored) || !empty($stored['error']) || empty($stored['file'])) throw new \RuntimeException('Não foi possível guardar a fotografia: ' . (string)($stored['error'] ?? 'erro desconhecido'));
        return ['path' => (string)$stored['file'], 'url' => (string)($stored['url'] ?? ''), 'mime' => (string)$info['mime']];
    }

    /** Admin test reading with an uploaded photo (no visit context). */
    public static function adminTestRead(int $clientId, array $productIds, array $file): array {
        $photo = self::livePhoto($file);
        $photo['capture'] = 'gallery';
        $index = Catalog::clientIndex($clientId);
        $items = [];
        foreach (array_slice(array_values(array_unique(array_map('absint', $productIds))), 0, self::MAX_ITEMS) as $productId) {
            if (!isset($index[$productId])) continue;
            $items[] = ['key' => 'p' . $productId, 'name' => $index[$productId]['name'], 'ref' => $index[$productId]['reference'], 'capacity' => $index[$productId]['capacity_cl']];
        }
        return self::read(['client_id' => $clientId, 'question_key' => 'bo_test', 'question_type' => 'product_matrix', 'scope_label' => 'Teste no BO', 'items' => $items, 'date' => current_time('Y-m-d')], $photo, (int)get_current_user_id());
    }
}
