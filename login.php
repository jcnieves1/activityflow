<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = t('login.error_missing_fields');
    } elseif (!captcha_verify($_POST['captcha_answer'] ?? null)) {
        $error = t('login.error_captcha');
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok']) {
            redirect('dashboard.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = t('login.submit');
require __DIR__ . '/includes/auth_layout_header.php';
?>
<h5 class="mb-3"><?= e(t('login.title')) ?></h5>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="email"><?= e(t('login.email')) ?></label>
    <input type="email" class="form-control" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="password"><?= e(t('login.password')) ?></label>
    <input type="password" class="form-control" id="password" name="password" required>
  </div>
  <?= captcha_field() ?>
  <button type="submit" class="btn btn-primary w-100"><?= e(t('login.submit')) ?></button>
</form>
<div class="d-flex justify-content-between mt-3 small">
  <a href="<?= e(base_url('forgot_password.php')) ?>"><?= e(t('login.forgot_password')) ?></a>
  <a href="<?= e(base_url('register.php')) ?>"><?= e(t('login.create_account')) ?></a>
</div>
<?php require __DIR__ . '/includes/auth_layout_footer.php'; ?>
