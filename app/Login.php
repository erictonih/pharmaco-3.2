<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
if (Auth::session()) { header('Location: index.php'); exit(); }
$error = null;
Auth::csrfToken(); // pose le cookie CSRF avant toute sortie HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfCheck();
    $error = Auth::attempt(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
    if ($error === null) { header('Location: index.php'); exit(); }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — DVSSER</title><link rel="icon" href="sary/Logo.png" type="image/png">
<link rel="stylesheet" href="dvsser-theme.css">
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}.box{max-width:380px;width:100%}.box img{height:56px;margin-bottom:12px}</style></head>
<body><div class="dv-card box" style="padding:28px">
  <img src="sary/icone1.png" alt="DVSSER"><h2 style="margin-bottom:4px">Connexion</h2>
  <p class="dv-stat-sub" style="margin-bottom:16px">Surveillance épidémiologique — DVSSER</p>
  <?php if ($error): ?><div class="dv-alert dv-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on"><?= Auth::csrfField() ?>
    <div class="dv-form-group"><label class="dv-label" for="u">Identifiant</label><input class="dv-input" id="u" name="username" required maxlength="100" autocomplete="username" autofocus></div>
    <div class="dv-form-group"><label class="dv-label" for="p">Mot de passe</label><input class="dv-input" id="p" name="password" type="password" required maxlength="1024" autocomplete="current-password"></div>
    <button class="dv-btn dv-btn-primary" style="width:100%;margin-top:8px">Se connecter</button>
  </form></div></body></html>
