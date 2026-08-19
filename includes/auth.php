<?php
declare(strict_types=1);

/**
 * Authentication, registration, and defensive password-recovery flow.
 * Passwords and secret answers are always hashed with password_hash()/verify()
 * — never stored or compared as plain text.
 */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (is_json_request()) {
            json_error('Authentication required.', 401);
        }
        redirect('login.php');
    }
    return $user;
}

function load_user_session(int $userId): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, u.status, u.theme, u.locale, p.id AS person_id, p.avatar_path
         FROM users u LEFT JOIN people p ON p.user_id = u.id
         WHERE u.id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $roleStmt = $pdo->prepare(
        'SELECT r.name FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?'
    );
    $roleStmt->execute([$userId]);
    $roles = array_column($roleStmt->fetchAll(), 'name');

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'person_id' => $user['person_id'] !== null ? (int)$user['person_id'] : null,
        'avatar_path' => $user['avatar_path'] ?? null,
        'roles'     => $roles,
        'theme'     => $user['theme'] ?? 'golden',
        'locale'    => $user['locale'] ?? 'en',
    ];
}

// ---------------------------------------------------------------------
// Login rate limiting
// ---------------------------------------------------------------------

function login_attempt_count(string $email, int $minutes): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND success = 0 AND created_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, $minutes]);
    return (int)$stmt->fetchColumn();
}

function record_login_attempt(string $email, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$email, client_ip(), $success ? 1 : 0]);
}

function attempt_login(string $email, string $password): array
{
    $security = app_config()['security'];
    $email = mb_strtolower(trim($email));

    $recentFailures = login_attempt_count($email, $security['login_lockout_minutes']);
    if ($recentFailures >= $security['login_max_attempts']) {
        return ['ok' => false, 'error' => t('auth.too_many_attempts')];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $genericError = t('auth.invalid_credentials');

    if (!$user) {
        record_login_attempt($email, false);
        return ['ok' => false, 'error' => $genericError];
    }

    if ($user['status'] === 'locked' || $user['status'] === 'inactive') {
        record_login_attempt($email, false);
        return ['ok' => false, 'error' => t('auth.account_unavailable')];
    }

    if (!password_verify($password, $user['password_hash'])) {
        record_login_attempt($email, false);
        db()->prepare('UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?')->execute([$user['id']]);
        return ['ok' => false, 'error' => $genericError];
    }

    // Success: reset counters, regenerate session id (fixation protection).
    record_login_attempt($email, true);
    db()->prepare('UPDATE users SET failed_login_count = 0, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    session_regenerate_id(true);
    load_user_session((int)$user['id']);

    return ['ok' => true];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// Registration
// ---------------------------------------------------------------------

function register_user(string $fullName, string $email, string $password, string $question, string $answer): array
{
    $email = mb_strtolower(trim($email));
    $security = app_config()['security'];

    if (mb_strlen(trim($fullName)) < 2) {
        return ['ok' => false, 'error' => t('auth.register_name_required')];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => t('auth.register_email_invalid')];
    }
    if (mb_strlen($password) < 8) {
        return ['ok' => false, 'error' => t('auth.register_password_length')];
    }
    if (mb_strlen(trim($question)) < 5) {
        return ['ok' => false, 'error' => t('auth.register_question_required')];
    }
    if (mb_strlen(normalize_secret_answer($answer)) < $security['min_secret_answer_length']) {
        return ['ok' => false, 'error' => t('auth.register_answer_too_short')];
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => t('auth.register_email_exists')];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $answerHash = password_hash(normalize_secret_answer($answer), PASSWORD_DEFAULT);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, secret_question, secret_answer_hash, status)
             VALUES (?, ?, ?, ?, ?, "active")'
        )->execute([trim($fullName), $email, $passwordHash, trim($question), $answerHash]);
        $userId = (int)$pdo->lastInsertId();

        $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
        $roleStmt->execute([ROLE_EMPLOYEE]);
        $role = $roleStmt->fetch();
        if ($role) {
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $role['id']]);
        }

        // A person record represents this user in the requester/employee directory.
        // If someone (an admin, a project manager, or a task form) already added a
        // person with this email before they ever had a login — a "placeholder"
        // directory entry — claim that existing record instead of creating a second,
        // duplicate person for the same human. Keeping the original person.id means
        // every activity/project/time-entry reference already pointing at them keeps
        // working with no migration needed; only the directory info is refreshed with
        // what they just entered, and it's now linked to their new account.
        $existingPerson = find_unclaimed_person_by_email($email);
        if ($existingPerson) {
            $personId = (int)$existingPerson['id'];
            $pdo->prepare(
                'UPDATE people SET full_name = ?, email = ?, is_active = 1, user_id = ? WHERE id = ?'
            )->execute([trim($fullName), $email, $userId, $personId]);

            audit_log(
                'person',
                $personId,
                'claimed_by_registration',
                ['full_name' => $existingPerson['full_name'], 'user_id' => null],
                ['full_name' => trim($fullName), 'user_id' => $userId]
            );
        } else {
            $pdo->prepare(
                'INSERT INTO people (full_name, email, is_active, user_id) VALUES (?, ?, 1, ?)'
            )->execute([trim($fullName), $email, $userId]);
            $personId = (int)$pdo->lastInsertId();

            audit_log('person', $personId, 'created_by_registration', null, [
                'full_name' => trim($fullName), 'email' => $email,
            ]);
        }

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId, 'person_id' => $personId, 'linked_existing' => (bool)$existingPerson];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[activityflow] registration failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => t('auth.register_failed')];
    }
}

