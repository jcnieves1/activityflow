<?php
declare(strict_types=1);

/**
 * A deliberately lightweight, dependency-free CAPTCHA for the login and
 * registration forms: a simple arithmetic challenge ("7 + 3 = ?") stored
 * server-side in the session. It requires no PHP extensions (no GD, no
 * external service/API key), so it works out of the box on any install.
 *
 * This is a "basic" bot deterrent aimed at stopping unattended scripts that
 * just POST email/password pairs in a loop — it is not a substitute for a
 * full image/behavioral CAPTCHA (e.g. hCaptcha/reCAPTCHA) against a
 * determined, custom-built attacker. It is meant to sit alongside, not
 * replace, the existing login attempt lockout in includes/auth.php.
 */

const CAPTCHA_EXPIRY_SECONDS = 600; // 10 minutes

/** Pick a new challenge and store the expected answer in the session. */
function captcha_generate(): void
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $op = random_int(0, 1) === 0 ? '+' : '-';
    if ($op === '-' && $b > $a) {
        // Keep subtraction results non-negative so the answer is always a
        // single, unambiguous small integer.
        [$a, $b] = [$b, $a];
    }
    $_SESSION['captcha_question'] = "$a $op $b";
    $_SESSION['captcha_answer'] = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['captcha_generated_at'] = time();
}

/**
 * The question text to display, generating a fresh one if none is pending
 * or the current one has expired. Reused across re-renders of the same
 * page load (e.g. a GET request) so the answer doesn't change out from
 * under someone mid-thought.
 */
function captcha_question(): string
{
    $generatedAt = $_SESSION['captcha_generated_at'] ?? 0;
    if (empty($_SESSION['captcha_question']) || (time() - $generatedAt) > CAPTCHA_EXPIRY_SECONDS) {
        captcha_generate();
    }
    return $_SESSION['captcha_question'];
}

/** Bootstrap-styled form field markup, matching the other auth form fields. */
function captcha_field(): string
{
    $question = e(captcha_question());
    return '<div class="mb-3">'
        . '<label class="form-label" for="captcha_answer">Security check: what is ' . $question . '?</label>'
        . '<input type="text" inputmode="numeric" autocomplete="off" class="form-control" id="captcha_answer" name="captcha_answer" required>'
        . '<div class="form-text">This helps us block automated sign-ins and account creation.</div>'
        . '</div>';
}

/**
 * Verify a submitted answer against the pending challenge. Single-use: the
 * expected answer is cleared as soon as it's checked (pass or fail) so it
 * can never be replayed, which also means the next captcha_field() render
 * will hand out a fresh question.
 */
function captcha_verify(?string $input): bool
{
    $expected = $_SESSION['captcha_answer'] ?? null;
    $generatedAt = $_SESSION['captcha_generated_at'] ?? 0;
    unset($_SESSION['captcha_question'], $_SESSION['captcha_answer'], $_SESSION['captcha_generated_at']);

    if ($expected === null || $input === null || trim($input) === '' || !is_numeric(trim($input))) {
        return false;
    }
    if ((time() - $generatedAt) > CAPTCHA_EXPIRY_SECONDS) {
        return false;
    }
    return hash_equals((string)$expected, (string)(int)trim($input));
}
