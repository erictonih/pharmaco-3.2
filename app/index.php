<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, EpiAnalyticsService, OutbreakAlertService};
Auth::requireLogin('home');

$ctx = ServiceFactory::context($_GET);
$page_title = 'Tableau de bord'; $active_page = 'index';
include __DIR__ . '/_layout.php';

if ($ctx['ds']):
    $epi = new EpiAnalyticsService($ctx['ds']);
    $ov = $epi->overview(); $ts = $epi->timeSeries(); $geo = $epi->geography();
    $al = (new OutbreakAlertService($ctx['full']))->districtAlerts();   // les alertes se calculent toujours sur l'ensemble des données
    $top = array_slice($geo['districts_28d'], 0, 10, true);
    $t = $ov['trend'];
?>
<div class="dv-page-header"><h1><i class="bi bi-virus" style="color:var(--accent)"></i> Situation Mpox — Madagascar</h1>
  <p class="muted">Données de notification du <?= h($ov['first_date'] ?? '—') ?> au <?= h($ov['last_date'] ?? '—') ?> · analyse calculée en direct depuis le Google Sheet</p></div>

<?php if ($al['summary']['critical'] + $al['summary']['high'] > 0): ?>
<div class="dv-alert dv-alert-warning"><i class="bi bi-exclamation-triangle-fill"></i>
  <b><?= $al['summary']['critical'] ?> foyer(s) critique(s), <?= $al['summary']['high'] ?> élevé(s)</b> sur les <?= OutbreakAlertService::RECENT_DAYS ?> derniers jours —
  <?= h(implode(', ', array_map(fn($a) => $a['district'], array_slice(array_filter($al['alerts'], fn($a) => $a['level'] >= 2), 0, 4)))) ?>.
  <a href="alertes.php">Voir les alertes</a></div>
<?php endif; ?>

<div class="grid g4 dv-stats-grid" style="margin-bottom:16px">
  <div class="dv-stat"><div class="dv-stat-label">Cas notifiés</div><div class="dv-stat-value"><?= fr($ov['total']) ?></div><div class="dv-stat-sub"><?= fr($ov['districts']) ?> districts · <?= fr($ov['regions']) ?> régions</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Cas confirmés</div><div class="dv-stat-value" style="color:#e53e3e"><?= fr($ov['status']['confirmed']) ?></div><div class="dv-stat-sub">positivité <?= fr($ov['positivity'], 1) ?> %</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Décès</div><div class="dv-stat-value"><?= fr($ov['deaths']) ?></div><div class="dv-stat-sub">létalité (confirmés) <?= fr($ov['cfr_confirmed'], 1) ?> %</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Tendance 7 jours</div><div class="dv-stat-value"><?= $t ? fr($t['current_7d']) : '—' ?></div>
    <div class="dv-stat-sub"><?= $t ? ('vs ' . fr($t['previous_7d']) . ' la semaine d\'avant' . ($t['change_pct'] !== null ? ' (' . ($t['change_pct'] > 0 ? '+' : '') . fr($t['change_pct']) . ' %)' : '')) : '' ?></div></div>
  <div class="dv-stat"><div class="dv-stat-label">Hospitalisés</div><div class="dv-stat-value"><?= fr($ov['hospitalised']) ?></div><div class="dv-stat-sub"><?= fr($ov['hospitalisation_rate'], 1) ?> % des cas · <?= fr($ov['icu']) ?> en soins intensifs</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Prélèvements</div><div class="dv-stat-value"><?= fr($ov['sampling_rate'], 1) ?> %</div><div class="dv-stat-sub"><?= fr($ov['sampled']) ?> cas prélevés</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Âge médian</div><div class="dv-stat-value"><?= fr($ov['age_median'], 0) ?></div><div class="dv-stat-sub">moyenne <?= fr($ov['age_mean'], 1) ?> ans</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Suspects / probables</div><div class="dv-stat-value"><?= fr($ov['status']['suspect'] + $ov['status']['probable']) ?></div><div class="dv-stat-sub"><?= fr($ov['status']['negative']) ?> négatifs</div></div>
