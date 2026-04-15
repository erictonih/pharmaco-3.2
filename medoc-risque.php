<?php
/**
 * medoc-risque.php
 * Endpoint JSON + include PHP pour l'analyse prédictive épidémique
 * Algorithme : score de risque multi-facteurs basé sur :
 *   - Volume de médicaments vendus par district
 *   - Tendance temporelle (accélération des ventes)
 *   - Diversité des types de médicaments (co-morbidités)
 *   - Poids clinique par type (Antipyrétique & Antidiarrhéique = signaux forts)
 */

$host   = 'sql209.infinityfree.com';
$dbname = 'if0_41511897_pharmacie';
$user   = 'if0_41511897';
$pass   = 'jR9A6jlS7c8f8r';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    if (php_sapi_name() !== 'cli' && empty($_SERVER['SCRIPT_FILENAME'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $conn->connect_error]);
        exit;
    }
}
$conn->set_charset('utf8mb4');

// ── Correction encodage ─────────────────────────────────
$fix = ['Antidiarrh?ique','AntidiarrhÃ©ique','AntipyrÃ©tique','Antipyr?tique','Hospitalis?'];
$rep = ['Antidiarrhéique','Antidiarrhéique','Antipyrétique','Antipyrétique','Hospitalisé'];

// ── Poids cliniques par type de médicament ──────────────
// Plus le poids est élevé, plus ce médicament est un signal d'alerte épidémique
$poids_cliniques = [
    'Antipyrétique'    => 3.0,   // Fièvre = signal fort (palu, typhoïde, dengue)
    'Antidiarrhéique'  => 2.8,   // Diarrhée = choléra, gastro-entérite épidémique
    'Antibiotique'     => 2.0,   // Infections bactériennes
    'Antalgique'       => 1.2,   // Douleur, moins spécifique
    'VitamineC'        => 0.5,   // Supplément, faible signal
    'Antihistaminique' => 1.5,   // Allergies / rhinovirus
];

// ── 1. Données agrégées par district et type ────────────
$analyse = [];
$sql_analyse = "
    SELECT c.District,
           m.Type,
           SUM(m.Nombres)   AS Total,
           COUNT(*)          AS NbVentes,
           MAX(m.Date)       AS DernierAchat,
           MIN(m.Date)       AS PremierAchat
    FROM medicament m
    INNER JOIN client c ON c.Id = m.id_client
    WHERE c.District != ''
      AND m.Date != '0000-00-00 00:00:00'
    GROUP BY c.District, m.Type
    ORDER BY c.District, Total DESC
";
$res = $conn->query($sql_analyse);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['Type'] = str_replace($fix, $rep, $row['Type']);
        $analyse[] = $row;
    }
}

// ── 2. Tendance mensuelle par district ──────────────────
$tendances = [];
$sql_tendance = "
    SELECT c.District,
           DATE_FORMAT(m.Date, '%Y-%m') AS Mois,
           SUM(m.Nombres) AS Total
    FROM medicament m
    INNER JOIN client c ON c.Id = m.id_client
    WHERE c.District != ''
      AND m.Date != '0000-00-00 00:00:00'
    GROUP BY c.District, Mois
    ORDER BY c.District, Mois
";
$res2 = $conn->query($sql_tendance);
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $d = $row['District'];
        if (!isset($tendances[$d])) $tendances[$d] = [];
        $tendances[$d][$row['Mois']] = (int)$row['Total'];
    }
}

// ── 3. Calcul du score de risque par district ───────────
$risque_districts = [];
$alertes = [];

// Grouper les données par district
$par_district = [];
foreach ($analyse as $row) {
    $d = $row['District'];
    if (!isset($par_district[$d])) $par_district[$d] = [];
    $par_district[$d][] = $row;
}

foreach ($par_district as $district => $rows) {
    $score_base  = 0;
    $total_ventes = 0;
    $types_presents = [];

    foreach ($rows as $r) {
        $type   = $r['Type'];
        $total  = (int)$r['Total'];
        $poids  = $poids_cliniques[$type] ?? 1.0;
        $score_base  += $total * $poids;
        $total_ventes += $total;
        $types_presents[] = $type;
    }

    // Diversité : plus de types différents = signal plus fort
    $diversite = count($types_presents);
    $bonus_diversite = ($diversite >= 3) ? 1.3 : ($diversite >= 2 ? 1.15 : 1.0);

    // Tendance : accélération sur les 2 derniers mois
    $bonus_tendance = 1.0;
    if (isset($tendances[$district]) && count($tendances[$district]) >= 2) {
        $mois_sorted = array_values($tendances[$district]);
        $n = count($mois_sorted);
        $dernier = $mois_sorted[$n-1];
        $avant   = $mois_sorted[$n-2];
        if ($avant > 0 && $dernier > $avant * 1.5) {
            $bonus_tendance = 1.5; // accélération forte
        } elseif ($avant > 0 && $dernier > $avant * 1.2) {
            $bonus_tendance = 1.25;
        }
    }

    $score_final = $score_base * $bonus_diversite * $bonus_tendance;

    // Normalisation : niveau 1-5
    if      ($score_final >= 200) $niveau = 5;
    elseif  ($score_final >= 100) $niveau = 4;
    elseif  ($score_final >= 50)  $niveau = 3;
    elseif  ($score_final >= 20)  $niveau = 2;
    else                          $niveau = 1;

    $risque_districts[$district] = $niveau;

    // Alertes automatiques (niveau 3+)
    if ($niveau >= 3) {
        // Identifier le médicament dominant
        usort($rows, fn($a,$b) => $b['Total'] - $a['Total']);
        $type_dominant = $rows[0]['Type'] ?? 'Inconnu';
        $total_dom     = $rows[0]['Total'] ?? 0;

        // Identifier le signal d'alerte
        $signal = '';
        foreach (['Antipyrétique','Antidiarrhéique','Antibiotique'] as $t) {
            foreach ($rows as $r) {
                if ($r['Type'] === $t) {
                    $signal = $t;
                    break 2;
                }
            }
        }

        $alerte_type = match($signal) {
            'Antipyrétique'   => 'Risque fièvre / paludisme / dengue',
            'Antidiarrhéique' => 'Risque gastro-entérite / choléra',
            'Antibiotique'    => 'Risque infection bactérienne',
            default           => 'Signal épidémique général',
        };

        $alertes[] = [
            'district'      => $district,
            'niveau'        => $niveau,
            'score'         => round($score_final, 1),
            'type_dominant' => $type_dominant,
            'total'         => $total_ventes,
            'signal'        => $alerte_type,
            'tendance'      => $bonus_tendance > 1.0 ? 'En hausse' : 'Stable',
            'diversite'     => $diversite,
        ];
    }
}

// Trier alertes par niveau décroissant
usort($alertes, fn($a,$b) => $b['niveau'] - $a['niveau']);

$conn->close();

// ── Si appelé directement en JSON ──────────────────────
if (basename($_SERVER['PHP_SELF'] ?? '') === 'medoc-risque.php'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($risque_districts, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
