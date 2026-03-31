<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$conn = new mysqli('sql209.infinityfree.com','if0_41511897','jR9A6jlS7c8f8r','if0_41511897_pharmacie');
if($conn->connect_error) die("Connexion échouée : ".$conn->connect_error);
$conn->set_charset('utf8mb4');

$fix=['Antidiarrh?ique','AntidiarrhÃ©ique','AntipyrÃ©tique','Antipyr?tique'];
$rep=['Antidiarrhéique','Antidiarrhéique','Antipyrétique','Antipyrétique'];

// 1. Médicaments
$medicaments=[]; $total_meds=0;
$r1=$conn->query("SELECT Type,SUM(Nombres) AS Nombre FROM medicament WHERE id_client IS NOT NULL GROUP BY Type");
if($r1) while($row=$r1->fetch_assoc()){
    $medicaments[]=['Type'=>str_replace($fix,$rep,$row['Type']),'Nombre'=>(int)$row['Nombre']];
    $total_meds+=(int)$row['Nombre'];
}

// 2. Clients
$clients=[]; $total_clients=0;
$r2=$conn->query("SELECT Type,COUNT(*) AS Nombre FROM client GROUP BY Type");
if($r2) while($row=$r2->fetch_assoc()){
    $clients[]=['Type'=>str_replace('Hospitalis?','Hospitalisé',$row['Type']),'Nombre'=>(int)$row['Nombre']];
    $total_clients+=(int)$row['Nombre'];
}

// 3. Médicaments par mois
$meds_mois=[]; $all_months=[]; $all_types=[];
$r3=$conn->query("SELECT DATE_FORMAT(Date,'%Y-%m') AS Mois,Type,SUM(Nombres) AS Total FROM medicament WHERE Date!='0000-00-00 00:00:00' GROUP BY Mois,Type ORDER BY Mois,Type");
if($r3) while($row=$r3->fetch_assoc()){
    $t=str_replace($fix,$rep,$row['Type']);
    $meds_mois[]=['Mois'=>$row['Mois'],'Type'=>$t,'Total'=>(int)$row['Total']];
    if(!in_array($row['Mois'],$all_months)) $all_months[]=$row['Mois'];
    if(!in_array($t,$all_types)) $all_types[]=$t;
}

// 4. Âge/genre
$age_genre=[];
$r4=$conn->query("SELECT Age,Genre,COUNT(*) AS Total FROM client WHERE Age!='0' GROUP BY Age,Genre ORDER BY Age,Genre");
if($r4) while($row=$r4->fetch_assoc())
    $age_genre[]=['Age'=>$row['Age'],'Genre'=>$row['Genre'],'Total'=>(int)$row['Total']];

$conn->close();

$page_title  = 'Tableau de Bord';
$active_page = 'dashboard';
$breadcrumb  = ['Accueil'=>'index.php','Tableau de Bord'=>''];
include 'sidebar.php';
?>

    <div class="dv-content dv-animate">

        <div class="dv-page-header">
            <h1><i class="bi bi-speedometer2" style="color:var(--accent);margin-right:8px;"></i>Tableau de Bord</h1>
            <div class="dv-breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                <span>Tableau de Bord</span>
            </div>
        </div>

        <div class="dv-alert dv-alert-info dv-animate dv-animate-d1">
            <i class="bi bi-info-circle"></i>
            Données collectées au niveau de la pharmacie. Statistiques de ventes et profils clients.
        </div>

        <!-- Cartes statistiques -->
        <div class="dv-stats-grid dv-animate dv-animate-d1">
            <div class="dv-stat primary">
                <div class="dv-stat-label">Médicaments vendus</div>
                <div class="dv-stat-value"><?php echo number_format($total_meds); ?></div>
                <div class="dv-stat-sub">toutes catégories</div>
            </div>
            <div class="dv-stat success">
                <div class="dv-stat-label">Clients enregistrés</div>
                <div class="dv-stat-value"><?php echo number_format($total_clients); ?></div>
                <div class="dv-stat-sub">dans la base</div>
            </div>
            <div class="dv-stat info">
                <div class="dv-stat-label">Types de médicaments</div>
                <div class="dv-stat-value"><?php echo count($all_types); ?></div>
                <div class="dv-stat-sub">catégories distinctes</div>
            </div>
            <div class="dv-stat warning">
                <div class="dv-stat-label">Périodes (mois)</div>
                <div class="dv-stat-value"><?php echo count($all_months); ?></div>
                <div class="dv-stat-sub">mois de données</div>
            </div>
        </div>

        <!-- Tableau : médicaments -->
        <div class="dv-card dv-animate dv-animate-d2">
            <div class="dv-card-header">
                <i class="bi bi-table"></i> Médicaments vendus par type
            </div>
            <div class="dv-table-wrap">
                <table class="dv-table" id="table-medicaments">
                    <thead>
                        <tr><th>Type</th><th>Quantité totale</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($medicaments as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['Type']); ?></td>
                            <td><span class="dv-badge dv-badge-primary"><?php echo $m['Nombre']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tableau : clients -->
        <div class="dv-card dv-animate dv-animate-d2">
            <div class="dv-card-header">
                <i class="bi bi-people"></i> Clients par type
            </div>
            <div class="dv-table-wrap">
                <table class="dv-table" id="table-clients">
                    <thead>
                        <tr><th>Type</th><th>Nombre</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($clients as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['Type']); ?></td>
                            <td><span class="dv-badge dv-badge-success"><?php echo $c['Nombre']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tableau : médicaments par mois -->
        <div class="dv-card dv-animate dv-animate-d3">
            <div class="dv-card-header">
                <i class="bi bi-calendar3"></i> Médicaments vendus par mois
            </div>
            <div class="dv-table-wrap">
                <table class="dv-table" id="table-meds-mois">
                    <thead>
                        <tr><th>Mois</th><th>Type</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($meds_mois as $item): ?>
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

        <!-- Tableau : âge et genre -->
        <div class="dv-card dv-animate dv-animate-d3">
            <div class="dv-card-header">
                <i class="bi bi-bar-chart-line"></i> Clients par âge et genre
            </div>
            <div class="dv-table-wrap">
                <table class="dv-table" id="table-age-genre">
                    <thead>
                        <tr><th>Âge</th><th>Genre</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($age_genre as $item): ?>
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

<link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var opts = {searchable:true,sortable:true,perPage:8,
        labels:{placeholder:'Rechercher…',perPage:'Lignes',noRows:'Aucune donnée',
                info:'Affichage {start}–{end} sur {rows}'}};
    try{new simpleDatatables.DataTable('#table-medicaments',opts);}catch(e){}
    try{new simpleDatatables.DataTable('#table-clients',opts);}catch(e){}
    try{new simpleDatatables.DataTable('#table-meds-mois',opts);}catch(e){}
    try{new simpleDatatables.DataTable('#table-age-genre',opts);}catch(e){}
});
</script>

<?php include 'footer_shared.php'; ?>
