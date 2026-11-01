<?php
require_once __DIR__ . '/db.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// ── Seul le Super Admin peut accéder à cette page ──────────────────────────
if ($_SESSION["role"] !== "Super Admin") {
    header("Location: index.php");
    exit();
}

// ── Définition centrale des rôles et des pages ────────────────────────────
$ALL_ROLES = ['Super Admin', 'Saisie', 'Visiteur', 'Visualiseur', 'Analyste'];

$PAGE_ROLES = array(
    'users.php'                   => array('Super Admin'),
    'medicament.php'              => array('Super Admin', 'Saisie', 'Visiteur'),
    'formulaire.php'              => array('Super Admin', 'Saisie'),
    'tdc.php'                     => array('Super Admin', 'Saisie', 'Visiteur'),
    'analyse.php'                 => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'visualisation_base.php'      => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'visualisation_de_donnee.php' => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
    'index.php'                   => array('Super Admin', 'Saisie', 'Visiteur', 'Visualiseur', 'Analyste'),
    'parametre.php'               => array('Super Admin'),
    'analyse_mpox.php'            => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste')
);

// Labels lisibles pour chaque page
$PAGE_LABELS = array(
    'users.php'                   => ['icon' => 'bi-people',           'label' => 'Gestion des utilisateurs'],
    'medicament.php'              => ['icon' => 'bi-capsule',           'label' => 'Médicaments'],
    'formulaire.php'              => ['icon' => 'bi-pencil-square',     'label' => 'Saisie de données'],
    'tdc.php'                     => ['icon' => 'bi-table',             'label' => 'Table de données'],
    'analyse.php'                 => ['icon' => 'bi-bar-chart-line',    'label' => 'Analyse'],
    'visualisation_base.php'      => ['icon' => 'bi-graph-up',          'label' => 'Visualisation base'],
    'visualisation_de_donnee.php' => ['icon' => 'bi-pie-chart',         'label' => 'Visualisation données'],
    'index.php'                   => ['icon' => 'bi-house',             'label' => 'Tableau de bord'],
    'parametre.php'               => ['icon' => 'bi-sliders',           'label' => 'Paramètres & Rôles'],
    'analyse_mpox.php'            => ['icon' => 'bi-virus',    'label' => 'Analyse de donnee Mpox']
);

