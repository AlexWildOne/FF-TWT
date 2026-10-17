<?php
namespace RoutesPro\Services;

use RoutesPro\Support\Config;
use RoutesPro\Repositories\AIUsageRepository;
use RoutesPro\AI\Governance;
use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

interface AIProviderInterface {
    public function complete($prompt, $params=[]);
}

trait TracksProviderUsage {
    protected array $lastUsage = [];

    public function getLastUsage(): array {
        return $this->lastUsage;
    }

    protected function captureUsage(array $body): void {
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        $gemini = is_array($body['usageMetadata'] ?? null) ? $body['usageMetadata'] : [];
        $this->lastUsage = [
            'input_tokens' => max(0, (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? $gemini['promptTokenCount'] ?? 0)),
            'output_tokens' => max(0, (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? $gemini['candidatesTokenCount'] ?? 0)),
        ];
    }
}

/**
 * 9CO fieldflow-fronts-vision-reading-v1: optional images for multimodal requests.
 * Images travel as base64 inside the request (never as URLs to private files). Without
 * $params['images'] every provider sends exactly the same payload as before.
 */
trait ProviderVisionPayload {
    protected function visionImages(array $params): array {
        $out = [];
        foreach ((array)($params['images'] ?? []) as $image) {
            if (!is_array($image)) continue;
            $mime = strtolower((string)($image['mime'] ?? 'image/jpeg'));
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) continue;
            $data = preg_replace('/\s+/', '', (string)($image['data'] ?? ''));
            if ($data === '' || !preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $data)) continue;
            $detail = (string)($image['detail'] ?? 'auto');
            $out[] = ['mime' => $mime, 'data' => $data, 'label' => trim((string)($image['label'] ?? '')), 'detail' => in_array($detail, ['low', 'high', 'auto'], true) ? $detail : 'auto'];
            if (count($out) >= 20) break;
        }
        return $out;
    }
    protected function chatVisionContent(string $prompt, array $images): array {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $image) {
            if ($image['label'] !== '') $content[] = ['type' => 'text', 'text' => $image['label']];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['data'], 'detail' => $image['detail']]];
        }
        return $content;
    }
    protected function responsesVisionContent(string $prompt, array $images): array {
        $content = [['type' => 'input_text', 'text' => $prompt]];
        foreach ($images as $image) {
            if ($image['label'] !== '') $content[] = ['type' => 'input_text', 'text' => $image['label']];
            $content[] = ['type' => 'input_image', 'image_url' => 'data:' . $image['mime'] . ';base64,' . $image['data'], 'detail' => $image['detail']];
        }
        return $content;
    }
    protected function geminiVisionParts(string $prompt, array $images): array {
        $parts = [['text' => $prompt]];
        foreach ($images as $image) {
            if ($image['label'] !== '') $parts[] = ['text' => $image['label']];
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]];
        }
        return $parts;
    }
}

