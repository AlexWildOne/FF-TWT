<?php
namespace RoutesPro\Integrations;

if (!defined('ABSPATH')) exit;

class SecretVault {
    private const PREFIX_SODIUM = 's1:';
    private const PREFIX_OPENSSL_GCM = 'o2:';
    private const PREFIX_OPENSSL_LEGACY = 'o1:';
    private const PREFIX_PLAINTEXT_LEGACY = 'p1:';

    private static function key(): string {
        return hash('sha256', (string)wp_salt('auth'), true);
    }

    public static function canEncrypt(): bool {
        if (function_exists('sodium_crypto_secretbox')) return true;
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_get_cipher_methods')) return false;
        return in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true);
    }

    public static function isEncrypted(string $stored): bool {
        return strpos($stored, self::PREFIX_SODIUM) === 0
            || strpos($stored, self::PREFIX_OPENSSL_GCM) === 0
            || strpos($stored, self::PREFIX_OPENSSL_LEGACY) === 0
            || strpos($stored, self::PREFIX_PLAINTEXT_LEGACY) === 0;
    }

    public static function needsMigration(string $stored): bool {
        if ($stored === '') return false;
        return strpos($stored, self::PREFIX_SODIUM) !== 0
            && strpos($stored, self::PREFIX_OPENSSL_GCM) !== 0;
    }

    public static function encrypt(string $plain): string {
        if ($plain === '') return '';
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $key = substr(hash('sha256', (string)wp_salt('secure_auth'), true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            return self::PREFIX_SODIUM . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        if (self::canEncrypt()) {
            $ivLength = openssl_cipher_iv_length('aes-256-gcm');
            if ($ivLength > 0) {
                $iv = random_bytes($ivLength);
                $tag = '';
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'fieldflow-secret-v2', 16);
                if ($cipher !== false && strlen($tag) === 16) {
                    return self::PREFIX_OPENSSL_GCM . base64_encode(chr($ivLength) . $iv . $tag . $cipher);
                }
            }
        }
        return '';
    }

    public static function decrypt(string $stored): string {
        if ($stored === '') return '';
        if (strpos($stored, self::PREFIX_SODIUM) === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($stored, 3), true);
            if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $key = substr(hash('sha256', (string)wp_salt('secure_auth'), true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return is_string($plain) ? $plain : '';
        }
        if (strpos($stored, self::PREFIX_OPENSSL_GCM) === 0 && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($stored, 3), true);
            if (!is_string($raw) || strlen($raw) < 30) return '';
            $ivLength = ord($raw[0]);
            if ($ivLength < 8 || $ivLength > 32 || strlen($raw) <= 1 + $ivLength + 16) return '';
            $iv = substr($raw, 1, $ivLength);
            $tag = substr($raw, 1 + $ivLength, 16);
            $cipher = substr($raw, 1 + $ivLength + 16);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'fieldflow-secret-v2');
            return is_string($plain) ? $plain : '';
        }
        if (strpos($stored, self::PREFIX_OPENSSL_LEGACY) === 0 && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($stored, 3), true);
            if (!is_string($raw) || strlen($raw) <= 16) return '';
            $plain = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
            return is_string($plain) ? $plain : '';
        }
        if (strpos($stored, self::PREFIX_PLAINTEXT_LEGACY) === 0) {
            $plain = base64_decode(substr($stored, 3), true);
            return is_string($plain) ? $plain : '';
        }
        return '';
    }
}
