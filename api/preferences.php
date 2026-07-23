<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * Saves the color scheme and/or language preference. Available to guests
 * (login/register pages) as well as logged-in users: a guest's choice is
 * kept in the session only (so it survives the register/login flow), while
 * a logged-in user's choice is written to their profile so it's restored on
 * every future login, from any device.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

csrf_require();
$data = request_input();
$user = current_user();

if (array_key_exists('theme', $data)) {
    $theme = (string)$data['theme'];
    if (!array_key_exists($theme, available_themes())) {
        json_error('Unknown color scheme.', 422);
    }
    if ($user) {
        db()->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $user['id']]);
        $_SESSION['user']['theme'] = $theme;
    } else {
        $_SESSION['guest_theme'] = $theme;
    }
}

if (array_key_exists('locale', $data)) {
    $locale = (string)$data['locale'];
    if (!array_key_exists($locale, available_locales())) {
        json_error('Unknown language.', 422);
    }
    if ($user) {
        db()->prepare('UPDATE users SET locale = ? WHERE id = ?')->execute([$locale, $user['id']]);
        $_SESSION['user']['locale'] = $locale;
    } else {
        $_SESSION['guest_locale'] = $locale;
    }
}

json_response(['ok' => true, 'theme' => current_theme(), 'locale' => current_locale()]);