class GoogleAIProvider implements AIProviderInterface {
    use TracksProviderUsage;
    use ProviderVisionPayload;
    // 9CJ fieldflow-ai-model-currency-v1: default económico atual e substituição de modelos
    // Gemini já descontinuados pela Google (1.0, 1.5 e 2.0), que hoje respondem 404.
    // 9CO fieldflow-gemini-model-availability-v1: a Google limita os modelos 2.5 a contas que já os
    // usavam (projetos novos recebem 404) e recomenda o 3.5 Flash-Lite para projetos novos.
    // 9CJ certification marker (historical default, now mapped to the line below): public const DEFAULT_MODEL = 'gemini-2.5-flash-lite';
    public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';
    private const RETIRED_MODELS = [
        'gemini-pro' => 'gemini-3.5-flash',
        'gemini-1.0-pro' => 'gemini-3.5-flash',
        'gemini-1.5-pro' => 'gemini-3.8-flash',
        'gemini-1.5-pro-latest' => 'gemini-3.8-flash',
        'gemini-1.5-pro-001' => 'gemini-3.8-flash',
        'gemini-1.5-pro-002' => 'gemini-3.8-flash',
        'gemini-1.5-flash' => 'gemini-3.5-flash-lite',
        'gemini-1.5-flash-latest' => 'gemini-3.5-flash-lite',
        'gemini-1.5-flash-001' => 'gemini-3.5-flash-lite',
        'gemini-1.5-flash-002' => 'gemini-3.5-flash-lite',
        'gemini-1.5-flash-8b' => 'gemini-3.5-flash-lite',
        'gemini-2.0-flash' => 'gemini-3.5-flash-lite',
        'gemini-2.0-flash-001' => 'gemini-3.5-flash-lite',
        'gemini-2.0-flash-lite' => 'gemini-3.5-flash-lite',
        'gemini-2.0-flash-lite-001' => 'gemini-3.5-flash-lite',
        // Antigo default do FieldFlow (9CJ): indisponível para projetos novos.
        'gemini-2.5-flash-lite' => 'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite-preview' => 'gemini-3.1-flash-lite',
        'gemini-3-pro-preview' => 'gemini-3.8-flash',
    ];
    // Modelos escolhidos à mão que a conta pode não ter: em 404 tenta uma vez o sucessor.
    private const UNAVAILABLE_FALLBACK = [
        'gemini-2.5-flash' => 'gemini-3.5-flash',
        'gemini-2.5-pro' => 'gemini-3.8-flash',
    ];
    public static function resolveModel(string $model): string {
        $model = trim($model);
        if ($model === '') return self::DEFAULT_MODEL;
        $normalised = strtolower(preg_replace('#^models/#i', '', $model));
        return self::RETIRED_MODELS[$normalised] ?? $model;
    }
    protected string $key;
    public function __construct(){ $this->key = trim((string)Config::get('google_ai_key')); }
    public function complete($prompt, $params=[]){
        if ($this->key === '') throw new \RuntimeException('Google Gemini sem API key.');
        $model = self::resolveModel((string)($params['model'] ?? Config::get('google_ai_model', self::DEFAULT_MODEL)));
        // 9CJ: a chave segue no header x-goog-api-key e nunca no URL (logs de proxy/servidor).
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
        $images = $this->visionImages($params);
        $payload = [
            'contents'=>[['parts'=>$images ? $this->geminiVisionParts((string)$prompt, $images) : [['text'=>(string)$prompt]]]],
            'generationConfig'=>[
                'temperature'=>isset($params['temperature']) ? (float)$params['temperature'] : 0.2,
                'maxOutputTokens'=>max(50, min(12000, (int)($params['max_tokens'] ?? 400))),
            ],
        ];
        if (!empty($params['json'])) $payload['generationConfig']['responseMimeType'] = 'application/json';
        $systemPrompt = trim((string)($params['system_prompt'] ?? ''));
        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = ['parts'=>[['text'=>$systemPrompt]]];
        }
        $res = OutboundHttp::post($url, [
            'headers'=>['Content-Type'=>'application/json','x-goog-api-key'=>$this->key],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout'=>max(10, min(180, (int)($params['timeout'] ?? 60))),
        ], [
            'purpose'=>'ai_google_gemini',
            'allowed_hosts'=>['generativelanguage.googleapis.com'],
            'allowed_ports'=>[443],
            'max_redirects'=>0,
            'max_timeout'=>180,
            'max_response_bytes'=>4194304,
        ]);
        if (is_wp_error($res)) throw new \RuntimeException('Google Gemini: ' . $res->get_error_message());
        $status = (int)wp_remote_retrieve_response_code($res);
        $raw = (string)wp_remote_retrieve_body($res);
        $body = json_decode($raw, true);
        if ($status === 404 && empty($params['disable_model_fallback']) && empty($params['_gemini_fallback']) && isset(self::UNAVAILABLE_FALLBACK[strtolower(preg_replace('#^models/#i', '', $model))])) {
            $params['_gemini_fallback'] = true;
            $params['model'] = self::UNAVAILABLE_FALLBACK[strtolower(preg_replace('#^models/#i', '', $model))];
            return $this->complete($prompt, $params);
        }
        if ($status < 200 || $status >= 300) {
            $message = is_array($body) ? (string)($body['error']['message'] ?? '') : '';
            throw new \RuntimeException('Google Gemini HTTP ' . $status . ': ' . ($message ?: wp_strip_all_tags(substr($raw, 0, 500))));
        }
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') throw new \RuntimeException('Google Gemini devolveu uma resposta vazia.');
        if (is_array($body)) $this->captureUsage($body);
        return $text;
    }
}

class AzureOpenAIProvider implements AIProviderInterface {
    use TracksProviderUsage;
    use ProviderVisionPayload;
    protected string $key;
    protected string $endpoint;
    protected string $deployment;
    protected string $apiVersion;

    public function __construct(){
        $this->key = trim((string)Config::get('azure_openai_key'));
        $this->endpoint = rtrim(trim((string)Config::get('azure_openai_endpoint','')), '/');
        $this->deployment = trim((string)Config::get('azure_openai_deployment',''));
        $this->apiVersion = trim((string)Config::get('azure_openai_api_version','2024-06-01')) ?: '2024-06-01';
    }

