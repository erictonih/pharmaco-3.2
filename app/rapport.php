<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, ReportService};
Auth::requireLogin('premium');

$ctx = ServiceFactory::context([]);                          // jeu de données complet : la période est appliquée par ReportService
$page_title = 'Point de situation'; $active_page = 'rapport';
include __DIR__ . '/_layout.php';
$rep = null;
if ($ctx['full']) {
    $rep = (new ReportService($ctx['full']))->build(
        isset($_GET['from']) ? (string) $_GET['from'] : null,
        isset($_GET['to'])   ? (string) $_GET['to']   : null
    );
}
?>
<style>
  .rp-presets{display:flex;flex-wrap:wrap;gap:6px}.rp-presets button{font-size:.78rem}
  .rp-wrap{width:100%;max-width:1100px;margin:0 auto;position:relative;overflow:hidden;border:1px solid var(--border,#e5e7eb);border-radius:6px;box-shadow:0 1px 6px rgba(0,0,0,.08);background:#fff}
  .rp-scale{position:absolute;left:0;top:0;transform-origin:0 0;width:1280px}
  .rp-cap{max-width:1100px;margin:4px auto 18px;font-size:.78rem;color:var(--text-muted)}
  .rp-slide table th,.rp-slide table td{font-family:Arial,Helvetica,sans-serif}
</style>
<?php if ($rep && empty($rep['empty'])): ?>
<form class="dv-card filters" method="get" id="rpForm" style="padding:14px;align-items:end">
  <div><label for="from">Du</label><input class="dv-input" type="date" id="from" name="from" value="<?= h($rep['from']) ?>" min="<?= h($rep['data_first']) ?>" max="<?= h($rep['data_last']) ?>"></div>
  <div><label for="to">Au (date du point)</label><input class="dv-input" type="date" id="to" name="to" value="<?= h($rep['to']) ?>" min="<?= h($rep['data_first']) ?>" max="<?= h($rep['data_last']) ?>"></div>
  <button class="dv-btn dv-btn-primary">Appliquer la période</button>
  <div style="flex-basis:100%">
    <label>Périodes prédéfinies (par rapport à la dernière notification, <?= h(date('d/m/Y', strtotime($rep['data_last']))) ?>)</label>
    <div class="rp-presets">
      <button type="button" class="dv-btn dv-btn-outline" data-days="all">Toute la période</button>
      <button type="button" class="dv-btn dv-btn-outline" data-days="365">12 mois</button>
      <button type="button" class="dv-btn dv-btn-outline" data-days="180">6 mois</button>
      <button type="button" class="dv-btn dv-btn-outline" data-days="90">3 mois</button>
      <button type="button" class="dv-btn dv-btn-outline" data-days="56">8 semaines</button>
      <button type="button" class="dv-btn dv-btn-outline" data-days="21">3 semaines</button>
    </div>
  </div>
</form>

<div class="dv-card" style="padding:14px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
  <div>
    <strong><?= h($rep['label']['from']) ?> → <?= h($rep['label']['to']) ?></strong>
    <span class="muted"> · du <?= h(date('d/m/Y', strtotime($rep['from']))) ?> au <?= h(date('d/m/Y', strtotime($rep['to']))) ?> · <?= fr($rep['cumul']['total']) ?> cas notifiés, <?= fr($rep['cumul']['confirmed']) ?> confirmés · dernières semaines : <?= h($rep['label']['recent_from']) ?> → <?= h($rep['label']['to']) ?></span>
    <div class="muted" id="rpMsg" role="status"></div>
  </div>
  <div style="display:flex;gap:8px">
    <button type="button" class="dv-btn dv-btn-primary" data-export="pdf" disabled><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</button>
    <button type="button" class="dv-btn dv-btn-primary" data-export="pptx" disabled><i class="bi bi-file-earmark-slides"></i> Télécharger le PowerPoint</button>
  </div>
</div>
<div id="deck"></div>

<script>window.REPORT=<?= json_js($rep) ?>;</script>
<script src="vendor/chart.umd.js"></script>
<script src="vendor/html2canvas.min.js"></script>
<script src="vendor/jspdf.umd.min.js"></script>
<script src="vendor/pptxgen.bundle.js"></script>
<script src="js/rapport.js"></script>
<script>
document.querySelectorAll('[data-days]').forEach(b=>b.addEventListener('click',()=>{
  const last=<?= json_js($rep['data_last']) ?>, first=<?= json_js($rep['data_first']) ?>, d=b.dataset.days;
  const t=new Date(last+'T00:00:00Z'), f=d==='all'?new Date(first+'T00:00:00Z'):new Date(t.getTime()-(+d-1)*864e5);
  const iso=x=>x.toISOString().slice(0,10);
  document.getElementById('from').value=iso(f<new Date(first+'T00:00:00Z')?new Date(first+'T00:00:00Z'):f);document.getElementById('to').value=last;
  document.getElementById('rpForm').submit();
}));
</script>
<?php elseif ($rep): ?><div class="dv-alert dv-alert-error">Aucune date de notification exploitable dans le Sheet.</div>
<?php else: ?><div class="dv-alert dv-alert-error"><?= h($ctx['error'] ?? 'Données indisponibles.') ?></div><?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