</div>

<div class="dv-card" style="margin-bottom:16px"><div class="dv-card-header"><i class="bi bi-graph-up"></i> Courbe épidémique hebdomadaire (semaines ISO)</div>
  <div class="chart-box tall"><canvas id="cEpi"></canvas></div></div>

<div class="grid g2" style="margin-bottom:16px">
  <div class="dv-card"><div class="dv-card-header"><i class="bi bi-geo-alt"></i> Districts les plus touchés (28 derniers jours)</div><div class="chart-box"><canvas id="cTop"></canvas></div></div>
  <div class="dv-card"><div class="dv-card-header"><i class="bi bi-pie-chart"></i> Classification des cas</div><div class="chart-box"><canvas id="cStatus"></canvas></div></div>
</div>

<?php if ($al['alerts']): ?>
<div class="dv-card"><div class="dv-card-header"><i class="bi bi-exclamation-triangle"></i> Alertes en cours (14 jours jusqu'au <?= h($al['reference_date']) ?>)</div>
  <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Niveau</th><th>District</th><th>Région</th><th class="num">Cas récents</th><th class="num">Attendus</th><th class="num">Décès</th></tr></thead><tbody>
  <?php foreach (array_slice($al['alerts'], 0, 5) as $a): ?>
    <tr><td><span class="lvl lvl<?= $a['level'] ?>"><?= ['', 'Surveillance', 'Élevé', 'Critique'][$a['level']] ?></span><?= $a['is_new'] ? ' <span class="dv-badge dv-badge-primary">nouveau</span>' : '' ?></td>
    <td><?= h($a['district']) ?></td><td><?= h($a['region']) ?></td><td class="num"><?= $a['recent'] ?></td><td class="num"><?= fr($a['expected'], 1) ?></td><td class="num"><?= $a['recent_deaths'] ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>

<script src="vendor/chart.umd.js"></script>
<script>
const W=<?= json_js($ts['weeks']) ?>, TOP=<?= json_js($top) ?>, ST=<?= json_js($ov['status']) ?>;
const css=n=>getComputedStyle(document.documentElement).getPropertyValue(n).trim();
Chart.defaults.font.family="'DM Sans',sans-serif";
function theme(){Chart.defaults.color=css('--text-secondary');Chart.defaults.borderColor=css('--border');}
theme();
new Chart(cEpi,{data:{labels:W.labels,datasets:[
 {type:'bar',label:'Cas notifiés',data:W.total,backgroundColor:'#93c5fd'},
 {type:'bar',label:'Confirmés',data:W.confirmed,backgroundColor:'#e53e3e'},
 {type:'line',label:'Moyenne mobile 4 sem.',data:W.moving_avg_4w,borderColor:'#0a6cbc',tension:.3,pointRadius:0,borderWidth:2}]},
 options:{responsive:true,maintainAspectRatio:false,scales:{x:{ticks:{maxTicksLimit:14}},y:{beginAtZero:true,ticks:{precision:0}}},plugins:{legend:{position:'bottom'}}}});
new Chart(cTop,{type:'bar',data:{labels:Object.keys(TOP),datasets:[{label:'Cas',data:Object.values(TOP).map(v=>v.total),backgroundColor:'#0a6cbc'},{label:'Confirmés',data:Object.values(TOP).map(v=>v.confirmed),backgroundColor:'#e53e3e'}]},
 options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,scales:{x:{beginAtZero:true,ticks:{precision:0}}},plugins:{legend:{position:'bottom'}}}});
const L={confirmed:'Confirmés',probable:'Probables',suspect:'Suspects',negative:'Négatifs',unknown:'Non classés'},C={confirmed:'#e53e3e',probable:'#f59e0b',suspect:'#3b82f6',negative:'#10b981',unknown:'#94a3b8'};
new Chart(cStatus,{type:'doughnut',data:{labels:Object.keys(ST).map(k=>L[k]),datasets:[{data:Object.values(ST),backgroundColor:Object.keys(ST).map(k=>C[k])}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
</script>
<?php endif; include __DIR__ . '/_footer.php';
