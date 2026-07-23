<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$entityType = $_GET['entity_type'] ?? '';
$actorId = $_GET['actor_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$sql = 'SELECT al.*, u.full_name AS actor_name FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id WHERE 1=1';
$params = [];
if ($entityType !== '') { $sql .= ' AND al.entity_type = ?'; $params[] = $entityType; }
if ($actorId !== '') { $sql .= ' AND al.actor_user_id = ?'; $params[] = $actorId; }
$countStmt = db()->prepare(str_replace('al.*, u.full_name AS actor_name', 'COUNT(*)', $sql));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql .= ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';
$stmt = db()->prepare($sql);
foreach ($params as $i => $p) { $stmt->bindValue($i + 1, $p); }
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$entityTypes = db()->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type')->fetchAll();
$actors = db()->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = t('audit.title');
$activeNav = 'audit_log';
$breadcrumbs = [['label' => t('audit.title')]];
require __DIR__ . '/includes/layout_header.php';
?>
<h4 class="mb-3"><?= e(t('audit.title')) ?></h4>
<form class="row g-2 af-card mb-3" method="get">
  <div class="col-md-4"><select class="form-select form-select-sm" name="entity_type"><option value=""><?= e(t('audit.all_entity_types')) ?></option>
    <?php foreach ($entityTypes as $et): ?><option value="<?= e($et['entity_type']) ?>" <?= $entityType === $et['entity_type'] ? 'selected' : '' ?>><?= e(status_label($et['entity_type'])) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><select class="form-select form-select-sm" name="actor_id"><option value=""><?= e(t('audit.all_users')) ?></option>
    <?php foreach ($actors as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (string)$actorId === (string)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-outline-secondary btn-sm w-100"><?= e(t('common.filter')) ?></button></div>
</form>

<div class="af-card p-0">
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th><?= e(t('audit.col_when')) ?></th><th><?= e(t('audit.col_entity')) ?></th><th><?= e(t('audit.col_action')) ?></th><th><?= e(t('audit.col_actor')) ?></th><th><?= e(t('audit.col_ip')) ?></th><th><?= e(t('audit.col_change')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="small text-nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
          <td><?= e(status_label($l['entity_type'])) ?> #<?= (int)$l['entity_id'] ?></td>
          <td><?= e(str_replace('_', ' ', $l['action'])) ?></td>
          <td><?= e($l['actor_name'] ?? t('audit.system')) ?></td>
          <td class="small text-muted"><?= e($l['ip_address'] ?? '') ?></td>
          <td class="small">
            <?php if ($l['old_value']): ?><span class="text-danger"><?= e(t('audit.old_value', ['value' => substr($l['old_value'], 0, 80)])) ?></span><br><?php endif; ?>
            <?php if ($l['new_value']): ?><span class="text-success"><?= e(t('audit.new_value', ['value' => substr($l['new_value'], 0, 80)])) ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="6"><div class="af-empty"><i class="bi bi-journal-text"></i><?= e(t('audit.empty')) ?></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
    <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>&entity_type=<?= e($entityType) ?>&actor_id=<?= e($actorId) ?>"><?= $p ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
