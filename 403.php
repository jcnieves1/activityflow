<?php
declare(strict_types=1);
if (!headers_sent()) { http_response_code(403); }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Access denied · ActivityFlow</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
</head><body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="text-center">
<h1 class="display-4">403</h1>
<p class="lead">You don't have permission to view this page.</p>
<a href="javascript:history.back()" class="btn btn-primary">Go back</a>
</div></body></html>
