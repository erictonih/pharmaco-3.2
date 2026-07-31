<?php
/**
 * auth_check.php — Controle d'acces par role (RBAC)
 * Compatible PHP 7.4+
 *
 * Usage en haut de chaque page protegee :
 *   $required_roles = array('Super Admin', 'Pharmacien');
 *   include 'auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$PAGE_ROLES = array(
    'users.php'                   => array('Super Admin'),
    'medicament.php'              => array('Super Admin', 'Saisie', 'Visiteur'),
    'formulaire.php'              => array('Super Admin', 'Saisie'),
    'TDC.php'                     => array('Super Admin', 'Saisie', 'Visiteur'),
    'analyse.php'                 => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'visualisation_Base.php'      => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'visualisation_de_donnée.php' => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'index.php'                   => array('Super Admin', 'Saisie', 'Visiteur', 'Visualiseur', 'Analyste'),
    'analyse_mpox.php' => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste')
);

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$check_roles = array();
if (isset($required_roles) && is_array($required_roles)) {
    $check_roles = $required_roles;
} elseif (isset($PAGE_ROLES[$current_page])) {
    $check_roles = $PAGE_ROLES[$current_page];
}

if (!empty($check_roles) && !in_array($user_role, $check_roles, true)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <title>Acces refuse - DVSSER</title>
    <style>
      body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
           min-height:100vh;margin:0;background:#f0f4f9;}
      .box{background:#fff;border-radius:20px;padding:48px;text-align:center;max-width:400px;
           box-shadow:0 4px 24px rgba(0,0,0,.08);}
      h2{color:#0a1628;margin-bottom:10px;}
      p{color:#64748b;margin-bottom:24px;}
      a{display:inline-block;background:#0a6cbc;color:#fff;padding:11px 22px;
        border-radius:10px;text-decoration:none;font-weight:600;}
    </style></head><body>
    <div class="box">
      <div style="font-size:3rem;margin-bottom:16px">&#128274;</div>
      <h2>Acces refuse</h2>
      <p>Votre role <strong>' . htmlspecialchars($user_role ?: 'inconnu') . '</strong>
         n\'a pas acces a cette page.</p>
      <a href="dashboard.php">Retour au tableau de bord</a>
    </div></body></html>';
    exit();
}

// Helper disponible dans toutes les pages
function can() {
    $roles = func_get_args();
    return in_array(
        isset($_SESSION['role']) ? $_SESSION['role'] : '',
        $roles,
        true
    );
}
