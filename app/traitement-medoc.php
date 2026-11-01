<?php
// traitement-medoc.php — enregistrement des médicaments du dernier client saisi
require_once __DIR__ . '/db.php';
require_role(['Super Admin', 'Saisie']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /medicament.php'); exit(); }
csrf_check();

$conn = db();

// Client concerné : celui saisi dans cette session (évite d'attacher les achats à un autre client)
$clientId = $_SESSION['last_client']['id']   ?? null;
$date     = $_SESSION['last_client']['date'] ?? null;
if (!$clientId) {
    echo "<script>alert('Aucun client trouvé. Veuillez d\\'abord enregistrer un client.'); window.location.href='/formulaire.php';</script>";
    exit();
}

$allowed = ['Antibiotique', 'Antipyrétique', 'Antalgique', 'Antidiarrhéique', 'VitamineC'];
$types   = isset($_POST['type_medoc']) && is_array($_POST['type_medoc']) ? $_POST['type_medoc'] : [];
$types   = array_values(array_intersect($allowed, array_map('strval', $types)));

if (!$types) {
    echo "<script>alert('Aucun médicament sélectionné.'); history.back();</script>";
    exit();
}

$inserted = 0;
$st = $conn->prepare('INSERT INTO medicament (Type, Nombres, id_client, Date) VALUES (?, ?, ?, ?)');
foreach ($types as $type) {
    $nombre = max(0, min(100000, (int)($_POST['autre_question_' . $type] ?? 0)));
    $st->bind_param('siis', $type, $nombre, $clientId, $date);
    if ($st->execute()) { $inserted++; } else { error_log('[pharmaco] insert medicament : ' . $st->error); }
}
$st->close();

if ($inserted > 0) {
    unset($_SESSION['last_client']);
    echo "<script>alert('" . (int)$inserted . " médicament(s) enregistré(s) avec succès.'); window.location.href='/index.php';</script>";
} else {
    echo "<script>alert('Aucun médicament n\\'a pu être enregistré.'); history.back();</script>";
}
