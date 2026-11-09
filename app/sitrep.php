<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, SitRepService};
Auth::requireLogin('premium');

$ctx = ServiceFactory::context([]);                          // jeu de données complet : la période est appliquée par SitRepService
$page_title = 'SitRep hebdomadaire (PDF / Word)'; $active_page = 'sitrep';
include __DIR__ . '/_layout.php';
$sr = null; $weeks = []; $months = [];
if ($ctx['full']) {
    $svc = new SitRepService($ctx['full']);
    $g = static fn(string $k): ?string => isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : null;
    $sr = $svc->build(['mode' => $g('mode') ?? 'week', 'week' => $g('week'), 'month' => $g('month'), 'from' => $g('from'), 'to' => $g('to'), 'cum_from' => $g('cum_from'), 'num' => $g('num')]);
    if (empty($sr['empty'])) {
        // listes de semaines / mois proposées (de la dernière notification vers la première)
        for ($d = new DateTimeImmutable($sr['data_last']); $d->format('Y-m-d') >= date('Y-m-d', strtotime($sr['data_first'] . ' monday this week')); $d = $d->modify('-7 days')) {
            $mon = $d->modify('monday this week');
            $weeks[$mon->format('o-\WW')] = explode('_', SitRepService::weekCode($mon->format('Y-m-d')))[0] . ' · ' . SitRepService::rangeLabel($mon->format('Y-m-d'), $mon->modify('+6 days')->format('Y-m-d'));
        }
        for ($d = (new DateTimeImmutable($sr['data_last']))->modify('first day of this month'); $d->format('Y-m-d') >= date('Y-m-01', strtotime($sr['data_first'])); $d = $d->modify('-1 month')) {
            $months[$d->format('Y-m')] = $d->format('m/Y');
        }
    }
}
?>
<style>
  .sr-modes{display:flex;gap:14px;flex-wrap:wrap;align-items:center}.sr-modes label{display:flex;gap:5px;align-items:center;font-size:.85rem;color:inherit;margin:0}
  .sr-man{display:grid;grid-template-columns:200px repeat(4,minmax(90px,1fr));gap:6px;align-items:center;font-size:.8rem}.sr-man input{width:100%}
  .sr-txt{width:100%;min-height:90px;font:inherit;font-size:.82rem}
  .sr-sheet{overflow:auto;background:#d9dee5;padding:18px;border-radius:6px}
  .sr-page{position:relative;width:794px;height:1123px;margin:0 auto 18px;background:#eaf3fa;overflow:hidden;box-shadow:0 1px 8px rgba(0,0,0,.2);font-family:Arial,Helvetica,sans-serif;color:#111;font-size:11.5px;line-height:1.3}
  .sr-page.over{outline:3px solid #e11d1d}
  .sr-page img{display:block}.sr-page table{border-collapse:collapse}
  .sr-page td,.sr-page th{border:1px solid #7a8a99;padding:2px 5px}
  .sr-h{background:#00a3c4;color:#fff;font-weight:700;text-align:center}
  .sr-band{background:#4da63d;color:#fff;font-weight:700;padding:4px 10px;letter-spacing:.3px}
  .sr-warn{font-size:.78rem;color:#b45309}
</style>
<?php if ($sr && empty($sr['empty'])): ?>
<form class="dv-card filters" method="get" id="srForm" style="padding:14px;align-items:end">
  <div style="flex-basis:100%"><label>Structure temporelle</label>
    <div class="sr-modes">
      <label><input type="radio" name="mode" value="week" <?= $sr['mode'] === 'week' ? 'checked' : '' ?>> Hebdomadaire (semaine ISO)</label>
      <label><input type="radio" name="mode" value="month" <?= $sr['mode'] === 'month' ? 'checked' : '' ?>> Mensuelle</label>
      <label><input type="radio" name="mode" value="custom" <?= $sr['mode'] === 'custom' ? 'checked' : '' ?>> Période libre</label>
    </div></div>
  <div data-m="week"><label for="week">Semaine</label>
    <select class="dv-input" id="week" name="week"><?php $curW = (new DateTimeImmutable($sr['from']))->format('o-\WW'); foreach ($weeks as $v => $l): ?><option value="<?= h($v) ?>" <?= $v === $curW ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
  <div data-m="month"><label for="month">Mois</label>
    <select class="dv-input" id="month" name="month"><?php $curM = substr($sr['from'], 0, 7); foreach ($months as $v => $l): ?><option value="<?= h($v) ?>" <?= $v === $curM ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
  <div data-m="custom"><label for="from">Du</label><input class="dv-input" type="date" id="from" name="from" value="<?= h($sr['from']) ?>" max="<?= h($sr['data_last']) ?>"></div>
  <div data-m="custom"><label for="to">Au</label><input class="dv-input" type="date" id="to" name="to" value="<?= h($sr['to']) ?>"></div>
  <div><label for="cum_from">Début du cumul</label><input class="dv-input" type="date" id="cum_from" name="cum_from" value="<?= h($sr['cum_from']) ?>" min="<?= h($sr['data_first']) ?>"></div>
  <div><label for="num">N° du SitRep</label><input class="dv-input" type="number" min="1" id="num" data-name="num" value="<?= h((string) $sr['num']) ?>" style="width:90px"></div>
  <button class="dv-btn dv-btn-primary">Appliquer</button>
</form>

<div class="dv-card" style="padding:14px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
  <div>
    <strong>SitRep N°<?= h((string) $sr['num']) ?> — <?= h($sr['period_text']) ?></strong>
    <span class="muted"> · cumul <?= h($sr['cum_label']) ?> · <?= fr($sr['cumul']['notified']) ?> cas notifiés, <?= fr($sr['cumul']['confirmed']) ?> confirmés</span>
    <div class="muted" id="srMsg" role="status"></div>
  </div>
  <div style="display:flex;gap:8px">
    <button type="button" class="dv-btn dv-btn-primary" data-export="pdf" disabled><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</button>
    <button type="button" class="dv-btn dv-btn-primary" data-export="docx" disabled><i class="bi bi-file-earmark-word"></i> Télécharger le Word</button>
  </div>
</div>

<details class="dv-card" style="padding:14px;margin-bottom:16px" open>
  <summary style="cursor:pointer;font-weight:600">Compléments à saisir (non disponibles dans le Sheet)</summary>
  <p class="muted">Ces valeurs ne sont pas calculables à partir de la liste linéaire : laissez vide pour afficher « ND ». Elles ne sont jamais inventées.</p>
  <div class="sr-man" id="srMan" style="margin-bottom:8px"></div>
  <p class="sr-warn">Les sections « Actions de santé publique » et « Actions prioritaires » sont qualitatives : le texte ci-dessous reprend celui du SitRep N°46 et doit être mis à jour avant diffusion.</p>
  <label for="srP1" class="muted">Points saillants — paragraphe 1 (généré, modifiable)</label><textarea class="dv-input sr-txt" id="srP1" data-k="p1"></textarea>
  <label for="srP2" class="muted">Points saillants — paragraphe 2 (généré, modifiable)</label><textarea class="dv-input sr-txt" id="srP2" data-k="p2"></textarea>
  <div class="grid g2" style="margin-top:8px">
    <div><label class="muted">Coordination (une ligne par puce)</label><textarea class="dv-input sr-txt" data-k="a_coord"></textarea></div>
    <div><label class="muted">Surveillance</label><textarea class="dv-input sr-txt" data-k="a_surv"></textarea></div>
    <div><label class="muted">Points d’entrée</label><textarea class="dv-input sr-txt" data-k="a_pe"></textarea></div>
    <div><label class="muted">Laboratoire</label><textarea class="dv-input sr-txt" data-k="a_lab"></textarea></div>
    <div><label class="muted">Actions prioritaires — Surveillance</label><textarea class="dv-input sr-txt" data-k="p_surv"></textarea></div>
    <div><label class="muted">Actions prioritaires — Laboratoire</label><textarea class="dv-input sr-txt" data-k="p_lab"></textarea></div>
    <div><label class="muted">Actions prioritaires — Logistique</label><textarea class="dv-input sr-txt" data-k="p_log"></textarea></div>
  </div>
</details>
<div class="sr-sheet"><div id="srDoc"></div></div>

<script>window.SITREP=<?= json_js($sr) ?>;</script>
<script src="vendor/chart.umd.js"></script>
<script src="vendor/html2canvas.min.js"></script>
<script src="vendor/jspdf.umd.min.js"></script>
<script src="vendor/docx.umd.js"></script>
<script src="js/sitrep.js"></script>
<script>
(function(){
  const f=document.getElementById('srForm');
  const sync=()=>{const m=f.querySelector('[name=mode]:checked').value;f.querySelectorAll('[data-m]').forEach(e=>{const on=e.dataset.m===m;e.style.display=on?'':'none';e.querySelectorAll('select,input').forEach(i=>i.disabled=!on);});};
  const n=document.getElementById('num');n.addEventListener('input',()=>n.setAttribute('name','num'));
  f.querySelectorAll('[name=mode]').forEach(r=>r.addEventListener('change',sync));sync();
})();
</script>
<?php elseif ($sr): ?><div class="dv-alert dv-alert-error">Aucune date de notification exploitable dans le Sheet.</div>
<?php else: ?><div class="dv-alert dv-alert-error"><?= h($ctx['error'] ?? 'Données indisponibles.') ?></div><?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
