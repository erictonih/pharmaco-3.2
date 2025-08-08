<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Connexion à la base de données
$conn = new mysqli('localhost', 'root', '', 'Pharmacie');
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// 1. Médicaments vendus (par type)
$sql_medicaments = "SELECT Type, SUM(Nombres) AS Nombre
                    FROM medicament
                    WHERE id_client IS NOT NULL 
                    GROUP BY Type";
$result_medicaments = $conn->query($sql_medicaments);

$medicaments = [];
$total_medicaments = 0;
if ($result_medicaments) {
    while ($row = $result_medicaments->fetch_assoc()) {
        $type = str_replace(['Antidiarrh�ique', 'AntidiarrhÃ©ique', 'AntipyrÃ©tique', 'Antipyr�tique'], ['Antidiarrhéique', 'Antidiarrhéique', 'Antipyrétique','Antipyrétique'], $row['Type']);
        $medicaments[] = [
            'Type' => $type,
            'Nombre' => (int)$row['Nombre']
        ];
        $total_medicaments += (int)$row['Nombre'];
    }
}

// 2. Clients (par type)
$sql_clients = "SELECT Type, COUNT(*) AS Nombre
                FROM client
                GROUP BY Type";
$result_clients = $conn->query($sql_clients);

$clients = [];
$total_clients = 0;
if ($result_clients) {
    while ($row = $result_clients->fetch_assoc()) {
        $type = str_replace('Hospitalis�', 'Hospitalisé', $row['Type']);
        $clients[] = [
            'Type' => $type,
            'Nombre' => (int)$row['Nombre']
        ];
        $total_clients += (int)$row['Nombre'];
    }
}

// 3. Médicaments par mois
$sql_medicaments_mois = "SELECT DATE_FORMAT(Date, '%Y-%m') AS Mois, Type, SUM(Nombres) AS Total
                         FROM medicament
                         WHERE Date != '0000-00-00 00:00:00'
                         GROUP BY Mois, Type
                         ORDER BY Mois, Type";
$result_medicaments_mois = $conn->query($sql_medicaments_mois);

$medicaments_mois = [];
$all_months = [];
$all_types = [];
if ($result_medicaments_mois) {
    while ($row = $result_medicaments_mois->fetch_assoc()) {
        $mois = $row['Mois'];
        $type = str_replace(['Antidiarrh�ique', 'AntidiarrhÃ©ique', 'AntipyrÃ©tique', 'Antipyr�tique'], ['Antidiarrhéique', 'Antidiarrhéique', 'Antipyrétique','Antipyrétique'], $row['Type']);
        $total = (int)$row['Total'];

        $medicaments_mois[] = [
            'Mois' => $mois,
            'Type' => $type,
            'Total' => $total
        ];

        if (!in_array($mois, $all_months)) {
            $all_months[] = $mois;
        }
        if (!in_array($type, $all_types)) {
            $all_types[] = $type;
        }
    }
}

// 4. Clients par âge et genre
$sql_age_genre = "SELECT Age, Genre, COUNT(*) AS Total
                  FROM client
                  WHERE Age != '0'
                  GROUP BY Age, Genre
                  ORDER BY Age, Genre";
$result_age_genre = $conn->query($sql_age_genre);

