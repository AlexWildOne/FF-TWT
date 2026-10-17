<?php
namespace RoutesPro\Billing;

use RoutesPro\Integrations\SecretVault;

if (!defined('ABSPATH')) exit;

class Settings {
    public const OPTION = 'routespro_billing_settings';
    public const PORTAL_PAGE_OPTION = 'routespro_billing_portal_page_id';
    public const SIGNUP_PAGE_OPTION = 'routespro_billing_signup_page_id';

    public static function defaults(): array {
        return [
            'enabled' => 0,
            'mode' => 'sandbox',
            'provider' => 'stripe',
            'sandbox_driver' => 'local',
            'sandbox_secret_key_enc' => '',
            'sandbox_webhook_secret_enc' => '',
            'live_secret_key_enc' => '',
            'live_webhook_secret_enc' => '',
            'success_url' => '',
            'cancel_url' => '',
            'grace_days' => 7,
            'allow_promotion_codes' => 0,
            'automatic_tax' => 0,
            'billing_address_required' => 1,
            'tax_id_collection' => 1,
            'notification_email' => (string)get_option('admin_email'),
            'onboarding_enabled' => 0,
            'um_form_id' => 0,
            'pending_role' => '',
            'um_company_meta_key' => 'company_name',
            'um_tax_meta_key' => 'tax_id',
            'um_phone_meta_key' => 'mobile_number',
            'transactional_emails_enabled' => 0,
            'checkout_recovery_enabled' => 0,
            'abandoned_checkout_hours' => 12,
            'max_recovery_emails' => 2,
            'renewal_emails_enabled' => 0,
            'renewal_notice_days' => 5,
            'billing_reply_to' => (string)get_option('admin_email'),
            'legal_consent_required' => 0,
            'terms_url' => '',
            'privacy_url' => '',
            'terms_version' => '1.0',
            'privacy_version' => '1.0',
            'email_templates' => [],
        ];
    }

    public static function get(): array {
        $saved = get_option(self::OPTION, []);
        $settings = array_replace(self::defaults(), is_array($saved) ? $saved : []);
        if (class_exists('\\RoutesPro\\Billing\\ProductionHardening')) {
            $settings['email_templates'] = array_replace_recursive(
                \RoutesPro\Billing\ProductionHardening::templateDefaults(),
                (array)($settings['email_templates'] ?? [])
            );
        }
        return $settings;
    }

