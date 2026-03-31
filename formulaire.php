<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$page_title = 'Saisie de données';
$active_page = 'formulaire';
$breadcrumb = ['Accueil' => 'index.php', 'Saisie de données' => ''];
include 'sidebar.php';
?>

    <div class="dv-content dv-animate">

        <div class="dv-page-header">
            <h1><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px;"></i>Saisie de données</h1>
            <div class="dv-breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                <span>Saisie de données</span>
            </div>
        </div>

        <div class="dv-alert dv-alert-info dv-animate dv-animate-d1">
            <i class="bi bi-info-circle"></i>
            Veuillez remplir les informations concernant le client. Les champs marqués <strong>*</strong> sont obligatoires.
        </div>

        <form method="POST" action="action.php">

            <div class="dv-card dv-animate dv-animate-d1">
                <div class="dv-card-header">
                    <i class="bi bi-person"></i> Informations du client
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:18px;">

                    <div class="dv-form-group">
                        <label class="dv-label" for="id">Identifiant</label>
                        <input type="text" id="id" name="identifiant" class="dv-input" placeholder="Ex : 110923001">
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="Nom">Nom <span style="color:#e55353;">*</span></label>
                        <input type="text" id="Nom" name="Nom" class="dv-input" required placeholder="Nom de famille">
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="Prenom">Prénom <span style="color:#e55353;">*</span></label>
                        <input type="text" id="Prenom" name="Prenom" class="dv-input" required placeholder="Prénom(s)">
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="age">Âge <span style="color:#e55353;">*</span></label>
                        <input type="number" id="age" name="age" class="dv-input" required placeholder="Ex : 32" min="0" max="150">
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="genre">Genre</label>
                        <select id="genre" name="genre" class="dv-select">
                            <option value="">— Sélectionner —</option>
                            <option value="Masculin">Masculin</option>
                            <option value="Feminin">Féminin</option>
                            <option value="Inconnue">Inconnu(e)</option>
                        </select>
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="adresse">Résidence <span style="color:#e55353;">*</span></label>
                        <input type="text" id="adresse" name="adresse" class="dv-input" required placeholder="Adresse / Quartier">
                    </div>

                    <div class="dv-form-group">
                        <label class="dv-label" for="district">District</label>
                        <input type="text" id="district" name="district" class="dv-input" placeholder="Ex : Andramasina">
                    </div>

                </div>

                <div class="dv-form-group" style="margin-top:8px;">
                    <label class="dv-label">Type de client <span style="color:#e55353;">*</span></label>
                    <div class="dv-radio-group">
                        <label class="dv-radio-label">
                            <input type="radio" name="type_client" value="Ambulatoire" required>
                            <span>Ambulatoire</span>
                        </label>
                        <label class="dv-radio-label">
                            <input type="radio" name="type_client" value="Hospitalisé">
                            <span>Hospitalisé(e)</span>
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end;" class="dv-animate dv-animate-d2">
                <a href="index.php" class="dv-btn dv-btn-outline">
                    <i class="bi bi-x-lg"></i> Annuler
                </a>
                <button type="submit" class="dv-btn dv-btn-primary">
                    <i class="bi bi-send"></i> Enregistrer
                </button>
            </div>

        </form>

    </div>

<?php include 'footer_shared.php'; ?>
