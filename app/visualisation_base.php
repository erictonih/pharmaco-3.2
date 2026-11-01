<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// Connexion
$conn = db();
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$fix = ['Antidiarrh?ique','AntidiarrhÃ©ique','AntipyrÃ©tique','Antipyr?tique'];
$rep = ['Antidiarrhéique','Antidiarrhéique','Antipyrétique','Antipyrétique'];

// 1. Médicaments par type
$types_medoc = []; $totals_medoc = []; $total_meds = 0;
$res = $conn->query("SELECT SUM(Nombres) AS Nombre, Type FROM medicament WHERE id_client IS NOT NULL GROUP BY Type");
if ($res) while ($row = $res->fetch_assoc()) {
    $types_medoc[]  = str_replace($fix, $rep, $row['Type']);
    $totals_medoc[] = (int)$row['Nombre'];
    $total_meds    += (int)$row['Nombre'];
}

// 2. Clients par type
$types_clients = []; $totals_clients = []; $total_clients = 0;
$res2 = $conn->query("SELECT Type, COUNT(*) AS nombre FROM client GROUP BY Type");
if ($res2) while ($row = $res2->fetch_assoc()) {
    $types_clients[]  = str_replace('Hospitalis?','Hospitalisé',$row['Type']);
    $totals_clients[] = (int)$row['nombre'];
    $total_clients   += (int)$row['nombre'];
}

// 3. Médicaments par mois
$all_months=[]; $all_types=[]; $data_mois=[];
$res3 = $conn->query("SELECT DATE_FORMAT(Date,'%Y-%m') AS mois, Type, SUM(Nombres) AS total FROM medicament WHERE Date != '0000-00-00 00:00:00' GROUP BY mois, Type ORDER BY mois, Type");
if ($res3) while ($row = $res3->fetch_assoc()) {
    $m = $row['mois'];
    $t = str_replace($fix,$rep,$row['Type']);
    if(!in_array($m,$all_months)) $all_months[]=$m;
    if(!in_array($t,$all_types))  $all_types[]=$t;
    $data_mois[$t][$m] = (int)$row['total'];
}
$datasets_mois = [];
foreach ($all_types as $type) {
    $pts = [];
    foreach ($all_months as $m) $pts[] = $data_mois[$type][$m] ?? 0;
    $r=rand(0,255);$g=rand(0,255);$b=rand(0,255);
    $datasets_mois[] = ['label'=>$type,'data'=>$pts,
        'backgroundColor'=>"rgba($r,$g,$b,0.5)",'borderColor'=>"rgba($r,$g,$b,1)",'borderWidth'=>1];
}

// 4. Âge / Genre
$all_ages=[]; $all_genres=[]; $data_age=[];
$res4 = $conn->query("SELECT Age, Genre, COUNT(Genre) AS total FROM client WHERE Age!='0' GROUP BY Genre,Age ORDER BY Age,Genre");
if ($res4) while ($row = $res4->fetch_assoc()) {
    $a=$row['Age']; $g=$row['Genre'];
    if(!in_array($a,$all_ages))   $all_ages[]=$a;
    if(!in_array($g,$all_genres)) $all_genres[]=$g;
    $data_age[$g][$a]=(int)$row['total'];
}
$datasets_age=[];
foreach($all_genres as $genre){
    $pts=[];
    foreach($all_ages as $a) $pts[]=$data_age[$genre][$a]??0;
    $r=rand(0,255);$g2=rand(0,255);$b=rand(0,255);
    $datasets_age[]=['label'=>$genre,'data'=>$pts,
        'backgroundColor'=>"rgba($r,$g2,$b,0.5)",'borderColor'=>"rgba($r,$g2,$b,1)",'borderWidth'=>1];
}

$conn->close();

