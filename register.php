<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    $question = trim((string)($_POST['secret_question'] ?? ''));
    $answer = (string)($_POST['secret_answer'] ?? '');

    if ($password !== $confirm) {
        $error = t('register.error_password_mismatch');
    } elseif (!captcha_verify($_POST['captcha_answer'] ?? null)) {
        $error = t('register.error_captcha');
    } else {
        $result = register_user($fullName, $email, $password, $question, $answer);
        if ($result['ok']) {
            $message = $result['linked_existing']
                ? t('auth.register_success_linked')
                : t('auth.register_success');
            flash_set('success', $message);
            redirect('login.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = t('register.submit');
require __DIR__ . '/includes/auth_layout_header.php';
?>
<h5 class="mb-3"><?= e(t('register.title')) ?></h5>
<p class="text-muted small"><?= e(t('register.subtitle')) ?></p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="full_name"><?= e(t('register.full_name')) ?></label>
    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="email"><?= e(t('register.email')) ?></label>
    <input type="email" class="form-control" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label" for="password"><?= e(t('register.password')) ?></label>
      <input type="password" class="form-control" id="password" name="password" required minlength="8">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label" for="password_confirm"><?= e(t('register.password_confirm')) ?></label>
      <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="secret_question"><?= e(t('register.secret_question')) ?></label>
    <input type="text" class="form-control" id="secret_question" name="secret_question" required
           placeholder="<?= e(t('register.secret_question_placeholder')) ?>" value="<?= e($_POST['secret_question'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="secret_answer"><?= e(t('register.secret_answer')) ?></label>
    <input type="text" class="form-control" id="secret_answer" name="secret_answer" required minlength="3">
    <div class="form-text"><?= e(t('register.secret_answer_help')) ?></div>
  </div>
  <?= captcha_field() ?>
  <button type="submit" class="btn btn-primary w-100"><?= e(t('register.submit')) ?></button>
</form>
<div class="text-center mt-3 small">
  <?= e(t('register.already_have_account')) ?> <a href="<?= e(base_url('login.php')) ?>"><?= e(t('register.login_link')) ?></a>
</div>
<?php require __DIR__ . '/includes/auth_layout_footer.php'; ?>
