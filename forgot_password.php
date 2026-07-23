<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
$stage = 'email';
$question = null;
$email = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $postStage = $_POST['stage'] ?? 'email';
    $email = trim((string)($_POST['email'] ?? ''));
    $token = (string)($_POST['token'] ?? '');

    if ($postStage === 'email') {
        $result = recovery_start($email);
        if ($result['ok']) {
            $stage = 'answer';
            $question = $result['question'];
            $token = $result['token'];
        } else {
            $error = $result['error'];
        }
    } elseif ($postStage === 'answer') {
        $answer = (string)($_POST['answer'] ?? '');
        $result = recovery_verify_answer($email, $answer, $token);
        if ($result['ok']) {
            $stage = 'reset';
        } else {
            $error = $result['error'];
            $stage = 'email'; // restart on failure, per defensive design
        }
    }
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/auth_layout_header.php';
?>
<h5 class="mb-3">Reset your password</h5>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($stage === 'email'): ?>
  <p class="text-muted small">Enter your account email to begin.</p>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="email">
    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <input type="email" class="form-control" id="email" name="email" required autofocus value="<?= e($email) ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">Continue</button>
  </form>

<?php elseif ($stage === 'answer'): ?>
  <p class="text-muted small">Answer your recovery question to continue.</p>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="answer">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
      <label class="form-label"><?= e($question) ?></label>
      <input type="text" class="form-control" name="answer" required minlength="3" autofocus>
    </div>
    <button type="submit" class="btn btn-primary w-100">Verify answer</button>
  </form>

<?php elseif ($stage === 'reset'): ?>
  <p class="text-muted small">Choose a new password.</p>
  <form method="post" action="<?= e(base_url('reset_password.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
      <label class="form-label" for="new_password">New password</label>
      <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label" for="new_password_confirm">Confirm new password</label>
      <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" required minlength="8">
    </div>
    <button type="submit" class="btn btn-primary w-100">Set new password</button>
  </form>
<?php endif; ?>

<div class="text-center mt-3 small">
  <a href="<?= e(base_url('login.php')) ?>">Back to log in</a>
</div>
<?php require __DIR__ . '/includes/auth_layout_footer.php'; ?>
