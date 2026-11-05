<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, EpiAnalyticsService, MpoxDataset};
Auth::requireLogin();

$ctx = ServiceFactory::context($_GET);
$page_title = 'Analyse épidémiologique'; $active_page = 'situation';
include __DIR__ . '/_layout.php';
if ($ctx['ds']):
    $epi = new EpiAnalyticsService($ctx['ds']);
    $ov = $epi->overview(); $ts = $epi->timeSeries(); $geo = $epi->geography(); $demo = $epi->demographics(); $cli = $epi->clinical(); $lab = $epi->laboratory();
    $f = $ctx['filters'];
?>
<form class="dv-card filters" method="get" style="padding:14px">
  <div><label for="from">Du</label><input class="dv-input" type="date" id="from" name="from" value="<?= h($f['from'] ?? '') ?>"></div>
  <div><label for="to">Au</label><input class="dv-input" type="date" id="to" name="to" value="<?= h($f['to'] ?? '') ?>"></div>
  <div><label for="region">Région</label><select class="dv-select" id="region" name="region"><option value="">Toutes</option>
    <?php foreach ($ctx['regions'] as $r): ?><option <?= ($f['region'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option><?php endforeach; ?></select></div>
  <button class="dv-btn dv-btn-primary">Filtrer</button>
  <?php if ($f): ?><a class="dv-btn dv-btn-outline" href="situation.php">Réinitialiser</a><?php endif; ?>
  <span class="muted"><?= fr($ov['total']) ?> cas dans la sélection</span>
</form>

<h3 class="dv-section-title">1. Temps</h3>
<div class="grid g2" style="margin-bottom:16px">
  <div class="dv-card"><div class="dv-card-header">Cas par semaine ISO (confirmés et décès)</div><div class="chart-box"><canvas id="cWeek"></canvas></div></div>
  <div class="dv-card"><div class="dv-card-header">Cas cumulés</div><div class="chart-box"><canvas id="cCum"></canvas></div></div>
</div>
<div class="dv-card" style="margin-bottom:16px"><div class="dv-card-header">Cas par mois de notification</div><div class="chart-box"><canvas id="cMonth"></canvas></div></div>

<h3 class="dv-section-title">2. Lieu</h3>
<div class="grid g2" style="margin-bottom:16px">
  <div class="dv-card"><div class="dv-card-header">Par région</div><div class="chart-box"><canvas id="cReg"></canvas></div></div>
  <div class="dv-card"><div class="dv-card-header">Par district (10 premiers)</div>
    <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>District</th><th class="num">Cas</th><th class="num">Confirmés</th><th class="num">Décès</th></tr></thead><tbody>
    <?php foreach (array_slice($geo['districts'], 0, 10, true) as $d => $v): ?><tr><td><?= h($d) ?></td><td class="num"><?= $v['total'] ?></td><td class="num"><?= $v['confirmed'] ?></td><td class="num"><?= $v['deaths'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>

<h3 class="dv-section-title">3. Personne</h3>
<div class="grid g3" style="margin-bottom:16px">
  <div class="dv-card"><div class="dv-card-header">Âge et sexe</div><div class="chart-box"><canvas id="cAge"></canvas></div><p class="muted"><?= fr($demo['unknown_age']) ?> cas sans âge renseigné · sex-ratio H/F : <?= fr($demo['sex_ratio_mf'], 2) ?></p></div>
  <div class="dv-card"><div class="dv-card-header">Sex-ratio H/F</div><div class="chart-box"><canvas id="cSex"></canvas></div><p class="muted">Global : <?= fr($demo['sex_ratio_mf'], 2) ?> homme(s) pour 1 femme · <?= fr($demo['sex']['M']) ?> H / <?= fr($demo['sex']['F']) ?> F<?= $demo['sex']['U'] ? ' / ' . fr($demo['sex']['U']) . ' non renseigné' : '' ?></p></div>
  <div class="dv-card"><div class="dv-card-header">Sex-ratio H/F par tranche d'âge</div><div class="chart-box"><canvas id="cSexAge"></canvas></div><p class="muted">Ligne pointillée = parité (1,0).</p></div>
  <div class="dv-card"><div class="dv-card-header">Statut VIH</div><div class="chart-box"><canvas id="cHiv"></canvas></div></div>
  <div class="dv-card"><div class="dv-card-header">Vaccination Mpox</div><div class="chart-box"><canvas id="cVac"></canvas></div><p class="muted">Femmes enceintes : <?= fr($demo['pregnant']) ?> · lien épidémiologique : <?= fr($demo['epi_link_pct'], 1) ?> %</p></div>
</div>

<h3 class="dv-section-title">4. Évolution clinique</h3>
<div class="grid g2" style="margin-bottom:16px">
  <div class="dv-card"><div class="dv-card-header">Évolution des cas</div><div class="chart-box"><canvas id="cOut"></canvas></div></div>
  <div class="dv-card"><div class="dv-card-header">Létalité et hospitalisation par tranche d'âge</div>
    <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Âge</th><th class="num">Cas</th><th class="num">Décès</th><th class="num">Létalité</th><th class="num">Hospit.</th></tr></thead><tbody>
    <?php foreach ($cli['by_age'] as $g => $v): ?><tr><td><?= h($g) ?></td><td class="num"><?= $v['cases'] ?></td><td class="num"><?= $v['deaths'] ?></td><td class="num"><?= fr($v['cfr'], 1) ?> %</td><td class="num"><?= fr($v['hosp_rate'], 1) ?> %</td></tr><?php endforeach; ?></tbody></table></div></div>
</div>

<h3 class="dv-section-title">5. Laboratoire</h3>
<div class="grid g3" style="margin-bottom:16px">
  <div class="dv-stat"><div class="dv-stat-label">Taux de prélèvement</div><div class="dv-stat-value"><?= fr($lab['sampling_rate'], 1) ?> %</div><div class="dv-stat-sub"><?= fr($lab['sampled']) ?> prélevés</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Positivité</div><div class="dv-stat-value"><?= fr($lab['positivity'], 1) ?> %</div><div class="dv-stat-sub">confirmés / (confirmés + négatifs)</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Délai notification → diagnostic</div><div class="dv-stat-value"><?= fr($lab['delay_median_days'], 1) ?> j</div><div class="dv-stat-sub">médiane (n = <?= fr($lab['delay_n']) ?>)</div></div>
</div>
<div class="grid g2">
  <div class="dv-card"><div class="dv-card-header">Laboratoires</div><div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Laboratoire</th><th class="num">Analyses</th><th class="num">Confirmés</th></tr></thead><tbody>
    <?php foreach ($lab['labs'] as $l => $v): ?><tr><td><?= h($l) ?></td><td class="num"><?= $v['tested'] ?></td><td class="num"><?= $v['confirmed'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="dv-card"><div class="dv-card-header">Clades / profil génomique</div><?php if ($lab['clades']): ?><div class="chart-box"><canvas id="cClade"></canvas></div><?php else: ?><p class="muted">Aucun clade renseigné dans la sélection.</p><?php endif; ?></div>
</div>

<script src="vendor/chart.umd.js"></script>
<script>
const W=<?= json_js($ts['weeks']) ?>, M=<?= json_js($ts['months']) ?>, REG=<?= json_js($geo['regions']) ?>, DEMO=<?= json_js($demo) ?>, CLI=<?= json_js($cli) ?>, LAB=<?= json_js($lab) ?>;
const css=n=>getComputedStyle(document.documentElement).getPropertyValue(n).trim();
Chart.defaults.font.family="'DM Sans',sans-serif";Chart.defaults.color=css('--text-secondary');Chart.defaults.borderColor=css('--border');
const opt=(x={})=>Object.assign({responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}},x);
const PAL=['#0a6cbc','#e53e3e','#f59e0b','#10b981','#8b5cf6','#06b6d4','#94a3b8'];
new Chart(cWeek,{type:'bar',data:{labels:W.labels,datasets:[{label:'Cas',data:W.total,backgroundColor:'#93c5fd'},{label:'Confirmés',data:W.confirmed,backgroundColor:'#e53e3e'},{label:'Décès',data:W.deaths,backgroundColor:'#111827'}]},options:opt({scales:{x:{ticks:{maxTicksLimit:10}},y:{beginAtZero:true,ticks:{precision:0}}}})});
new Chart(cCum,{type:'line',data:{labels:W.labels,datasets:[{label:'Cumul',data:W.cumulative,borderColor:'#0a6cbc',backgroundColor:'rgba(10,108,188,.12)',fill:true,pointRadius:0,tension:.2}]},options:opt({scales:{x:{ticks:{maxTicksLimit:10}}}})});
new Chart(cMonth,{type:'bar',data:{labels:M.labels,datasets:[{label:'Cas',data:M.total,backgroundColor:'#93c5fd'},{label:'Confirmés',data:M.confirmed,backgroundColor:'#e53e3e'},{label:'Décès',data:M.deaths,backgroundColor:'#111827'}]},options:opt({scales:{y:{beginAtZero:true,ticks:{precision:0}}}})});
new Chart(cReg,{type:'bar',data:{labels:Object.keys(REG),datasets:[{label:'Cas',data:Object.values(REG).map(v=>v.total),backgroundColor:'#0a6cbc'},{label:'Confirmés',data:Object.values(REG).map(v=>v.confirmed),backgroundColor:'#e53e3e'}]},options:opt({indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}})});
const AG=Object.keys(DEMO.age_sex);
new Chart(cAge,{type:'bar',data:{labels:AG,datasets:[{label:'Hommes',data:AG.map(g=>DEMO.age_sex[g].M),backgroundColor:'#0a6cbc'},{label:'Femmes',data:AG.map(g=>DEMO.age_sex[g].F),backgroundColor:'#ec4899'}]},options:opt({scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}})});
new Chart(cSex,{type:'doughnut',data:{labels:['Hommes','Femmes','Non renseigné'],datasets:[{data:[DEMO.sex.M,DEMO.sex.F,DEMO.sex.U],backgroundColor:['#0a6cbc','#ec4899','#94a3b8']}]},options:opt()});
(function(){const r=AG.map(g=>DEMO.sex_ratio_by_age[g].ratio);
new Chart(cSexAge,{data:{labels:AG,datasets:[{type:'bar',label:'Sex-ratio H/F',data:r,backgroundColor:'#0a6cbc'},{type:'line',label:'Parité',data:AG.map(()=>1),borderColor:'#6b7280',borderDash:[6,4],pointRadius:0}]},options:opt({scales:{y:{beginAtZero:true}}})});})();
new Chart(cHiv,{type:'doughnut',data:{labels:Object.keys(DEMO.hiv),datasets:[{data:Object.values(DEMO.hiv),backgroundColor:PAL}]},options:opt()});
const VL={yes:'Vacciné',no:'Non vacciné',unknown:'Non renseigné'};
new Chart(cVac,{type:'doughnut',data:{labels:Object.keys(DEMO.vaccination).map(k=>VL[k]),datasets:[{data:Object.values(DEMO.vaccination),backgroundColor:['#10b981','#e53e3e','#94a3b8']}]},options:opt()});
const OL={favorable:'Favorable',deceased:'Décédé',other:'Autre',unknown:'Non renseigné'};
new Chart(cOut,{type:'doughnut',data:{labels:Object.keys(CLI.outcomes).map(k=>OL[k]),datasets:[{data:Object.values(CLI.outcomes),backgroundColor:['#10b981','#111827','#f59e0b','#94a3b8']}]},options:opt()});
if(document.getElementById('cClade'))new Chart(cClade,{type:'bar',data:{labels:Object.keys(LAB.clades),datasets:[{label:'Cas',data:Object.values(LAB.clades),backgroundColor:'#8b5cf6'}]},options:opt({plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}})});
</script>
<?php endif; include __DIR__ . '/_footer.php';
