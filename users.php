<?php
// ═══════════════════════════════════════════════════════════════════
//  users.php  —  Gestion utilisateurs DVSSER
//  Compatible PHP 7.4+  |  Pas de match()  |  Erreurs bien gérées
// ═══════════════════════════════════════════════════════════════════
session_start();

// ── 1. Authentification ───────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Lire le rôle sans l'écraser
$current_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// ── 2. Connexion BDD ──────────────────────────────────────────────
$conn = @new mysqli(
    'sql209.infinityfree.com',
    'if0_41511897',
    'jR9A6jlS7c8f8r',
    'if0_41511897_pharmacie'
);
if ($conn->connect_error) {
    die("Erreur connexion BDD : " . htmlspecialchars($conn->connect_error));
}
$conn->set_charset('utf8mb4');

// ── 3. Contrôle d'accès ───────────────────────────────────────────
$access_denied = ($current_role !== 'Super Admin');

// ── 4. Variables sidebar ──────────────────────────────────────────
$page_title  = 'Administration';
$active_page = 'users_settings';
$breadcrumb  = array('Administration', 'Utilisateurs');

// ── 5. Traitement POST ────────────────────────────────────────────
$feedback = null;

if (!$access_denied && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $prenom = trim($_POST['prenom']);
        $nom    = trim($_POST['nom']);
        $email  = trim($_POST['email']);
        $role   = trim($_POST['role']);
        $statut = trim($_POST['statut']);
        $pass   = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $by     = (int)$_SESSION['user_id'];

        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($chk) {
            $chk->bind_param("s", $email);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $feedback = array('type'=>'error','msg'=>'Cet email est deja utilise.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO users (username, prenom, nom, email, role, mdp, statut, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if ($stmt) {
                   $stmt->bind_param("sssssssi", $username, $prenom, $nom, $email, $role, $pass, $statut, $by);
                    if ($stmt->execute()) {
                        $feedback = array('type'=>'success','msg'=>"Compte de $prenom $nom cree avec succes.");
                    } else {
                        $feedback = array('type'=>'error','msg'=>"Erreur : " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
            $chk->close();
        }

    } elseif (isset($_POST['edit_user'])) {
        $id     = (int)$_POST['edit_id'];
        $username = trim($_POST['edit_username']);
        $prenom = trim($_POST['edit_prenom']);
        $nom    = trim($_POST['edit_nom']);
        $email  = trim($_POST['edit_email']);
        $role   = trim($_POST['edit_role']);
        $statut = trim($_POST['edit_statut']);

        if (!empty($_POST['edit_password'])) {
            $pass = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users SET username=?, prenom=?, nom=?, email=?, role=?, mdp=?, statut=? WHERE id=?"
            );
            if ($stmt) {$stmt->bind_param("sssssssi", $username, $prenom, $nom, $email, $role, $pass, $statut, $id);; $stmt->execute(); $stmt->close(); }
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET username=?, prenom=?, nom=?, email=?, role=?, statut=? WHERE id=?"
            );
            if ($stmt) { $stmt->bind_param("sssssi",$username,$prenom,$nom,$email,$role,$statut,$id); $stmt->execute(); $stmt->close(); }
        }
        $feedback = array('type'=>'success','msg'=>"Utilisateur mis a jour.");

    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['delete_id'];
        if ($id === (int)$_SESSION['user_id']) {
            $feedback = array('type'=>'error','msg'=>"Impossible de supprimer votre propre compte.");
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            if ($stmt) { $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close(); }
            $feedback = array('type'=>'success','msg'=>"Utilisateur supprime.");
        }

    } elseif (isset($_POST['toggle_statut'])) {
        $id         = (int)$_POST['toggle_id'];
        $new_statut = (trim($_POST['new_statut']) === 'Actif') ? 'Actif' : 'Inactif';
        $stmt = $conn->prepare("UPDATE users SET statut=? WHERE id=?");
        if ($stmt) { $stmt->bind_param("si",$new_statut,$id); $stmt->execute(); $stmt->close(); }
        $feedback = array('type'=>'success','msg'=>"Statut mis a jour.");
    }
}

