<?php
declare(strict_types=1);
if (!headers_sent()) { http_response_code(500); }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>System error · ActivityFlow</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
</head><body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="text-center">
<h1 class="display-4">500</h1>
<p class="lead">Something went wrong on our end. Please try again shortly.</p>
<a href="/" class="btn btn-primary">Return home</a>
</div></body></html>