    public function complete($prompt, $params=[]){
        // 9CO: a module can use its own deployment (for example one that accepts images).
        $deploymentOverride = trim((string)($params['deployment'] ?? ''));
        if ($deploymentOverride !== '') $this->deployment = $deploymentOverride;
        if ($this->endpoint === '' || $this->deployment === '' || $this->key === '') {
            throw new \RuntimeException('Azure OpenAI incompleto. Confirma endpoint, deployment e chave.');
        }
        $images = $this->visionImages($params);

        $systemPrompt = trim((string)($params['system_prompt'] ?? ''));
        if ($systemPrompt === '') $systemPrompt = 'Responde em português de Portugal. Cumpre rigorosamente o formato pedido pelo utilizador.';
        $messages = [
            ['role'=>'system','content'=>$systemPrompt],
            ['role'=>'user','content'=>$images ? $this->chatVisionContent((string)$prompt, $images) : (string)$prompt],
        ];
        $temperature = isset($params['temperature']) ? (float)$params['temperature'] : 0.2;
        $maxTokens = max(50, min(12000, (int)($params['max_tokens'] ?? 400)));
        $timeout = max(10, min(180, (int)($params['timeout'] ?? 60)));
        $reasoningModel = (bool)preg_match('/^(o1|o3|o4|gpt-5)/i', $this->deployment);

        $chatPayload = ['messages' => $messages];
        if ($reasoningModel) {
            $chatPayload['max_completion_tokens'] = $maxTokens;
        } else {
            $chatPayload['temperature'] = $temperature;
            $chatPayload['max_tokens'] = $maxTokens;
        }

        $endpoint = $this->normaliseEndpoint($this->endpoint);
        $isProject = $this->isProjectEndpoint($endpoint);
        $attempts = [];

        if ($isProject) {
            $projectPayload = $chatPayload;
            $projectPayload['model'] = $this->deployment;
            $attempts[] = [
                'label' => 'Microsoft Foundry Project, Chat Completions',
                'url' => $endpoint . '/openai/v1/chat/completions',
                'payload' => $projectPayload,
                'response_type' => 'chat',
            ];
            $responsePayload = [
                'model' => $this->deployment,
                'input' => [
                    ['role'=>'system','content'=>[['type'=>'input_text','text'=>$messages[0]['content']]]],
                    ['role'=>'user','content'=>$images ? $this->responsesVisionContent((string)$prompt, $images) : [['type'=>'input_text','text'=>$messages[1]['content']]]],
                ],
                'max_output_tokens' => $maxTokens,
            ];
            if (!$reasoningModel) $responsePayload['temperature'] = $temperature;
            $attempts[] = [
                'label' => 'Microsoft Foundry Project, Responses API',
                'url' => $endpoint . '/openai/v1/responses',
                'payload' => $responsePayload,
                'response_type' => 'responses',
            ];
        } else {
            $base = $this->resourceBase($endpoint);
            $v1Payload = $chatPayload;
            $v1Payload['model'] = $this->deployment;
            $attempts[] = [
                'label' => 'Azure OpenAI v1',
                'url' => $base . '/openai/v1/chat/completions',
                'payload' => $v1Payload,
                'response_type' => 'chat',
            ];
            $versions = array_values(array_unique(array_filter([$this->apiVersion,'2024-06-01','2024-10-01-preview'])));
            foreach ($versions as $version) {
                $attempts[] = [
                    'label' => 'Azure OpenAI ' . $version,
                    'url' => $base . '/openai/deployments/' . rawurlencode($this->deployment) . '/chat/completions?api-version=' . rawurlencode($version),
                    'payload' => $chatPayload,
                    'response_type' => 'chat',
                ];
            }
        }

        $maxAttempts = max(1, min(count($attempts), (int)($params['max_attempts'] ?? count($attempts))));
        $attempts = array_slice($attempts, 0, $maxAttempts);
        $errors = [];
        foreach ($attempts as $attempt) {
            $res = OutboundHttp::post($attempt['url'], [
                'headers'=>['Content-Type'=>'application/json','api-key'=>$this->key],
                'body'=>wp_json_encode($attempt['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout'=>$timeout,
            ], OutboundHttp::configuredPolicy($endpoint, 'ai_azure_openai', [
                'max_timeout'=>180,
                'max_response_bytes'=>4194304,
            ]));
            if (is_wp_error($res)) {
                $errors[] = $attempt['label'] . ': ' . $this->formatTransportError($res, $endpoint, $isProject);
                continue;
            }
            $status = (int)wp_remote_retrieve_response_code($res);
            $rawBody = (string)wp_remote_retrieve_body($res);
            $body = json_decode($rawBody, true);
            $text = $this->extractText(is_array($body) ? $body : [], (string)$attempt['response_type']);
            if ($status >= 200 && $status < 300 && is_string($text) && trim($text) !== '') {
                if (is_array($body)) $this->captureUsage($body);
                return $text;
            }

            $message = '';
            if (is_array($body)) {
                $message = (string)($body['error']['message'] ?? $body['message'] ?? '');
                $code = (string)($body['error']['code'] ?? '');
                if ($code !== '') $message = $code . ($message !== '' ? ': ' . $message : '');
            }
            if ($message === '') $message = trim(wp_strip_all_tags(substr($rawBody, 0, 700)));
            if ($message === '') $message = 'resposta vazia';
            if ($isProject && in_array($status, [401,403], true)) $message .= ' Confirma que a chave pertence ao recurso Foundry e que a autenticação por chave está ativa.';
            if ($status === 404 && $isProject) $message .= ' Confirma o nome exato do deployment em Models + endpoints.';
            $errors[] = $attempt['label'] . ' HTTP ' . $status . ': ' . $message;
            if (in_array($status, [401,403], true)) break;
        }
        $errors = array_slice(array_values(array_unique(array_filter($errors))), 0, 4);
        throw new \RuntimeException('Azure OpenAI sem resposta. ' . implode(' | ', $errors));
    }

    private function normaliseEndpoint(string $endpoint): string {
        $endpoint = rtrim(trim($endpoint), '/');
        if ($endpoint === '') return '';
        $endpoint = preg_replace('#/openai/v1/(chat/completions|responses)/?$#i', '', $endpoint);
        $endpoint = preg_replace('#/openai/v1/?$#i', '', $endpoint);
        $endpoint = preg_replace('#/openai/deployments/[^/]+/chat/completions/?$#i', '', $endpoint);
        return rtrim((string)$endpoint, '/');
    }

    private function isProjectEndpoint(string $endpoint): bool {
        $path = (string)(wp_parse_url($endpoint, PHP_URL_PATH) ?: '');
        return (bool)preg_match('#/api/projects/[^/]+/?$#i', rtrim($path, '/'));
    }

    private function resourceBase(string $endpoint): string {
        $parts = wp_parse_url(rtrim(trim($endpoint), '/'));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return rtrim($endpoint, '/');
        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) $base .= ':' . (int)$parts['port'];
        return rtrim($base, '/');
    }

    private function extractText(array $body, string $type): ?string {
        if ($type === 'chat') {
            $content = $body['choices'][0]['message']['content'] ?? null;
            if (is_string($content)) return $content;
            if (is_array($content)) {
                $parts = [];
                foreach ($content as $item) {
                    if (!is_array($item)) continue;
                    $value = $item['text'] ?? $item['content'] ?? null;
                    if (is_string($value) && trim($value) !== '') $parts[] = $value;
                }
                return $parts ? implode("\n", $parts) : null;
            }
            return null;
        }
        if (isset($body['output_text']) && is_string($body['output_text'])) return $body['output_text'];
        $parts = [];
        foreach (($body['output'] ?? []) as $output) {
            if (!is_array($output)) continue;
            foreach (($output['content'] ?? []) as $content) {
                if (!is_array($content)) continue;
                $text = $content['text'] ?? null;
                if (is_string($text) && trim($text) !== '') $parts[] = $text;
            }
        }
        return $parts ? implode("\n", $parts) : null;
    }

    private function formatTransportError($error, string $endpoint, bool $isProject): string {
        $message = method_exists($error, 'get_error_message') ? (string)$error->get_error_message() : 'erro de transporte';
        if (stripos($message, 'Could not resolve host') !== false || stripos($message, 'cURL error 6') !== false) {
            $host = (string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: '');
            $mode = $isProject ? 'O endpoint Microsoft Foundry foi reconhecido e o caminho do projeto foi preservado. ' : '';
            return $message . '. ' . $mode . 'O servidor WordPress não consegue resolver o domínio ' . $host . '. Isto é um problema de DNS ou de saída HTTPS do alojamento, anterior à autenticação Azure.';
        }
        return $message;
    }
}

class OpenAIProvider implements AIProviderInterface {
    use TracksProviderUsage;
    use ProviderVisionPayload;
    protected string $key;
    protected string $base;
    protected string $model;
    public function __construct(){
        $this->key = trim((string)Config::get('openai_api_key',''));
        $this->base = rtrim(trim((string)Config::get('openai_base_url','https://api.openai.com')) ?: 'https://api.openai.com', '/');
        $this->model = trim((string)Config::get('openai_model','gpt-4o-mini')) ?: 'gpt-4o-mini';
    }
    public function complete($prompt, $params=[]){
        if ($this->key === '') throw new \RuntimeException('OpenAI sem API key.');
        $model = trim((string)($params['model'] ?? $this->model)) ?: $this->model;
        $maxTokens = max(50, min(12000, (int)($params['max_tokens'] ?? 400)));
        $reasoningModel = (bool)preg_match('/^(o1|o3|o4|gpt-5)/i', $model);
        $systemPrompt = trim((string)($params['system_prompt'] ?? ''));
        if ($systemPrompt === '') $systemPrompt = 'Responde em português de Portugal, de forma prática e objetiva.';
        $payload = [
            'model'=>$model,
            'messages'=>[
                ['role'=>'system','content'=>$systemPrompt],
                ['role'=>'user','content'=>($openaiImages = $this->visionImages($params)) ? $this->chatVisionContent((string)$prompt, $openaiImages) : (string)$prompt],
            ],
        ];
        if ($reasoningModel) $payload['max_completion_tokens'] = $maxTokens;
        else {
            $payload['temperature'] = isset($params['temperature']) ? (float)$params['temperature'] : 0.2;
            $payload['max_tokens'] = $maxTokens;
        }
        $res = OutboundHttp::post($this->base . '/v1/chat/completions', [
            'headers'=>['Content-Type'=>'application/json','Authorization'=>'Bearer '.$this->key],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout'=>max(10, min(180, (int)($params['timeout'] ?? 60))),
        ], OutboundHttp::configuredPolicy($this->base, 'ai_openai', [
            'max_timeout'=>180,
            'max_response_bytes'=>4194304,
        ]));
        if (is_wp_error($res)) throw new \RuntimeException('OpenAI: ' . $res->get_error_message());
        $status = (int)wp_remote_retrieve_response_code($res);
        $raw = (string)wp_remote_retrieve_body($res);
        $body = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($body) ? (string)($body['error']['message'] ?? '') : '';
            throw new \RuntimeException('OpenAI HTTP ' . $status . ': ' . ($message ?: wp_strip_all_tags(substr($raw, 0, 600))));
        }
        $text = $body['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || trim($text) === '') throw new \RuntimeException('OpenAI devolveu uma resposta vazia.');
        if (is_array($body)) $this->captureUsage($body);
        return $text;
    }
}

class CopilotWebhookProvider implements AIProviderInterface {
    use TracksProviderUsage;
    protected string $url;
    protected string $authHeader;
    public function __construct(){
        $this->url = trim((string)Config::get('copilot_webhook_url',''));
        $this->authHeader = trim((string)Config::get('copilot_auth_header',''));
    }
    public function complete($prompt, $params=[]){
        if ($this->url === '') throw new \RuntimeException('Webhook IA sem URL.');
        $headers = ['Content-Type'=>'application/json'];
        if ($this->authHeader){
            $parts = explode(':', $this->authHeader, 2);
            if(count($parts)===2) $headers[trim($parts[0])] = trim($parts[1]);
        }
        $payload = ['prompt'=>(string)$prompt,'task'=>$params['task'] ?? 'route_notes','context'=>$params['context'] ?? null];
        if (!empty($params['system_prompt'])) $payload['system_prompt'] = (string)$params['system_prompt'];
        $res = OutboundHttp::post($this->url, [
            'headers'=>$headers,
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout'=>max(10, min(180, (int)($params['timeout'] ?? 60))),
        ], OutboundHttp::configuredPolicy($this->url, 'ai_custom_webhook', [
            'max_timeout'=>180,
            'max_response_bytes'=>4194304,
        ]));
        if (is_wp_error($res)) throw new \RuntimeException('Webhook IA: ' . $res->get_error_message());
        $status = (int)wp_remote_retrieve_response_code($res);
        $raw = (string)wp_remote_retrieve_body($res);
        $body = json_decode($raw, true);
        if ($status < 200 || $status >= 300) throw new \RuntimeException('Webhook IA HTTP ' . $status . ': ' . wp_strip_all_tags(substr($raw, 0, 600)));
        $text = is_array($body) ? ($body['text'] ?? ($body['message'] ?? null)) : null;
        if (!is_string($text) || trim($text) === '') throw new \RuntimeException('Webhook IA devolveu uma resposta vazia.');
        if (is_array($body)) $this->captureUsage($body);
        return $text;
    }
}

class AIFactory {
    public static function make(string $module = 'general', ?string $forceProvider = null): ?AIProviderInterface {
        $provider = $forceProvider ?: Config::aiProviderForModule($module);
        if ($provider === 'google') return new GoogleAIProvider();
        if ($provider === 'azure') return new AzureOpenAIProvider();
        if ($provider === 'openai') return new OpenAIProvider();
        if ($provider === 'copilot') return new CopilotWebhookProvider();
        return null;
    }