// ── 6. Lecture des donnees ────────────────────────────────────────
$rows        = array();
$stats       = array('total'=>0,'actifs'=>0,'inactifs'=>0);
$search      = trim(isset($_GET['q'])           ? $_GET['q']           : '');
$filter_role = trim(isset($_GET['role_filter']) ? $_GET['role_filter'] : '');

if (!$access_denied) {
    $rs = $conn->query(
        "SELECT COUNT(*) AS total,
                SUM(statut='Actif')    AS actifs,
                SUM(statut='Inactif')  AS inactifs
         FROM users"
    );
    if ($rs) { $stats = $rs->fetch_assoc(); }

    // Verifier si derniere_connexion existe
    $dc_check = $conn->query("SHOW COLUMNS FROM users LIKE 'derniere_connexion'");
    $has_dc   = ($dc_check && $dc_check->num_rows > 0);

    $sql    = "SELECT user_id, prenom, nom, email, role, statut";
    if ($has_dc) $sql .= ", derniere_connexion";
    $sql .= " FROM users WHERE 1";

    $params = array();
    $types  = '';
    if ($search !== '') {
        $s      = "%$search%";
        $sql   .= " AND (username LIKE ? OR prenom LIKE ? OR nom LIKE ? OR email LIKE ?)";
        $params = array($s, $s, $s, $s);
        $types  = 'ssss';
    }
    if ($filter_role !== '') {
        $sql     .= " AND role = ?";
        $params[] = $filter_role;
        $types   .= 's';
    }
    $sql .= " ORDER BY user_id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($u = $result->fetch_assoc()) {
            if (!$has_dc) $u['derniere_connexion'] = null;
            $rows[] = $u;
        }
        $stmt->close();
    }
}

// ── Helper badge role ─────────────────────────────────────────────
function role_badge_class($role) {
    $map = array(
        'Super Admin'=>'badge-role-sa',
        'Saisie' =>'badge-role-ph',
        'Visiteur'   =>'badge-role-ca',
        'Visualiseur'=>'badge-role-pr',
        'Analyste'   =>'badge-role-an',
    );
    return isset($map[$role]) ? $map[$role] : 'badge-role-an';
}

