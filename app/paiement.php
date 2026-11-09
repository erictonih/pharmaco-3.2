<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Billing\PaymentService as P;
use Pharmaco\Users\UserService;
Auth::requireLogin('billing');                         // accessible au VIP même sans abonnement actif

$me = (string) Auth::user(); $error = null; $ok = null;
if (($_GET['poll'] ?? '') === '1') { header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['active' => P::isActive($me)]); exit(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfCheck();
    $error = P::submit($me, (string) ($_POST['reference'] ?? ''), (string) ($_POST['sender'] ?? ''));
    if ($error === null) { header('Location: paiement.php?ok=sent'); exit(); }
}
if (($_GET['ok'] ?? '') === 'sent') { $ok = 'Référence enregistrée. Votre paiement est en attente de validation par l’administrateur.'; }
$st = P::status($me);
$page_title = 'Abonnement VIP'; $active_page = 'paiement';
include __DIR__ . '/_layout.php';
$fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
$stLbl = ['submitted' => 'En attente de validation', 'confirmed' => 'Validé', 'rejected' => 'Rejeté'];
?>
<?php if ($error): ?><div class="dv-alert dv-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="dv-alert dv-alert-success" role="status"><?= h($ok) ?></div><?php endif; ?>
<?php if ($st['error']): ?><div class="dv-alert dv-alert-error"><?= h($st['error']) ?></div><?php endif; ?>

<div class="dv-card" style="max-width:600px;margin:0 auto 16px;padding:24px">
  <h2 style="margin-top:0"><i class="bi bi-star-fill" style="color:#f59e0b"></i> Module VIP — paiement par MVola</h2>
  <?php if ($st['active']): ?><p>Abonnement actif jusqu’au <b><?= h(UserService::localTime($st['until'])) ?></b> (heure de Madagascar). <a href="index.php">Aller à l’accueil</a></p><?php endif; ?>

<?php if ($st['waiting']): ?>
  <div class="dv-alert dv-alert-warning" id="waitBox"><i class="bi bi-hourglass-split"></i> Transaction <b><?= h($st['waiting']['reference']) ?></b> en attente de validation. L’accès à tous les modules s’ouvre dès que l’administrateur valide le paiement (cette page se met à jour automatiquement).</div>
  <script>setTimeout(function chk(){fetch('paiement.php?poll=1',{credentials:'same-origin'}).then(r=>r.json()).then(j=>{if(j.active){location.href='index.php';}else{setTimeout(chk,10000);}}).catch(()=>setTimeout(chk,15000));},10000);</script>
<?php elseif (P::number() === ''): ?>
  <div class="dv-alert dv-alert-error">Le paiement n’est pas encore configuré (numéro MVola de réception manquant). Contactez l’administrateur.</div>
<?php else: ?>
  <p>Accès à <b>tous les modules</b> (accueil, analyses, SitRep, Point de situation) pendant <b><?= P::days() ?> jours</b> :</p>
  <p style="font-size:1.4rem;margin:8px 0"><b><?= h($fmt(P::price())) ?> <?= h(P::currency()) ?></b></p>
  <ol>
    <li>Envoyez <b><?= h($fmt(P::price())) ?> <?= h(P::currency()) ?></b> par <b>MVola</b> au <b><?= h(P::number()) ?></b><?= P::holder() !== '' ? ' (' . h(P::holder()) . ')' : '' ?>.</li>
    <li>Relevez la <b>référence de la transaction</b> indiquée dans le SMS de confirmation MVola.</li>
    <li>Saisissez-la ci-dessous : l’administrateur vérifie le virement puis valide votre accès.</li>
  </ol>
  <form method="post" autocomplete="off"><?= Auth::csrfField() ?>
    <div class="dv-form-group"><label class="dv-label" for="rf">Référence de la transaction MVola</label><input class="dv-input" id="rf" name="reference" required minlength="6" maxlength="40" pattern="[A-Za-z0-9._\-]+" placeholder="ex. MP260109.1234.A56789"></div>
    <div class="dv-form-group"><label class="dv-label" for="sn">Numéro MVola utilisé pour le virement (facultatif)</label><input class="dv-input" id="sn" name="sender" maxlength="20" inputmode="tel" placeholder="034 00 000 00"></div>
    <button class="dv-btn dv-btn-primary"><i class="bi bi-send-check"></i> Envoyer la référence</button>
  </form>
<?php endif; ?>
</div>

<?php if ($st['history']): ?>
<div class="dv-card" style="max-width:600px;margin:0 auto"><div class="dv-card-header">Mes dernières transactions</div>
  <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Date (UTC)</th><th>Référence</th><th>Montant</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($st['history'] as $p): ?><tr><td class="muted"><?= h($p['created_at']) ?></td><td><?= h($p['reference']) ?></td><td><?= h($fmt($p['amount'])) ?> <?= h($p['currency']) ?></td>
    <td><?= h($stLbl[$p['status']] ?? $p['status']) ?><?= $p['status'] === 'rejected' && $p['note'] !== '' ? ' <span class="muted">— ' . h($p['note']) . '</span>' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
