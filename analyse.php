<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// Charger le moteur d'analyse
include 'medoc-risque.php';
// Variables disponibles : $analyse, $risque_districts, $alertes, $tendances, $par_district

// ── Statistiques globales ──────────────────────────────
$nb_alertes_critiques = count(array_filter($alertes, fn($a) => $a['niveau'] >= 4));
$nb_alertes_moderees  = count(array_filter($alertes, fn($a) => $a['niveau'] == 3));
$nb_districts_surveilles = count($risque_districts);
$district_plus_risque = !empty($alertes) ? $alertes[0]['district'] : '—';

// ── Agrégation par type pour sparklines ───────────────
$par_type_total = [];
foreach ($analyse as $r) {
    $t = $r['Type'];
    if (!isset($par_type_total[$t])) $par_type_total[$t] = 0;
    $par_type_total[$t] += (int)$r['Total'];
}
arsort($par_type_total);

// ── Tendance globale mensuelle (tous districts) ────────
$tendance_globale = [];
foreach ($tendances as $district => $mois_data) {
    foreach ($mois_data as $mois => $val) {
        if (!isset($tendance_globale[$mois])) $tendance_globale[$mois] = 0;
        $tendance_globale[$mois] += $val;
    }
}
ksort($tendance_globale);

$page_title  = 'Analyse épidémiologique';
$active_page = 'analyse';
$breadcrumb  = ['Accueil' => 'index.php', 'Analyse' => ''];
include 'sidebar.php';
?>

<!-- ══ Styles spécifiques à ce module ═══════════════════ -->
<style>
/* ── Carte ─────────────────────────────────────────────── */
#map {
   height: 520px;
    min-height: 300px;
    max-height: 90vh;
    resize: vertical;
    overflow: auto;
    border-radius: 14px;
    border: 1px solid var(--border);
    position: relative;
    z-index: 1;
    transition: height 0.25s ease;
}

/* Fix Leaflet z-index dans sidebar layout */
.leaflet-pane { z-index: 4 !important; }
.leaflet-top, .leaflet-bottom { z-index: 5 !important; }
.leaflet-control { z-index: 6 !important; }

