<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, OutbreakAlertService, DataQualityService};
Auth::requireLogin();

$ctx = ServiceFactory::context([]);
$page_title = 'Alertes & qualité des données'; $active_page = 'alertes';
include __DIR__ . '/_layout.php';
if ($ctx['full']):
    $al = (new OutbreakAlertService($ctx['full']))->districtAlerts();
    $q  = (new DataQualityService($ctx['full']))->report();
    $S = $al['summary']; $lv = ['', 'Surveillance', 'Élevé', 'Critique'];
    $labels = ['date' => 'Date de notification', 'district' => 'District', 'region' => 'Région', 'status' => 'Classification', 'age' => 'Âge', 'sex' => 'Sexe', 'outcome' => 'Évolution'];
?>
<h3 class="dv-section-title">Alertes de foyers</h3>
<p class="muted" style="margin-bottom:12px">Méthode : cas des <?= OutbreakAlertService::RECENT_DAYS ?> derniers jours (jusqu'au <?= h($al['reference_date'] ?? '—') ?>, dernière notification du Sheet) comparés à la moyenne hebdomadaire des <?= OutbreakAlertService::BASELINE_WEEKS ?> semaines précédentes ; probabilité de Poisson (queue supérieure).
  Critique : ≥ 5 cas et p &lt; 0,01, ou décès récent et p &lt; 0,05 · Élevé : ≥ 3 cas et p &lt; 0,05 · Surveillance : ≥ 2 cas et p &lt; 0,20, ou nouveau foyer.</p>
<div class="grid g4" style="margin-bottom:16px">
  <div class="dv-stat"><div class="dv-stat-label">Critiques</div><div class="dv-stat-value" style="color:#e53e3e"><?= $S['critical'] ?></div></div>
  <div class="dv-stat"><div class="dv-stat-label">Élevés</div><div class="dv-stat-value" style="color:#f59e0b"><?= $S['high'] ?></div></div>
  <div class="dv-stat"><div class="dv-stat-label">Surveillance</div><div class="dv-stat-value" style="color:#3b82f6"><?= $S['watch'] ?></div></div>
  <div class="dv-stat"><div class="dv-stat-label">Nouveaux foyers</div><div class="dv-stat-value"><?= $S['new'] ?></div></div>
</div>
<div class="dv-card" style="margin-bottom:24px"><div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Niveau</th><th>District</th><th>Région</th><th class="num">Cas (14 j)</th><th class="num">Confirmés</th><th class="num">Décès</th><th class="num">Attendus</th><th class="num">Ratio</th><th class="num">p</th></tr></thead><tbody>
<?php foreach ($al['alerts'] as $a): ?>
  <tr><td><span class="lvl lvl<?= $a['level'] ?>"><?= $lv[$a['level']] ?></span><?= $a['is_new'] ? ' <span class="dv-badge dv-badge-primary">nouveau</span>' : '' ?></td><td><?= h($a['district']) ?></td><td><?= h($a['region']) ?></td>
  <td class="num"><?= $a['recent'] ?></td><td class="num"><?= $a['recent_confirmed'] ?></td><td class="num"><?= $a['recent_deaths'] ?></td><td class="num"><?= fr($a['expected'], 1) ?></td><td class="num">×<?= fr($a['ratio'], 1) ?></td><td class="num"><?= fr($a['p_value'], 4) ?></td></tr>
<?php endforeach; if (!$al['alerts']): ?><tr><td colspan="9" class="muted">Aucune alerte : situation stable.</td></tr><?php endif; ?></tbody></table></div></div>

<h3 class="dv-section-title">Qualité des données du Google Sheet</h3>
<div class="grid g3" style="margin-bottom:16px">
  <div class="dv-stat"><div class="dv-stat-label">Score de complétude</div><div class="dv-stat-value"><?= fr($q['score'], 1) ?> %</div><div class="dv-stat-sub"><?= fr($q['rows']) ?> lignes analysées</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Dates incohérentes</div><div class="dv-stat-value"><?= fr($q['future_dates'] + $q['old_dates']) ?></div><div class="dv-stat-sub"><?= $q['future_dates'] ?> futures · <?= $q['old_dates'] ?> avant 2022</div></div>
  <div class="dv-stat"><div class="dv-stat-label">Source</div><div class="dv-stat-value" style="font-size:1.1rem"><?= h($q['meta']['source'] ?? '') ?></div><div class="dv-stat-sub">actualisée le <?= h(date('d/m/Y H:i', (int) ($q['meta']['fetched_at'] ?? time()))) ?></div></div>
</div>
<?php foreach ($q['issues'] as $i): ?><div class="dv-alert dv-alert-<?= $i['level'] === 'error' ? 'error' : ($i['level'] === 'warning' ? 'warning' : 'info') ?>"><?= h($i['text']) ?></div><?php endforeach; ?>
<div class="grid g2">
  <div class="dv-card"><div class="dv-card-header">Complétude par champ</div>
    <?php foreach ($q['fields'] as $k => $v): ?>
      <div style="margin:10px 0"><div style="display:flex;justify-content:space-between"><span><?= h($labels[$k] ?? $k) ?></span><span class="muted"><?= fr($v['completeness'], 1) ?> % · <?= $v['missing'] ?> manquant(s)</span></div>
      <div class="bar"><i style="width:<?= (float) $v['completeness'] ?>%"></i></div></div>
    <?php endforeach; ?></div>
  <div class="dv-card"><div class="dv-card-header">Valeurs non reconnues à corriger dans le Sheet</div>
    <?php if ($q['unrecognized']): ?><div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Valeur</th><th class="num">Lignes</th></tr></thead><tbody>
      <?php foreach ($q['unrecognized'] as $v => $n): ?><tr><td><?= h($v) ?></td><td class="num"><?= $n ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">Toutes les valeurs de classification sont reconnues.</p><?php endif; ?></div>
</div>
<?php endif; include __DIR__ . '/_footer.php';
