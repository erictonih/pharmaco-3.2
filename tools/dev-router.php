<?php
// Serveur local : php -S 127.0.0.1:8000 tools/dev-router.php   (sert public/ puis le routeur PHP)
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = realpath(__DIR__ . '/../public' . $p);
if ($f && is_file($f) && str_starts_with($f, realpath(__DIR__ . '/../public'))) { return false; }
require __DIR__ . '/../api/index.php';
