<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Billing\PaymentService as P;
use Pharmaco\Users\UserService;
Auth::requireLogin('billing');                         // accessible au VIP même sans abonnement actif

$me = (string) Auth::user(); $error = null;
$tx = isset($_GET['tx']) && is_string($_GET['tx']) ? $_GET['tx'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfCheck();
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'start') {
        $r = P::start($me);
        if ($r['id']) { header('Location: paiement.php?tx=' . $r['id']); exit(); }
        $error = $r['error'];
    } elseif ($a === 'confirm') {
        $r = P::confirm($me, (string) ($_POST['tx'] ?? ''));
        if ($r['error'] === null) { header('Location: index.php'); exit(); }          // paiement confirmé : la page d'accueil s'ouvre
        $error = $r['error']; $tx = '';
    } elseif ($a === 'cancel') {
        P::cancel($me, (string) ($_POST['tx'] ?? '')); header('Location: paiement.php?ok=cancelled'); exit();
    } else { http_response_code(400); $error = 'Action inconnue.'; }
}
$pending = $tx !== '' ? P::pending($me, $tx) : null;
if ($tx !== '' && !$pending && !$error) { $error = 'Transaction introuvable, déjà traitée ou expirée.'; }
$st = P::status($me);
$page_title = 'Abonnement VIP'; $active_page = 'paiement';
include __DIR__ . '/_layout.php';
$fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
?>
<?php if ($error): ?><div class="dv-alert dv-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (($_GET['ok'] ?? '') === 'cancelled'): ?><div class="dv-alert dv-alert-warning">Paiement annulé : aucun montant n’a été débité.</div><?php endif; ?>
<?php if (P::simulated()): ?><div class="dv-alert dv-alert-warning"><i class="bi bi-info-circle"></i> <b>Mode simulation</b> : la passerelle de paiement est simulée, aucun débit réel n’est effectué.</div><?php endif; ?>

<div class="dv-card" style="max-width:560px;margin:0 auto 16px;padding:24px">
<?php if ($pending): ?>
  <h2 style="margin-top:0"><i class="bi bi-credit-card"></i> Confirmer le paiement</h2>
  <p class="muted" style="margin-top:0">Passerelle de paiement <?= P::simulated() ? 'simulée' : '' ?> · transaction <code><?= h(substr($pending['id'], 0, 8)) ?></code></p>
  <table class="dv-table"><tbody>
    <tr><td>Compte</td><td><b><?= h($me) ?></b></td></tr>
    <tr><td>Abonnement VIP</td><td><?= (int) $pending['days'] ?> jours</td></tr>
    <tr><td>Montant</td><td><b><?= h($fmt($pending['amount'])) ?> <?= h($pending['currency']) ?></b></td></tr>
  </tbody></table>
  <form method="post" style="display:flex;gap:8px;margin-top:16px"><?= Auth::csrfField() ?><input type="hidden" name="tx" value="<?= h($pending['id']) ?>">
    <button class="dv-btn dv-btn-primary" name="action" value="confirm"><i class="bi bi-check-circle"></i> Confirmer la transaction</button>
    <button class="dv-btn" name="action" value="cancel">Annuler</button>
  </form>
  <p class="muted" style="margin-bottom:0">Après confirmation, vous êtes redirigé vers la page d’accueil.</p>
<?php else: ?>
  <h2 style="margin-top:0"><i class="bi bi-star-fill" style="color:#f59e0b"></i> Module VIP</h2>
  <?php if ($st['error']): ?><div class="dv-alert dv-alert-error"><?= h($st['error']) ?></div><?php endif; ?>
  <?php if ($st['active']): ?>
    <p>Abonnement actif jusqu’au <b><?= h(UserService::localTime($st['until'])) ?></b> (heure de Madagascar).</p>
  <?php else: ?>
    <p>Votre compte VIP n’a pas d’abonnement actif. Le paiement donne accès à l’accueil et au module payant :</p>
  <?php endif; ?>
  <ul><li>SitRep hebdomadaire (PDF / Word) avec filtre temporel</li><li>Point de situation (PDF / PowerPoint)</li></ul>
  <p style="font-size:1.4rem;margin:12px 0"><b><?= h($fmt(P::price())) ?> <?= h(P::currency()) ?></b> <span class="muted">pour <?= P::days() ?> jours</span></p>
  <form method="post" style="display:flex;gap:8px"><?= Auth::csrfField() ?><input type="hidden" name="action" value="start">
    <button class="dv-btn dv-btn-primary"><i class="bi bi-credit-card"></i> <?= $st['active'] ? 'Prolonger l’abonnement' : 'Payer maintenant' ?></button>
    <?php if ($st['active']): ?><a class="dv-btn" href="index.php">Accueil</a><?php endif; ?>
  </form>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
