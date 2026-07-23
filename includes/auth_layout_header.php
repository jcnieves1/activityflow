<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'ActivityFlow';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ActivityFlow</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<style>
  body.af-auth-body { background: linear-gradient(135deg,#111827,#4361ee); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
  .af-auth-card { max-width:440px; width:100%; background:#fff; border-radius:1rem; padding:2rem; box-shadow:0 20px 45px rgba(0,0,0,.25); }
  .af-auth-brand { display:flex; align-items:center; gap:.5rem; font-weight:700; font-size:1.3rem; margin-bottom:1.25rem; color:#111827; }
</style>
</head>
<body class="af-auth-body">
<div class="af-auth-card">
  <div class="af-auth-brand"><i class="bi bi-activity text-primary"></i> ActivityFlow</div>
  <?php foreach (flash_all() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
