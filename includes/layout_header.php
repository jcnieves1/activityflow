<?php
declare(strict_types=1);
/**
 * Shared page shell. Expects (all optional except handled defaults):
 *   $pageTitle   string
 *   $activeNav   string matching one of the nav keys below
 *   $breadcrumbs array of ['label' => ..., 'url' => ...|null]
 */
$pageTitle = $pageTitle ?? t('app.name');
$activeNav = $activeNav ?? '';
$user = current_user();
$unread = $user ? unread_notification_count($user['id']) : 0;

$navItems = [
    ['key' => 'dashboard', 'label' => t('nav.dashboard'), 'icon' => 'speedometer2', 'url' => 'dashboard.php'],
    ['key' => 'my_day', 'label' => t('nav.my_day'), 'icon' => 'sun', 'url' => 'my_day.php'],
    ['key' => 'my_tasks', 'label' => t('nav.my_tasks'), 'icon' => 'check2-square', 'url' => 'my_tasks.php'],
    ['key' => 'team', 'label' => t('nav.team'), 'icon' => 'people', 'url' => 'team_activities.php'],
    ['key' => 'calendar', 'label' => t('nav.calendar'), 'icon' => 'calendar3', 'url' => 'calendar.php'],
    ['key' => 'timeline', 'label' => t('nav.timeline'), 'icon' => 'clock-history', 'url' => 'timeline.php'],
    ['key' => 'projects', 'label' => t('nav.projects'), 'icon' => 'kanban', 'url' => 'projects.php'],
    ['key' => 'my_projects', 'label' => t('nav.my_projects'), 'icon' => 'person-workspace', 'url' => 'my_projects.php'],
    ['key' => 'people', 'label' => t('nav.people'), 'icon' => 'person-lines-fill', 'url' => 'people.php'],
    ['key' => 'reports', 'label' => t('nav.reports'), 'icon' => 'bar-chart-line', 'url' => 'reports.php'],
    ['key' => 'requesters', 'label' => t('nav.requesters'), 'icon' => 'graph-up-arrow', 'url' => 'requester_analytics.php'],
];
?><!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="<?= e(current_theme()) ?>" data-bs-theme="<?= e(bs_color_mode()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(t('app.name')) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<?php if (!empty($pageStyles)) foreach ($pageStyles as $href): ?>
<link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
</head>
<body>
<?php if ($user): ?>
<div class="af-shell">
  <aside class="af-sidebar" id="afSidebar">
    <div class="af-brand">
      <i class="bi bi-activity"></i> <span><?= e(t('app.name')) ?></span>
      <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" id="afSidebarClose" aria-label="Close"></button>
    </div>
    <nav class="af-nav">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= e(base_url($item['url'])) ?>" class="af-nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>">
          <i class="bi bi-<?= e($item['icon']) ?>"></i> <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (is_admin()): ?>
        <div class="af-nav-heading"><?= e(t('nav.admin_heading')) ?></div>
        <a href="<?= e(base_url('admin/users.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_users' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> <span><?= e(t('nav.admin_users')) ?></span></a>
        <a href="<?= e(base_url('admin/categories.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_categories' ? 'active' : '' ?>"><i class="bi bi-tags"></i> <span><?= e(t('nav.admin_categories')) ?></span></a>
        <a href="<?= e(base_url('admin/departments.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_departments' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i> <span><?= e(t('nav.admin_departments')) ?></span></a>
        <a href="<?= e(base_url('admin/statuses.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_statuses' ? 'active' : '' ?>"><i class="bi bi-flag"></i> <span><?= e(t('nav.admin_statuses')) ?></span></a>
        <a href="<?= e(base_url('admin/request_channels.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_request_channels' ? 'active' : '' ?>"><i class="bi bi-signpost-split"></i> <span><?= e(t('nav.admin_request_channels')) ?></span></a>
        <a href="<?= e(base_url('admin/releases.php')) ?>" class="af-nav-link <?= in_array($activeNav, ['admin_releases'], true) ? 'active' : '' ?>"><i class="bi bi-rocket-takeoff"></i> <span><?= e(t('nav.admin_releases')) ?></span></a>
        <a href="<?= e(base_url('admin/settings.php')) ?>" class="af-nav-link <?= $activeNav === 'admin_settings' ? 'active' : '' ?>"><i class="bi bi-gear"></i> <span><?= e(t('nav.admin_settings')) ?></span></a>
        <a href="<?= e(base_url('audit_log.php')) ?>" class="af-nav-link <?= $activeNav === 'audit_log' ? 'active' : '' ?>"><i class="bi bi-journal-text"></i> <span><?= e(t('nav.audit_log')) ?></span></a>
      <?php endif; ?>
    </nav>
  </aside>
  <div class="af-backdrop" id="afBackdrop"></div>

  <div class="af-main">
    <header class="af-topbar">
      <button type="button" class="btn btn-light d-lg-none" id="afSidebarToggle" aria-label="Open menu"><i class="bi bi-list"></i></button>
      <form class="af-search" action="<?= e(base_url('my_tasks.php')) ?>" method="get" role="search">
        <i class="bi bi-search"></i>
        <input type="search" name="search" placeholder="<?= e(t('topbar.search_placeholder')) ?>" aria-label="<?= e(t('topbar.search_placeholder')) ?>" value="<?= e($_GET['search'] ?? '') ?>">
      </form>
      <div class="af-topbar-actions">
        <div class="dropdown">
          <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="<?= e(t('topbar.theme')) ?>" title="<?= e(t('topbar.theme')) ?>">
            <span class="af-theme-swatch af-theme-<?= e(current_theme()) ?>"></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach (available_themes() as $key => $label): ?>
              <li>
                <a class="dropdown-item d-flex align-items-center gap-2 <?= current_theme() === $key ? 'active' : '' ?>" href="#" data-theme-option="<?= e($key) ?>" data-theme-active>
                  <span class="af-theme-swatch af-theme-<?= e($key) ?>"></span> <?= e(t('theme.' . $key)) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="dropdown">
          <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="<?= e(t('topbar.language')) ?>" title="<?= e(t('topbar.language')) ?>">
            <i class="bi bi-translate"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach (available_locales() as $key => $label): ?>
              <li><a class="dropdown-item <?= current_locale() === $key ? 'active' : '' ?>" href="#" data-locale-option="<?= e($key) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="dropdown">
          <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-label="<?= e(t('topbar.notifications')) ?>">
            <i class="bi bi-bell"></i>
            <?php if ($unread > 0): ?><span class="badge rounded-pill bg-danger af-notif-badge"><?= (int)$unread ?></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end af-notif-menu">
            <div class="d-flex justify-content-between align-items-center px-3 py-2">
              <strong><?= e(t('topbar.notifications')) ?></strong>
              <a href="<?= e(base_url('notifications.php')) ?>" class="small"><?= e(t('topbar.view_all')) ?></a>
            </div>
            <div id="afNotifList" class="af-notif-list"><div class="p-3 text-muted small"><?= e(t('topbar.loading')) ?></div></div>
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
            <li><a class="dropdown-item" href="<?= e(base_url('profile.php')) ?>"><i class="bi bi-person-circle"></i> <?= e(t('topbar.profile')) ?></a></li>
            <li><a class="dropdown-item" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> <?= e(t('topbar.logout')) ?></a></li>
          </ul>
        </div>
      </div>
    </header>

    <?php if (is_impersonating()): $impersonatedUser = current_user(); ?>
      <div class="af-impersonation-banner">
        <i class="bi bi-person-badge-fill"></i>
        <span><?= e(t('impersonation.banner', ['name' => $impersonatedUser['full_name'] ?? ''])) ?></span>
        <button type="button" id="afStopImpersonationBtn" class="btn btn-sm btn-light ms-auto"><?= e(t('impersonation.stop_button')) ?></button>
      </div>
    <?php endif; ?>

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
