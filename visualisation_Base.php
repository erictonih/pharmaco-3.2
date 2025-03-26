<html>
    <head>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body>
    <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Respresentation des résultats</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.html">Dashboard</a></li>
                            <li class="breadcrumb-item active">Charts</li>
                        </ol>
                        <div class="card mb-4">
                            <div class="card-body">
                                Resprésentation graphique du situation de données collecter au niveau de pharmacie
                            </div>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-area me-1"></i>
                                Le Nombre de médicaments vendues
                            </div>
                            <div class="card-body">
                            <canvas id="mygraph" width="125" height="75"></canvas>
                            </div>
                            <div class="card-footer small text-muted"><p id="lastModified"> </p></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-chart-bar me-1"></i>
                                        Medicaments par mois
                                    </div>
                                    <div class="card-body"><canvas id="medicamentsparmois" width="125%" height="125"></canvas></div>
                                    <div class="card-footer small text-muted"><p id="lastModified2"> </p></div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-chart-pie me-1"></i>
                                        Type de client
                                    </div>
                                    <div class="card-body">
                                    <canvas id="clientPieChart" width="100%" height="30"></canvas>
                                    </div>
                                    <div class="card-footer small text-muted"><p id="lastModified3"> </p></div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                            <i class="fas fa-chart-area me-1"></i>
                                Type de clients
                            </div>
                            <div class="card-body">
                            <canvas id="agegenre" width="125" height="75"></canvas>
                            </div>
                            <div class="card-footer small text-muted"><p id=""> </p></div>
                        </div>
                    </div>
                </main>
