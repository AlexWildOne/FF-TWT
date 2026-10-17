<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$react = $read('frontend/src/react-front/locale-switcher.ts');
$runtime = $read('assets/fieldflow-i18n.js');
$css = $read('assets/fieldflow-i18n.css');
$gate = $read('src/Support/ReleaseGate.php');
$checks = [
    'initial_visible' => strpos($react, 'useState<boolean>(false)') !== false
        && strpos($react, "className: switcherClasses.join(' ')") !== false,
    'scroll_hidden' => strpos($react, 'setScrolling(true)') !== false
        && strpos($react, 'setDocked(true)') !== false
        && strpos($css, '.ff-language-switcher.is-scrolling:not(.is-open)') !== false,
    'resting_tab' => strpos($css, '.ff-language-switcher.is-docked:not(.is-open):not(.is-scrolling)') !== false
        && strpos($css, 'linear-gradient(180deg,var(--ff-lang-primary),var(--ff-lang-accent))') !== false,
    'tap_reveal' => strpos($react, 'if (docked || scrolling)') !== false
        && strpos($react, 'setOpen(true)') !== false
        && strpos($runtime, 'wireLegacyAdaptiveDock') !== false,
    'safe_area' => strpos($css, 'safe-area-inset-right') !== false
        && strpos($css, 'safe-area-inset-bottom') !== false
        && strpos($css, 'prefers-reduced-motion:reduce') !== false,
    'historical_continuity' => strpos($gate, 'APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT') !== false
        && strpos($gate, 'PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT') !== false
        && strpos($gate, 'CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'ADAPTIVE LOCALE DOCK 9CD RUNTIME FAIL | ' . implode(',', $failed) . "\n");
    exit(1);
}
echo 'ADAPTIVE LOCALE DOCK 9CD RUNTIME OK | initial_visible=1 | scroll_hidden=1 | resting_tab=1 | tap_reveal=1 | safe_area=1 | historical_continuity=1' . "\n";
