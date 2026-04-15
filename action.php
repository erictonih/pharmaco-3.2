<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host   = 'sql209.infinityfree.com';
$dbname = 'if0_41511897_pharmacie';
$user   = 'if0_41511897';
$pass   = 'jR9A6jlS7c8f8r';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if (isset($_POST['identifiant'], $_POST['Nom'], $_POST['Prenom'], $_POST['age'], $_POST['genre'], $_POST['type_client'], $_POST['adresse'])) {
    $id           = trim($_POST['identifiant']);
    $nom          = trim($_POST['Nom']);
    $prenom       = trim($_POST['Prenom']);
    $age          = (int)$_POST['age'];
    $genre        = $_POST['genre'];
    $type_client  = $_POST['type_client'];
    $district     = trim($_POST['district'] ?? '');
    $adresse      = trim($_POST['adresse']);

    date_default_timezone_set('Indian/Antananarivo');
    $date = date('Y-m-d H:i:s');

    // Si identifiant vide, générer un ID basé sur la date
    if ($id === '') {
        $id = date('dmyHi') . rand(100, 999);
    }

    $sql = "INSERT INTO client (Id, Nom, Prenom, Age, Genre, Type, Residence, District, date_de_sousmission)
            VALUES (:id, :nom, :prenom, :age, :genre, :type, :adresse, :district,  :date)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id'       => $id,
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':age'      => $age,
        ':genre'    => $genre,
        ':type'     => $type_client,
        ':district' => $district,
        ':adresse'  => $adresse,
        ':date'     => $date,
    ]);

    echo "<script>alert('Enregistrement réussi : " . addslashes($nom . ' ' . $prenom) . "'); window.location.href='medicament.php';</script>";
} else {
    echo "<script>alert('Données incomplètes. Veuillez remplir tous les champs obligatoires.'); history.back();</script>";
}
?>