<?php
    // Connexion à la base de données
    $conn = new mysqli('localhost', 'root', '', 'pharmacie');
    if ($conn->connect_error) {
        die("Connexion échouée : " . $conn->connect_error);
    }

    // Requête SQL pour le bar chart (médicaments)
    $sql_medicaments = "SELECT SUM(Nombres) AS Nombre, Type
                        FROM medicament
                        WHERE id_client IS NOT NULL 
                        GROUP BY Type";
    $result_medicaments = $conn->query($sql_medicaments);

    $types_medoc = [];
    $totals_medoc = [];

    if ($result_medicaments) {
        while ($row = $result_medicaments->fetch_assoc()) {
            $types_medoc[] = $row['Type'];
            $totals_medoc[] = $row['Nombre'];
        }
    }

    // Requête SQL pour le pie chart (clients)
    $sql_clients = "SELECT Type, COUNT(*) AS nombre FROM client GROUP BY Type";
    $result_clients = $conn->query($sql_clients);

    $types_clients = [];
    $totals_clients = [];

    if ($result_clients) {
        while ($row = $result_clients->fetch_assoc()) {
            $types_clients[] = $row['Type'];
            $totals_clients[] = $row['nombre'];
        }
    }
     // Requête SQL pour regrouper par mois et par type
     $sql = " SELECT 
         DATE_FORMAT(Date, '%Y-%m') AS mois, 
         Type, 
         SUM(Nombres) AS total FROM medicament WHERE Date != '0000-00-00 00:00:00' GROUP BY mois, Type ORDER BY mois, Type";

    $result = $conn->query($sql);

    $data = [];
    $all_months = [];
    $all_types = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
         $mois = $row['mois'];
         $type = $row['Type'];
         $total = $row['total'];

         // Ajouter le mois et le type dans les listes uniques
         if (!in_array($mois, $all_months)) 
            { $all_months[] = $mois; }
         if (!in_array($type, $all_types)) 
         { $all_types[] = $type; }

         // Ajouter les données dans une structure imbriquée
         $data[$type][$mois] = $total;
        }
    }
    $sql = "SELECT Age , Genre, COUNT(Genre) AS total
            FROM client
            WHERE Age!= '0'
            GROUP BY Genre, Age
            ORDER BY Age, Genre ";

    $result = $conn->query($sql);

    $data = [];
    $all_age = [];
    $all_genre = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $age = $row['Age'];
            $genre = $row['Genre'];
            $total = $row['total'];

            // Ajouter le mois et le type dans les listes uniques
            if (!in_array($age, $all_age)) {
                $all_age[] = $age;
            }
            if (!in_array($genre, $all_genre)) {
                $all_genre[] = $genre;
            }

            // Ajouter les données dans une structure imbriquée
            $data[$genre][$age] = $total;
        }
    }
    $conn->close();
    
    $dataset = [];
    foreach ($all_genre as $genre) {
        $type_data = [];
        foreach ($all_age as $age) {
            $type_data[] = $data[$genre][$age] ?? 0; // Mettre 0 si aucune donnée pour ce mois
        }
        $dataset[] = [
            'label' => $genre,
            'data' => $type_data,
            'backgroundColor' => 'rgba(' . rand(0, 255) . ',' . rand(0, 255) . ',' . rand(0, 255) . ', 0.5)',
            'borderColor' => 'rgba(' . rand(0, 255) . ',' . rand(0, 255) . ',' . rand(0, 255) . ', 1)',
            'borderWidth' => 1
        ];
    }
     // Préparer les données pour Chart.js
     $datasets = [];
     foreach ($all_types as $type) {
         $type_datas = [];
         foreach ($all_months as $mois) {
             $type_datas[] = $datas[$type][$mois] ?? 0; // Mettre 0 si aucune donnée pour ce mois
         }
         $datasets[] = [
             'label' => $type,
             'data' => $type_datas,
             'backgroundColor' => 'rgba(' . rand(0, 255) . ',' . rand(0, 255) . ',' . rand(0, 255) . ', 0.5)',
             'borderColor' => 'rgba(' . rand(0, 255) . ',' . rand(0, 255) . ',' . rand(0, 255) . ', 1)',
             'borderWidth' => 1
         ];
     }

    // Convertir les données en JSON
    $json_types_medoc = json_encode($types_medoc);
    $json_totals_medoc = json_encode($totals_medoc);
    $json_types_clients = json_encode($types_clients);
    $json_totals_clients = json_encode($totals_clients);
    $json_months = json_encode($all_months);
    $json_datasets = json_encode($datasets);
    $json_age = json_encode($all_age);
    $json_dataset = json_encode($dataset);
    ?>

    <script>
        // Données pour le graphique
        const age = <?php echo $json_age; ?>;
        const dataset = <?php echo $json_dataset; ?>; 

        // Initialiser le graphique Chart.js
        const agechart = document.getElementById("agegenre").getContext("2d");
        new Chart(agechart, {
            type: 'bar',
            data: {
                labels: age, 
                datasets: dataset 
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre de clients par Age'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Age des clients'
                        }
                    }
                }
            }
        });
        // Données pour le graphique des médicaments
        const medLabels = <?php echo $json_types_medoc; ?>;
        const medData = <?php echo $json_totals_medoc; ?>;

        // Bar chart pour les médicaments
        const ctx1 = document.getElementById("mygraph").getContext("2d");
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: medLabels,
                datasets: [{
                    label: "Nombre de médicaments",
                    backgroundColor: "rgba(75, 192, 192, 0.5)",
                    borderColor: "rgba(75, 192, 192, 1)",
                    data: medData,
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Données pour le graphique des clients
        const clientLabels = <?php echo $json_types_clients; ?>;
        const clientData = <?php echo $json_totals_clients; ?>;

        // Pie chart pour les clients
        const ctx2 = document.getElementById("clientPieChart").getContext("2d");
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: clientLabels,
                datasets: [{
                    data: clientData,
                    backgroundColor: [
                        "rgba(255, 99, 132, 0.6)",
                        "rgba(54, 162, 235, 0.6)",
                        "rgba(75, 192, 192, 0.6)",
                        "rgba(153, 102, 255, 0.6)"
                    ],
                    borderColor: [
                        "rgba(255, 99, 132, 1)",
                        "rgba(54, 162, 235, 1)",
                        "rgba(75, 192, 192, 1)",
                        "rgba(153, 102, 255, 1)"
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });

        const months = <?php echo $json_months; ?>; // Les mois (YYYY-MM)
        const datasets = <?php echo $json_datasets; ?>; // Les séries de données

        // Initialiser le graphique Chart.js
        const ctx3 = document.getElementById("medicamentsparmois").getContext("2d");
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: months, // Les mois au format YYYY-MM
                datasets: datasets // Les datasets (un pour chaque type)
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre de Médicaments'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mois (année-mois)'
                        }
                    }
                }
            }
        });
         // Fonction pour obtenir la dernière date de modification de la page
    function getLastModified() {
        const lastModified = new Date(document.lastModified);
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit'
        };
        return lastModified.toLocaleDateString('fr-FR', options);
    }

    // Afficher la date de modification dans un élément HTML
    document.addEventListener('DOMContentLoaded', () => {
        const footerElement = document.getElementById('lastModified','lastModified2','lastModified3');
        if (footerElement) {
            footerElement.innerText = `Dernière modification : ${getLastModified()}`;
        }
    });
    // Fonction pour récupérer l'année en cours
    function getCurrentYear() {
        const currentYear = new Date().getFullYear();
        return currentYear;
    }

    // Afficher l'année dans un élément HTML
    document.addEventListener('DOMContentLoaded', () => {
        const daty = document.getElementById('daty');
            daty.innerText = getCurrentYear();
        const yearElement = document.getElementById('currentYear');
        if (yearElement) {
            yearElement.innerText = getCurrentYear();
        }
    });
</script>
    </script>
    </body>
<footer>
  <img src= "sary\Logo.png" alt="Logo-Ministere">
  <br>
  © <p1 id="daty"></p1> Ministère de la Santé Pulblidue de Madagascar<br>
<br>Direction de la Veille Sanitaire, de la Surveillance Epidémiologique et Riposte</p>
    <p><a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a></p>
</footer>
</html>
