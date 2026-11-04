<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\ServiceFactory;
Auth::requireRole('Super Admin', 'Analyste');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit(); }
Auth::csrfCheck();
ServiceFactory::context([], true);                       // force le rechargement depuis le Google Sheet
$allowed = ['index.php', 'situation.php', 'carte.php', 'alertes.php'];
$back = basename((string) ($_POST['back'] ?? 'index.php'));
header('Location: ' . (in_array($back, $allowed, true) ? $back : 'index.php'));
