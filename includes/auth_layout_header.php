<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? t('app.name');
?><!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="<?= e(current_theme()) ?>" data-bs-theme="<?= e(bs_color_mode()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(t('app.name')) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<style>
  body.af-auth-body { background: linear-gradient(135deg,#111827,#4361ee); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
  .af-auth-card { max-width:440px; width:100%; background:#fff; border-radius:1rem; padding:2rem; box-shadow:0 20px 45px rgba(0,0,0,.25); position:relative; }
  .af-auth-brand { display:flex; align-items:center; gap:.5rem; font-weight:700; font-size:1.3rem; margin-bottom:1.25rem; color:#111827; }
  .af-auth-lang { position:absolute; top:1rem; right:1rem; }
</style>
</head>
<body class="af-auth-body">
<div class="af-auth-card">
  <div class="dropdown af-auth-lang">
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-label="<?= e(t('topbar.language')) ?>"><i class="bi bi-translate"></i></button>
    <ul class="dropdown-menu dropdown-menu-end">
      <?php foreach (available_locales() as $key => $label): ?>
        <li><a class="dropdown-item <?= current_locale() === $key ? 'active' : '' ?>" href="#" data-locale-option="<?= e($key) ?>"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="af-auth-brand"><i class="bi bi-activity text-primary"></i> <?= e(t('app.name')) ?></div>
  <?php foreach (flash_all() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
