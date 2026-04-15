<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host   = 'sql209.infinityfree.com';
$dbname = 'if0_41511897_pharmacie';
$user   = 'if0_41511897';
$pass   = 'jR9A6jlS7c8f8r';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Récupérer le dernier client enregistré
$sql_last = "SELECT Id, date_de_sousmission FROM client ORDER BY date_de_sousmission DESC LIMIT 1";
$result   = $conn->query($sql_last);

if (!$result || $result->num_rows === 0) {
    echo "<script>alert('Aucun client trouvé. Veuillez d abord enregistrer un client.'); window.location.href='formulaire.php';</script>";
    exit();
}

$row    = $result->fetch_assoc();
$dernierIdentifiant = $row['Id'];
$date   = $row['date_de_sousmission'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["type_medoc"]) && is_array($_POST["type_medoc"])) {

    $inserted = 0;
    foreach ($_POST["type_medoc"] as $type) {
        $field  = "autre_question_" . $type;
        $nombre = isset($_POST[$field]) ? (int)$_POST[$field] : 0;

        $stmt = $conn->prepare("INSERT INTO medicament (Type, Nombres, id_client, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siis', $type, $nombre, $dernierIdentifiant, $date);

        if ($stmt->execute()) {
            $inserted++;
        } else {
            error_log("Erreur insertion médicament : " . $stmt->error);
        }
        $stmt->close();
    }

    $conn->close();

    if ($inserted > 0) {
        echo "<script>alert('$inserted médicament(s) enregistré(s) avec succès.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Aucun médicament n\\'a pu être enregistré. Vérifiez les données.'); history.back();</script>";
    }
} else {
    $conn->close();
    echo "<script>alert('Aucun médicament sélectionné.'); history.back();</script>";
}
?>
