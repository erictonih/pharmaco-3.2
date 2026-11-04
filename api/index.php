<?php
/**
 * api/index.php — routeur unique (front controller) pour Vercel.
 * Seuls les scripts listés ci-dessous sont exécutables depuis l'extérieur.
 */
$root = dirname(__DIR__) . '/app';

// Fichiers internes (inclus par d'autres pages) : jamais appelables directement
// Fichiers internes : tout nom commençant par « _ » (gabarits inclus par les pages)

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (isset($_GET['__p'])) { $path = (string) $_GET['__p']; unset($_GET['__p'], $_REQUEST['__p']); }
$name = basename(rawurldecode($path));           // basename : neutralise tout "../"
if ($name === '' || $name === 'api' || $name === 'index') { $name = 'index.php'; }

$pages = array_map('basename', glob($root . '/*.php') ?: []);
if (!in_array($name, $pages, true) || $name[0] === '_') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>404</title><h1>Page introuvable</h1><p><a href="/">Accueil</a></p>');
}

$script = $root . '/' . $name;
$_SERVER['SCRIPT_FILENAME'] = $script;
$_SERVER['SCRIPT_NAME']     = '/' . $name;
$_SERVER['PHP_SELF']        = '/' . $name;
chdir($root);
require $script;
