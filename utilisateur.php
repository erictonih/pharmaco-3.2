<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: Login.php");
    exit();
}

$servername = 'sql209.infinityfree.com';
$dbname     = 'if0_41511897_pharmacie';
$db_user    = 'if0_41511897';
$db_pass    = 'jR9A6jlS7c8f8r';

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);

if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$username = trim($_POST['username'] ?? '');
$passwordSaisi = trim($_POST['password'] ?? '');

// Vérification des champs
if (empty($username) || empty($passwordSaisi)) {
    $_SESSION['error'] = 'Veuillez remplir tous les champs.';
    header("Location: Login.php");
    exit();
}

// Requête : récupérer le mot de passe hashé
$stmt = $conn->prepare("SELECT user_id, username, role, mdp FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // mot de passe hashé dans la base
    $hashBase = $row['mdp'];

    // Vérification du mot de passe
    if (password_verify($passwordSaisi, $hashBase)) {

        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['user_name'] = $row['username'];
        $_SESSION['role']      = trim($row['role']);

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = 'Mot de passe incorrect.';
        header("Location: Login.php");
        exit();
    }

} else {
    $_SESSION['error'] = 'Utilisateur introuvable.';
    header("Location: Login.php");
    exit();
}

$stmt->close();
$conn->close();
?>
