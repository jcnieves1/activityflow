<?php
declare(strict_types=1);
if (!headers_sent()) { http_response_code(404); }
$_t = function_exists('t') ? 't' : fn($k) => ['error.404.title' => 'Not found', 'error.404.body' => "The page you're looking for doesn't exist.", 'error.404.action' => 'Return home'][$k];
?><!DOCTYPE html>
<html lang="<?= function_exists('current_locale') ? htmlspecialchars(current_locale()) : 'en' ?>" data-theme="<?= function_exists('current_theme') ? htmlspecialchars(current_theme()) : 'golden' ?>" data-bs-theme="<?= function_exists('bs_color_mode') ? htmlspecialchars(bs_color_mode()) : 'light' ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($_t('error.404.title')) ?> · <?= function_exists('t') ? htmlspecialchars(t('app.name')) : 'ActivityFlow' ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
</head><body class="d-flex align-items-center justify-content-center vh-100 bg-body">
<div class="text-center">
<h1 class="display-4">404</h1>
<p class="lead"><?= htmlspecialchars($_t('error.404.body')) ?></p>
<a href="/" class="btn btn-primary"><?= htmlspecialchars($_t('error.404.action')) ?></a>
</div></body></html>
