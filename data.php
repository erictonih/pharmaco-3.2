<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// data.php
header('Content-Type: application/json; charset=utf-8');
$servername = "localhost";
$username = "root";
$password = ""; // ou ton mot de passe
$dbname = "Pharmacie"; // ou le nom de ta base de données

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT 
            c.Id, 
            c.Age, 
            c.Genre, 
            c.Type AS TypeClient, 
            c.District, 
            m.id_client, 
            m.Type AS TypeMedicament, 
            m.Nombres, 
            DATE_FORMAT(m.date, '%Y-%m') AS mois
        FROM client c
        INNER JOIN medicament m ON c.Id = m.id_client";
$res = $conn->query($sql);

if ($res === false) {
    echo json_encode(['error' => 'SQL error: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

// Toujours renvoyer un tableau JSON (même vide)
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
