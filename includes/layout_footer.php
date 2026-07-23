<?php declare(strict_types=1); $user = current_user(); ?>
<?php if ($user): ?>
    </main>
    <footer class="af-footer">ActivityFlow &middot; <?= date('Y') ?></footer>
  </div>
</div>
<?php require __DIR__ . '/quickadd_modal.php'; ?>
<?php else: ?>
  </main>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
window.AF_BASE_URL = <?= json_encode(base_url()) ?>;
window.AF_CSRF = <?= json_encode(csrf_token()) ?>;
window.AF_PERSON_ID = <?= json_encode(current_person_id()) ?>;
window.AF_USER_ID = <?= json_encode($user['id'] ?? null) ?>;
</script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
<?php if ($user): ?><script src="<?= e(base_url('assets/js/quickadd.js')) ?>"></script><?php endif; ?>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
