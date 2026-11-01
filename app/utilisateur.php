<?php
// utilisateur.php — traitement de la connexion
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Login.php');
    exit();
}
csrf_check();

$conn = db();
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$ip       = substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$ip       = trim(explode(',', $ip)[0]);

function login_fail(string $msg): void {
    $_SESSION['error'] = $msg;
    header('Location: /Login.php');
    exit();
}

if ($username === '' || $password === '' || strlen($username) > 255 || strlen($password) > 1024) {
    login_fail('Veuillez remplir tous les champs.');
}

// Anti brute-force : 5 échecs / 15 min par identifiant ou par IP
$since = time() - 900;
$st = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE ts > ? AND (username = ? OR ip = ?)');
$st->bind_param('iss', $since, $username, $ip);
$st->execute();
$st->bind_result($fails);
$st->fetch();
$st->close();
if ($fails >= 5) {
    login_fail('Trop de tentatives. Réessayez dans 15 minutes.');
}

$st = $conn->prepare('SELECT user_id, username, role, mdp, statut FROM users WHERE username = ? LIMIT 1');
$st->bind_param('s', $username);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

// Hash factice pour garder un temps de réponse constant si l'utilisateur n'existe pas
$hash  = $row['mdp'] ?? '$2y$10$usesomesillystringforsaltuQ3m3o3ZyqkVJ7k0mR8mV1l6w5p9x2a';
$valid = password_verify($password, $hash) && $row && ($row['statut'] ?? '') === 'Actif';

if (!$valid) {
    $now = time();
    $st = $conn->prepare('INSERT INTO login_attempts (username, ip, ts) VALUES (?, ?, ?)');
    $st->bind_param('ssi', $username, $ip, $now);
    $st->execute();
    $st->close();
    // Message volontairement générique (pas d'énumération des comptes)
    login_fail('Identifiant ou mot de passe incorrect.');
}

// Succès : on nettoie les échecs, on régénère l'ID de session (anti fixation)
$st = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
$st->bind_param('s', $username);
$st->execute();
$st->close();

if (password_needs_rehash($row['mdp'], PASSWORD_DEFAULT)) {
    $new = password_hash($password, PASSWORD_DEFAULT);
    $st = $conn->prepare('UPDATE users SET mdp = ? WHERE user_id = ?');
    $st->bind_param('si', $new, $row['user_id']);
    $st->execute();
    $st->close();
}

$st = $conn->prepare('UPDATE users SET derniere_connexion = NOW() WHERE user_id = ?');
$st->bind_param('i', $row['user_id']);
$st->execute();
$st->close();

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$row['user_id'];
$_SESSION['user_name'] = $row['username'];
$_SESSION['role']      = trim($row['role']);
unset($_SESSION['csrf']);

header('Location: /index.php');
exit();
