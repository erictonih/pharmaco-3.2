<?php
// Vérification de l'authentification
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <title>Accueil — DVSSER</title>
    <meta charset="UTF-8">
    <meta name="description" content="Page d'accueil de l'application de gestion des médicaments">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" href="sary/MINS.png" type="image/png">
    <link rel="stylesheet" href="bootstrap.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        /* ─── CSS Variables ─────────────────────────────────── */
        :root {
            --sidebar-w: 260px;

            /* Light theme */
            --bg-main:       #f0f4f8;
            --bg-sidebar:    #ffffff;
            --bg-card:       #ffffff;
            --bg-topbar:     #ffffff;

            --text-primary:  #0d1b2a;
            --text-secondary:#4a5568;
            --text-muted:    #8a99aa;

            --accent:        #0a6cbc;
            --accent-soft:   #e8f2fb;
            --accent-hover:  #085297;

            --accent2:       #00b894;
            --accent2-soft:  #e0f9f4;

            --border:        #e2eaf2;
            --shadow:        0 2px 16px rgba(10,108,188,0.07);
            --shadow-hover:  0 6px 28px rgba(10,108,188,0.15);

            --sidebar-item-active-bg: #e8f2fb;
            --sidebar-item-active-text: #0a6cbc;
        }

        [data-theme="dark"] {
            --bg-main:       #0f1923;
            --bg-sidebar:    #151f2e;
            --bg-card:       #1a2535;
            --bg-topbar:     #151f2e;

            --text-primary:  #e8f0fb;
            --text-secondary:#a0b4cc;
            --text-muted:    #5a7080;

            --accent:        #3b9ede;
            --accent-soft:   #1a2f45;
            --accent-hover:  #5ab3ea;

            --accent2:       #00cba4;
            --accent2-soft:  #0d2e28;

            --border:        #1e2e42;
            --shadow:        0 2px 16px rgba(0,0,0,0.35);
            --shadow-hover:  0 6px 28px rgba(0,0,0,0.5);

            --sidebar-item-active-bg: #1a2f45;
            --sidebar-item-active-text: #3b9ede;
        }

        /* ─── Reset & Base ──────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        /* ─── Sidebar ───────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 200;
            transition: background 0.3s, border-color 0.3s, transform 0.3s;
            box-shadow: var(--shadow);
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            border-radius: 10px;
            background: var(--accent-soft);
            padding: 4px;
        }

        .sidebar-logo-text {
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo-text .app-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            color: var(--accent);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .sidebar-logo-text .app-sub {
            font-size: 0.68rem;
            color: var(--text-muted);
            font-weight: 400;
            line-height: 1.3;
            margin-top: 2px;
        }

        /* Nav section */
        .sidebar-section {
            padding: 20px 16px 8px;
        }

        .sidebar-section-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .sidebar-nav {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }

        .sidebar-nav li a:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .sidebar-nav li.active a {
            background: var(--sidebar-item-active-bg);
            color: var(--sidebar-item-active-text);
            font-weight: 600;
        }

        .sidebar-nav li a .nav-icon {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-nav li a .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }

        /* Sidebar footer */
        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--accent-soft);
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-details .user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-details .user-role {
            font-size: 0.68rem;
            color: var(--text-muted);
        }

        /* ─── Main content ──────────────────────────────────── */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        /* ─── Topbar ────────────────────────────────────────── */
        .topbar {
            height: 68px;
            background: var(--bg-topbar);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
            transition: background 0.3s, border-color 0.3s;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-left .page-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-primary);
        }

        .topbar-left .breadcrumb-sep {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Theme toggle */
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .theme-toggle:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }

        /* Sidebar toggle (mobile) */
        .sidebar-toggle {
            display: none;
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* ─── Page content ──────────────────────────────────── */
        .page-content {
            flex: 1;
            padding: 40px 40px 60px;
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--accent) 0%, #0556a4 100%);
            border-radius: 18px;
            padding: 36px 40px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 36px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(10,108,188,0.25);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }

        .welcome-text h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .welcome-text p {
            font-size: 0.88rem;
            opacity: 0.85;
            line-height: 1.5;
            max-width: 420px;
        }

        .welcome-icon {
            font-size: 4rem;
            opacity: 0.25;
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* ─── Action cards grid ─────────────────────────────── */
        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .action-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.25s, transform 0.25s, border-color 0.25s;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
            border-radius: 0;
            opacity: 0;
            transition: opacity 0.25s;
        }

        .action-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
            border-color: var(--accent);
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-card.card-green::before { background: var(--accent2); }
        .action-card.card-green:hover { border-color: var(--accent2); }
        .action-card.card-green .card-icon-wrap { background: var(--accent2-soft); color: var(--accent2); }

        .card-icon-wrap {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .card-body-text .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .card-body-text .card-desc {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .card-arrow {
            margin-top: auto;
            align-self: flex-end;
            width: 32px; height: 32px;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: background 0.2s, transform 0.2s;
        }

        .action-card:hover .card-arrow {
            background: var(--accent);
            color: #fff;
            transform: translateX(3px);
        }

        .action-card.card-green .card-arrow { background: var(--accent2-soft); color: var(--accent2); }
        .action-card.card-green:hover .card-arrow { background: var(--accent2); color: #fff; }

        /* ─── Quick stats row ───────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            transition: box-shadow 0.2s;
        }

        .stat-card:hover { box-shadow: var(--shadow-hover); }

        .stat-card .stat-label {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-card .stat-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .stat-card .stat-sub {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ─── Footer ────────────────────────────────────────── */
        footer {
            background: var(--bg-sidebar);
            border-top: 1px solid var(--border);
            padding: 24px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            transition: background 0.3s;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-brand img {
            height: 38px;
            object-fit: contain;
            opacity: 0.85;
        }

        .footer-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .footer-links {
            display: flex;
            gap: 16px;
        }

        .footer-links a {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover { color: var(--accent); }

        /* ─── Overlay (mobile) ──────────────────────────────── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 199;
        }

        /* ─── Animations ────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .welcome-banner { animation: fadeUp 0.5s ease both; }
        .stats-row       { animation: fadeUp 0.5s 0.1s ease both; }
        .action-grid     { animation: fadeUp 0.5s 0.2s ease both; }

        /* ─── Responsive ────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .main-wrapper {
                margin-left: 0;
            }
            .sidebar-toggle {
                display: flex;
            }
            .page-content {
                padding: 24px 20px 48px;
            }
            .topbar {
                padding: 0 20px;
            }
            footer {
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
            }
            .welcome-banner {
                padding: 24px;
            }
        }
    </style>
    <script src="jquery.js"></script>
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══ SIDEBAR ════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="sary/icone1.png" alt="DVSSER">
        <div class="sidebar-logo-text">
            <span class="app-name">DVSSER</span>
            <span class="app-sub">Pharmaco-vigilance</span>
        </div>
    </div>

    <!-- Navigation principale -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">Navigation</div>
        <ul class="sidebar-nav">
            <li class="active">
                <a href="index.php">
                    <i class="bi bi-house-door nav-icon"></i>
                    Accueil
                </a>
            </li>
            <li>
                <a href="visualisation_de_donnée.php">
                    <i class="bi bi-speedometer2 nav-icon"></i>
                    Tableau de Bord
                </a>
            </li>
            <li>
                <a href="analyse.php">
                    <i class="bi bi-bar-chart-line nav-icon"></i>
                    Analyse
                </a>
            </li>
            <li>
                <a href="tdc.php">
                    <i class="bi bi-table nav-icon"></i>
                    TDC
                </a>
            </li>
        </ul>
    </div>

    <!-- Actions rapides -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">Actions</div>
        <ul class="sidebar-nav">
            <li>
                <a href="formulaire.php">
                    <i class="bi bi-pencil-square nav-icon"></i>
                    Saisie de données
                </a>
            </li>
            <li>
                <a href="visualisation_Base.php">
                    <i class="bi bi-database nav-icon"></i>
                    Visualisation des données
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer sidebar -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 2)) : 'US'; ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                    <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Utilisateur'; ?>
                </div>
                <div class="user-role">Agent DVSSER</div>
            </div>
            <a href="logout.php" title="Déconnexion" style="margin-left:auto; color:var(--text-muted); font-size:1rem; text-decoration:none;">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

</aside>

<!-- ═══ MAIN WRAPPER ═══════════════════════════════════ -->
<div class="main-wrapper">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <span class="page-title">Accueil</span>
        </div>
        <div class="topbar-right">
            <button class="theme-toggle" id="themeToggle" title="Changer le thème">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
        </div>
    </header>

    <!-- Page content -->
    <main class="page-content">

        <!-- Welcome banner -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Bienvenue sur la plateforme DVSSER</h2>
                <p>Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte — Ministère de la Santé Publique de Madagascar</p>
            </div>
            <i class="bi bi-shield-plus welcome-icon"></i>
        </div>

        <!-- Quick stats -->
        <p class="section-title">Vue d'ensemble</p>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Médicaments</div>
                <div class="stat-value">—</div>
                <div class="stat-sub">dans la base</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Saisies</div>
                <div class="stat-value">—</div>
                <div class="stat-sub">ce mois-ci</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Alertes</div>
                <div class="stat-value">—</div>
                <div class="stat-sub">en attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Rapport</div>
                <div class="stat-value">—</div>
                <div class="stat-sub">dernière analyse</div>
            </div>
        </div>

        <!-- Action cards -->
        <p class="section-title">Accès rapide</p>
        <div class="action-grid">

            <a href="formulaire.php" class="action-card">
                <div class="card-icon-wrap">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div class="card-body-text">
                    <div class="card-title">Saisie de données</div>
                    <div class="card-desc">Enregistrer de nouvelles données de pharmacovigilance dans le système.</div>
                </div>
                <div class="card-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

            <a href="visualisation_Base.php" class="action-card card-green">
                <div class="card-icon-wrap">
                    <i class="bi bi-database-check"></i>
                </div>
                <div class="card-body-text">
                    <div class="card-title">Visualisation des données</div>
                    <div class="card-desc">Consulter et explorer les données enregistrées dans la base.</div>
                </div>
                <div class="card-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

            <a href="visualisation_de_donnée.php" class="action-card">
                <div class="card-icon-wrap">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="card-body-text">
                    <div class="card-title">Tableau de Bord</div>
                    <div class="card-desc">Vue synthétique des indicateurs et statistiques clés.</div>
                </div>
                <div class="card-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

            <a href="analyse.php" class="action-card card-green">
                <div class="card-icon-wrap">
                    <i class="bi bi-bar-chart-line"></i>
                </div>
                <div class="card-body-text">
                    <div class="card-title">Analyse</div>
                    <div class="card-desc">Lancer des analyses approfondies sur les données de surveillance.</div>
                </div>
                <div class="card-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

        </div>

    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-brand">
            <img src="sary/MINS.png" alt="Logo Ministère de la Santé">
            <div class="footer-text">
                ©<span id="currentYear"></span> Ministère de la Santé Publique de Madagascar<br>
                Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte
            </div>
        </div>
        <div class="footer-links">
            <a href="#">Mentions légales</a>
            <a href="#">Confidentialité</a>
        </div>
    </footer>

</div><!-- /.main-wrapper -->

<script src="bootstrap.min.js"></script>
<script>
    // ── Current year
    document.getElementById('currentYear').textContent = new Date().getFullYear();

    // ── Theme toggle
    const html        = document.documentElement;
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon   = document.getElementById('themeIcon');

    const savedTheme = localStorage.getItem('dvsser-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateIcon(savedTheme);

    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        const next    = current === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('dvsser-theme', next);
        updateIcon(next);
    });

    function updateIcon(theme) {
        themeIcon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }

    // ── Mobile sidebar toggle
    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle  = document.getElementById('sidebarToggle');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('open');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
    });
</script>
</body>
</html>
