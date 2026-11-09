<?php
/**
 * _layout.php — en-tête + barre latérale (inclus par les pages). Variables attendues :
 * $page_title, $active_page, $breadcrumb (facultatif), $ctx (facultatif, pour le bandeau de fraîcheur des données)
 */
use Pharmaco\Auth\Auth;
$active_page = $active_page ?? 'index';
$page_title  = $page_title ?? 'DVSSER';
$uname = Auth::user() ?? 'Utilisateur';
$nav = [
    'index'     => ['index.php',     'bi-speedometer2', 'Tableau de bord'],
    'situation' => ['situation.php', 'bi-bar-chart-line', 'Analyse épidémiologique'],
    'carte'     => ['carte.php',     'bi-map', 'Carte des districts'],
    'rapport'   => ['rapport.php',   'bi-file-earmark-slides', 'Point de situation (PDF / PPT) ★'],
    'sitrep'    => ['sitrep.php',    'bi-file-earmark-word', 'SitRep hebdo (PDF / Word) ★'],
    'alertes'   => ['alertes.php',   'bi-exclamation-triangle', 'Alertes & qualité des données'],
];
$zones = ['index' => 'home', 'situation' => 'std', 'carte' => 'std', 'alertes' => 'std', 'rapport' => 'premium', 'sitrep' => 'premium'];
$nav = array_filter($nav, fn($k) => Auth::allows($zones[$k] ?? 'std'), ARRAY_FILTER_USE_KEY);
if (Auth::can('VIP')) {
    if (!\Pharmaco\Billing\PaymentService::isActive((string) Auth::user())) { $nav = []; }          // sans abonnement : seul le paiement est accessible
    $nav['paiement'] = ['paiement.php', 'bi-star', 'Abonnement VIP'];
}
$admin_nav = [];
if (Auth::can('Super Admin')) {
    $np = \Pharmaco\Billing\PaymentService::pendingCount();
    $admin_nav = ['utilisateurs' => ['utilisateurs.php', 'bi-people', 'Utilisateurs'], 'paiements' => ['paiements.php', 'bi-cash-coin', 'Paiements MVola' . ($np ? ' (' . $np . ')' : '')]];
}
function fr($n, int $d = 0): string { return $n === null ? '—' : number_format((float) $n, $d, ',', ' '); }
function json_js($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR); }
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> — DVSSER</title>
    <link rel="icon" href="sary/Logo.png" type="image/png">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="dvsser-theme.css">
    <style>
      .grid{display:grid;gap:16px}.g2{grid-template-columns:repeat(2,minmax(0,1fr))}.g3{grid-template-columns:repeat(3,minmax(0,1fr))}.g4{grid-template-columns:repeat(4,minmax(0,1fr))}
      @media(max-width:1000px){.g3,.g4{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.g2,.g3,.g4{grid-template-columns:1fr}}
      .chart-box{position:relative;height:300px}.chart-box.tall{height:380px}
      .lvl{display:inline-block;padding:2px 10px;border-radius:99px;font-size:.75rem;font-weight:600;color:#fff}
      .lvl3{background:#e53e3e}.lvl2{background:#f59e0b}.lvl1{background:#3b82f6}
      .filters{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:16px}
      .filters label{font-size:.75rem;color:var(--text-muted);display:block;margin-bottom:3px}
      .dv-logo-text{display:flex;flex-direction:column;line-height:1.15}.muted{color:var(--text-muted);font-size:.8rem}.num{text-align:right;font-variant-numeric:tabular-nums}
      .bar{height:8px;border-radius:4px;background:var(--accent-soft);overflow:hidden}.bar>i{display:block;height:100%;background:var(--accent)}
    </style>
</head>
<body>
<div class="dv-overlay" id="dvOverlay"></div>
<aside class="dv-sidebar" id="dvSidebar">
    <div class="dv-sidebar-logo">
        <img src="sary/icone1.png" alt="DVSSER">
        <div class="dv-logo-text"><span class="app-name">DVSSER</span><span class="app-sub">Surveillance Mpox</span></div>
    </div>
    <div class="dv-sidebar-section">
        <div class="dv-sidebar-label">Analyse</div>
        <ul class="dv-nav">
            <?php foreach ($nav as $key => [$href, $icon, $label]): ?>
            <li class="<?= $active_page === $key ? 'active' : '' ?>"><a href="<?= h($href) ?>"><i class="bi <?= h($icon) ?> nav-icon"></i> <?= h($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($admin_nav): ?>
    <div class="dv-sidebar-section">
        <div class="dv-sidebar-label">Administration</div>
        <ul class="dv-nav">
            <?php foreach ($admin_nav as $key => [$href, $icon, $label]): ?>
            <li class="<?= $active_page === $key ? 'active' : '' ?>"><a href="<?= h($href) ?>"><i class="bi <?= h($icon) ?> nav-icon"></i> <?= h($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <div class="dv-sidebar-footer">
        <div class="dv-user-box">
            <div class="dv-avatar"><?= h(strtoupper(substr($uname, 0, 2))) ?></div>
            <div><div class="dv-user-name"><?= h($uname) ?></div><div class="dv-user-role"><?= h(Auth::role()) ?></div></div>
            <a href="logout.php" class="dv-logout-btn" title="Déconnexion"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>
<div class="dv-main">
    <header class="dv-topbar">
        <div class="dv-topbar-left">
            <button class="dv-hamburger" id="dvHamburger"><i class="bi bi-list"></i></button>
            <span class="dv-page-title"><?= h($page_title) ?></span>
        </div>
        <div class="dv-topbar-right">
            <?php if (!empty($ctx['full'])): $m = $ctx['full']->meta; ?>
              <span class="muted" title="Source : Google Sheet (lecture seule)">
                <i class="bi bi-cloud-check"></i> Données du <?= h(date('d/m/Y H:i', (int) $m['fetched_at'])) ?><?= !empty($m['stale']) ? ' <b style="color:#e53e3e">(cache périmé)</b>' : '' ?>
              </span>
              <?php if (Auth::can('Super Admin', 'Analyste')): ?>
              <form method="post" action="actualiser.php" style="display:inline"><?= Auth::csrfField() ?><input type="hidden" name="back" value="<?= h(basename($_SERVER['PHP_SELF'])) ?>">
                <button class="dv-theme-btn" title="Actualiser depuis le Google Sheet"><i class="bi bi-arrow-clockwise"></i></button></form>
              <?php endif; ?>
            <?php endif; ?>
            <button class="dv-theme-btn" id="dvThemeBtn" title="Changer le thème"><i class="bi bi-moon-stars" id="dvThemeIcon"></i></button>
        </div>
    </header>
    <div class="dv-content dv-animate">
<?php if (!empty($ctx['error'])): ?>
    <div class="dv-alert dv-alert-error"><i class="bi bi-exclamation-octagon"></i> <?= h($ctx['error']) ?></div>
<?php endif; ?>
