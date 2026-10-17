<?php
namespace RoutesPro\Localization;

use RoutesPro\Services\AIFactory;
use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * Browser-like translation fallback for text not covered by the local packs.
 *
 * The front runtime always prefers its zero-cost local dictionary and, when the
 * browser exposes the native Translator API, the on-device browser translator.
 * This service is the server fallback for unknown UI strings. Results are
 * cached by target locale and exact source string to keep provider traffic low.
 */
final class TranslationService {
    public const CONTRACT = 'fieldflow-browserlike-auto-translation-v1';
    // 9CC protected entities outside Chinese: store names, postal addresses and product names.
    private const CACHE_TTL = 2592000; // 30 days
    private const MAX_ITEMS = 80;
    private const MAX_ITEM_CHARS = 1400;
    private const MAX_TOTAL_CHARS = 24000;

    public static function targetLanguage(string $locale): string {
        $locale = LanguageService::normaliseLocale($locale);
        return [
            'en-GB'=>'English (United Kingdom)',
            'es-ES'=>'Spanish (Spain)',
            'fr-FR'=>'French (France)',
            'zh-CN'=>'Simplified Chinese',
        ][$locale] ?? '';
    }

    public static function browserLanguage(string $locale): string {
        $locale = LanguageService::normaliseLocale($locale);
        return [
            'en-GB'=>'en',
            'es-ES'=>'es',
            'fr-FR'=>'fr',
            'zh-CN'=>'zh',
        ][$locale] ?? '';
    }

    public static function providerAvailable(): bool {
        $provider = Config::aiProviderForModule('translation');
        return $provider !== 'none' && Config::aiProviderReady($provider) && AIFactory::make('translation') !== null;
    }

    public static function translateBatch(array $texts, string $locale, int $clientId = 0): array {
        $locale = LanguageService::normaliseLocale($locale);
        $target = self::targetLanguage($locale);
        if ($locale === 'pt-PT' || $target === '') {
            return ['translations'=>array_values(array_map('strval', $texts)), 'provider'=>'identity', 'cached'=>0];
        }

        $clean = [];
        $total = 0;
        foreach (array_slice($texts, 0, self::MAX_ITEMS) as $text) {
            $value = self::cleanText((string)$text);
            if ($value === '') { $clean[] = ''; continue; }
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($length > self::MAX_ITEM_CHARS) {
                $value = function_exists('mb_substr') ? mb_substr($value, 0, self::MAX_ITEM_CHARS) : substr($value, 0, self::MAX_ITEM_CHARS);
            }
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($total + $length > self::MAX_TOTAL_CHARS) break;
            $total += $length;
            $clean[] = $value;
        }

        $results = $clean;
        $missingIndexes = [];
        $missingTexts = [];
        $cached = 0;
        foreach ($clean as $index=>$text) {
            if ($text === '' || self::isMachineToken($text)) continue;
            $cachedValue = get_transient(self::cacheKey($locale, $text));
            if (is_string($cachedValue) && $cachedValue !== '') {
                $results[$index] = $cachedValue;
                $cached++;
                continue;
            }
            $missingIndexes[] = $index;
            $missingTexts[] = $text;
        }

        if (!$missingTexts) return ['translations'=>array_values($results), 'provider'=>'cache', 'cached'=>$cached];
        if (!self::providerAvailable()) {
            return ['translations'=>array_values($results), 'provider'=>'none', 'cached'=>$cached];
        }

        $provider = AIFactory::make('translation');
        if (!$provider) return ['translations'=>array_values($results), 'provider'=>'none', 'cached'=>$cached];

        $translateProperNames = $locale === 'zh-CN';
        $policy = $translateProperNames
            ? 'Translate every natural-language phrase, including store names and other proper names when they have a meaningful Chinese rendering. Keep URLs, email addresses, EAN/SKU codes, IDs and pure numbers unchanged.'
            : 'Translate all interface and natural-language content. Store names, postal addresses and product names are normally filtered out before this request; if one appears to be a proper name, preserve it exactly. Keep URLs, email addresses, EAN/SKU codes, IDs and pure numbers unchanged.';

        $payload = wp_json_encode(array_values($missingTexts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $systemPrompt = 'You are a deterministic software localisation engine. Return only a valid JSON array of strings, with exactly the same number and order of items as the input. Do not add explanations, markdown or keys.';
        $prompt = "Translate the following JSON array from Portuguese (Portugal) into {$target}. {$policy}\nINPUT:\n{$payload}";
        try {
            $raw = (string)$provider->complete($prompt, [
                'task'=>'fieldflow_translation',
                'temperature'=>0,
                'max_tokens'=>max(400, min(8000, count($missingTexts) * 90)),
                'timeout'=>45,
                'system_prompt'=>$systemPrompt,
            ]);
            $translated = self::decodeArray($raw);
            if (count($translated) !== count($missingTexts)) {
                return ['translations'=>array_values($results), 'provider'=>'invalid_response', 'cached'=>$cached];
            }
            foreach ($missingIndexes as $position=>$originalIndex) {
                $value = self::cleanText((string)($translated[$position] ?? ''));
                if ($value === '') $value = $missingTexts[$position];
                $results[$originalIndex] = $value;
                if ($value !== $missingTexts[$position]) set_transient(self::cacheKey($locale, $missingTexts[$position]), $value, self::CACHE_TTL);
            }
            return [
                'translations'=>array_values($results),
                'provider'=>sanitize_key(Config::aiProviderForModule('translation')) ?: 'ai',
                'cached'=>$cached,
                'client_id'=>$clientId,
            ];
        } catch (\Throwable $e) {
            return ['translations'=>array_values($results), 'provider'=>'error', 'cached'=>$cached, 'error'=>self::safeError($e->getMessage())];
        }
    }

    private static function cacheKey(string $locale, string $text): string {
        return 'ff_i18n_' . str_replace('-', '_', strtolower($locale)) . '_' . substr(hash('sha256', $text), 0, 40);
    }

    private static function cleanText(string $value): string {
        $value = wp_check_invalid_utf8($value, true);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        return trim($value);
    }

    private static function isMachineToken(string $value): bool {
        if (preg_match('/^(?:https?:\/\/|www\.|mailto:)/i', $value)) return true;
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/u', $value)) return true;
        if (preg_match('/^[\d\s.,:%+\-\/()#]+$/u', $value)) return true;
        if (preg_match('/^(?:EAN|SKU|ID|REF)[:#\s-]*[A-Z0-9._-]+$/iu', $value)) return true;
        return false;
    }

    private static function decodeArray(string $raw): array {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $start = strpos($raw, '['); $end = strrpos($raw, ']');
            if ($start !== false && $end !== false && $end > $start) $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        }
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $value) $out[] = is_scalar($value) ? (string)$value : '';
        return $out;
    }

    private static function safeError(string $message): string {
        $message = wp_strip_all_tags($message);
        if (function_exists('mb_substr')) $message = mb_substr($message, 0, 220);
        else $message = substr($message, 0, 220);
        return $message;
    }
}