$age_genre = [];
$all_ages = [];
$all_genres = [];
if ($result_age_genre) {
    while ($row = $result_age_genre->fetch_assoc()) {
        $age = $row['Age'];
        $genre = $row['Genre'];
        $total = (int)$row['Total'];

        $age_genre[] = [
            'Age' => $age,
            'Genre' => $genre,
            'Total' => $total
        ];

        if (!in_array($age, $all_ages)) {
            $all_ages[] = $age;
        }
        if (!in_array($genre, $all_genres)) {
            $all_genres[] = $genre;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Tableau de bord des statistiques de ventes de médicaments et profils clients pour une pharmacie à Madagascar.">
    <meta name="keywords" content="pharmacie, statistiques, médicaments, Madagascar, tableau de bord">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Pharmacie Madagascar</title>
    <link rel="icon" href="sary/dvsser.jpg" type="image/jpg">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fad0;
        }
        .card {
            margin-bottom: 1.5rem;
        }
        .table-responsive {
            margin-top: 1rem;
        }
        .table {
            background-color: #fff;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .card-stat {
            text-align: center;
            padding: 1.5rem;
        }
        .card-stat h3 {
            margin: 0;
            font-size: 2rem;
        }
        .card-stat p {
            margin: 0.5rem 0 0;
            color: #000000ff;
        }
        @media (max-width: 768px) {
            .card-stat h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Tableau de Bord</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="index.php">Acceuil</a></li>
                    <li class="breadcrumb-item active">Tableau de Bord</li>
                </ol>
                <div class="card mb-4">
                    <div class="card-body">
                        Tableau de bord des données collectées au niveau de la pharmacie, présentant les statistiques des ventes de médicaments et des profils clients.
                    </div>
                </div>

                <!-- Cartes de résumé -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat bg-primary text-white">
                            <div class="card-body">
                                <h3><?php echo $total_medicaments; ?></h3>
                                <p>Médicaments vendus</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat bg-success text-white">
                            <div class="card-body">
                                <h3><?php echo $total_clients; ?></h3>
                                <p>Clients enregistrés</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat bg-info text-white">
                            <div class="card-body">
                                <h3><?php echo count($all_types); ?></h3>
                                <p>Types de médicaments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat bg-warning text-white">
                            <div class="card-body">
                                <h3><?php echo count($all_ages); ?></h3>
                                <p>Tranches d'âge</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau 1: Médicaments vendus -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Médicaments vendus par type
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-medicaments">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Nombre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicaments as $med): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($med['Type']); ?></td>
                                            <td><?php echo $med['Nombre']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tableau 2: Clients par type -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Clients par type
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-clients">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Nombre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($client['Type']); ?></td>
                                            <td><?php echo $client['Nombre']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tableau 3: Médicaments par mois -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Médicaments vendus par mois
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-medicaments-mois">
                                <thead>
                                    <tr>
                                        <th>Mois</th>
                                        <th>Type</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicaments_mois as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['Mois']); ?></td>
                                            <td><?php echo htmlspecialchars($item['Type']); ?></td>
                                            <td><?php echo $item['Total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tableau 4: Clients par âge et genre -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Clients par âge et genre
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-age-genre">
                                <thead>
                                    <tr>
                                        <th>Âge</th>
                                        <th>Genre</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($age_genre as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['Age']); ?></td>
                                            <td><?php echo htmlspecialchars($item['Genre']); ?></td>
                                            <td><?php echo $item['Total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
<footer>
  <img src= "sary\Logo.png" alt="Logo-Ministere">
  <p> 
©<span id="currentYear"></span>
  Ministère de la Santé Pulblidue de Madagascar<br>
<br>Direction de la Veille Sanitaire, de la Surveillance Epidémiologique et Riposte</p>
    <p><a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a></p>
</footer>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            try {
                // Initialiser Simple DataTables pour chaque tableau
                new simpleDatatables.DataTable("#table-medicaments", {
                    searchable: true,
                    sortable: true,
                    perPage: 10,
                    labels: {
                        placeholder: "Rechercher...",
                        perPage: "Nb de lignes par page",
                        noRows: "Aucune donnée disponible",
                        info: "Affichage de {start} à {end} sur {rows} lignes"
                    }
                });

                new simpleDatatables.DataTable("#table-clients", {
                    searchable: true,
                    sortable: true,
                    perPage: 10,
                    labels: {
                        placeholder: "Rechercher...",
                        perPage: "Nb de lignes par page",
                        noRows: "Aucune donnée disponible",
                        info: "Affichage de {start} à {end} sur {rows} lignes"
                    }
                });

                new simpleDatatables.DataTable("#table-medicaments-mois", {
                    searchable: true,
                    sortable: true,
                    perPage: 10,
                    labels: {
                        placeholder: "Rechercher...",
                        perPage: "Nb de lignes par page",
                        noRows: "Aucune donnée disponible",
                        info: "Affichage de {start} à {end} sur {rows} lignes"
                    }
                });

                new simpleDatatables.DataTable("#table-age-genre", {
                    searchable: true,
                    sortable: true,
                    perPage: 10,
                    labels: {
                        placeholder: "Rechercher...",
                        perPage: "Nb de lignes par page",
                        noRows: "Aucune donnée disponible",
                        info: "Affichage de {start} à {end} sur {rows} lignes"
                    }
                });

                // Mettre à jour l'année dans le footer
                document.getElementById('daty').textContent = new Date().getFullYear();

            } catch (e) {
                console.error('Erreur lors de l\'initialisation des tableaux:', e);
            }
        });
    </script>
</body>
</html>