// ── Sidebar APRES tout le PHP ─────────────────────────────────────
include 'sidebar.php';
?>
<style>
:root{
  --accent:#0a6cbc;--accent-dark:#0556a4;--accent-soft:#e8f2fd;
  --success:#16a34a;--success-soft:#dcfce7;
  --danger:#dc2626;--danger-soft:#fee2e2;
  --text-primary:#0a1628;--text-muted:#64748b;
  --border:#e2e8f0;--bg-card:#fff;
  --shadow-soft:0 2px 16px rgba(0,0,0,.06);
  --radius:16px;
}
.welcome-banner{
  background:linear-gradient(135deg,var(--accent) 0%,#0556a4 100%);
  border-radius:var(--radius);padding:26px 30px;color:#fff;
  position:relative;margin-bottom:22px;overflow:hidden;
  box-shadow:0 8px 24px rgba(10,108,188,.22);
}
.welcome-banner h2{margin:0 0 5px;font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;}
.welcome-banner p{margin:0;opacity:.88;font-size:.86rem;}
.wb-icon{position:absolute;right:22px;top:50%;transform:translateY(-50%);font-size:3.8rem;opacity:.11;}
.denied-box{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:48px;text-align:center;margin-top:24px;}
.denied-box .d-icon{font-size:3rem;margin-bottom:14px;}
.denied-box h3{font-family:'Syne',sans-serif;font-size:1.1rem;margin-bottom:8px;color:var(--text-primary);}
.denied-box p{color:var(--text-muted);font-size:.88rem;}
.stats-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.stat-chip{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:13px 18px;display:flex;align-items:center;gap:11px;box-shadow:var(--shadow-soft);flex:1;min-width:120px;}
.stat-chip .val{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;color:var(--text-primary);}
.stat-chip .lbl{font-size:.74rem;color:var(--text-muted);}
.dot{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.dot-blue{background:var(--accent-soft);}
.dot-green{background:var(--success-soft);}
.dot-red{background:var(--danger-soft);}
.toolbar{display:flex;gap:9px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.search-box{display:flex;align-items:center;gap:7px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:0 13px;flex:1;min-width:170px;box-shadow:var(--shadow-soft);}
.search-box input{border:none;outline:none;padding:10px 0;font-size:.86rem;width:100%;background:transparent;color:var(--text-primary);}
.filter-select{padding:10px 13px;border:1px solid var(--border);border-radius:12px;font-size:.86rem;background:var(--bg-card);color:var(--text-primary);box-shadow:var(--shadow-soft);cursor:pointer;}
.btn-primary{background:var(--accent);color:#fff;border:none;padding:10px 16px;border-radius:12px;font-weight:600;font-size:.86rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:background .2s;}
.btn-primary:hover{background:var(--accent-dark);}
.feedback{border-radius:12px;padding:11px 16px;margin-bottom:16px;font-size:.86rem;font-weight:500;display:flex;align-items:center;gap:9px;}
.feedback.success{background:var(--success-soft);color:var(--success);border:1px solid #bbf7d0;}
.feedback.error{background:var(--danger-soft);color:var(--danger);border:1px solid #fecaca;}
.table-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-soft);overflow-x:auto;}
.table-card table{width:100%;border-collapse:collapse;min-width:580px;}
.table-card thead th{padding:11px 15px;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);background:#f8fafc;border-bottom:1px solid var(--border);white-space:nowrap;}
.table-card tbody tr:hover{background:#f8fafc;}
.table-card tbody td{padding:11px 15px;font-size:.84rem;color:var(--text-primary);border-bottom:1px solid var(--border);vertical-align:middle;}
.table-card tbody tr:last-child td{border-bottom:none;}
.avatar{width:33px;height:33px;border-radius:9px;background:var(--accent-soft);color:var(--accent);font-weight:700;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.user-cell{display:flex;align-items:center;gap:9px;}
.user-name{font-weight:600;font-size:.85rem;}
.user-email{font-size:.75rem;color:var(--text-muted);}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:7px;font-size:.74rem;font-weight:600;white-space:nowrap;}
.badge-role-sa{background:#ede9fe;color:#6d28d9;}
.badge-role-ph{background:#dbeafe;color:#1d4ed8;}
.badge-role-ca{background:#fef9c3;color:#a16207;}
.badge-role-pr{background:#d1fae5;color:#065f46;}
.badge-role-an{background:#fce7f3;color:#be185d;}
.badge-actif{background:var(--success-soft);color:var(--success);}
.badge-inactif{background:var(--danger-soft);color:var(--danger);}
.action-btns{display:flex;gap:4px;}
.btn-icon{width:29px;height:29px;border-radius:7px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:background .15s;}
.btn-edit{background:#eff6ff;color:#2563eb;}
.btn-edit:hover{background:#dbeafe;}
.btn-del{background:#fff1f2;color:#e11d48;}
.btn-del:hover{background:#ffe4e6;}
.btn-tog{background:#f0fdf4;color:#15803d;}
.btn-tog:hover{background:#dcfce7;}
.btn-tog.off{background:#fefce8;color:#a16207;}
.btn-tog.off:hover{background:#fef9c3;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,22,40,.5);backdrop-filter:blur(3px);z-index:9999;align-items:center;justify-content:center;padding:16px;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:18px;padding:26px;width:min(490px,100%);box-shadow:0 20px 60px rgba(0,0,0,.17);animation:mIn .22s ease;max-height:90vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(8px)}}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.modal-title{font-family:'Syne',sans-serif;font-size:.98rem;font-weight:800;color:var(--text-primary);}
.modal-close{background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--text-muted);width:29px;height:29px;border-radius:7px;display:flex;align-items:center;justify-content:center;}
.modal-close:hover{background:#f1f5f9;}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.form-group{display:flex;flex-direction:column;gap:4px;}
.form-group label{font-size:.74rem;font-weight:600;color:var(--text-muted);}
.form-group input,.form-group select{padding:9px 11px;border:1.5px solid var(--border);border-radius:9px;font-size:.85rem;color:var(--text-primary);outline:none;transition:border-color .15s;font-family:inherit;}
.form-group input:focus,.form-group select:focus{border-color:var(--accent);}
.form-group.full{grid-column:1/-1;}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;}
.btn-cancel{background:#f1f5f9;color:var(--text-muted);border:none;padding:9px 15px;border-radius:9px;font-weight:600;cursor:pointer;font-size:.85rem;}
.btn-cancel:hover{background:#e2e8f0;}
.btn-danger{background:var(--danger);color:#fff;border:none;padding:9px 15px;border-radius:9px;font-weight:600;cursor:pointer;font-size:.85rem;}
.btn-danger:hover{background:#b91c1c;}
.empty-state{padding:44px 20px;text-align:center;color:var(--text-muted);}
.empty-state .es-icon{font-size:2rem;margin-bottom:9px;}
.empty-state p{font-size:.85rem;}
.perm-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-soft);margin-top:22px;padding:20px;overflow-x:auto;}
.perm-title{font-family:'Syne',sans-serif;font-size:.9rem;font-weight:800;color:var(--text-primary);margin-bottom:13px;}
.perm-table{width:100%;border-collapse:collapse;font-size:.79rem;min-width:500px;}
.perm-table th{padding:7px 11px;background:#f8fafc;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);text-align:left;}
.perm-table td{padding:8px 11px;border-bottom:1px solid var(--border);}
.perm-table tr:last-child td{border-bottom:none;}
.perm-check{color:var(--success);}
.perm-cross{color:#cbd5e1;}
@media(max-width:600px){
  .modal-grid{grid-template-columns:1fr;}
  .stats-bar{flex-direction:column;}
}
</style>

<div class="dv-content dv-animate">

  <div class="welcome-banner">
    <h2>Gestion des utilisateurs</h2>
    <p>Administration des comptes, roles et droits d'acces — Plateforme DVSSER</p>
    <i class="bi bi-shield-lock-fill wb-icon"></i>
  </div>

  <?php if ($access_denied): ?>
  <div class="denied-box">
    <div class="d-icon">&#128274;</div>
    <h3>Acces restreint</h3>
    <p>Votre role <strong><?= htmlspecialchars($current_role ?: 'inconnu') ?></strong>
       n'a pas acces a cette section.<br>Contactez un Super Admin.</p>
  </div>

  <?php else: ?>

  <?php if ($feedback): ?>
  <div class="feedback <?= htmlspecialchars($feedback['type']) ?>">
    <?= ($feedback['type']==='success') ? '&#10003;' : '&#9888;' ?>
    <?= htmlspecialchars($feedback['msg']) ?>
  </div>
  <?php endif; ?>

  <div class="stats-bar">
    <div class="stat-chip">
      <div class="dot dot-blue">&#128101;</div>
      <div><div class="val"><?= (int)$stats['total'] ?></div><div class="lbl">Total</div></div>
    </div>
    <div class="stat-chip">
      <div class="dot dot-green">&#10003;</div>
      <div><div class="val"><?= (int)$stats['actifs'] ?></div><div class="lbl">Actifs</div></div>
    </div>
    <div class="stat-chip">
      <div class="dot dot-red">&#128683;</div>
      <div><div class="val"><?= (int)$stats['inactifs'] ?></div><div class="lbl">Inactifs</div></div>
    </div>
  </div>

  <form method="GET" action="">
    <div class="toolbar">
      <div class="search-box">
        &#128269;
        <input type="text" name="q" placeholder="Rechercher nom, email..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <select class="filter-select" name="role_filter" onchange="this.form.submit()">
        <option value="">Tous les roles</option>
        <?php
        $all_roles = array('Super Admin','Saisie','Visiteur','Visualiseur','Analyste');
        foreach ($all_roles as $r):
        ?>
        <option value="<?= htmlspecialchars($r) ?>"
          <?= ($filter_role === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary">Filtrer</button>
      <button type="button" class="btn-primary" onclick="openCreate()">+ Nouveau</button>
    </div>
  </form>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Role</th>
          <th>Statut</th>
          <th>Derniere connexion</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <div class="es-icon">&#128269;</div>
            <p>Aucun utilisateur trouve<?= $search ? ' pour "'.htmlspecialchars($search).'"' : '' ?></p>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($rows as $u):
          $us  = isset($u['username']) ? $u['username'] : '';
          $p  = isset($u['prenom']) ? $u['prenom'] : '';
          $n  = isset($u['nom'])    ? $u['nom']    : '';
          $ini = mb_strtoupper(mb_substr($p,0,1,'UTF-8').mb_substr($n,0,1,'UTF-8'));
          $rc  = role_badge_class($u['role']);
          $act = ($u['statut'] === 'Actif');
          $tgl = $act ? 'Inactif' : 'Actif';
          $dc  = (!empty($u['derniere_connexion']))
                   ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : '&mdash;';
         $uj  = json_encode(array(
            'id'       => $u['user_id'] ?? '',
            'username' => $u['username'] ?? '',
            'prenom'   => $u['prenom'] ?? '',
            'nom'      => $u['nom'] ?? '',
            'email'    => $u['email'] ?? '',
            'role'     => $u['role'] ?? '',
            'status'   => $u['status'] ?? '',
        ), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
        ?>
        <tr>
          <td>
            <div class="user-cell">
              <div class="avatar"><?= htmlspecialchars($ini) ?></div>
              <div>
                <div class="user-name"><?= htmlspecialchars("$p $n") ?></div>
                <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $rc ?>"><?= htmlspecialchars($u['role']) ?></span></td>
          <td><span class="badge <?= $act ? 'badge-actif' : 'badge-inactif' ?>"><?= $act ? 'Actif' : 'Inactif' ?></span></td>
          <td><?= $dc ?></td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon btn-edit" title="Modifier"
                      onclick='openEdit(<?= $uj ?>)'>&#9998;</button>

              <form method="POST" action="" style="display:inline">
                <input type="hidden" name="toggle_id"   value="<?= (int)$u['user_id'] ?>">
                <input type="hidden" name="new_statut"  value="<?= htmlspecialchars($tgl) ?>">
                <button type="submit" name="toggle_statut"
                        class="btn-icon btn-tog <?= $act ? '' : 'off' ?>"
                        title="Passer <?= htmlspecialchars($tgl) ?>">
                  <?= $act ? '&#128308;' : '&#128994;' ?>
                </button>
              </form>

              <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
              <button type="button" class="btn-icon btn-del" title="Supprimer"
                      onclick="openDelete(<?= (int)$u['user_id'] ?>,'<?= htmlspecialchars(addslashes("$p $n")) ?>')">
                &#128465;
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="perm-card">
    <div class="perm-title">&#128203; Matrice des droits par role</div>
    <table class="perm-table">
      <thead>
        <tr>
          <th>Module</th>
          <th>Super Admin</th>
          <th>Saisie</th>
          <th>Visiteur</th>
          <th>Visualiseur</th>
          <th>Analyste</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $matrix = array(
        array('Dashboard',          1,1,1,1,1),
        array('Analyse',            1,1,0,1,0),
        array('TDC',                1,1,1,0,0),
        array('Carte',              1,1,0,0,0),
        array('Alertes',            1,1,0,0,1),
        array('Saisie de données',  1,1,0,0,0),
        array('Utilisateurs',       1,0,0,0,0),
        array('Parametres systeme', 1,0,0,0,0),
      );
      foreach ($matrix as $row):
        $pg = array_shift($row);
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($pg) ?></strong></td>
        <?php foreach ($row as $v): ?>
        <td><?= $v ? '<span class="perm-check">&#10003;</span>' : '<span class="perm-cross">&mdash;</span>' ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; // fin access_denied ?>
</div>


<!-- Modal Creer -->
<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">+ Nouvel utilisateur</div>
      <button class="modal-close" onclick="closeModal('modal-create')">&#10005;</button>
    </div>
    <form method="POST" action="">
      <div class="modal-grid">
        <div class="form-group">
          <label>Prenom *</label>
          <input type="text" name="prenom" required placeholder="Jean">
        </div>
        <div class="form-group">
          <label>Nom *</label>
          <input type="text" name="nom" required placeholder="Dupont">
        </div>
           <div class="form-group">
          <label>Nom d'utilisateur *</label>
          <input type="text" name="username" required placeholder="Jean352">
        </div>
        <div class="form-group full">
          <label>Email *</label>
          <input type="email" name="email" required placeholder="jean@pharma.mg">
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" required>
            <option value="Saisie">Saisie</option>
            <option value="Visiteur">Visiteur</option>
            <option value="Visualiseur">Visualiseur</option>
            <option value="Analyste">Analyste</option>
            <option value="Super Admin">Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select name="statut">
            <option value="Actif">Actif</option>
            <option value="Inactif">Inactif</option>
          </select>
        </div>
        <div class="form-group full">
          <label>Mot de passe temporaire *</label>
          <input type="password" name="password" required placeholder="Min. 8 caracteres">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-create')">Annuler</button>
        <button type="submit" name="create_user" class="btn-primary">Creer le compte</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Modifier l'utilisateur</div>
      <button class="modal-close" onclick="closeModal('modal-edit')">&#10005;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="edit_id" id="edit_id">
      <div class="modal-grid">
        <div class="form-group">
          <label>Nom d'utilisateur *</label>
          <input type="text" name="edit_username" id="edit_username" required>
        </div>
        <div class="form-group">
          <label>Prenom *</label>
          <input type="text" name="edit_prenom" id="edit_prenom" required>
        </div>
        <div class="form-group">
          <label>Nom *</label>
          <input type="text" name="edit_nom" id="edit_nom" required>
        </div>
        <div class="form-group full">
          <label>Email *</label>
          <input type="email" name="edit_email" id="edit_email" required>
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="edit_role" id="edit_role">
            <option value="Saisie">Saisie</option>
            <option value="Visiteur">Visiteur</option>
            <option value="Visualiseur">Visualiseur</option>
            <option value="Analyste">Analyste</option>
            <option value="Super Admin">Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select name="edit_statut" id="edit_statut">
            <option value="Actif">Actif</option>
            <option value="Inactif">Inactif</option>
          </select>
        </div>
        <div class="form-group full">
          <label>Nouveau mot de passe (laisser vide = inchange)</label>
          <input type="password" name="edit_password" placeholder="Nouveau mot de passe">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-edit')">Annuler</button>
        <button type="submit" name="edit_user" class="btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Supprimer -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Confirmer la suppression</div>
      <button class="modal-close" onclick="closeModal('modal-delete')">&#10005;</button>
    </div>
    <p style="color:var(--text-muted);font-size:.87rem;line-height:1.6;margin-bottom:18px">
      Voulez-vous supprimer le compte de <strong id="delete_name"></strong> ?
      Cette action est <strong>irreversible</strong>.
    </p>
    <form method="POST" action="">
      <input type="hidden" name="delete_id" id="delete_id">
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-delete')">Annuler</button>
        <button type="submit" name="delete_user" class="btn-danger">Supprimer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreate(){ document.getElementById('modal-create').classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openEdit(u){
  document.getElementById('edit_id').value     = u.id;
   document.getElementById('edit_username').value     = u.username;
  document.getElementById('edit_prenom').value = u.prenom;
  document.getElementById('edit_nom').value    = u.nom;
  document.getElementById('edit_email').value  = u.email;
  document.getElementById('edit_role').value   = u.role;
  document.getElementById('edit_statut').value = u.statut;
  document.getElementById('modal-edit').classList.add('open');
}
function openDelete(id, name){
  document.getElementById('delete_id').value         = id;
  document.getElementById('delete_name').textContent = name;
  document.getElementById('modal-delete').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(function(o){
  o.addEventListener('click', function(e){ if(e.target===o) o.classList.remove('open'); });
});
</script>

<?php include 'footer_shared.php'; ?>
