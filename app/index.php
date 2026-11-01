<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// Districts en alerte (même calcul que analyse.php)
$alertes = [];
include_once __DIR__ . '/medoc-risque.php';
$nb_alertes = count(array_filter($alertes ?? [], fn($a) => ($a['niveau'] ?? 0) >= 3));

// Stats rapides depuis la BDD
$conn = db();
$total_meds = $total_clients = $total_types = $total_mois = 0;
if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $r = $conn->query("SELECT SUM(Nombres) AS t FROM medicament WHERE id_client IS NOT NULL");
    if ($r) { $row=$r->fetch_assoc(); $total_meds = (int)($row['t'] ?? 0); }

    $r = $conn->query("SELECT COUNT(*) AS t FROM client");
    if ($r) { $row=$r->fetch_assoc(); $total_clients = (int)($row['t'] ?? 0); }

    $r = $conn->query("SELECT COUNT(DISTINCT Type) AS t FROM medicament WHERE id_client IS NOT NULL");
    if ($r) { $row=$r->fetch_assoc(); $total_types = (int)($row['t'] ?? 0); }

    $r = $conn->query("SELECT COUNT(DISTINCT to_char(Date,'YYYY-MM')) AS t FROM medicament WHERE Date IS NOT NULL");
    if ($r) { $row=$r->fetch_assoc(); $total_mois = (int)($row['t'] ?? 0); }

    $conn->close();
}

