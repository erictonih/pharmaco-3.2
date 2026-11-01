<?php
/**
 * sidebar.php — Composant partagé DVSSER
 * Inclure avec : <?php $active_page = 'index'; include 'sidebar.php'; ?>
 * 
 * $active_page : 'index' | 'dashboard' | 'analyse' | 'tdc' | 'formulaire' | 'visualisation'
 * $page_title  : titre affiché dans la topbar
 * $breadcrumb  : tableau ['label'=>'url'] ou vide
 */

$active_page = $active_page ?? 'index';
$page_title  = $page_title  ?? 'DVSSER';
$username = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Utilisateur';
$initials = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 2)) : 'US';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($page_title); ?> — DVSSER</title>
    <link rel="icon" href="sary/Logo.png" type="image/png">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <!-- Thème DVSSER -->
    <link rel="stylesheet" href="dvsser-theme.css">
    <script src="jquery.js" defer></script>
</head>
<body>

<!-- Overlay mobile -->
<div class="dv-overlay" id="dvOverlay"></div>

<!-- ═══ SIDEBAR ═════════════════════════════════════════ -->
<aside class="dv-sidebar" id="dvSidebar">

    <div class="dv-sidebar-logo">
        <img src="sary/icone1.png" alt="DVSSER">
        <div class="dv-logo-text">
            <span class="app-name">DVSSER</span>
            <span class="app-sub">Pharmaco-vigilance</span>
        </div>
    </div>

    <div class="dv-sidebar-section">
        <div class="dv-sidebar-label">Navigation</div>
        <ul class="dv-nav">
            <li class="<?php echo $active_page === 'index' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="bi bi-house-door nav-icon"></i> Accueil
                </a>
            </li>
            <li class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                <a href="visualisation_de_donnee.php">
                    <i class="bi bi-speedometer2 nav-icon"></i> Tableau de Bord
                </a>
            </li>
            <li class="<?php echo $active_page === 'analyse' ? 'active' : ''; ?>">
                <a href="analyse.php">
                    <i class="bi bi-bar-chart-line nav-icon"></i> Analyse
                </a>
            </li>
            <li>
            <a href="analyse_mpox.php"
                class="nav-link <?= ($active_page === 'analyse_mpox') ? 'active' : '' ?>"
                title="Analyse Mpox">
                <i class="bi bi-virus"></i>
                <span>Analyse Mpox</span>
                </a>
                </li>
            <li class="<?php echo $active_page === 'tdc' ? 'active' : ''; ?>">
                <a href="tdc.php">
                    <i class="bi bi-table nav-icon"></i> TDC
                </a>
            </li>
        </ul>
    </div>

    <div class="dv-sidebar-section">
        <div class="dv-sidebar-label">Saisie &amp; données</div>
        <ul class="dv-nav">
            <li class="<?php echo $active_page === 'formulaire' ? 'active' : ''; ?>">
                <a href="formulaire.php">
                    <i class="bi bi-pencil-square nav-icon"></i> Saisie de données
                </a>
            </li>
            <li class="<?php echo $active_page === 'visualisation' ? 'active' : ''; ?>">
                <a href="visualisation_base.php">
                    <i class="bi bi-database nav-icon"></i> Visualisation
                </a>
            </li>
        </ul>
    </div>
    
	<div class="dv-sidebar-section">
    <div class="dv-sidebar-label">Administration</div>
    <ul class="dv-nav">
        <li class="<?php echo $active_page === 'Utilisateur' ? 'active' : ''; ?>">
            <a href="users.php">
                <i class="bi bi-person-gear nav-icon"> </i>Utilisateurs & Rôles
            </a>
        </li>
        <li class="<?php echo $active_page === 'Paramétre' ? 'active': ''; ?>">
    		<a href="parametre.php">
            <i class="bi bi-gear nav-icon"></i>Paramètres
            </a>
        </li>
        </ul>
    </div>
    
    <div class="dv-sidebar-footer">
        <div class="dv-user-box">
            <div class="dv-avatar"><?php echo $initials; ?></div>
            <div>
                <div class="dv-user-name"><?php echo $username; ?></div>
                <div class="dv-user-role">Agent DVSSER</div>
            </div>
            <a href="logout.php" class="dv-logout-btn" title="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ════════════════════════════════════════════ -->
<div class="dv-main">

    <!-- TOPBAR -->
    <header class="dv-topbar">
        <div class="dv-topbar-left">
            <button class="dv-hamburger" id="dvHamburger">
                <i class="bi bi-list"></i>
            </button>
            <span class="dv-page-title"><?php echo htmlspecialchars($page_title); ?></span>
            <?php if (!empty($breadcrumb)): ?>
            <div class="dv-breadcrumb">
                <?php foreach ($breadcrumb as $label => $url): ?>
                    <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                    <?php if ($url): ?>
                        <a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($label); ?></a>
                    <?php else: ?>
                        <span><?php echo htmlspecialchars($label); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="dv-topbar-right">
            <button class="dv-theme-btn" id="dvThemeBtn" title="Changer le thème">
                <i class="bi bi-moon-stars" id="dvThemeIcon"></i>
            </button>
        </div>
    </header>

    <!-- CONTENU DE LA PAGE (fermé dans footer.php) -->