    public static function save(array $input): array {
        $current = self::get();
        $mode = sanitize_key((string)($input['mode'] ?? $current['mode']));
        if (!in_array($mode, ['sandbox', 'live'], true)) $mode = 'sandbox';
        $next = $current;
        $next['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $next['mode'] = $mode;
        $next['provider'] = 'stripe';
        $driver = sanitize_key((string)($input['sandbox_driver'] ?? $current['sandbox_driver'] ?? 'local'));
        $next['sandbox_driver'] = in_array($driver, ['local','stripe'], true) ? $driver : 'local';
        $next['grace_days'] = max(0, min(60, absint($input['grace_days'] ?? $current['grace_days'])));
        $next['allow_promotion_codes'] = !empty($input['allow_promotion_codes']) ? 1 : 0;
        $next['automatic_tax'] = !empty($input['automatic_tax']) ? 1 : 0;
        $next['billing_address_required'] = !empty($input['billing_address_required']) ? 1 : 0;
        $next['tax_id_collection'] = !empty($input['tax_id_collection']) ? 1 : 0;
        $next['success_url'] = esc_url_raw((string)($input['success_url'] ?? $current['success_url']));
        $next['cancel_url'] = esc_url_raw((string)($input['cancel_url'] ?? $current['cancel_url']));
        $next['notification_email'] = sanitize_email((string)($input['notification_email'] ?? $current['notification_email']));
        if (array_key_exists('onboarding_enabled', $input)) $next['onboarding_enabled'] = !empty($input['onboarding_enabled']) ? 1 : 0;
        if (array_key_exists('um_form_id', $input)) $next['um_form_id'] = absint($input['um_form_id']);
        if (array_key_exists('pending_role', $input)) {
            $pendingRole = sanitize_key((string)$input['pending_role']);
            $availableRoles = class_exists('\\RoutesPro\\Billing\\RoleMappingService') ? \RoutesPro\Billing\RoleMappingService::availableRoles() : [];
            $next['pending_role'] = isset($availableRoles[$pendingRole]) ? $pendingRole : '';
        }
        foreach (['transactional_emails_enabled','checkout_recovery_enabled','renewal_emails_enabled','legal_consent_required'] as $field) {
            if (array_key_exists($field, $input)) $next[$field] = !empty($input[$field]) ? 1 : 0;
        }
        if (array_key_exists('abandoned_checkout_hours', $input)) $next['abandoned_checkout_hours'] = max(1, min(168, absint($input['abandoned_checkout_hours'])));
        if (array_key_exists('max_recovery_emails', $input)) $next['max_recovery_emails'] = max(1, min(5, absint($input['max_recovery_emails'])));
        if (array_key_exists('renewal_notice_days', $input)) $next['renewal_notice_days'] = max(1, min(30, absint($input['renewal_notice_days'])));
        if (array_key_exists('billing_reply_to', $input)) $next['billing_reply_to'] = sanitize_email((string)$input['billing_reply_to']);
        foreach (['terms_url','privacy_url'] as $field) if (array_key_exists($field, $input)) $next[$field] = esc_url_raw((string)$input[$field]);
        foreach (['terms_version','privacy_version'] as $field) if (array_key_exists($field, $input)) $next[$field] = substr(sanitize_text_field((string)$input[$field]), 0, 32) ?: '1.0';
        if (isset($input['email_templates']) && is_array($input['email_templates'])) {
            $defaults = class_exists('\\RoutesPro\\Billing\\ProductionHardening') ? \RoutesPro\Billing\ProductionHardening::templateDefaults() : [];
            $templates = [];
            foreach ($defaults as $key=>$definition) {
                $row = (array)($input['email_templates'][$key] ?? []);
                $templates[$key] = [
                    'subject' => sanitize_text_field((string)($row['subject'] ?? $definition['subject'])),
                    'body' => sanitize_textarea_field((string)($row['body'] ?? $definition['body'])),
                ];
            }
            $next['email_templates'] = $templates;
        }
        foreach (['um_company_meta_key'=>'company_name','um_tax_meta_key'=>'tax_id','um_phone_meta_key'=>'mobile_number'] as $field=>$fallback) {
            if (!array_key_exists($field, $input)) continue;
            $value = sanitize_key((string)$input[$field]);
            $next[$field] = $value !== '' ? $value : $fallback;
        }
        foreach (['sandbox_secret_key','sandbox_webhook_secret','live_secret_key','live_webhook_secret'] as $field) {
            $value = trim((string)($input[$field] ?? ''));
            if ($value !== '') $next[$field . '_enc'] = SecretVault::encrypt($value);
        }
        update_option(self::OPTION, $next, false);
        return $next;
    }

    public static function mode(): string { return (string)self::get()['mode']; }
    public static function isSandbox(): bool { return self::mode() === 'sandbox'; }
    public static function isEnabled(): bool { return !empty(self::get()['enabled']); }

    public static function secretKey(?string $mode = null): string {
        $settings = self::get();
        $mode = $mode ?: (string)$settings['mode'];
        return SecretVault::decrypt((string)($settings[$mode . '_secret_key_enc'] ?? ''));
    }

    public static function webhookSecret(?string $mode = null): string {
        $settings = self::get();
        $mode = $mode ?: (string)$settings['mode'];
        return SecretVault::decrypt((string)($settings[$mode . '_webhook_secret_enc'] ?? ''));
    }


    public static function signupPageId(): int { return absint(get_option(self::SIGNUP_PAGE_OPTION, 0)); }

    public static function setSignupPageId(int $pageId): void {
        update_option(self::SIGNUP_PAGE_OPTION, max(0, $pageId), false);
    }

    public static function signupUrl(): string {
        $pageId = self::signupPageId();
        if ($pageId > 0 && get_post_status($pageId) === 'publish') {
            $url = get_permalink($pageId);
            if (is_string($url) && $url !== '') return $url;
        }
        $discovered = self::discoverShortcodePage('fieldflow_subscription_signup');
        if ($discovered > 0) {
            self::setSignupPageId($discovered);
            $url = get_permalink($discovered);
            if (is_string($url) && $url !== '') return $url;
        }
        return home_url('/subscrever-fieldflow/');
    }



    public static function discoverShortcodePage(string $shortcode): int {
        global $wpdb;
        $shortcode = sanitize_key($shortcode);
        if ($shortcode === '') return 0;
        $like = '%' . $wpdb->esc_like($shortcode) . '%';
        $postId = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND post_content LIKE %s ORDER BY post_modified_gmt DESC,ID DESC LIMIT 1",
            $like
        ));
        if ($postId > 0) return $postId;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID WHERE p.post_type='page' AND p.post_status='publish' AND pm.meta_key='_elementor_data' AND pm.meta_value LIKE %s ORDER BY p.post_modified_gmt DESC,p.ID DESC LIMIT 1",
            $like
        ));
    }

    public static function portalPageId(): int { return absint(get_option(self::PORTAL_PAGE_OPTION, 0)); }

    public static function setPortalPageId(int $pageId): void {
        update_option(self::PORTAL_PAGE_OPTION, max(0, $pageId), false);
    }

    public static function pageContainsShortcode(int $pageId, string $shortcode): bool {
        $pageId = absint($pageId);
        $shortcode = sanitize_key($shortcode);
        if ($pageId <= 0 || $shortcode === '') return false;
        $post = get_post($pageId);
        if (!$post || (string)$post->post_type !== 'page' || (string)$post->post_status !== 'publish') return false;
        $content = (string)$post->post_content;
        if ((function_exists('has_shortcode') && has_shortcode($content, $shortcode)) || strpos($content, '[' . $shortcode) !== false) return true;
        $elementor = (string)get_post_meta($pageId, '_elementor_data', true);
        return $elementor !== '' && strpos($elementor, $shortcode) !== false;
    }

    public static function portalUrl(): string {
        $pageId = self::portalPageId();
        if (self::pageContainsShortcode($pageId, 'fieldflow_billing_portal')) {
            $url = get_permalink($pageId);
            if (is_string($url) && $url !== '') return $url;
        }
        $discovered = self::discoverShortcodePage('fieldflow_billing_portal');
        if ($discovered > 0) {
            self::setPortalPageId($discovered);
            $url = get_permalink($discovered);
            if (is_string($url) && $url !== '') return $url;
        }
        return home_url('/planos-e-faturacao/');
    }

    public static function publicSummary(): array {
        $settings = self::get();
        $mode = (string)$settings['mode'];
        return [
            'enabled' => !empty($settings['enabled']),
            'mode' => $mode,
            'provider' => 'stripe',
            'sandbox_driver' => (string)($settings['sandbox_driver'] ?? 'local'),
            'secret_configured' => self::secretKey($mode) !== '',
            'webhook_configured' => self::webhookSecret($mode) !== '',
            'grace_days' => (int)$settings['grace_days'],
            'automatic_tax' => !empty($settings['automatic_tax']),
            'onboarding_enabled' => !empty($settings['onboarding_enabled']),
            'signup_page_url' => self::signupUrl(),
            'ultimate_member_form_id' => absint($settings['um_form_id'] ?? 0),
            'transactional_emails_enabled' => !empty($settings['transactional_emails_enabled']),
            'checkout_recovery_enabled' => !empty($settings['checkout_recovery_enabled']),
            'legal_consent_required' => !empty($settings['legal_consent_required']),
        ];
    }
}