    public static function providerName(string $module = 'general'): string {
        return Config::aiProviderForModule($module);
    }

    public static function providerLabel(string $provider): string {
        return [
            'google'=>'Google Gemini',
            'azure'=>'Azure OpenAI / Microsoft Foundry',
            'openai'=>'OpenAI',
            'copilot'=>'Webhook IA',
            'none'=>'Sem IA',
        ][$provider] ?? ucfirst($provider);
    }

    public static function modelLabel(string $provider): string {
        if ($provider === 'google') return GoogleAIProvider::resolveModel((string)Config::get('google_ai_model', GoogleAIProvider::DEFAULT_MODEL));
        if ($provider === 'azure') return (string)Config::get('azure_openai_deployment','');
        if ($provider === 'openai') return (string)Config::get('openai_model','gpt-4o-mini');
        if ($provider === 'copilot') {
            $host = (string)(wp_parse_url((string)Config::get('copilot_webhook_url',''), PHP_URL_HOST) ?: 'webhook');
            return $host;
        }
        return '';
    }
}

class AIManager {
    private static array $lastVisionReceipt = [];

    /** Receipt for the one camera call made during this PHP request, never a bill. */
    public static function lastVisionReceipt(): array {
        return self::$lastVisionReceipt;
    }

    public static function available(string $module = 'general'): bool {
        $provider = Config::aiProviderForModule($module);
        return $provider !== 'none' && Config::aiProviderReady($provider) && AIFactory::make($module) !== null;
    }

