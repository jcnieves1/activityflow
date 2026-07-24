<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free translation layer. English lives in lang/en.php
 * and is treated as the authoritative key list / fallback; lang/es.php only
 * needs to override the keys it translates. Add a language by dropping in
 * lang/{code}.php and adding it to available_locales().
 *
 * Locale resolution order: the logged-in user's saved profile preference,
 * then a guest's in-session choice (set before they ever register/log in),
 * then the app default ('en'). Theme resolution mirrors this.
 */

const AF_DEFAULT_LOCALE = 'en';
const AF_DEFAULT_THEME = 'golden';

function available_locales(): array
{
    return [
        'en' => 'English',
        'es' => 'Español',
    ];
}

function available_themes(): array
{
    return [
        'golden' => 'Golden & White',
        'green'  => 'Light Green',
        'blue'   => 'Dark Blue',
    ];
}

function current_locale(): string
{
    $user = current_user();
    $locale = $user['locale'] ?? ($_SESSION['guest_locale'] ?? AF_DEFAULT_LOCALE);
    return array_key_exists($locale, available_locales()) ? $locale : AF_DEFAULT_LOCALE;
}

function current_theme(): string
{
    $user = current_user();
    $theme = $user['theme'] ?? ($_SESSION['guest_theme'] ?? AF_DEFAULT_THEME);
    return array_key_exists($theme, available_themes()) ? $theme : AF_DEFAULT_THEME;
}

/**
 * Maps our color scheme to Bootstrap 5.3's own light/dark color mode
 * (`<html data-bs-theme="...">`), so components Bootstrap itself controls the
 * palette for — modals, dropdowns, tables, form controls, buttons, etc. — flip
 * to their dark-mode colors automatically instead of staying light (white
 * background, black text) underneath our dark sidebar/topbar. Only the "blue"
 * scheme is dark; golden and green stay on Bootstrap's normal light mode.
 */
function bs_color_mode(): string
{
    return current_theme() === 'blue' ? 'dark' : 'light';
}

/** Memoized per-request load of the active language's string table, merged over the English base. */
function _af_translations(): array
{
    static $cache = [];
    $locale = current_locale();
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $base = require __DIR__ . '/../lang/en.php';
    if ($locale === AF_DEFAULT_LOCALE) {
        $cache[$locale] = $base;
        return $base;
    }

    $overrideFile = __DIR__ . "/../lang/$locale.php";
    $override = file_exists($overrideFile) ? require $overrideFile : [];
    $cache[$locale] = array_merge($base, $override);
    return $cache[$locale];
}

/**
 * Translate a string by key. Falls back to the key itself if it's missing
 * from both the active language and the English base, so a missing
 * translation never crashes the page or hides content.
 *
 * @param array<string,string|int> $vars sprintf-style {placeholder} substitutions
 */
function t(string $key, array $vars = []): string
{
    $strings = _af_translations();
    $text = $strings[$key] ?? $key;
    if ($vars) {
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
    }
    return $text;
}
