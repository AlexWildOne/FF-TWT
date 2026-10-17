<?php
namespace RoutesPro\Core;

if (!defined('ABSPATH')) exit;

class DataContract {
    public const VERSION = '1.0';

    public static function envelope(string $type, array $data, array $meta = []): array {
        return [
            'contract_version' => self::VERSION,
            'contract_type' => sanitize_key($type),
            'generated_at' => current_time('mysql'),
            'data' => self::normalise($data),
            'meta' => self::normalise($meta),
        ];
    }

    public static function submission(int $submissionId, array $payload = [], array $answers = []): array {
        $payload['submission_id'] = $submissionId;
        $normalisedAnswers = [];
        foreach ($answers as $key => $answer) {
            if (!is_array($answer)) {
                $normalisedAnswers[sanitize_key((string)$key)] = ['type' => 'unknown', 'label' => (string)$key, 'value' => self::normalise($answer)];
                continue;
            }
            $normalisedAnswers[sanitize_key((string)$key)] = [
                'type' => sanitize_key((string)($answer['type'] ?? 'unknown')),
                'label' => sanitize_text_field((string)($answer['label'] ?? $key)),
                'value' => self::normalise($answer['value'] ?? null),
            ];
        }
        $payload['answers'] = $normalisedAnswers;
        return self::envelope('submission', $payload);
    }

    public static function state(string $entityType, array $state): array {
        return self::envelope($entityType . '_state', $state);
    }

    public static function normalise($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::normalise($item);
            }
            return $out;
        }
        if (is_object($value)) return self::normalise(get_object_vars($value));
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
        return is_scalar($value) ? (string)$value : null;
    }
}
