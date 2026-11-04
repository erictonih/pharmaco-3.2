<?php
declare(strict_types=1);
/**
 * bootstrap.php — point d'entrée commun : autoload, configuration par variables d'environnement,
 * en-têtes de sécurité. Aucune base de données : toutes les données viennent du Google Sheet.
 */
if (defined('PHARMACO_BOOT')) { return; }
define('PHARMACO_BOOT', true);
define('PHARMACO_ROOT', dirname(__DIR__));

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Indian/Antananarivo');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Pharmaco\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) { require $file; }
});

function env_get(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') { $v = $_ENV[$key] ?? $_SERVER[$key] ?? null; }
    return ($v === null || $v === '') ? $default : (string) $v;
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Répertoire de cache (disque éphémère de la fonction serverless). */
function cache_dir(): string {
    $d = sys_get_temp_dir() . '/pharmaco_cache';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cache-Control: private, no-store');   // pages authentifiées : jamais en cache partagé
    header_remove('X-Powered-By');
    if (is_https()) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }
}
