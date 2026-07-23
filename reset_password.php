<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('forgot_password.php');
}

csrf_require();
$email = trim((string)($_POST['email'] ?? ''));
$token = (string)($_POST['token'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['new_password_confirm'] ?? '');

if ($newPassword !== $confirm) {
    flash_set('danger', t('register.error_password_mismatch'));
    redirect('forgot_password.php');
}

$result = recovery_reset_password($email, $newPassword, $token);
if ($result['ok']) {
    flash_set('success', t('reset.success'));
    redirect('login.php');
}

flash_set('danger', $result['error']);
redirect('forgot_password.php');
