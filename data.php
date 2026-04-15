<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$servername = "sql209.infinityfree.com";
$username   = "if0_41511897";
$password   = "jR9A6jlS7c8f8r";
$dbname     = "if0_41511897_pharmacie";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connexion échouée : ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}
$conn->set_charset('utf8mb4');

$sql = "SELECT
            c.Id,
            c.Age,
            c.Genre,
            c.Type        AS TypeClient,
            c.District,
            m.id_client,
            m.Type        AS TypeMedicament,
            m.Nombres     AS Quantite,
            DATE_FORMAT(m.Date, '%Y-%m') AS Mois
        FROM client c
        INNER JOIN medicament m ON c.Id = m.id_client
        ORDER BY m.Date DESC";

$res = $conn->query($sql);

if ($res === false) {
    echo json_encode(['error' => 'Erreur SQL : ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

// Correction encodage
$fix = ['Antidiarrh?ique','AntidiarrhÃ©ique','AntipyrÃ©tique','Antipyr?tique','Hospitalis?'];
$rep = ['Antidiarrhéique','Antidiarrhéique','Antipyrétique','Antipyrétique','Hospitalisé'];

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['TypeMedicament'] = str_replace($fix, $rep, $row['TypeMedicament']);
    $row['TypeClient']     = str_replace($fix, $rep, $row['TypeClient']);
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
