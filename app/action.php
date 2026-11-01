<?php
// action.php — enregistrement d'un client (formulaire.php)
require_once __DIR__ . '/db.php';
require_role(['Super Admin', 'Saisie']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /formulaire.php'); exit(); }
csrf_check();

$need = ['identifiant', 'Nom', 'Prenom', 'age', 'genre', 'type_client', 'adresse'];
foreach ($need as $k) {
    if (!isset($_POST[$k])) {
        echo "<script>alert('Données incomplètes. Veuillez remplir tous les champs obligatoires.'); history.back();</script>";
        exit();
    }
}

$id      = trim($_POST['identifiant']);
$nom     = mb_substr(trim($_POST['Nom']), 0, 50);
$prenom  = mb_substr(trim($_POST['Prenom']), 0, 50);
$age     = max(0, min(130, (int)$_POST['age']));
$genre   = in_array($_POST['genre'], ['Masculin', 'Feminin'], true) ? $_POST['genre'] : '';
$type    = in_array($_POST['type_client'], ['Ambulatoire', 'Hospitalisé'], true) ? $_POST['type_client'] : '';
$district= mb_substr(trim($_POST['district'] ?? ''), 0, 250);
$adresse = mb_substr(trim($_POST['adresse']), 0, 100);

if ($nom === '' || $prenom === '' || $genre === '' || $type === '') {
    echo "<script>alert('Données invalides.'); history.back();</script>";
    exit();
}
// Identifiant : numérique ou généré (jamais 0, réservé)
if ($id === '' || !ctype_digit($id) || (int)$id === 0) {
    $id = date('dmyHi') . random_int(100, 999);
}

$date = date('Y-m-d H:i:s');
$conn = db();
$st = $conn->prepare('INSERT INTO client (Id, Nom, Prenom, Age, Genre, Type, Residence, District, date_de_sousmission)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$st->bind_param('ississsss', $id, $nom, $prenom, $age, $genre, $type, $adresse, $district, $date);
if (!$st->execute()) {
    error_log('[pharmaco] insert client : ' . $st->error);
    echo "<script>alert('Enregistrement impossible (identifiant déjà utilisé ?).'); history.back();</script>";
    exit();
}
$st->close();
$_SESSION['last_client'] = ['id' => $id, 'date' => $date];

$msg = json_encode($nom . ' ' . $prenom, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
echo "<script>alert('Enregistrement réussi : ' + $msg); window.location.href='/medicament.php';</script>";
