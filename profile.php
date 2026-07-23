<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user()['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        if ($fullName === '') {
            flash_set('danger', 'Full name cannot be empty.');
        } else {
            $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $userId]);
            $personId = current_person_id();
            if ($personId) {
                $pdo->prepare('UPDATE people SET full_name = ? WHERE id = ?')->execute([$fullName, $personId]);
            }
            load_user_session($userId);
            flash_set('success', 'Profile updated.');
        }
    } elseif ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash_set('danger', 'Current password is incorrect.');
        } elseif (mb_strlen($new) < 8) {
            flash_set('danger', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash_set('danger', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            audit_log('user', $userId, 'password_changed');
            flash_set('success', 'Password updated.');
        }
    } elseif ($action === 'change_secret') {
        $question = trim((string)($_POST['secret_question'] ?? ''));
        $answer = (string)($_POST['secret_answer'] ?? '');
        if (mb_strlen($question) < 5 || mb_strlen(normalize_secret_answer($answer)) < 3) {
            flash_set('danger', 'Please provide a valid question and answer.');
        } else {
            $pdo->prepare('UPDATE users SET secret_question = ?, secret_answer_hash = ? WHERE id = ?')
                ->execute([$question, password_hash(normalize_secret_answer($answer), PASSWORD_DEFAULT), $userId]);
            audit_log('user', $userId, 'recovery_question_changed');
            flash_set('success', 'Recovery question updated.');
        }
    }
    redirect('profile.php');
}

$stmt = $pdo->prepare('SELECT full_name, email, status, secret_question, created_at, last_login_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$account = $stmt->fetch();

$pageTitle = 'Profile & Settings';
$activeNav = '';
$breadcrumbs = [['label' => 'Profile & Settings']];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="af-card mb-4">
      <h5 class="mb-3">Profile</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="mb-3">
          <label class="form-label">Full name</label>
          <input type="text" class="form-control" name="full_name" value="<?= e($account['full_name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= e($account['email']) ?>" disabled>
          <div class="form-text">Contact an administrator to change your email.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Account status</label>
          <div><span class="badge bg-<?= $account['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(status_label($account['status'])) ?></span></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Roles</label>
          <div><?php foreach (user_roles() as $r): ?><span class="badge bg-primary me-1"><?= e(status_label($r)) ?></span><?php endforeach; ?></div>
        </div>
        <button type="submit" class="btn btn-primary">Save profile</button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="af-card mb-4">
      <h5 class="mb-3">Change password</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="mb-3">
          <label class="form-label">Current password</label>
          <input type="password" class="form-control" name="current_password" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New password</label>
          <input type="password" class="form-control" name="new_password" minlength="8" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm new password</label>
          <input type="password" class="form-control" name="new_password_confirm" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </div>

    <div class="af-card">
      <h5 class="mb-3">Recovery question</h5>
      <p class="text-muted small">Current question: <strong><?= e($account['secret_question']) ?></strong></p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_secret">
        <div class="mb-3">
          <label class="form-label">New recovery question</label>
          <input type="text" class="form-control" name="secret_question" required minlength="5">
        </div>
        <div class="mb-3">
          <label class="form-label">New recovery answer</label>
          <input type="text" class="form-control" name="secret_answer" required minlength="3">
        </div>
        <button type="submit" class="btn btn-outline-primary">Update recovery question</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