$page_title  = 'Visualisation graphique';
$active_page = 'visualisation';
$breadcrumb  = ['Accueil'=>'index.php','Visualisation'=>''];
include 'sidebar.php';
?>

    <div class="dv-content dv-animate">

        <div class="dv-page-header">
            <h1><i class="bi bi-bar-chart-line" style="color:var(--accent);margin-right:8px;"></i>Représentation graphique</h1>
            <div class="dv-breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                <span>Visualisation</span>
            </div>
        </div>

        <!-- Stats rapides -->
        <div class="dv-stats-grid dv-animate dv-animate-d1">
            <div class="dv-stat primary">
                <div class="dv-stat-label">Total médicaments</div>
                <div class="dv-stat-value"><?php echo number_format($total_meds); ?></div>
                <div class="dv-stat-sub">unités dispensées</div>
            </div>
            <div class="dv-stat success">
                <div class="dv-stat-label">Clients</div>
                <div class="dv-stat-value"><?php echo number_format($total_clients); ?></div>
                <div class="dv-stat-sub">enregistrés</div>
            </div>
            <div class="dv-stat info">
                <div class="dv-stat-label">Types de médicaments</div>
                <div class="dv-stat-value"><?php echo count($types_medoc); ?></div>
                <div class="dv-stat-sub">catégories</div>
            </div>
            <div class="dv-stat warning">
                <div class="dv-stat-label">Tranches d'âge</div>
                <div class="dv-stat-value"><?php echo count($all_ages); ?></div>
                <div class="dv-stat-sub">groupes</div>
            </div>
        </div>

        <!-- Graphique 1 : bar médicaments par type -->
        <div class="dv-card dv-animate dv-animate-d2">
            <div class="dv-card-header">
                <i class="bi bi-bar-chart"></i> Nombre de médicaments vendus par type
            </div>
            <canvas id="mygraph" height="80"></canvas>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:10px;" id="lastModified"></div>
        </div>

        <!-- Graphiques 2 & 3 côte à côte -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;" class="dv-animate dv-animate-d2">

            <div class="dv-card">
                <div class="dv-card-header">
                    <i class="bi bi-bar-chart-steps"></i> Médicaments par mois
                </div>
                <canvas id="medicamentsparmois" height="160"></canvas>
            </div>

            <div class="dv-card">
                <div class="dv-card-header">
                    <i class="bi bi-pie-chart"></i> Type de clients
                </div>
                <canvas id="clientPieChart" height="160"></canvas>
            </div>

        </div>

        <!-- Graphique 4 : Âge & Genre -->
        <div class="dv-card dv-animate dv-animate-d3">
            <div class="dv-card-header">
                <i class="bi bi-people"></i> Répartition des clients par âge et genre
            </div>
            <canvas id="agegenre" height="80"></canvas>
        </div>

    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#8a99aa';

// 1. Bar — médicaments par type
new Chart(document.getElementById('mygraph').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?php echo json_encode($types_medoc); ?>,
        datasets:[{
            label:'Nombre de médicaments',
            backgroundColor:'rgba(10,108,188,0.6)',
            borderColor:'rgba(10,108,188,1)',
            data:<?php echo json_encode($totals_medoc); ?>
        }]
    },
    options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true}}}
});

// 2. Bar empilé — médicaments par mois
new Chart(document.getElementById('medicamentsparmois').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?php echo json_encode($all_months); ?>,
        datasets:<?php echo json_encode($datasets_mois); ?>
    },
    options:{responsive:true,plugins:{legend:{position:'top'}},
             scales:{y:{beginAtZero:true,title:{display:true,text:'Nb médicaments'}},
                     x:{title:{display:true,text:'Mois (AAAA-MM)'}}}}
});

// 3. Pie — type clients
new Chart(document.getElementById('clientPieChart').getContext('2d'),{
    type:'pie',
    data:{
        labels:<?php echo json_encode($types_clients); ?>,
        datasets:[{
            data:<?php echo json_encode($totals_clients); ?>,
            backgroundColor:['rgba(10,108,188,0.7)','rgba(0,184,148,0.7)','rgba(99,102,241,0.7)','rgba(245,158,11,0.7)']
        }]
    },
    options:{responsive:true,plugins:{legend:{position:'top'}}}
});

// 4. Bar — âge et genre
new Chart(document.getElementById('agegenre').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?php echo json_encode($all_ages); ?>,
        datasets:<?php echo json_encode($datasets_age); ?>
    },
    options:{responsive:true,plugins:{legend:{position:'top'}},
             scales:{y:{beginAtZero:true,title:{display:true,text:'Nombre de clients'}},
                     x:{title:{display:true,text:'Âge'}}}}
});

// Date de modification
document.addEventListener('DOMContentLoaded',function(){
    var el=document.getElementById('lastModified');
    if(el){
        var d=new Date(document.lastModified);
        el.textContent='Dernière modification : '+d.toLocaleDateString('fr-FR',{year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});
    }
});
</script>

<?php include 'footer_shared.php'; ?>