$page_title  = 'Accueil';
$active_page = 'index';
$breadcrumb  = [];
include 'sidebar.php';
?>

    <style>
    .welcome-banner {
        background: linear-gradient(135deg, var(--accent) 0%, #0556a4 100%);
        border-radius: 16px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        margin-bottom: 28px;
        box-shadow: 0 8px 32px rgba(10,108,188,0.25);
    }
    .welcome-banner::before {
        content:''; position:absolute; top:-40px; right:-40px;
        width:200px; height:200px; border-radius:50%;
        background:rgba(255,255,255,0.07);
    }
    .welcome-banner::after {
        content:''; position:absolute; bottom:-50px; right:60px;
        width:140px; height:140px; border-radius:50%;
        background:rgba(255,255,255,0.05);
    }
    .welcome-banner h2 {
        font-family:'Syne',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:6px;
    }
    .welcome-banner p { font-size:0.85rem; opacity:.85; max-width:420px; line-height:1.5; }
    .wb-icon { position:absolute; right:36px; top:50%; transform:translateY(-50%);
               font-size:5rem; opacity:0.12; }

    .action-grid {
        display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:18px; margin-bottom:28px;
    }
    .action-card {
        background:var(--bg-card); border:1px solid var(--border); border-radius:14px;
        padding:24px; display:flex; flex-direction:column; gap:14px;
        text-decoration:none; color:inherit; cursor:pointer;
        transition:box-shadow 0.25s, transform 0.25s, border-color 0.25s;
        position:relative; overflow:hidden;
    }
    .action-card::before {
        content:''; position:absolute; top:0; left:0; width:4px; height:100%;
        background:var(--accent); opacity:0; transition:opacity 0.25s;
    }
    .action-card:hover { box-shadow:var(--shadow-hover); transform:translateY(-3px); border-color:var(--accent); }
    .action-card:hover::before { opacity:1; }
    .action-card.green::before { background:var(--accent2); }
    .action-card.green:hover { border-color:var(--accent2); }

    .ac-icon {
        width:48px; height:48px; border-radius:12px;
        background:var(--accent-soft); color:var(--accent);
        display:flex; align-items:center; justify-content:center; font-size:1.4rem;
    }
    .action-card.green .ac-icon { background:var(--accent2-soft); color:var(--accent2); }

    .ac-title { font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; color:var(--text-primary); }
    .ac-desc  { font-size:0.8rem; color:var(--text-secondary); line-height:1.4; }

    .ac-arrow {
        margin-top:auto; align-self:flex-end;
        width:30px; height:30px; border-radius:8px;
        background:var(--accent-soft); color:var(--accent);
        display:flex; align-items:center; justify-content:center;
        font-size:0.9rem; transition:background 0.2s, transform 0.2s;
    }
    .action-card:hover .ac-arrow { background:var(--accent); color:#fff; transform:translateX(3px); }
    .action-card.green .ac-arrow { background:var(--accent2-soft); color:var(--accent2); }
    .action-card.green:hover .ac-arrow { background:var(--accent2); color:#fff; }
    </style>

    <div class="dv-content dv-animate">

        <!-- Banner -->
        <div class="welcome-banner">
            <h2>Bienvenue sur la plateforme DVSSER</h2>
            <p>Direction de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte — Ministère de la Santé Publique de Madagascar</p>
            <i class="bi bi-shield-plus wb-icon"></i>
        </div>
	 <?php if (!empty($alertes_preview)): ?>
    <div class="epi-alert dv-animate dv-animate-d1">
        <div class="epi-alert-title">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Signal épidémique détecté — Districts actifs (6 derniers mois)
        </div>
        <div>
            <?php foreach ($alertes_preview as $a): ?>
            <span class="epi-district">
                <i class="bi bi-geo-alt-fill"></i>
                <?php echo htmlspecialchars($a['District']); ?>
                <strong>(<?php echo number_format((int)$a['total']); ?> unités)</strong>
            </span>
            <?php endforeach; ?>
            <a href="analyse.php" style="font-size:0.78rem;color:var(--accent);margin-left:8px;font-weight:600;">
                Voir l'analyse complète →
            </a>
        </div>
    </div>
    <?php endif; ?>
        <!-- Stats -->
        <p class="dv-section-title dv-animate dv-animate-d1">Vue d'ensemble</p>
        <div class="dv-stats-grid dv-animate dv-animate-d1">
            <div class="dv-stat primary">
                <div class="dv-stat-label"> <img src="medoc.png" alt="medoco icone" style = "width: 24px; height: 24px;"> Médicaments</div>
                <div class="dv-stat-sub">unités dans la base</div>
            </div>
            <div class="dv-stat success">
                <div class="dv-stat-label"> <img src="client.png" alt="client icone"style = "width: 24px; height: 24px;"> Clients</div>
                <div class="dv-stat-sub">enregistrés</div>
            </div>
            <div class="dv-stat info">
                <div class="dv-stat-label"><img src="type.png" alt="client icone"style = "width: 24px; height: 24px;"> Type de médicaments</div>
                <div class="dv-stat-sub">catégories médicaments</div>
            </div>
            <div class="dv-stat warning">
                <div class="dv-stat-label"><img src="agenda.png" alt="agenda icone"style = "width: 24px; height: 24px;">Période </div>
                <div class="dv-stat-sub">périodes couvertes</div>
            </div>
            <div class="dv-stat" style="border-left:3px solid #e53e3e;">
            <div class="dv-stat-label">Districts en alerte</div>
            <div class="dv-stat-value" style="color:#e53e3e;"><?php echo $nb_alertes ?: '—'; ?></div>
            <div class="dv-stat-sub">signal récent</div>
        </div>
        </div>

        <!-- Accès rapide -->
        <p class="dv-section-title dv-animate dv-animate-d2">Accès rapide</p>
        <div class="action-grid dv-animate dv-animate-d2">

            <a href="formulaire.php" class="action-card">
                <div class="ac-icon"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <div class="ac-title">Saisie de données</div>
                    <div class="ac-desc">Enregistrer un nouveau client et ses médicaments.</div>
                </div>
                <div class="ac-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

            <a href="visualisation_base.php" class="action-card green">
                <div class="ac-icon"><i class="bi bi-bar-chart-line"></i></div>
                <div>
                    <div class="ac-title">Visualisation graphique</div>
                    <div class="ac-desc">Graphiques des médicaments, clients et âges.</div>
                </div>
                <div class="ac-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>
			 <a href="analyse.php" class="action-card red">
            <div class="ac-icon"><i class="bi bi-activity"></i></div>
            <div><div class="ac-title">Analyse épidémiologique</div>
                 <div class="ac-desc">Carte de risque, alertes et prédictions par district.</div></div>
            <div class="ac-arrow"><i class="bi bi-arrow-right"></i></div>
        </a>

            <a href="visualisation_de_donnee.php" class="action-card">
                <div class="ac-icon"><i class="bi bi-speedometer2"></i></div>
                <div>
                    <div class="ac-title">Tableau de Bord</div>
                    <div class="ac-desc">Tableaux de synthèse avec recherche et tri.</div>
                </div>
                <div class="ac-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

            <a href="tdc.php" class="action-card green">
                <div class="ac-icon"><i class="bi bi-table"></i></div>
                <div>
                    <div class="ac-title">Tableau Croisé Dynamique</div>
                    <div class="ac-desc">Analyse pivot interactive des données.</div>
                </div>
                <div class="ac-arrow"><i class="bi bi-arrow-right"></i></div>
            </a>

        </div>

    </div>

<?php include 'footer_shared.php'; ?>
