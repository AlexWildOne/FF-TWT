<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

/**
 * 9CQ fieldflow-theme-isolation-v1.
 *
 * WordPress themes style every button and link of the page. Hello Elementor (the theme of the
 * FieldFlow sites) paints buttons and their borders magenta and fills them on hover and
 * focus; on a phone a tapped button keeps the focus, so it stays magenta. FieldFlow controls must
 * use the colours of the campaign template instead.
 *
 * 1. FieldFlow stylesheets are printed after the theme and Elementor stylesheets, so rules of the
 *    same specificity follow FieldFlow.
 * 2. A small late stylesheet gives the FieldFlow controls whose rules were weaker than the theme
 *    state rules (`[type=button]:hover`, `button:focus`) their own colours in every state, using the
 *    template variables (--rp-form-*, --ff-*, --rp-primary-solid).
 */
final class ThemeIsolation {
    public const CONTRACT = 'fieldflow-theme-isolation-v1';
    public const HANDLE = 'fieldflow-theme-isolation';

    public static function registerHooks(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 9999);
        // Pages built with Elementor may enqueue the FieldFlow styles while rendering the shortcode (printed in the footer).
        add_action('wp_footer', [self::class, 'enqueue'], 5);
    }

    public static function isFieldFlowHandle(string $handle): bool {
        return (bool)preg_match('/^(routespro|fieldflow|ff-|rp-)/i', $handle);
    }

    public static function enqueue(): void {
        if (!function_exists('wp_styles')) return;
        $styles = wp_styles();
        $own = []; $others = [];
        foreach ((array)$styles->queue as $handle) {
            $handle = (string)$handle;
            if ($handle === self::HANDLE || empty($styles->registered[$handle])) continue;
            if (self::isFieldFlowHandle($handle)) $own[] = $handle; else $others[] = $handle;
        }
        if (!$own) return;
        if (!empty($styles->done) && in_array(self::HANDLE, (array)$styles->done, true)) return;
        foreach ($own as $handle) {
            $item = $styles->registered[$handle];
            foreach ($others as $dependency) {
                if (in_array($dependency, (array)$item->deps, true)) continue;
                if (self::dependsOn($styles, $dependency, $handle, [])) continue;
                $item->deps[] = $dependency;
            }
        }
        if (empty($styles->registered[self::HANDLE])) {
            wp_register_style(self::HANDLE, false, array_values(array_unique(array_merge($others, $own))), defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : null);
            wp_add_inline_style(self::HANDLE, self::css());
        } else {
            $styles->registered[self::HANDLE]->deps = array_values(array_unique(array_merge((array)$styles->registered[self::HANDLE]->deps, $own)));
        }
        if (!in_array(self::HANDLE, (array)$styles->queue, true)) wp_enqueue_style(self::HANDLE);
    }

    /** Whether $handle depends (directly or not) on $target: such a dependency would create a loop. */
    private static function dependsOn($styles, string $handle, string $target, array $seen): bool {
        if ($handle === $target) return true;
        if (isset($seen[$handle]) || empty($styles->registered[$handle])) return false;
        $seen[$handle] = true;
        foreach ((array)$styles->registered[$handle]->deps as $dependency) {
            if (self::dependsOn($styles, (string)$dependency, $target, $seen)) return true;
        }
        return false;
    }

    public static function css(): string {
        return <<<'CSS'
/* 2.2.614 9CQ fieldflow-theme-isolation-v1: os botões e ligações do FieldFlow usam as cores do template da campanha, nunca as do tema WordPress. */
.rp-form-ui button.routespro-form-btn{background:var(--rp-form-primary,#2563eb);color:var(--rp-form-button-text,#fff);border:0;text-decoration:none}
.rp-form-ui button.routespro-form-btn:is(:hover,:focus,:active){background:var(--rp-form-primary-hover,var(--rp-form-primary,#2563eb));color:var(--rp-form-button-text,#fff);border-color:transparent;text-decoration:none}
.rp-form-ui button.routespro-form-btn.secondary{background:color-mix(in srgb,var(--rp-form-primary,#2563eb) 9%,var(--rp-form-surface,#fff));color:var(--rp-form-button-secondary-text,#0f172a);border:1px solid #dbe3ee}
.rp-form-ui button.routespro-form-btn.secondary:is(:hover,:focus,:active){background:color-mix(in srgb,var(--rp-form-primary,#2563eb) 16%,var(--rp-form-surface,#fff));color:var(--rp-form-button-secondary-text,#0f172a);border-color:color-mix(in srgb,var(--rp-form-primary,#2563eb) 34%,#dbe3ee)}
.rp-form-ui button.routespro-form-btn:focus-visible{outline:3px solid color-mix(in srgb,var(--rp-form-primary,#2563eb) 32%,transparent);outline-offset:3px}
.rp-form-ui button.routespro-product-remove{background:#fff;color:#991b1b;border:1px solid rgba(0,0,0,.16)}
.rp-form-ui button.routespro-product-remove:is(:hover,:focus,:active){background:#fee2e2;color:#991b1b;border-color:#fecaca}
.rp-form-ui button.routespro-upload-source-btn{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.rp-form-ui button.routespro-upload-source-btn.is-camera{background:#fff7ed}
.rp-form-ui button.routespro-upload-source-btn.is-gallery{background:#f8fafc}
.rp-form-ui button.routespro-upload-source-btn:is(:hover,:focus,:active){color:#0f172a;border-color:var(--rp-form-primary,#2563eb)}
.rp-form-ui button:is(.routespro-upload-add,.routespro-upload-remove){background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.rp-form-ui button.routespro-upload-remove{color:#991b1b}
.rp-form-ui button:is(.routespro-upload-add,.routespro-upload-remove):is(:hover,:focus,:active){background:#f8fafc;border-color:#94a3b8}
.rp-form-ui button.routespro-existing-media-remove{background:rgba(255,255,255,.97);color:#991b1b;border:0}
.rp-form-ui button.routespro-existing-media-remove:is(:hover,:focus,:active){background:#fee2e2;color:#991b1b}
.rp-form-ui .is-pending-removal button.routespro-existing-media-remove{background:#0f172a;color:#fff}
.rp-form-ui button.routespro-product-thumb,.rp-form-ui button.routespro-product-thumb:is(:hover,:focus,:active){background:#fff;color:#94a3b8;border:1px solid #e2e8f0}
.rp-form-ui button.routespro-fronts-camera-btn,.rp-form-ui button.routespro-fronts-camera-btn:is(:hover,:focus,:active){background:var(--rp-form-primary,#2563eb);color:var(--rp-form-button-text,#fff);border:0}
.routespro-camera-capture button.routespro-camera-capture-shot,.routespro-camera-capture button.routespro-camera-capture-shot:is(:hover,:focus,:active){background:var(--rp-form-primary,#2563eb);color:#fff;border:0}
.routespro-camera-capture button.routespro-camera-capture-cancel,.routespro-camera-capture button.routespro-camera-capture-cancel:is(:hover,:focus,:active){background:#e2e8f0;color:#0f172a;border:0}
.routespro-camera-capture button.routespro-camera-capture-close,.routespro-camera-capture button.routespro-camera-capture-close:is(:hover,:focus,:active){background:#fff;color:#0f172a;border:0}
.routespro-product-zoom button.routespro-product-zoom-close:is(:hover,:focus,:active){color:#0f172a}
/* Botões partilhados das abas da app (Minha Rota, Briefing, Despesas, Mensagens, Analytics…). */
button.rp-btn:is(:hover,:focus,:active),a.rp-btn:is(:hover,:focus,:active){background:var(--rp-primary-solid,var(--ff-primary,var(--rp-primary,#2563eb)));color:#fff;text-decoration:none;filter:brightness(.96)}
button.rp-btn:is(.alt,.secondary):is(:hover,:focus,:active),a.rp-btn:is(.alt,.secondary):is(:hover,:focus,:active){background:var(--rp-accent-solid,var(--ff-accent,var(--rp-accent,#06b6d4)));color:#fff}
button.rp-btn.ghost:is(:hover,:focus,:active),a.rp-btn.ghost:is(:hover,:focus,:active){background:#fff;color:#0f172a;border-color:#cbd5e1;filter:none}
button.rp-cta:is(:hover,:focus,:active){background:var(--rp-primary-solid,var(--ff-primary,#2563eb));color:#fff;text-decoration:none}
/* Botões e ligações sem classe dentro do FieldFlow: nunca a cor do tema. */
:is(.rp-premium-shell,.rp-form-ui,.rp-client-premium,.routespro-vision-sheet) button:not([class]),:is(.rp-premium-shell,.rp-form-ui,.rp-client-premium,.routespro-vision-sheet) button:not([class]):is(:hover,:focus,:active){background-color:transparent;color:inherit;border-color:var(--ff-border,var(--rp-form-border,#e2e8f0))}
:is(.rp-premium-shell,.rp-form-ui,.rp-client-premium) a:not([class]){color:var(--ff-link,var(--rp-form-primary,var(--rp-primary-solid,#2563eb)))}
:is(.rp-premium-shell,.rp-form-ui,.rp-client-premium) a:not([class]):is(:hover,:active){color:var(--ff-link-hover,var(--rp-form-primary-hover,var(--rp-primary-solid,#1d4ed8)))}
:is(.rp-premium-shell,.rp-form-ui,.rp-client-premium) :is(button,[type=button],[type=submit]):focus:not(:focus-visible){outline:none}
CSS;
    }
}
