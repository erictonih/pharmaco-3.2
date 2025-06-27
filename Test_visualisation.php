<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="sary/dvsser.jpg" type="image/jpg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respresentation des résultats</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart.js -->
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

                <!-- Card: Médicaments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i> Médicaments
                    </div>
                    <div class="card-body">
                        <canvas id="mygraph" width="100%" height="50"></canvas>
                    </div>
                </div>

                <!-- Card: Clients -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-1"></i> Type de Clients
                    </div>
                    <div class="card-body">
                        <canvas id="clientPieChart" width="100%" height="50"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php
    // Connexion à la base de données
    $conn = new mysqli('localhost', 'root', '', 'pharmacie');
    if ($conn->connect_error) {
        die("Connexion échouée : " . $conn->connect_error);
    }

    // Requête SQL pour le bar chart (médicaments)
    $sql_medicaments = "SELECT SUM(Nombres) AS Nombre, Types_medoc
                        FROM medicament
                        WHERE id_client IS NOT NULL 
                        GROUP BY Types_medoc";
    $result_medicaments = $conn->query($sql_medicaments);

    $types_medoc = [];
    $totals_medoc = [];

    if ($result_medicaments) {
        while ($row = $result_medicaments->fetch_assoc()) {
            $types_medoc[] = $row['Types_medoc'];
            $totals_medoc[] = $row['Nombre'];
        }
    }

    // Requête SQL pour le pie chart (clients)
    $sql_clients = "SELECT Type_client, COUNT(*) AS nombre FROM client GROUP BY Type_client";
    $result_clients = $conn->query($sql_clients);

    $types_clients = [];
    $totals_clients = [];

    if ($result_clients) {
        while ($row = $result_clients->fetch_assoc()) {
            $types_clients[] = $row['Type_client'];
            $totals_clients[] = $row['nombre'];
        }
    }

    $conn->close();

    // Convertir les données en JSON
    $json_types_medoc = json_encode($types_medoc);
    $json_totals_medoc = json_encode($totals_medoc);
    $json_types_clients = json_encode($types_clients);
    $json_totals_clients = json_encode($totals_clients);
    ?>

    <script>
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
    </script>
</body>
</html>
