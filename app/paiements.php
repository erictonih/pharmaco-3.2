<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Billing\PaymentService as P;
use Pharmaco\Users\UserService;
Auth::requireRole('Super Admin');                      // validation réservée au Super Admin

$me = (string) Auth::user(); $error = null; $ok = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfCheck();
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'accept' || $a === 'reject') {
        $r = P::decide($me, (string) ($_POST['id'] ?? ''), $a === 'accept', (string) ($_POST['note'] ?? ''));
        if ($r['error'] === null) { header('Location: paiements.php?ok=' . ($a === 'accept' ? 'accepted' : 'rejected')); exit(); }
        $error = $r['error'];
    } else { http_response_code(400); $error = 'Action inconnue.'; }
}
$ok = ['accepted' => 'Paiement validé : l’utilisateur a maintenant accès à tous les modules.', 'rejected' => 'Paiement rejeté.'][(string) ($_GET['ok'] ?? '')] ?? null;
try { $pending = P::pendingList(); $recent = P::recent(50); } catch (\Throwable $e) { error_log('[pharmaco][billing] liste : ' . $e->getMessage()); $pending = []; $recent = []; $error = $error ?? 'Base de données injoignable.'; }
$page_title = 'Paiements MVola'; $active_page = 'paiements';
include __DIR__ . '/_layout.php';
$fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
$lbl = ['submitted' => 'En attente', 'confirmed' => 'Validé', 'rejected' => 'Rejeté'];
?>
<?php if ($ok): ?><div class="dv-alert dv-alert-success" role="status"><?= h($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="dv-alert dv-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (P::number() === ''): ?><div class="dv-alert dv-alert-warning">Variable <code>MVOLA_NUMBER</code> non définie : les utilisateurs ne peuvent pas déclarer de paiement.</div><?php endif; ?>

<div class="dv-card" style="margin-bottom:16px"><div class="dv-card-header">Transactions à valider (<?= count($pending) ?>)</div>
<?php if (!$pending): ?><p class="muted" style="padding:14px;margin:0">Aucune transaction en attente.</p><?php else: ?>
  <p class="muted" style="padding:0 14px">Vérifiez dans votre compte MVola que la référence existe avec le bon montant, puis validez.</p>
  <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Déclaré le (UTC)</th><th>Utilisateur</th><th>Référence MVola</th><th>Expéditeur</th><th>Montant</th><th>Durée</th><th></th></tr></thead><tbody>
  <?php foreach ($pending as $p): ?><tr>
    <td class="muted"><?= h($p['created_at']) ?></td><td><b><?= h($p['username']) ?></b></td><td><code><?= h($p['reference']) ?></code></td><td><?= h($p['sender'] ?: '—') ?></td>
    <td><?= h($fmt($p['amount'])) ?> <?= h($p['currency']) ?></td><td><?= (int) $p['days'] ?> j</td>
    <td><form method="post" style="display:flex;gap:6px;align-items:center"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= h($p['id']) ?>">
      <input class="dv-input" name="note" maxlength="200" placeholder="Motif (rejet)" style="width:140px">
      <button class="dv-btn dv-btn-primary" name="action" value="accept" onclick="return confirm('Valider le paiement <?= h($p['reference']) ?> de « <?= h(addslashes($p['username'])) ?> » ? L\'accès sera ouvert immédiatement.')"><i class="bi bi-check-lg"></i> Valider</button>
      <button class="dv-btn" style="color:#e53e3e" name="action" value="reject" onclick="return confirm('Rejeter cette transaction ?')"><i class="bi bi-x-lg"></i> Rejeter</button></form></td>
  </tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></div>

<div class="dv-card"><div class="dv-card-header">Historique (50 dernières)</div>
  <div class="dv-table-wrap"><table class="dv-table"><thead><tr><th>Date (UTC)</th><th>Utilisateur</th><th>Référence</th><th>Montant</th><th>Statut</th><th>Traité par</th></tr></thead><tbody>
  <?php foreach ($recent as $p): ?><tr><td class="muted"><?= h($p['created_at']) ?></td><td><?= h($p['username']) ?></td><td><?= h($p['reference']) ?></td><td><?= h($fmt($p['amount'])) ?> <?= h($p['currency']) ?></td>
    <td><?= h($lbl[$p['status']] ?? $p['status']) ?><?= $p['note'] !== '' ? ' <span class="muted">— ' . h($p['note']) . '</span>' : '' ?></td><td class="muted"><?= h($p['decided_by'] ?: '—') ?></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="6" class="muted">Aucune transaction.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/_footer.php'; ?>
