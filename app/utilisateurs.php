<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Users\UserService;
Auth::requireRole('Super Admin');                     // page réservée au Super Admin

$me = (string) Auth::user();
$error = null; $form = ['username' => '', 'role' => 'Visualiseur'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfCheck();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $form = ['username' => trim((string) ($_POST['username'] ?? '')), 'role' => (string) ($_POST['role'] ?? '')];
        $error = UserService::create($me, $form['username'], $form['role'], (string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        if ($error === null) { header('Location: utilisateurs.php?ok=created'); exit(); }
    } elseif ($action === 'delete') {
        $error = UserService::delete($me, trim((string) ($_POST['username'] ?? '')));
        if ($error === null) { header('Location: utilisateurs.php?ok=deleted'); exit(); }
    } else { http_response_code(400); $error = 'Action inconnue.'; }
}
$ok = ['created' => 'Utilisateur créé.', 'deleted' => 'Utilisateur supprimé.'][(string) ($_GET['ok'] ?? '')] ?? null;

$L = UserService::listAll();
$page_title = 'Gestion des utilisateurs'; $active_page = 'utilisateurs';
include __DIR__ . '/_layout.php';
$roleDesc = ['Super Admin' => 'Accès complet, gestion des utilisateurs', 'Analyste' => 'Consultation et actualisation des données', 'Visualiseur' => 'Consultation seule'];
?>
<?php if ($ok): ?><div class="dv-alert dv-alert-success" role="status"><?= h($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="dv-alert dv-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (!$L['configured']): ?><div class="dv-alert dv-alert-error">Base de données des utilisateurs non connectée : définissez <code>TURSO_DATABASE_URL</code> et <code>TURSO_AUTH_TOKEN</code> (intégration Turso de Vercel). Seuls les comptes de <code>APP_USERS_JSON</code> sont actifs.</div>
<?php elseif ($L['error']): ?><div class="dv-alert dv-alert-error"><?= h($L['error']) ?> Seuls les comptes de secours sont affichés.</div><?php endif; ?>

<div class="dv-card" style="margin-bottom:16px">
  <div class="dv-card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span>Utilisateurs (<?= count($L['users']) ?>)</span>
    <button type="button" class="dv-btn dv-btn-primary" onclick="document.getElementById('addBox').hidden=false;document.getElementById('nu').focus()" <?= $L['configured'] ? '' : 'disabled' ?>><i class="bi bi-person-plus"></i> Nouvel utilisateur</button>
  </div>
  <div class="dv-table-wrap"><table class="dv-table">
    <thead><tr><th>Identifiant</th><th>Rôle</th><th>Origine</th><th>Créé le</th><th>Créé par</th><th></th></tr></thead><tbody>
    <?php foreach ($L['users'] as $u): $self = strcasecmp($u['username'], $me) === 0; ?>
      <tr>
        <td><strong><?= h($u['username']) ?></strong><?= $self ? ' <span class="muted">(vous)</span>' : '' ?></td>
        <td><?= h($u['role']) ?></td>
        <td><?= $u['source'] === 'env' ? '<span class="muted">Configuration serveur</span>' : 'Base de données' ?></td>
        <td class="muted"><?= $u['created_at'] ? h($u['created_at']) . ' UTC' : '—' ?></td>
        <td class="muted"><?= h($u['created_by'] ?: '—') ?></td>
        <td style="text-align:right">
          <?php if ($u['source'] === 'db' && !$self): ?>
          <form method="post" onsubmit="return confirm('Supprimer l\'utilisateur « <?= h(addslashes($u['username'])) ?> » ? Il perdra immédiatement l\'accès.')" style="display:inline">
            <?= Auth::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="username" value="<?= h($u['username']) ?>">
            <button class="dv-btn" style="color:#e53e3e" title="Supprimer"><i class="bi bi-trash"></i> Supprimer</button>
          </form>
          <?php else: ?><span class="muted"><?= $self ? '—' : 'verrouillé' ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>

<div class="dv-card" id="addBox" <?= ($error && ($_POST['action'] ?? '') === 'create') ? '' : 'hidden' ?>>
  <div class="dv-card-header">Ajouter un utilisateur</div>
  <form method="post" autocomplete="off" style="max-width:460px"><?= Auth::csrfField() ?><input type="hidden" name="action" value="create">
    <div class="dv-form-group"><label class="dv-label" for="nu">Identifiant</label><input class="dv-input" id="nu" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._@\-]+" value="<?= h($form['username']) ?>" autocomplete="off"></div>
    <div class="dv-form-group"><label class="dv-label" for="nr">Rôle</label>
      <select class="dv-input" id="nr" name="role"><?php foreach (Auth::ROLES as $r): ?><option value="<?= h($r) ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= h($r) ?> — <?= h($roleDesc[$r]) ?></option><?php endforeach; ?></select></div>
    <div class="dv-form-group"><label class="dv-label" for="np">Mot de passe (<?= UserService::MIN_PASSWORD ?> caractères minimum)</label><input class="dv-input" id="np" name="password" type="password" required minlength="<?= UserService::MIN_PASSWORD ?>" maxlength="1024" autocomplete="new-password"></div>
    <div class="dv-form-group"><label class="dv-label" for="nc">Confirmer le mot de passe</label><input class="dv-input" id="nc" name="confirm" type="password" required minlength="<?= UserService::MIN_PASSWORD ?>" maxlength="1024" autocomplete="new-password"></div>
    <div style="display:flex;gap:8px;margin-top:8px"><button class="dv-btn dv-btn-primary">Créer l'utilisateur</button><button type="button" class="dv-btn" onclick="document.getElementById('addBox').hidden=true">Annuler</button></div>
  </form>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