    public static function providerName(string $module = 'general'): string {
        return Config::aiProviderForModule($module);
    }

    public static function providerLabel(string $module = 'general'): string {
        return AIFactory::providerLabel(self::providerName($module));
    }

    public static function complete(string $module, string $prompt, array $params = []): string {
        $module = sanitize_key($module) ?: 'general';
        if ($module === 'fronts_vision') self::$lastVisionReceipt = [];
        if (empty($params['bypass_enabled']) && empty(Config::get('ai_enabled', 1))) throw new \RuntimeException('A inteligência artificial está desligada no Centro de IA.');
        $params['fieldflow_attribution'] = self::resolveAttribution($module, $params);
        $governance = class_exists(Governance::class) ? Governance::preflight($module, $prompt, $params) : [
            'prompt'=>$prompt,'mode'=>'off','blocked'=>false,'reason'=>'','risk_flags'=>[],'redaction_types'=>[],
            'prompt_version'=>sanitize_key((string)($params['task'] ?? 'general')).'@1','prompt_hash'=>hash('sha256',$prompt),'governance_version'=>'0',
        ];
        $prompt = (string)$governance['prompt'];
        if (!empty($governance['blocked'])) {
            $usage = ['input_tokens'=>Governance::estimateTokens($prompt),'output_tokens'=>0,'usage_source'=>'estimated','estimated_cost_eur'=>0.0];
            self::logAttempt($module, self::providerName($module), 'blocked', 0, $prompt, '', 'ai_governance_block', (string)$governance['reason'], self::governanceMeta($governance, $params), $usage);
            throw new \RuntimeException((string)$governance['reason']);
        }
        $saasClientId = absint($params['fieldflow_attribution']['client_id'] ?? 0);
        if (empty($params['bypass_entitlement']) && class_exists('\RoutesPro\SaaS\EntitlementService')) {
            if ($saasClientId <= 0) $saasClientId = \RoutesPro\SaaS\EntitlementService::resolveClientId($params);
            if ($saasClientId > 0 && !\RoutesPro\SaaS\EntitlementService::clientHas($saasClientId, 'ai.use')) {
                throw new \RuntimeException('A inteligência artificial não está incluída no plano deste cliente.');
            }
            if ($saasClientId > 0 && class_exists('\RoutesPro\SaaS\UsageService')) {
                $quota = \RoutesPro\SaaS\UsageService::canConsume($saasClientId, 'ai_requests_monthly', 1);
                if (empty($quota['allowed'])) throw new \RuntimeException('O limite mensal de pedidos de IA foi atingido para este cliente.');
            }
        }
        $limit = max(0, (int)Config::get('ai_daily_limit', 0));
        if (empty($params['bypass_limit']) && $limit > 0 && class_exists(AIUsageRepository::class) && AIUsageRepository::todayCount() >= $limit) {
            $usage = class_exists(Governance::class) ? ['input_tokens'=>Governance::estimateTokens($prompt),'output_tokens'=>0,'usage_source'=>'estimated','estimated_cost_eur'=>0.0] : [];
            self::logAttempt($module, self::providerName($module), 'blocked', 0, $prompt, '', 'daily_limit', 'Limite diário de IA atingido.', self::governanceMeta($governance, $params), $usage);
            throw new \RuntimeException('Limite diário de chamadas de IA atingido. Revê o Centro de IA.');
        }

        $forced = !empty($params['force_provider']);
        $primary = sanitize_key((string)($params['force_provider'] ?? self::providerName($module)));
        if ($primary === '' || $primary === 'none') throw new \RuntimeException('Este módulo não tem um fornecedor de IA ativo.');
        $fallback = sanitize_key((string)Config::get('ai_fallback_provider','none'));
        $providers = [$primary];
        if (!$forced && $fallback !== 'none' && $fallback !== $primary && Config::aiProviderReady($fallback)) $providers[] = $fallback;

        $params['timeout'] = max(10, min(180, (int)($params['timeout'] ?? Config::get('ai_timeout',60))));
        $params['max_tokens'] = max(50, min(12000, (int)($params['max_tokens'] ?? Config::get('ai_default_max_tokens',1600))));
        $errors = [];
        foreach ($providers as $index => $providerName) {
            if (!Config::aiProviderReady($providerName)) {
                $errors[] = AIFactory::providerLabel($providerName) . ': credenciais incompletas';
                continue;
            }
            $provider = AIFactory::make($module, $providerName);
            if (!$provider) continue;
            $start = microtime(true);
            try {
                $output = $provider->complete($prompt, $params);
                $duration = (int)round((microtime(true) - $start) * 1000);
                if (!is_string($output) || trim($output) === '') throw new \RuntimeException('Resposta vazia.');
                $usage = class_exists(Governance::class) ? Governance::usageFor($provider, $prompt, $output) : [];
                $meta = array_merge(self::governanceMeta($governance, $params), ['fallback_used'=>$index > 0]);
                $receipt = [];
                $loggedModel = AIFactory::modelLabel($providerName);
                if ($module === 'fronts_vision' && class_exists('\RoutesPro\Vision\VisionCostMeter')) {
                    // 9CZ: real provider token counts, including image tokens when reported.
                    // The configured tariff is an estimate only, never Azure invoice data.
                    $loggedModel = $providerName === 'azure'
                        ? (string)($params['deployment'] ?? $loggedModel)
                        : (string)($params['model'] ?? $loggedModel);
                    $receipt = \RoutesPro\Vision\VisionCostMeter::quote($providerName, $loggedModel,
                        method_exists($provider, 'getLastUsage') ? (array)$provider->getLastUsage() : []);
                    $usage = self::visionMeteredUsage($receipt);
                    $meta['vision_cost_status'] = (string)$receipt['status'];
                    $meta['vision_finops'] = $receipt;
                }
                $logId = self::logAttempt($module, $providerName, 'success', $duration, $prompt, $output, '', '', $meta, $usage, $loggedModel);
                if ($receipt) {
                    $receipt['usage_log_id'] = $logId;
                    self::$lastVisionReceipt = $receipt;
                }
                if ($saasClientId > 0 && class_exists('\RoutesPro\SaaS\UsageService')) \RoutesPro\SaaS\UsageService::increment($saasClientId, 'ai_requests_monthly', 1);
                self::cleanupLogs();
                return $output;
            } catch (\Throwable $e) {
                $duration = (int)round((microtime(true) - $start) * 1000);
                $usage = class_exists(Governance::class) ? Governance::usageFor($provider, $prompt, '') : [];
                $meta = array_merge(self::governanceMeta($governance, $params), ['fallback_attempt'=>$index > 0]);
                $loggedModel = AIFactory::modelLabel($providerName);
                if ($module === 'fronts_vision' && class_exists('\RoutesPro\Vision\VisionCostMeter')) {
                    $loggedModel = $providerName === 'azure'
                        ? (string)($params['deployment'] ?? $loggedModel)
                        : (string)($params['model'] ?? $loggedModel);
                    $receipt = \RoutesPro\Vision\VisionCostMeter::quote($providerName, $loggedModel,
                        method_exists($provider, 'getLastUsage') ? (array)$provider->getLastUsage() : []);
                    $usage = self::visionMeteredUsage($receipt);
                    $meta['vision_cost_status'] = (string)$receipt['status'];
                    $meta['vision_finops'] = $receipt;
                }
                self::logAttempt($module, $providerName, 'error', $duration, $prompt, '', sanitize_key((new \ReflectionClass($e))->getShortName()), $e->getMessage(), $meta, $usage, $loggedModel);
                $errors[] = AIFactory::providerLabel($providerName) . ': ' . $e->getMessage();
            }
        }
        throw new \RuntimeException(implode(' | ', array_filter($errors)) ?: 'Nenhum fornecedor de IA respondeu.');
    }