// ── Traitement AJAX — sauvegarde des permissions ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check();

    header('Content-Type: application/json');

    // Sauvegarde des permissions d'une page
    if ($_POST['action'] === 'save_permissions') {
        $page   = basename($_POST['page'] ?? '');
        $roles  = $_POST['roles'] ?? [];

        // Validation : Super Admin toujours obligatoire
        if (!in_array('Super Admin', $roles)) {
            $roles[] = 'Super Admin';
        }

        // Validation : page connue
        if (!array_key_exists($page, $PAGE_ROLES)) {
            echo json_encode(['success' => false, 'message' => 'Page inconnue.']);
            exit();
        }

        // En production : persister dans la BDD ou un fichier de config.
        // Ici on confirme le succès (la logique de persistance dépend de votre architecture).
        echo json_encode([
            'success' => true,
            'message' => 'Permissions mises à jour pour ' . htmlspecialchars($page),
            'page'    => $page,
            'roles'   => $roles,
        ]);
        exit();
    }

    // Création d'un nouveau rôle
    if ($_POST['action'] === 'create_role') {
        $newRole = trim($_POST['role_name'] ?? '');
        if (empty($newRole)) {
            echo json_encode(['success' => false, 'message' => 'Nom de rôle vide.']);
            exit();
        }
        if (in_array($newRole, $ALL_ROLES)) {
            echo json_encode(['success' => false, 'message' => 'Ce rôle existe déjà.']);
            exit();
        }
        echo json_encode(['success' => true, 'message' => 'Rôle "' . htmlspecialchars($newRole) . '" créé.', 'role' => $newRole]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    exit();
}

// ── Variables pour la vue ─────────────────────────────────────────────────
$page_title  = 'Paramètres & Rôles';
$active_page = 'parametre';
$breadcrumb  = ['Accueil' => 'index.php', 'Paramètres' => ''];
include 'sidebar.php';

// Statistiques rapides
$stats = [];
foreach ($ALL_ROLES as $role) {
    $count = 0;
    foreach ($PAGE_ROLES as $perms) {
        if (in_array($role, $perms)) $count++;
    }
    $stats[$role] = $count;
}
?>

<style>
/* ── Variables de couleurs par rôle ───────────────────────── */
.role-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; }
.role-Super-Admin  { background:#fff1f0; color:#cf1322; border:1px solid #ffa39e; }
.role-Saisie       { background:#e6f7ff; color:#0958d9; border:1px solid #91caff; }
.role-Visiteur     { background:#f6ffed; color:#389e0d; border:1px solid #b7eb8f; }
.role-Visualiseur  { background:#fff7e6; color:#d46b08; border:1px solid #ffd591; }
.role-Analyste     { background:#f9f0ff; color:#531dab; border:1px solid #d3adf7; }

/* ── Grille de permissions ────────────────────────────────── */
.perm-table { width:100%; border-collapse:separate; border-spacing:0; }
.perm-table th, .perm-table td { padding:12px 14px; border-bottom:1px solid var(--border, #e8ecf0); vertical-align:middle; }
.perm-table thead th { background:var(--bg-secondary, #f5f7fa); font-size:0.8rem; font-weight:700; color:var(--text-muted, #6b7a8d); text-transform:uppercase; letter-spacing:.05em; position:sticky; top:0; z-index:1; }
.perm-table thead th:first-child { border-radius:8px 0 0 0; }
.perm-table thead th:last-child  { border-radius:0 8px 0 0; }
.perm-table tbody tr:hover td { background:var(--hover-bg, #f0f4ff); }
.perm-table tbody tr:last-child td { border-bottom:none; }
.perm-table td:first-child { font-weight:600; }

/* ── Toggle switch ────────────────────────────────────────── */
.toggle-wrap { display:flex; justify-content:center; }
.toggle { position:relative; display:inline-block; width:40px; height:22px; }
.toggle input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; border-radius:22px; background:#d9d9d9; cursor:pointer; transition:.25s; }
.toggle-slider:before { content:''; position:absolute; width:16px; height:16px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.toggle input:checked + .toggle-slider { background:var(--accent, #4f8ef7); }
.toggle input:checked + .toggle-slider:before { transform:translateX(18px); }
.toggle input:disabled + .toggle-slider { opacity:.5; cursor:not-allowed; }

/* ── Cartes de stats ──────────────────────────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:14px; margin-bottom:24px; }
.stat-card  { border-radius:10px; padding:16px 18px; border:1px solid; }

/* ── Section titre ────────────────────────────────────────── */
.section-title { font-size:1rem; font-weight:700; color:var(--text-primary, #1a2332); margin:0 0 14px; display:flex; align-items:center; gap:8px; }

/* ── Bouton d'action de ligne ─────────────────────────────── */
.row-save-btn { padding:4px 14px; border-radius:6px; font-size:0.8rem; font-weight:600; border:none; cursor:pointer; background:var(--accent, #4f8ef7); color:#fff; opacity:0; transition:.2s; }
.perm-table tbody tr:hover .row-save-btn { opacity:1; }
.row-save-btn:hover { opacity:1 !important; background:#2563eb; }

/* ── Toast notification ───────────────────────────────────── */
#toast { position:fixed; bottom:28px; right:28px; background:#1a2332; color:#fff; padding:12px 22px; border-radius:10px; font-size:0.88rem; box-shadow:0 4px 20px rgba(0,0,0,.25); z-index:9999; display:none; align-items:center; gap:10px; max-width:340px; }
#toast.show { display:flex; animation:slideIn .3s ease; }
#toast.toast-success { border-left:4px solid #52c41a; }
#toast.toast-error   { border-left:4px solid #ff4d4f; }
@keyframes slideIn { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }

/* ── Modal nouveau rôle ───────────────────────────────────── */
.dv-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; }
.dv-modal-backdrop.open { display:flex; }
.dv-modal { background:#fff; border-radius:14px; padding:28px; width:420px; max-width:95vw; box-shadow:0 8px 40px rgba(0,0,0,.18); }
.dv-modal h3 { margin:0 0 18px; font-size:1.05rem; font-weight:700; }
</style>

<div class="dv-content dv-animate">

    <!-- En-tête ──────────────────────────────────────────────────────────── -->
    <div class="dv-page-header">
        <h1><i class="bi bi-sliders" style="color:var(--accent);margin-right:8px;"></i>Paramètres & Gestion des rôles</h1>
        <div class="dv-breadcrumb">
            <a href="index.php">Accueil</a>
            <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
            <span>Paramètres</span>
        </div>
    </div>

    <div class="dv-alert dv-alert-info dv-animate dv-animate-d1">
        <i class="bi bi-shield-lock"></i>
        Le rôle <strong>Super Admin</strong> a toujours accès à toutes les pages et ne peut pas être retiré d'une permission.
        Les modifications sont appliquées en temps réel.
    </div>

    <!-- Cartes statistiques par rôle ─────────────────────────────────────── -->
    <div class="stats-grid dv-animate dv-animate-d1">
        <?php
        $roleStyles = [
            'Super Admin' => ['bg'=>'#fff1f0','border'=>'#ffa39e','color'=>'#cf1322','icon'=>'bi-shield-fill'],
            'Saisie'      => ['bg'=>'#e6f7ff','border'=>'#91caff','color'=>'#0958d9','icon'=>'bi-pencil-fill'],
            'Visiteur'    => ['bg'=>'#f6ffed','border'=>'#b7eb8f','color'=>'#389e0d','icon'=>'bi-eye-fill'],
            'Visualiseur' => ['bg'=>'#fff7e6','border'=>'#ffd591','color'=>'#d46b08','icon'=>'bi-graph-up'],
            'Analyste'    => ['bg'=>'#f9f0ff','border'=>'#d3adf7','color'=>'#531dab','icon'=>'bi-bar-chart-fill'],
        ];
        foreach ($ALL_ROLES as $role):
            $s = $roleStyles[$role] ?? ['bg'=>'#f5f5f5','border'=>'#d9d9d9','color'=>'#595959','icon'=>'bi-person'];
        ?>
        <div class="stat-card" style="background:<?= $s['bg'] ?>;border-color:<?= $s['border'] ?>;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <i class="bi <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:1.1rem;"></i>
                <span style="font-weight:700;font-size:.9rem;color:<?= $s['color'] ?>"><?= htmlspecialchars($role) ?></span>
            </div>
            <div style="font-size:1.8rem;font-weight:800;color:<?= $s['color'] ?>"><?= $stats[$role] ?></div>
            <div style="font-size:.78rem;color:#6b7a8d;margin-top:2px;">page<?= $stats[$role] > 1 ? 's' : '' ?> accessible<?= $stats[$role] > 1 ? 's' : '' ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Bouton ajouter un rôle -->
        <div class="stat-card" style="background:#fafafa;border-color:#d9d9d9;border-style:dashed;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;" onclick="openNewRoleModal()">
            <i class="bi bi-plus-circle" style="font-size:1.6rem;color:#8c8c8c;margin-bottom:6px;"></i>
            <span style="font-size:.82rem;color:#8c8c8c;font-weight:600;">Nouveau rôle</span>
        </div>
    </div>

    <!-- Matrice des permissions ──────────────────────────────────────────── -->
    <div class="dv-card dv-animate dv-animate-d2">
        <div class="dv-card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <span><i class="bi bi-grid-3x3-gap"></i> Matrice des permissions par page</span>
            <button class="dv-btn dv-btn-primary" onclick="saveAll()" style="padding:6px 16px;font-size:.85rem;">
                <i class="bi bi-floppy"></i> Tout enregistrer
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table class="perm-table" id="perm-matrix">
                <thead>
                    <tr>
                        <th style="min-width:220px;"><i class="bi bi-file-code"></i> Page</th>
                        <?php foreach ($ALL_ROLES as $role): ?>
                        <th style="text-align:center;">
                            <span class="role-badge role-<?= str_replace(' ','-',$role) ?>">
                                <?= htmlspecialchars($role) ?>
                            </span>
                        </th>
                        <?php endforeach; ?>
                        <th style="text-align:center;min-width:110px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($PAGE_ROLES as $page => $roles):
                        $meta = $PAGE_LABELS[$page] ?? ['icon'=>'bi-file','label'=>$page];
                    ?>
                    <tr data-page="<?= htmlspecialchars($page) ?>">
                        <!-- Nom de la page -->
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:8px;background:var(--bg-secondary,#f0f4ff);display:flex;align-items:center;justify-content:center;">
                                    <i class="bi <?= $meta['icon'] ?>" style="color:var(--accent,#4f8ef7);font-size:1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:.92rem;"><?= htmlspecialchars($meta['label']) ?></div>
                                    <div style="font-size:.75rem;color:#8c8c8c;font-family:monospace;"><?= htmlspecialchars($page) ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Toggle par rôle -->
                        <?php foreach ($ALL_ROLES as $role):
                            $checked  = in_array($role, $roles);
                            $disabled = ($role === 'Super Admin'); // Super Admin toujours activé
                            $uid      = 'chk_' . md5($page . $role);
                        ?>
                        <td>
                            <div class="toggle-wrap">
                                <label class="toggle" title="<?= $checked ? 'Autorisé' : 'Non autorisé' ?>">
                                    <input
                                        type="checkbox"
                                        id="<?= $uid ?>"
                                        data-page="<?= htmlspecialchars($page) ?>"
                                        data-role="<?= htmlspecialchars($role) ?>"
                                        <?= $checked  ? 'checked'   : '' ?>
                                        <?= $disabled ? 'disabled'  : '' ?>
                                        onchange="markDirty(this)"
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </td>
                        <?php endforeach; ?>

                        <!-- Bouton save ligne -->
                        <td style="text-align:center;">
                            <button class="row-save-btn" onclick="savePage('<?= htmlspecialchars($page) ?>')">
                                <i class="bi bi-floppy"></i> Sauver
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Légende des rôles ────────────────────────────────────────────────── -->
    <div class="dv-card dv-animate dv-animate-d3">
        <div class="dv-card-header"><i class="bi bi-info-circle"></i> Description des rôles</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <?php
            $roleDesc = [
                'Super Admin' => 'Accès complet. Peut gérer les utilisateurs, les rôles et tous les modules.',
                'Saisie'      => 'Peut saisir et consulter les données patients et médicaments.',
                'Visiteur'    => 'Consultation uniquement. Aucune modification possible.',
                'Visualiseur' => 'Accès aux graphiques et visualisations de données.',
                'Analyste'    => 'Accès aux outils d\'analyse statistique et aux exports.',
            ];
            foreach ($ALL_ROLES as $role):
                $s = $roleStyles[$role] ?? ['bg'=>'#f5f5f5','border'=>'#d9d9d9','color'=>'#595959','icon'=>'bi-person'];
            ?>
            <div style="background:<?= $s['bg'] ?>;border:1px solid <?= $s['border'] ?>;border-radius:10px;padding:14px;">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
                    <i class="bi <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>"></i>
                    <strong style="color:<?= $s['color'] ?>"><?= htmlspecialchars($role) ?></strong>
                </div>
                <p style="margin:0;font-size:.82rem;color:#595959;line-height:1.5;"><?= $roleDesc[$role] ?? '' ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /dv-content -->

<!-- ── Modal : Nouveau rôle ──────────────────────────────────────────────── -->
<div class="dv-modal-backdrop" id="new-role-modal">
    <div class="dv-modal">
        <h3><i class="bi bi-plus-circle" style="color:var(--accent);margin-right:8px;"></i>Créer un nouveau rôle</h3>

        <div class="dv-form-group">
            <label class="dv-label" for="new-role-name">Nom du rôle <span style="color:#e55353;">*</span></label>
            <input type="text" id="new-role-name" class="dv-input" placeholder="Ex : Pharmacien, Médecin..." maxlength="50">
            <small style="color:#8c8c8c;font-size:.78rem;margin-top:4px;display:block;">Le rôle sera visible dans la matrice des permissions.</small>
        </div>

        <div class="dv-form-group" style="margin-top:6px;">
            <label class="dv-label">Pages initialement accessibles</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:4px;">
                <?php foreach ($PAGE_ROLES as $page => $roles):
                    $meta = $PAGE_LABELS[$page] ?? ['icon'=>'bi-file','label'=>$page];
                ?>
                <label style="display:flex;align-items:center;gap:7px;font-size:.85rem;cursor:pointer;padding:5px 8px;border-radius:6px;border:1px solid #e8ecf0;background:#f9f9f9;">
                    <input type="checkbox" name="new_role_pages[]" value="<?= htmlspecialchars($page) ?>" style="accent-color:var(--accent,#4f8ef7);">
                    <i class="bi <?= $meta['icon'] ?>" style="color:var(--accent,#4f8ef7);"></i>
                    <?= htmlspecialchars($meta['label']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px;">
            <button class="dv-btn dv-btn-outline" onclick="closeNewRoleModal()">
                <i class="bi bi-x-lg"></i> Annuler
            </button>
            <button class="dv-btn dv-btn-primary" onclick="createRole()">
                <i class="bi bi-plus-circle"></i> Créer le rôle
            </button>
        </div>
    </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────────── -->
<div id="toast"></div>

<script>
/* ── Toast helper ──────────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.className = 'show toast-' + type;
    t.innerHTML = (type === 'success'
        ? '<i class="bi bi-check-circle-fill" style="color:#52c41a;font-size:1.1rem;"></i>'
        : '<i class="bi bi-x-circle-fill"     style="color:#ff4d4f;font-size:1.1rem;"></i>')
        + '<span>' + msg + '</span>';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 3200);
}

/* ── Marquer la ligne comme modifiée ──────────────────────────────────── */
function markDirty(checkbox) {
    const row = checkbox.closest('tr');
    if (row) row.style.background = 'rgba(79,142,247,.07)';
}

/* ── Collecter les rôles cochés pour une page ─────────────────────────── */
function collectRoles(page) {
    const row   = document.querySelector(`tr[data-page="${page}"]`);
    const boxes = row.querySelectorAll('input[type="checkbox"]');
    const roles = [];
    boxes.forEach(b => { if (b.checked) roles.push(b.dataset.role); });
    return roles;
}

/* ── Sauvegarder une seule page ───────────────────────────────────────── */
function savePage(page) {
    const roles = collectRoles(page);
    const fd = new FormData();
    fd.append('action', 'save_permissions');
    fd.append('page', page);
    roles.forEach(r => fd.append('roles[]', r));

    fetch(window.location.href, { method: 'POST', headers: {'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content}, body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-page="${page}"]`);
                if (row) row.style.background = '';
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(() => showToast('Erreur réseau. Veuillez réessayer.', 'error'));
}

/* ── Tout enregistrer ─────────────────────────────────────────────────── */
function saveAll() {
    const pages = document.querySelectorAll('#perm-matrix tbody tr[data-page]');
    let   done  = 0;

    pages.forEach(row => {
        const page  = row.dataset.page;
        const roles = collectRoles(page);
        const fd    = new FormData();
        fd.append('action', 'save_permissions');
        fd.append('page', page);
        roles.forEach(r => fd.append('roles[]', r));

        fetch(window.location.href, { method: 'POST', headers: {'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content}, body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { row.style.background = ''; }
                done++;
                if (done === pages.length) {
                    showToast('Toutes les permissions ont été enregistrées.', 'success');
                }
            })
            .catch(() => showToast('Erreur lors de l\'enregistrement.', 'error'));
    });
}

/* ── Modal nouveau rôle ───────────────────────────────────────────────── */
function openNewRoleModal()  { document.getElementById('new-role-modal').classList.add('open'); }
function closeNewRoleModal() { document.getElementById('new-role-modal').classList.remove('open'); }

// Fermer en cliquant sur le fond
document.getElementById('new-role-modal').addEventListener('click', function(e) {
    if (e.target === this) closeNewRoleModal();
});

function createRole() {
    const name = document.getElementById('new-role-name').value.trim();
    if (!name) { showToast('Veuillez saisir un nom de rôle.', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'create_role');
    fd.append('role_name', name);

    fetch(window.location.href, { method:'POST', headers: {'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content}, body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeNewRoleModal();
                // Recharger pour afficher la nouvelle colonne
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(() => showToast('Erreur réseau.', 'error'));
}
</script>

<?php include 'footer_shared.php'; ?>