// ---------------------------------------------------------------------
// Password recovery (secret question/answer) — defensive by design:
// rate limited, generic messaging, session-bound token with expiry,
// fully audit logged.
// ---------------------------------------------------------------------

function recovery_attempt_count(string $key, string $column, int $minutes): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM password_recovery_attempts
         WHERE $column = ? AND created_at >= (NOW() - INTERVAL ? MINUTE)"
    );
    $stmt->execute([$key, $minutes]);
    return (int)$stmt->fetchColumn();
}

function record_recovery_attempt(string $email, string $step, bool $success): void
{
    db()->prepare(
        'INSERT INTO password_recovery_attempts (email, ip_address, step, success) VALUES (?, ?, ?, ?)'
    )->execute([$email, client_ip(), $step, $success ? 1 : 0]);
}

function recovery_rate_limited(string $email): bool
{
    $security = app_config()['security'];
    $byEmail = recovery_attempt_count($email, 'email', $security['recovery_lockout_minutes']);
    $byIp = recovery_attempt_count(client_ip(), 'ip_address', $security['recovery_lockout_minutes']);
    return $byEmail >= $security['recovery_max_attempts'] || $byIp >= ($security['recovery_max_attempts'] * 3);
}

const RECOVERY_GENERIC_QUESTION = 'What is the answer to your recovery question?';

/** Step 1: look up the question to display. Never reveals whether the email exists. */
function recovery_start(string $email): array
{
    $email = mb_strtolower(trim($email));

    if (recovery_rate_limited($email)) {
        record_recovery_attempt($email, 'question_requested', false);
        return ['ok' => false, 'error' => t('auth.recovery_too_many_attempts')];
    }

    $stmt = db()->prepare('SELECT id, secret_question FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $question = $user['secret_question'] ?? t('auth.recovery_generic_question');

    $ttl = (int)app_config()['security']['recovery_token_ttl_minutes'];
    $token = bin2hex(random_bytes(24));
    $_SESSION['recovery'] = [
        'email'           => $email,
        'token'           => $token,
        'answer_verified' => false,
        'expires_at'      => time() + $ttl * 60,
    ];

    record_recovery_attempt($email, 'question_requested', true);

    return ['ok' => true, 'question' => $question, 'token' => $token];
}

function recovery_session_valid(string $email, string $token): bool
{
    $r = $_SESSION['recovery'] ?? null;
    if (!$r) {
        return false;
    }
    if ($r['email'] !== mb_strtolower(trim($email)) || !hash_equals($r['token'], $token)) {
        return false;
    }
    if (time() > $r['expires_at']) {
        unset($_SESSION['recovery']);
        return false;
    }
    return true;
}

/** Step 2: verify the answer. Always takes a similar code path whether or not the account exists. */
function recovery_verify_answer(string $email, string $answer, string $token): array
{
    $email = mb_strtolower(trim($email));
    $generic = ['ok' => false, 'error' => t('auth.recovery_answer_mismatch')];

    if (recovery_rate_limited($email) || !recovery_session_valid($email, $token)) {
        record_recovery_attempt($email, 'answer_checked', false);
        return ['ok' => false, 'error' => t('auth.recovery_session_expired')];
    }

    $stmt = db()->prepare('SELECT id, secret_answer_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $normalized = normalize_secret_answer($answer);
    $matched = $user && password_verify($normalized, $user['secret_answer_hash']);

    record_recovery_attempt($email, 'answer_checked', (bool)$matched);

    if (!$matched) {
        return $generic;
    }

    $_SESSION['recovery']['answer_verified'] = true;
    return ['ok' => true];
}

/** Step 3: set a new password once the answer has been verified in this session. */
function recovery_reset_password(string $email, string $newPassword, string $token): array
{
    $email = mb_strtolower(trim($email));

    if (!recovery_session_valid($email, $token) || empty($_SESSION['recovery']['answer_verified'])) {
        return ['ok' => false, 'error' => t('auth.recovery_session_expired')];
    }
    if (mb_strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => t('auth.register_password_length')];
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        record_recovery_attempt($email, 'password_reset', false);
        return ['ok' => false, 'error' => 'Unable to reset password.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ?, failed_login_count = 0, status = "active" WHERE id = ?')
        ->execute([$hash, $user['id']]);

    record_recovery_attempt($email, 'password_reset', true);
    unset($_SESSION['recovery']);

    return ['ok' => true];
}