    public static function test(string $provider, string $module = 'general'): array {
        $provider = sanitize_key($provider);
        $started = microtime(true);
        try {
            $text = self::complete($module, 'Responde apenas com: FIELDFLOW OK', [
                'force_provider'=>$provider,
                'bypass_enabled'=>true,
                'bypass_limit'=>true,
                'bypass_entitlement'=>true,
                'bypass_budget'=>true,
                'task'=>'connection_test',
                'feature_key'=>'connection_test',
                'request_kind'=>'admin_test',
                'temperature'=>0,
                'max_tokens'=>60,
            ]);
            return ['ok'=>true,'provider'=>$provider,'label'=>AIFactory::providerLabel($provider),'model'=>AIFactory::modelLabel($provider),'duration_ms'=>(int)round((microtime(true)-$started)*1000),'response'=>trim($text)];
        } catch (\Throwable $e) {
            return ['ok'=>false,'provider'=>$provider,'label'=>AIFactory::providerLabel($provider),'model'=>AIFactory::modelLabel($provider),'duration_ms'=>(int)round((microtime(true)-$started)*1000),'message'=>$e->getMessage()];
        }
    }

    public static function diagnostics(): array {
        $out = [];
        foreach (['google','azure','openai','copilot'] as $provider) {
            $out[$provider] = [
                'label'=>AIFactory::providerLabel($provider),
                'ready'=>Config::aiProviderReady($provider),
                'model'=>AIFactory::modelLabel($provider),
            ];
        }
        return $out;
    }

