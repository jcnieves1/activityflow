<?php
declare(strict_types=1);
if (!headers_sent()) { http_response_code(403); }
$_t = function_exists('t') ? 't' : fn($k) => ['error.403.title' => 'Access denied', 'error.403.body' => "You don't have permission to view this page.", 'error.403.action' => 'Go back'][$k];
?><!DOCTYPE html>
<html lang="<?= function_exists('current_locale') ? htmlspecialchars(current_locale()) : 'en' ?>" data-theme="<?= function_exists('current_theme') ? htmlspecialchars(current_theme()) : 'golden' ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($_t('error.403.title')) ?> · <?= function_exists('t') ? htmlspecialchars(t('app.name')) : 'ActivityFlow' ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
</head><body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="text-center">
<h1 class="display-4">403</h1>
<p class="lead"><?= htmlspecialchars($_t('error.403.body')) ?></p>
<a href="javascript:history.back()" class="btn btn-primary"><?= htmlspecialchars($_t('error.403.action')) ?></a>
</div></body></html>
