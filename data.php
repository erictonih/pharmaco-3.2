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
/*
 * Exemple d'utilisation :
 * POST ou GET avec les paramètres :
 *  - rows: tableau des champs pour les lignes (ex: ["Genre"])
 *  - cols: tableau des champs pour les colonnes (ex: ["TypeMedicament"])
 *  - values: champ à agréger (ex: "Nombres")
 *  - agg: fonction d'agrégation ("SUM", "COUNT", etc.)
 */

$rows = isset($_REQUEST['rows']) ? $_REQUEST['rows'] : ['Genre'];
$cols = isset($_REQUEST['cols']) ? $_REQUEST['cols'] : ['TypeMedicament'];
$value = isset($_REQUEST['values']) ? $_REQUEST['values'] : 'Nombres';
$agg = isset($_REQUEST['agg']) ? strtoupper($_REQUEST['agg']) : 'SUM';

// Sécurisation basique des champs
$allowedFields = ['Id','Age','Genre','TypeClient','District','id_client','TypeMedicament','Nombres','mois'];
foreach (array_merge($rows, $cols, [$value]) as $field) {
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['error' => 'Invalid field: ' . $field], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Construction dynamique de la requête
$selectRows = implode(', ', $rows);
$selectCols = implode(', ', $cols);
$groupBy = implode(', ', array_merge($rows, $cols));
$sqlPivot = "SELECT $selectRows, $selectCols, $agg($value) AS result
             FROM client c
             INNER JOIN medicament m ON c.Id = m.id_client
             GROUP BY $groupBy";

$resPivot = $conn->query($sqlPivot);

if ($resPivot === false) {
    echo json_encode(['error' => 'SQL error: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$pivotData = [];
while ($row = $resPivot->fetch_assoc()) {
    $pivotData[] = $row;
}

echo json_encode($pivotData, JSON_UNESCAPED_UNICODE);