    private static function resolveAttribution(string $module, array $params): array {
        $context = is_array($params['context'] ?? null) ? (array)$params['context'] : [];
        $clientId = absint($params['client_id'] ?? $context['client_id'] ?? 0);
        $projectId = absint($params['project_id'] ?? $context['project_id'] ?? 0);
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) {
            if ($clientId <= 0 && $projectId > 0) {
                $clientId = \RoutesPro\SaaS\EntitlementService::resolveClientId(['project_id'=>$projectId]);
            }
            if ($clientId <= 0) {
                $clientId = \RoutesPro\SaaS\EntitlementService::resolveClientId($params);
            }
        }
        $feature = sanitize_key((string)($params['feature_key'] ?? $params['task'] ?? $module)) ?: sanitize_key($module) ?: 'general';
        $kind = sanitize_key((string)($params['request_kind'] ?? ''));
        if ($kind === '') {
            if ($feature === 'connection_test') $kind = 'admin_test';
            elseif (!empty($params['automated']) || !empty($params['schedule_id'])) $kind = 'automation';
            elseif (!empty($params['manual'])) $kind = 'manual';
            else $kind = 'runtime';
        }
        return [
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'feature_key'=>$feature,
            'request_kind'=>$kind,
        ];
    }

    private static function visionMeteredUsage(array $receipt): array {
        return [
            'input_tokens' => (int)($receipt['input_tokens'] ?? 0),
            'output_tokens' => (int)($receipt['output_tokens'] ?? 0),
            'usage_source' => (string)($receipt['token_source'] ?? 'unavailable'),
            'estimated_cost_eur' => (float)($receipt['estimated_cost_eur'] ?? 0),
        ];
    }

