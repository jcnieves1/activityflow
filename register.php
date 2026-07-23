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
        $error = 'Passwords do not match.';
    } elseif (!captcha_verify($_POST['captcha_answer'] ?? null)) {
        $error = 'Incorrect answer to the security check. Please try again.';
    } else {
        $result = register_user($fullName, $email, $password, $question, $answer);
        if ($result['ok']) {
            $message = $result['linked_existing']
                ? 'Account created. This matched an existing entry in the people directory, so it was linked to your new login instead of creating a duplicate. You can log in now.'
                : 'Account created. You can log in now.';
            flash_set('success', $message);
            redirect('login.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/auth_layout_header.php';
?>
<h5 class="mb-3">Create your account</h5>
<p class="text-muted small">No email verification required — you can log in right away.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="full_name">Full name</label>
    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="email">Email address</label>
    <input type="email" class="form-control" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label" for="password">Password</label>
      <input type="password" class="form-control" id="password" name="password" required minlength="8">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label" for="password_confirm">Confirm password</label>
      <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="secret_question">Secret recovery question</label>
    <input type="text" class="form-control" id="secret_question" name="secret_question" required
           placeholder="e.g. What city were you born in?" value="<?= e($_POST['secret_question'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="secret_answer">Secret recovery answer</label>
    <input type="text" class="form-control" id="secret_answer" name="secret_answer" required minlength="3">
    <div class="form-text">Used only to verify your identity if you forget your password.</div>
  </div>
  <?= captcha_field() ?>
  <button type="submit" class="btn btn-primary w-100">Create account</button>
</form>
<div class="text-center mt-3 small">
  Already have an account? <a href="<?= e(base_url('login.php')) ?>">Log in</a>
</div>
<?php require __DIR__ . '/includes/auth_layout_footer.php'; ?>
