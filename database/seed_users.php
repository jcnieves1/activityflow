<?php
/**
 * Creates demo login accounts with properly hashed passwords and links them
 * to the people rows inserted by seed.sql. Run once from the command line:
 *
 *   php database/seed_users.php
 *
 * DEVELOPMENT/DEMO USE ONLY. Every account below uses the same throw-away
 * password and secret answer — change or delete these before any real use.
 * Demo password for all accounts: Password123!
 * Demo secret question: "What city were you born in?"  Demo answer: "Chicago"
 */

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$pdo = db();

$demoPassword = 'Password123!';
$demoQuestion = 'What city were you born in?';
$demoAnswer = 'Chicago';

$passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);
$answerHash = password_hash(normalize_secret_answer($demoAnswer), PASSWORD_DEFAULT);

// [full_name, email, person_id, role_name]
$accounts = [
    ['Alicia Moreno', 'alicia.moreno@activityflow.test', 1, 'administrator'],
    ['Ben Ortiz',      'ben.ortiz@activityflow.test',      2, 'project_manager'],
    ['Carla Diaz',     'carla.diaz@activityflow.test',     3, 'employee'],
    ['David Kim',      'david.kim@activityflow.test',      4, 'employee'],
    ['Elena Petrova',  'elena.petrova@activityflow.test',  5, 'project_manager'],
    ['Frank Lucas',    'frank.lucas@activityflow.test',    6, 'employee'],
    ['Grace Han',      'grace.han@activityflow.test',      7, 'viewer'],
];

$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
$insertUser = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, secret_question, secret_answer_hash, status)
     VALUES (?, ?, ?, ?, ?, "active")'
);
$insertUserRole = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
$linkPerson = $pdo->prepare('UPDATE people SET user_id = ? WHERE id = ?');
$existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');

$created = 0;
foreach ($accounts as [$fullName, $email, $personId, $roleName]) {
    $existsStmt->execute([$email]);
    if ($existsStmt->fetch()) {
        echo "skip (exists): $email\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        $insertUser->execute([$fullName, $email, $passwordHash, $demoQuestion, $answerHash]);
        $userId = (int)$pdo->lastInsertId();

        $roleStmt->execute([$roleName]);
        $role = $roleStmt->fetch();
        if ($role) {
            $insertUserRole->execute([$userId, $role['id']]);
        }

        $linkPerson->execute([$userId, $personId]);

        $pdo->commit();
        $created++;
        echo "created: $email (role: $roleName)\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "failed for $email: " . $e->getMessage() . "\n");
    }
}

echo "\nDone. $created account(s) created.\n";
echo "All demo accounts use password: $demoPassword\n";
echo "Secret question/answer for all: \"$demoQuestion\" / \"$demoAnswer\"\n";
echo "These are development credentials only — do not use in production.\n";