    private static function logAttempt(string $module, string $provider, string $status, int $duration, string $input, string $output, string $errorCode, string $errorMessage, array $meta, array $usage = [], string $modelOverride = ''): int {
        if (!class_exists(AIUsageRepository::class)) return 0;
        return AIUsageRepository::insert([
            'module_key'=>$module,
            'provider'=>$provider,
            'model_name'=>$modelOverride !== '' ? $modelOverride : AIFactory::modelLabel($provider),
            'status'=>$status,
            'duration_ms'=>$duration,
            'input_chars'=>strlen($input),
            'output_chars'=>strlen($output),
            'input_tokens'=>isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : null,
            'output_tokens'=>isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : null,
            'usage_source'=>(string)($usage['usage_source'] ?? ''),
            'estimated_cost_eur'=>(float)($usage['estimated_cost_eur'] ?? 0),
            'prompt_version'=>(string)($meta['prompt_version'] ?? ''),
            'risk_flags'=>(array)($meta['risk_flags'] ?? []),
            'client_id'=>absint($meta['attribution']['client_id'] ?? 0),
            'project_id'=>absint($meta['attribution']['project_id'] ?? 0),
            'feature_key'=>sanitize_key((string)($meta['attribution']['feature_key'] ?? '')),
            'request_kind'=>sanitize_key((string)($meta['attribution']['request_kind'] ?? 'runtime')) ?: 'runtime',
            'error_code'=>$errorCode,
            'error_message'=>$errorMessage,
            'meta'=>$meta,
        ]);
        if (class_exists(Governance::class)) Governance::reconcileBudgetAlerts((array)($meta['attribution'] ?? []), $module);
    }

    private static function governanceMeta(array $governance, array $params): array {
        return [
            'task'=>(string)($params['task'] ?? ''),
            'governance_mode'=>(string)($governance['mode'] ?? 'off'),
            'governance_version'=>(string)($governance['governance_version'] ?? ''),
            'prompt_version'=>(string)($governance['prompt_version'] ?? ''),
            'prompt_hash'=>(string)($governance['prompt_hash'] ?? ''),
            'risk_flags'=>(array)($governance['risk_flags'] ?? []),
            'redaction_types'=>(array)($governance['redaction_types'] ?? []),
            'attribution'=>(array)($governance['attribution'] ?? ($params['fieldflow_attribution'] ?? [])),
            'budgets'=>(array)($governance['budgets'] ?? []),
        ];
    }

    private static function cleanupLogs(): void {
        if (!class_exists(AIUsageRepository::class)) return;
        // A partir da 2.2.263 a retenção central é governada pelo Privacy Center.
        if (class_exists('\RoutesPro\Privacy\PrivacyGovernance')) return;
        if (mt_rand(1, 25) !== 1) return;
        AIUsageRepository::deleteOlderThan(max(7, (int)Config::get('ai_log_retention_days',90)));
    }
}