/* ── Alertes ────────────────────────────────────────────── */
.alert-critical {
    background: linear-gradient(135deg, #fef2f2, #fff5f5);
    border: 1px solid #fca5a5;
    border-left: 4px solid #e53e3e;
}
.alert-warning {
    background: linear-gradient(135deg, #fffbeb, #fefce8);
    border: 1px solid #fcd34d;
    border-left: 4px solid #f59e0b;
}
.alert-moderate {
    background: linear-gradient(135deg, #fff7ed, #fef9ee);
    border: 1px solid #fdba74;
    border-left: 4px solid #ea580c;
}
[data-theme="dark"] .alert-critical {
    background: linear-gradient(135deg, #2d0808, #3a0a0a);
    border-color: #7f1d1d;
    border-left-color: #ef4444;
}
[data-theme="dark"] .alert-warning {
    background: linear-gradient(135deg, #2d2208, #332710);
    border-color: #78350f;
    border-left-color: #f59e0b;
}
[data-theme="dark"] .alert-moderate {
    background: linear-gradient(135deg, #2a1408, #301608);
    border-color: #7c2d12;
    border-left-color: #f97316;
}

.alert-item {
    border-radius: 12px;
    padding: 14px 18px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 12px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.alert-item:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }

.alert-icon {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.alert-icon.c5 { background: #fef2f2; color: #e53e3e; }
.alert-icon.c4 { background: #fff7ed; color: #ea580c; }
.alert-icon.c3 { background: #fffbeb; color: #d97706; }
[data-theme="dark"] .alert-icon.c5 { background: #3a0a0a; }
[data-theme="dark"] .alert-icon.c4 { background: #301608; }
[data-theme="dark"] .alert-icon.c3 { background: #332710; }

.alert-body { flex: 1; }
.alert-district { font-family:'Syne',sans-serif; font-weight:700; font-size:0.92rem; color:var(--text-primary); }
.alert-signal { font-size:0.8rem; color:var(--text-secondary); margin-top:3px; }
.alert-meta { display:flex; gap:10px; margin-top:6px; flex-wrap:wrap; }
.alert-tag {
    font-size:0.68rem; font-weight:600; padding:2px 9px; border-radius:20px;
    background:var(--accent-soft); color:var(--accent);
}
.alert-tag.danger { background:#fef2f2; color:#e53e3e; }
.alert-tag.warn   { background:#fffbeb; color:#d97706; }
.alert-tag.up     { background:#f0fdf4; color:#16a34a; }
[data-theme="dark"] .alert-tag.danger { background:#3a0a0a; }
[data-theme="dark"] .alert-tag.warn   { background:#332710; }
[data-theme="dark"] .alert-tag.up     { background:#0a2d15; }

/* ── Niveau de risque badge ─────────────────────────────── */
.risk-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 8px;
    font-family: 'Syne', sans-serif; font-weight: 800; font-size: 0.9rem;
    flex-shrink: 0;
}
.risk-1 { background:#f0fdf4; color:#16a34a; }
.risk-2 { background:#fefce8; color:#ca8a04; }
.risk-3 { background:#fff7ed; color:#ea580c; }
.risk-4 { background:#fef2f2; color:#dc2626; }
.risk-5 { background:#7f1d1d; color:#fff; }
[data-theme="dark"] .risk-1 { background:#0a2d15; }
[data-theme="dark"] .risk-2 { background:#332710; }
[data-theme="dark"] .risk-3 { background:#301608; }
[data-theme="dark"] .risk-4 { background:#3a0a0a; }

/* ── Légende carte ─────────────────────────────────────── */
.map-legend {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 0.78rem;
    color: var(--text-secondary);
    line-height: 2;
}
.legend-dot {
    display: inline-block;
    width: 16px; height: 16px;
    border-radius: 4px;
    margin-right: 6px;
    vertical-align: middle;
}

/* ── Filtres ───────────────────────────────────────────── */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 14px;
}
.filter-chip {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.filter-chip:hover, .filter-chip.active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

/* ── Tableau enrichi ───────────────────────────────────── */
.risk-bar-wrap { background: var(--border); border-radius: 4px; height: 6px; width: 80px; display:inline-block; vertical-align:middle; }
.risk-bar      { height: 6px; border-radius: 4px; transition: width 0.5s; }
.rb-1 { background: #16a34a; }
.rb-2 { background: #ca8a04; }
.rb-3 { background: #ea580c; }
.rb-4 { background: #dc2626; }
.rb-5 { background: #7f1d1d; }

/* ── Pulse animation pour alertes critiques ─────────────── */
@keyframes pulse-ring {
    0%   { box-shadow: 0 0 0 0 rgba(229,62,62,0.4); }
    70%  { box-shadow: 0 0 0 8px rgba(229,62,62,0); }
    100% { box-shadow: 0 0 0 0 rgba(229,62,62,0); }
}
.pulse { animation: pulse-ring 2s infinite; }

/* ── Sticky controls ───────────────────────────────────── */
.map-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
</style>

<div class="dv-content dv-animate">

    <!-- ══ En-tête ══════════════════════════════════════════ -->
    <div class="dv-page-header">
        <h1><i class="bi bi-activity" style="color:#e53e3e;margin-right:8px;"></i>Analyse épidémiologique prédictive</h1>
        <div class="dv-breadcrumb">
            <a href="index.php">Accueil</a>
            <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
            <span>Analyse</span>
        </div>
    </div>

    <!-- ══ Panneau d'alertes ═════════════════════════════════ -->
    <?php if (!empty($alertes)): ?>
    <div class="dv-card dv-animate dv-animate-d1" style="border-left:4px solid #e53e3e;">
        <div class="dv-card-header" style="color:#e53e3e;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Alertes épidémiques actives
            <span class="dv-badge" style="background:#fef2f2;color:#e53e3e;margin-left:auto;"><?php echo count($alertes); ?> district(s)</span>
        </div>

        <?php foreach (array_slice($alertes, 0, 6) as $al):
            $cls = $al['niveau'] >= 5 ? 'alert-critical' : ($al['niveau'] >= 4 ? 'alert-moderate' : 'alert-warning');
            $icls = 'c' . $al['niveau'];
            $icon = $al['niveau'] >= 5 ? 'bi-exclamation-octagon-fill' : ($al['niveau'] >= 4 ? 'bi-exclamation-triangle-fill' : 'bi-exclamation-circle-fill');
        ?>
        <div class="alert-item <?php echo $cls; ?> <?php echo $al['niveau'] >= 5 ? 'pulse' : ''; ?>">
            <div class="alert-icon <?php echo $icls; ?>">
                <i class="bi <?php echo $icon; ?>"></i>
            </div>
            <div class="alert-body">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div class="alert-district"><?php echo htmlspecialchars($al['district']); ?></div>
                    <div class="risk-badge risk-<?php echo $al['niveau']; ?>">N<?php echo $al['niveau']; ?></div>
                </div>
                <div class="alert-signal">
                    <i class="bi bi-virus2"></i>
                    <strong><?php echo htmlspecialchars($al['signal']); ?></strong>
                    — médicament dominant : <em><?php echo htmlspecialchars($al['type_dominant']); ?></em>
                </div>
                <div class="alert-meta">
                    <span class="alert-tag <?php echo $al['niveau'] >= 4 ? 'danger' : 'warn'; ?>">
                        <i class="bi bi-bar-chart-fill"></i> Score <?php echo $al['score']; ?>
                    </span>
                    <span class="alert-tag">
                        <i class="bi bi-capsule"></i> <?php echo $al['total']; ?> unités
                    </span>
                    <span class="alert-tag">
                        <i class="bi bi-tags"></i> <?php echo $al['diversite']; ?> types
                    </span>
                    <?php if ($al['tendance'] === 'En hausse'): ?>
                    <span class="alert-tag up">
                        <i class="bi bi-arrow-up-right"></i> Tendance en hausse
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (count($alertes) > 6): ?>
        <div style="text-align:center;margin-top:8px;">
            <button class="dv-btn dv-btn-outline" style="font-size:0.8rem;" onclick="document.getElementById('all-alerts').style.display='block';this.style.display='none';">
                <i class="bi bi-chevron-down"></i> Voir <?php echo count($alertes)-6; ?> alerte(s) supplémentaire(s)
            </button>
            <div id="all-alerts" style="display:none;">
                <?php foreach (array_slice($alertes, 6) as $al):
                    $cls = $al['niveau'] >= 5 ? 'alert-critical' : ($al['niveau'] >= 4 ? 'alert-moderate' : 'alert-warning');
                    $icls = 'c'.$al['niveau'];
                ?>
                <div class="alert-item <?php echo $cls; ?>" style="margin-top:12px;">
                    <div class="alert-icon <?php echo $icls; ?>"><i class="bi bi-exclamation-circle-fill"></i></div>
                    <div class="alert-body">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="alert-district"><?php echo htmlspecialchars($al['district']); ?></div>
                            <div class="risk-badge risk-<?php echo $al['niveau']; ?>">N<?php echo $al['niveau']; ?></div>
                        </div>
                        <div class="alert-signal"><?php echo htmlspecialchars($al['signal']); ?> — <?php echo htmlspecialchars($al['type_dominant']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ Stats rapides ════════════════════════════════════ -->
    <div class="dv-stats-grid dv-animate dv-animate-d1">
        <div class="dv-stat" style="border-left:3px solid #e53e3e;">
            <div class="dv-stat-label">Alertes critiques</div>
            <div class="dv-stat-value" style="color:#e53e3e;"><?php echo $nb_alertes_critiques; ?></div>
            <div class="dv-stat-sub">Niveau 4–5</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #f59e0b;">
            <div class="dv-stat-label">Alertes modérées</div>
            <div class="dv-stat-value" style="color:#f59e0b;"><?php echo $nb_alertes_moderees; ?></div>
            <div class="dv-stat-sub">Niveau 3</div>
        </div>
        <div class="dv-stat primary">
            <div class="dv-stat-label">Districts surveillés</div>
            <div class="dv-stat-value"><?php echo $nb_districts_surveilles; ?></div>
            <div class="dv-stat-sub">avec données</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #7c3aed;">
            <div class="dv-stat-label">District le plus à risque</div>
            <div class="dv-stat-value" style="font-size:1rem;color:#7c3aed;"><?php echo htmlspecialchars($district_plus_risque); ?></div>
            <div class="dv-stat-sub">score le plus élevé</div>
        </div>
    </div>

    <!-- ══ Carte choroplèthe ═════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d2">
        <div class="dv-card-header">
            <i class="bi bi-map"></i> Carte de risque épidémique — Districts de Madagascar
           <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;" onclick="setMapHeight(400)">
                        Petite
                    </button>

                    <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;" onclick="setMapHeight(520)">
                        Moyenne
                    </button>

                    <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;" onclick="setMapHeight(700)">
                        Grande
                    </button>

                    <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;" onclick="toggleFullscreenMap()">
                        <i class="bi bi-arrows-fullscreen"></i> Plein écran
                    </button>

                    <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;" onclick="resetMap()">
                        Recentrer
                    </button>
                </div>
        </div>

        <div class="map-controls">
            <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">Filtrer :</span>
            <button class="filter-chip active" onclick="filtrerCarte('tous',this)">Tous types</button>
            <button class="filter-chip" onclick="filtrerCarte('Antipyrétique',this)">Antipyrétique</button>
            <button class="filter-chip" onclick="filtrerCarte('Antidiarrhéique',this)">Antidiarrhéique</button>
            <button class="filter-chip" onclick="filtrerCarte('Antibiotique',this)">Antibiotique</button>
            <button class="filter-chip" onclick="filtrerCarte('Antalgique',this)">Antalgique</button>
        </div>

        <div id="map"></div>

        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:14px;align-items:flex-start;">
            <div class="map-legend">
                <div style="font-weight:700;margin-bottom:6px;color:var(--text-primary);">Niveau de risque</div>
                <div><span class="legend-dot" style="background:#a8d5b5;"></span> Niveau 1 — Faible</div>
                <div><span class="legend-dot" style="background:#ffffcc;"></span> Niveau 2 — Surveillé</div>
                <div><span class="legend-dot" style="background:#feb24c;"></span> Niveau 3 — Modéré</div>
                <div><span class="legend-dot" style="background:#f03b20;"></span> Niveau 4 — Élevé</div>
                <div><span class="legend-dot" style="background:#7d0025;"></span> Niveau 5 — Critique</div>
                <div><span class="legend-dot" style="background:#d4d4d4;"></span> Aucune donnée</div>
            </div>
            <div class="map-legend" style="flex:1;min-width:200px;">
                <div style="font-weight:700;margin-bottom:6px;color:var(--text-primary);">Algorithme de prédiction</div>
                <div style="font-size:0.75rem;line-height:1.7;color:var(--text-muted);">
                    Score = Σ(Ventes × Poids clinique) × Bonus diversité × Bonus tendance<br>
                    <strong style="color:var(--text-secondary);">Poids :</strong>
                    Antipyrétique ×3, Antidiarrhéique ×2.8,<br>
                    Antibiotique ×2, Antalgique ×1.2, VitamineC ×0.5<br>
                    <strong style="color:var(--text-secondary);">Tendance :</strong>
                    Hausse &gt;50% → ×1.5 | Hausse &gt;20% → ×1.25
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Tendance globale Chart.js ════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;" class="dv-animate dv-animate-d2">

        <div class="dv-card">
            <div class="dv-card-header">
                <i class="bi bi-graph-up-arrow"></i> Évolution mensuelle des ventes
            </div>
            <canvas id="chartTendance" height="180"></canvas>
        </div>

        <div class="dv-card">
            <div class="dv-card-header">
                <i class="bi bi-pie-chart-fill"></i> Répartition par type de médicament
            </div>
            <canvas id="chartTypes" height="180"></canvas>
        </div>

    </div>

    <!-- ══ Tableau détaillé ══════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d3">
        <div class="dv-card-header">
            <i class="bi bi-table"></i> Données détaillées par district
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button class="dv-btn dv-btn-outline" style="padding:5px 12px;font-size:0.78rem;" onclick="exportExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
                <button class="dv-btn dv-btn-outline" style="padding:5px 12px;font-size:0.78rem;" onclick="exportPDF()">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
            </div>
        </div>

        <div class="filter-bar">
            <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">Filtrer :</span>
            <button class="filter-chip active" id="ft-tous" onclick="filtrerTableau('tous')">Tous</button>
            <?php foreach (array_keys($par_type_total) as $t): ?>
            <button class="filter-chip" id="ft-<?php echo htmlspecialchars(str_replace(['é','è','ê','à','ç',' '],['e','e','e','a','c','-'],$t)); ?>"
                    onclick="filtrerTableau('<?php echo htmlspecialchars($t, ENT_QUOTES); ?>')">
                <?php echo htmlspecialchars($t); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="dv-table-wrap">
            <table class="dv-table" id="analyse-table">
                <thead>
                    <tr>
                        <th>District</th>
                        <th>Type médicament</th>
                        <th>Total unités</th>
                        <th>Nb ventes</th>
                        <th>Dernier achat</th>
                        <th>Niveau risque</th>
                        <th>Barre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyse as $row):
                        $district = $row['District'];
                        $niv = $risque_districts[$district] ?? 1;
                        $type_clean = htmlspecialchars(str_replace(['é','è','ê','à','ç',' '],['e','e','e','a','c','-'],$row['Type']));
                    ?>
                    <tr data-district="<?php echo htmlspecialchars($district); ?>"
                        data-type="<?php echo htmlspecialchars($row['Type']); ?>"
                        data-total="<?php echo (int)$row['Total']; ?>">
                        <td style="font-weight:600;">
                            <?php echo htmlspecialchars($district); ?>
                        </td>
                        <td>
                            <span class="dv-badge dv-badge-primary"><?php echo htmlspecialchars($row['Type']); ?></span>
                        </td>
                        <td style="font-family:'Syne',sans-serif;font-weight:700;">
                            <?php echo number_format((int)$row['Total']); ?>
                        </td>
                        <td><?php echo (int)$row['NbVentes']; ?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);">
                            <?php echo $row['DernierAchat'] ? date('d/m/Y', strtotime($row['DernierAchat'])) : '—'; ?>
                        </td>
                        <td>
                            <div class="risk-badge risk-<?php echo $niv; ?>">N<?php echo $niv; ?></div>
                        </td>
                        <td>
                            <div class="risk-bar-wrap">
                                <div class="risk-bar rb-<?php echo $niv; ?>" style="width:<?php echo ($niv*20); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.dv-content -->

<!-- ══ Scripts ═══════════════════════════════════════════ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// ════════════════════════════════════════════════════════
// DONNÉES PHP → JS
// ════════════════════════════════════════════════════════
const risqueData = <?php echo json_encode($risque_districts, JSON_UNESCAPED_UNICODE); ?>;

const tendanceGlobale = {
    labels: <?php echo json_encode(array_keys($tendance_globale)); ?>,
    values: <?php echo json_encode(array_values($tendance_globale)); ?>
};

const parType = {
    labels: <?php echo json_encode(array_keys($par_type_total)); ?>,
    values: <?php echo json_encode(array_values($par_type_total)); ?>
};

const analyseRows = <?php
    $rows_js = [];
    foreach ($analyse as $r) {
        $rows_js[] = [
            'district' => $r['District'],
            'type'     => $r['Type'],
            'total'    => (int)$r['Total'],
        ];
    }
    echo json_encode($rows_js, JSON_UNESCAPED_UNICODE);
?>;

// ════════════════════════════════════════════════════════
// CARTE LEAFLET
// ════════════════════════════════════════════════════════
const RISK_COLORS = {
    0: '#d4d4d4',
    1: '#a8d5b5',
    2: '#ffffcc',
    3: '#feb24c',
    4: '#f03b20',
    5: '#7d0025'
};

let map = L.map('map').setView([-18.879, 47.508], 6);
let currentLayer = null;
let geojsonData = null;
let currentFilter = 'tous';

// Tuiles sombres/claires selon thème
function getTileUrl() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return dark
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
}

let tileLayer = L.tileLayer(getTileUrl(), {
    attribution: '&copy; <a href="https://openstreetmap.org">OSM</a> &copy; <a href="https://carto.com">CARTO</a>',
    maxZoom: 10
}).addTo(map);

// Recalculer le risque par type de médicament filtré
function getRisqueParType(districtName, type) {
    let total = 0;
    analyseRows.forEach(r => {
        if (r.district === districtName && (type === 'tous' || r.type === type)) {
            total += r.total;
        }
    });
    if (total === 0) return 0;
    const poids = {
        'Antipyrétique':   3.0,
        'Antidiarrhéique': 2.8,
        'Antibiotique':    2.0,
        'Antalgique':      1.2,
        'VitamineC':       0.5
    };
    const p = type !== 'tous' ? (poids[type] || 1.0) : 1.0;
    const score = total * p;
    if      (score >= 200) return 5;
    else if (score >= 100) return 4;
    else if (score >= 50)  return 3;
    else if (score >= 20)  return 2;
    else if (score > 0)    return 1;
    return 0;
}

function styleFeature(feature) {
    const name  = feature.properties?.NAME_3 || feature.properties?.District || '';
    const niveau = currentFilter === 'tous'
        ? (risqueData[name] || 0)
        : getRisqueParType(name, currentFilter);
    return {
        color: '#555',
        weight: 0.7,
        fillColor: RISK_COLORS[niveau] || '#d4d4d4',
        fillOpacity: niveau > 0 ? 0.75 : 0.35
    };
}

function buildPopup(feature) {
    const name   = feature.properties?.NAME_3 || feature.properties?.District || 'Inconnu';
    const niveau = currentFilter === 'tous'
        ? (risqueData[name] || 0)
        : getRisqueParType(name, currentFilter);

    // Données détaillées pour ce district
    const rows = analyseRows.filter(r => r.district === name);
    let detail = '';
    if (rows.length > 0) {
        detail = '<table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:11px;">'
               + '<tr style="background:#f5f5f5;"><th style="padding:3px 6px;text-align:left;">Type</th><th style="padding:3px 6px;">Unités</th></tr>';
        rows.forEach(r => {
            detail += `<tr><td style="padding:3px 6px;">${r.type}</td><td style="padding:3px 6px;text-align:center;font-weight:bold;">${r.total}</td></tr>`;
        });
        detail += '</table>';
    } else {
        detail = '<p style="color:#888;font-size:11px;margin-top:6px;">Aucune donnée disponible</p>';
    }

    const levels = ['—','Faible','Surveillé','Modéré ⚠️','Élevé 🔴','Critique 🚨'];
    return `<div style="min-width:180px;">
        <div style="font-weight:bold;font-size:13px;margin-bottom:4px;">${name}</div>
        <div style="font-size:11px;color:#666;margin-bottom:6px;">
            Risque : <strong style="color:${niveau>=4?'#e53e3e':niveau>=3?'#ea580c':'#ca8a04'}">${levels[niveau]||'—'}</strong>
            (Niveau ${niveau}/5)
        </div>
        ${detail}
    </div>`;
}

function renderMap() {
    if (currentLayer) map.removeLayer(currentLayer);
    if (!geojsonData) return;
    currentLayer = L.geoJSON(geojsonData, {
        style: styleFeature,
        onEachFeature: (feature, layer) => {
            layer.bindPopup(buildPopup(feature));
            layer.on('mouseover', function(e) {
                this.setStyle({ weight: 2.5, color: '#0a6cbc', fillOpacity: 0.9 });
                this.bringToFront();
            });
            layer.on('mouseout', function(e) {
                currentLayer.resetStyle(this);
            });
        }
    }).addTo(map);
}

function resetMap() {
    map.setView([-18.879, 47.508], 6);
}

// Charger le GeoJSON
fetch('Districts.geojson')
    .then(r => r.json())
    .then(data => {
        geojsonData = data;
        renderMap();
    })
    .catch(() => {
        document.getElementById('map').innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:12px;color:var(--text-muted);">'
            + '<i class="bi bi-map" style="font-size:3rem;opacity:0.3;"></i>'
            + '<p style="font-size:0.85rem;">Fichier <code>Districts.geojson</code> introuvable.<br>'
            + 'Placez le fichier GeoJSON des districts de Madagascar<br>dans le même dossier que ce script.</p>'
            + '<a href="https://data.humdata.org/dataset/cod-ab-mdg" target="_blank" class="dv-btn dv-btn-outline" style="font-size:0.78rem;padding:6px 14px;">'
            + '<i class="bi bi-download"></i> Télécharger Districts.geojson</a></div>';
    });
                function setMapHeight(height){
                    const mapDiv = document.getElementById('map');
                    mapDiv.style.height = height + 'px';

                    setTimeout(() => {
                        map.invalidateSize();
                    }, 200);
                }

                function toggleFullscreenMap(){
                    const mapDiv = document.getElementById('map');

                    if(mapDiv.style.height !== '90vh'){
                        mapDiv.dataset.oldHeight = mapDiv.style.height || '520px';
                        mapDiv.style.height = '90vh';
                    }else{
                        mapDiv.style.height = mapDiv.dataset.oldHeight || '520px';
                    }

                    setTimeout(() => {
                        map.invalidateSize();
                    }, 200);
                }
                // Sync tuiles avec thème
                const themeBtn = document.getElementById('dvThemeBtn');
                if (themeBtn) {
                    themeBtn.addEventListener('click', () => {
                        setTimeout(() => {
                            map.removeLayer(tileLayer);
                            tileLayer = L.tileLayer(getTileUrl(), {
                                attribution: '&copy; OSM &copy; CARTO', maxZoom: 10
                            }).addTo(map);
                            renderMap();
                        }, 150);
                    });
                }

// Filtre carte
function filtrerCarte(type, btn) {
    currentFilter = type;
    document.querySelectorAll('.map-controls .filter-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderMap();
}

// ════════════════════════════════════════════════════════
// CHART.JS
// ════════════════════════════════════════════════════════
const accent  = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#0a6cbc';
const accent2 = getComputedStyle(document.documentElement).getPropertyValue('--accent2').trim() || '#00b894';

// Tendance mensuelle
const ctxTendance = document.getElementById('chartTendance').getContext('2d');
new Chart(ctxTendance, {
    type: 'line',
    data: {
        labels: tendanceGlobale.labels,
        datasets: [{
            label: 'Unités dispensées',
            data: tendanceGlobale.values,
            borderColor: '#e53e3e',
            backgroundColor: 'rgba(229,62,62,0.12)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#e53e3e',
            pointRadius: 5,
            pointHoverRadius: 7,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Unités' } },
            x: { title: { display: true, text: 'Mois' } }
        }
    }
});

// Répartition par type
const ctxTypes = document.getElementById('chartTypes').getContext('2d');
new Chart(ctxTypes, {
    type: 'doughnut',
    data: {
        labels: parType.labels,
        datasets: [{
            data: parType.values,
            backgroundColor: ['#0a6cbc','#00b894','#f59e0b','#e53e3e','#7c3aed','#06b6d4'],
            borderWidth: 2,
            borderColor: 'var(--bg-card)'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right', labels: { font: { size: 11 } } }
        }
    }
});

// ════════════════════════════════════════════════════════
// FILTRE TABLEAU
// ════════════════════════════════════════════════════════
function filtrerTableau(type) {
    document.querySelectorAll('.filter-bar .filter-chip').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('#analyse-table tbody tr').forEach(row => {
        if (type === 'tous' || row.dataset.type === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    // Active le bon chip
    if (type === 'tous') {
        document.getElementById('ft-tous')?.classList.add('active');
    } else {
        const slug = type.replace(/[éèê]/g,'e').replace(/[àâ]/g,'a').replace(/[ç]/g,'c').replace(/ /g,'-');
        document.getElementById('ft-'+slug)?.classList.add('active');
    }
}

// DataTables
document.addEventListener('DOMContentLoaded', function() {
    try {
        new simpleDatatables.DataTable('#analyse-table', {
            searchable: true,
            sortable: true,
            perPage: 10,
            labels: {
                placeholder: 'Rechercher…',
                perPage: 'Lignes',
                noRows: 'Aucune donnée',
                info: 'Page {page}/{pages}'
            }
        });
    } catch(e) { console.warn('DataTable init:', e); }
});

// ════════════════════════════════════════════════════════
// EXPORT
// ════════════════════════════════════════════════════════
function exportExcel() {
    try {
        const wb = XLSX.utils.table_to_book(document.getElementById('analyse-table'), { sheet: 'Analyse Épidémique' });
        XLSX.writeFile(wb, 'analyse_epidemique_dvsser.xlsx');
    } catch(e) { alert('Erreur export Excel : ' + e.message); }
}

async function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.text('DVSSER — Analyse épidémiologique prédictive', 14, 16);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.text('Ministère de la Santé Publique de Madagascar — ' + new Date().toLocaleDateString('fr-FR'), 14, 23);
    doc.setTextColor(0);

    // Alertes
    let y = 32;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('Alertes actives :', 14, y); y += 7;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);

    <?php foreach (array_slice($alertes, 0, 8) as $al): ?>
    doc.text('• <?php echo addslashes($al['district']); ?> — Niveau <?php echo $al['niveau']; ?> — <?php echo addslashes($al['signal']); ?>', 16, y);
    y += 6;
    <?php endforeach; ?>

    // Tableau
    y += 4;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('District', 14, y); doc.text('Type', 80, y); doc.text('Total', 160, y); doc.text('Risque', 200, y);
    y += 5;
    doc.setLineWidth(0.3);
    doc.line(14, y, 285, y); y += 4;
    doc.setFont('helvetica', 'normal');

    document.querySelectorAll('#analyse-table tbody tr:not([style*="none"])').forEach(row => {
        if (y > 190) { doc.addPage(); y = 20; }
        const cells = row.querySelectorAll('td');
        if (cells.length >= 4) {
            doc.text(cells[0].innerText.substring(0,30), 14, y);
            doc.text(cells[1].innerText.substring(0,25), 80, y);
            doc.text(cells[2].innerText, 160, y);
            doc.text(cells[5].innerText, 200, y);
            y += 6;
        }
    });

    doc.save('analyse_epidemique_dvsser.pdf');
}
</script>

<?php include 'footer_shared.php'; ?>
