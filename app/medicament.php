<?php
require_once __DIR__ . '/db.php';
require_role(['Super Admin', 'Saisie']);
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// Connexion

$dernierIdentifiant = '';
$dernierNom = '';
$dernierPrenom = '';

$conn = db();
if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $lcid = (string)($_SESSION['last_client']['id'] ?? '');
    $sql = "SELECT Id, Nom, Prenom FROM client WHERE Id = " . (int)$lcid . " LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dernierIdentifiant = $row["Id"];
        $dernierNom  = $row["Nom"];
        $dernierPrenom = $row["Prenom"];
    }
    $conn->close();
}

$page_title  = 'Saisie médicaments';
$active_page = 'formulaire';
$breadcrumb  = ['Accueil' => 'index.php', 'Saisie médicaments' => ''];
include 'sidebar.php';
?>

    <div class="dv-content dv-animate">

        <div class="dv-page-header">
            <h1><i class="bi bi-capsule" style="color:var(--accent);margin-right:8px;"></i>Saisie des médicaments</h1>
            <div class="dv-breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                <span>Médicaments</span>
            </div>
        </div>

        <!-- Info client -->
        <div class="dv-card dv-animate dv-animate-d1">
            <div class="dv-card-header">
                <i class="bi bi-person-check"></i> Client enregistré
            </div>
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div>
                    <div class="dv-stat-label">Identifiant</div>
                    <div style="font-family:'Syne',sans-serif; font-weight:700; color:var(--accent);"><?php echo htmlspecialchars($dernierIdentifiant ?: '—'); ?></div>
                </div>
                <div>
                    <div class="dv-stat-label">Nom</div>
                    <div style="font-weight:600; color:var(--text-primary);"><?php echo htmlspecialchars($dernierNom ?: '—'); ?></div>
                </div>
                <div>
                    <div class="dv-stat-label">Prénom</div>
                    <div style="font-weight:600; color:var(--text-primary);"><?php echo htmlspecialchars($dernierPrenom ?: '—'); ?></div>
                </div>
            </div>
        </div>

        <!-- Formulaire médicaments -->
        <form method="POST" action="traitement-medoc.php">
            <?= csrf_field() ?>

            <div class="dv-card dv-animate dv-animate-d1">
                <div class="dv-card-header">
                    <i class="bi bi-clipboard2-pulse"></i> Type(s) de médicament(s) achetés
                </div>

                <div class="dv-checkbox-group">

                    <?php
                    $medicaments = [
                        'Antibiotique'    => ['id' => 'Anti',   'field' => 'autre_question_Antibiotique'],
                        'Antipyrétique'   => ['id' => 'Antipy', 'field' => 'autre_question_Antipyrétique'],
                        'Antalgique'      => ['id' => 'Anta',   'field' => 'autre_question_Antalgique'],
                        'Antidiarrhéique' => ['id' => 'Antidi', 'field' => 'autre_question_Antidiarrhéique'],
                        'VitamineC'       => ['id' => 'Vit',    'field' => 'autre_question_VitamineC'],
                    ];
                    $labels = [
                        'Antibiotique'    => 'Antibiotique',
                        'Antipyrétique'   => 'Antipyrétique',
                        'Antalgique'      => 'Antalgique',
                        'Antidiarrhéique' => 'Antidiarrhéique',
                        'VitamineC'       => 'Vitamine C',
                    ];
                    foreach ($medicaments as $val => $info):
                    ?>
                    <div class="dv-checkbox-item" style="flex-direction:column; align-items:flex-start;">
                        <label class="dv-radio-label">
                            <input type="checkbox"
                                   id="<?php echo $info['id']; ?>"
                                   name="type_medoc[]"
                                   value="<?php echo $val; ?>"
                                   onchange="toggleSub('sub_<?php echo $info['id']; ?>', this.checked)">
                            <span><?php echo $labels[$val]; ?></span>
                        </label>
                        <div class="dv-sub-field" id="sub_<?php echo $info['id']; ?>">
                            <label class="dv-label">Nombre de plaquettes :</label>
                            <input type="number" name="<?php echo $info['field']; ?>" class="dv-input" min="0" style="max-width:180px;" placeholder="0">
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end;" class="dv-animate dv-animate-d2">
                <a href="formulaire.php" class="dv-btn dv-btn-outline">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
                <button type="submit" class="dv-btn dv-btn-primary">
                    <i class="bi bi-send"></i> Enregistrer
                </button>
            </div>

        </form>

    </div>

<script>
function toggleSub(id, show){
    document.getElementById(id).style.display = show ? 'block' : 'none';
}
</script>

<?php include 'footer_shared.php'; ?>
