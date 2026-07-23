<?php
declare(strict_types=1);
/**
 * Shared page shell. Expects (all optional except handled defaults):
 *   $pageTitle   string
 *   $activeNav   string matching one of the nav keys below
 *   $breadcrumbs array of ['label' => ..., 'url' => ...|null]
 */
$pageTitle = $pageTitle ?? 'ActivityFlow';
$activeNav = $activeNav ?? '';
$user = current_user();
$unread = $user ? unread_notification_count($user['id']) : 0;

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'url' => 'dashboard.php'],
    ['key' => 'my_day', 'label' => 'My Day', 'icon' => 'sun', 'url' => 'my_day.php'],
    ['key' => 'my_tasks', 'label' => 'My Tasks', 'icon' => 'check2-square', 'url' => 'my_tasks.php'],
    ['key' => 'team', 'label' => 'Team Activities', 'icon' => 'people', 'url' => 'team_activities.php'],
    ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar3', 'url' => 'calendar.php'],
    ['key' => 'timeline', 'label' => 'Timeline', 'icon' => 'clock-history', 'url' => 'timeline.php'],
    ['key' => 'projects', 'label' => 'Projects', 'icon' => 'kanban', 'url' => 'projects.php'],
    ['key' => 'people', 'label' => 'People Directory', 'icon' => 'person-lines-fill', 'url' => 'people.php'],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bar-chart-line', 'url' => 'reports.php'],
    ['key' => 'requesters', 'label' => 'Requester Analytics', 'icon' => 'graph-up-arrow', 'url' => 'requester_analytics.php'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ActivityFlow</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body>
<?php if ($user): ?>
<div class="af-shell">
  <aside class="af-sidebar" id="afSidebar">
    <div class="af-brand">
      <i class="bi bi-activity"></i> <span>ActivityFlow</span>
      <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" id="afSidebarClose" aria-label="Close"></button>
    </div>
    <nav class="af-nav">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= e(base_url($item['url'])) ?>" class="af-nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>">
          <i class="bi bi-<?= e($item['icon']) ?>"></i> <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (is_admin()): ?>
        <div class="af-nav-heading">Administration</div>
        <a href="<?= e(base_url('admin/users.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_users' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> <span>Users &amp; Roles</span></a>
        <a href="<?= e(base_url('admin/categories.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_categories' ? 'active' : '' ?>"><i class="bi bi-tags"></i> <span>Categories</span></a>
        <a href="<?= e(base_url('admin/departments.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_departments' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i> <span>Departments</span></a>
        <a href="<?= e(base_url('admin/settings.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_settings' ? 'active' : '' ?>"><i class="bi bi-gear"></i> <span>System Settings</span></a>
        <a href="<?= e(base_url('audit_log.php')) ?>" class="af-nav-link <?= $activeNav === 'audit_log' ? 'active' : '' ?>"><i class="bi bi-journal-text"></i> <span>Audit Log</span></a>
      <?php endif; ?>
    </nav>
  </aside>
  <div class="af-backdrop" id="afBackdrop"></div>

  <div class="af-main">
    <header class="af-topbar">
      <button type="button" class="btn btn-light d-lg-none" id="afSidebarToggle" aria-label="Open menu"><i class="bi bi-list"></i></button>
      <form class="af-search" action="<?= e(base_url('my_tasks.php')) ?>" method="get" role="search">
        <i class="bi bi-search"></i>
        <input type="search" name="search" placeholder="Search activities…" aria-label="Search activities" value="<?= e($_GET['search'] ?? '') ?>">
      </form>
      <div class="af-topbar-actions">
        <div class="dropdown">
          <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <?php if ($unread > 0): ?><span class="badge rounded-pill bg-danger af-notif-badge"><?= (int)$unread ?></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end af-notif-menu">
            <div class="d-flex justify-content-between align-items-center px-3 py-2">
              <strong>Notifications</strong>
              <a href="<?= e(base_url('notifications.php')) ?>" class="small">View all</a>
            </div>
            <div id="afNotifList" class="af-notif-list"><div class="p-3 text-muted small">Loading…</div></div>
          </div>
        </div>
        <div class="dropdown">
          <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <span class="af-avatar"><?= e(mb_substr($user['full_name'], 0, 1)) ?></span>
            <span class="d-none d-md-inline"><?= e($user['full_name']) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e(implode(', ', array_map('status_label', user_roles()))) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= e(base_url('profile.php')) ?>"><i class="bi bi-person-circle"></i> Profile &amp; Settings</a></li>
            <li><a class="dropdown-item" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> Log out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <?php $flashes = flash_all(); if ($flashes): ?>
      <div class="af-flash-container">
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($breadcrumbs)): ?>
      <nav aria-label="breadcrumb" class="af-breadcrumb">
        <ol class="breadcrumb mb-0">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if (!empty($bc['url']) && $i < count($breadcrumbs) - 1): ?>
              <li class="breadcrumb-item"><a href="<?= e($bc['url']) ?>"><?= e($bc['label']) ?></a></li>
            <?php else: ?>
              <li class="breadcrumb-item active" aria-current="page"><?= e($bc['label']) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <main class="af-content">
<?php else: ?>
  <main>
<?php endif; ?>
