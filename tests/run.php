<?php
declare(strict_types=1);
// Tests des services : php tests/run.php   (aucune dépendance, aucune base, aucun réseau)
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Sheets\FixtureSheetsSource;
use Pharmaco\Services\{MpoxDataset, EpiAnalyticsService, OutbreakAlertService, DataQualityService};

$fails = 0; $n = 0;
function t(string $name, $got, $want): void {
    global $fails, $n; $n++;
    if ($got === $want || (is_float($got) && is_float($want) && abs($got - $want) < 1e-9)) { echo "  ok   $name\n"; return; }
    $fails++; echo "  FAIL $name : obtenu " . json_encode($got) . " / attendu " . json_encode($want) . "\n";
}
$exp = json_decode((string) file_get_contents(__DIR__ . '/fixtures/expected.json'), true);
$src = new FixtureSheetsSource(__DIR__ . '/fixtures/mpox_synthetic.json');
$ds  = MpoxDataset::load($src);

echo "Normalisation\n";
t('parseDate 1/1/2026', MpoxDataset::parseDate('1/1/2026'), '2026-01-01');
t('parseDate 24/12/2025', MpoxDataset::parseDate('24/12/2025'), '2025-12-24');
t('parseDate ISO', MpoxDataset::parseDate('2026-02-03'), '2026-02-03');
t('parseDate serial 46023', MpoxDataset::parseDate('46023'), '2026-01-01');
t('parseDate invalide', MpoxDataset::parseDate('31/02/2026'), null);
t('parseDate texte', MpoxDataset::parseDate('pas une date'), null);
t('clé district accents', MpoxDataset::key('Antananarivo-Nord'), 'antananarivonord');
t('alias Mahajanga II', MpoxDataset::key('Mahajanga II'), 'mahajangarural');
t('alias Toliara 1', MpoxDataset::key('Toliara 1'), 'toliaryurban');
t('tranche 4 ans', MpoxDataset::ageGroup(4), '< 5');
t('tranche 45', MpoxDataset::ageGroup(45), '45+');
t('lignes lues (168 valides + 1 date illisible)', count($ds->cases), $exp['parsed_total'] + $exp['bad_rows']);
t('aucune donnée nominative dans les cas', array_key_exists('NOM PRENOMS', $ds->cases[0]) || str_contains(json_encode($ds->cases), 'FICTIF'), false);

echo "Indicateurs\n";
$ov = (new EpiAnalyticsService($ds))->overview();
foreach (['confirmed', 'suspect', 'probable', 'negative'] as $s) { t("statut $s", $ov['status'][$s], $exp['status'][$s]); }
t('statut unknown (ligne date illisible)', $ov['status']['unknown'], 1);
t('décès (via « Décès oui/non »)', $ov['deaths'], $exp['deaths']);
t('hospitalisés', $ov['hospitalised'], $exp['hospitalised']);
t('première date', $ov['first_date'], $exp['first']);
t('dernière date', $ov['last_date'], $exp['last']);
t('cas 7 derniers jours', $ov['trend']['current_7d'], $exp['cur7']);
t('cas 7 jours précédents', $ov['trend']['previous_7d'], $exp['prev7']);
t('districts', $ov['districts'], $exp['districts']);

echo "Séries & géographie\n";
$ts = (new EpiAnalyticsService($ds))->timeSeries();
t('somme hebdomadaire = cas datés', array_sum($ts['weeks']['total']), $exp['parsed_total']);
t('cumul final = cas datés', end($ts['weeks']['cumulative']), $exp['parsed_total']);
t('semaines sans trous', count($ts['weeks']['labels']) >= 20, true);
$geo = (new EpiAnalyticsService($ds))->geography();
t('carte : clé carte mahajangaurban (alias Mahajanga I)', isset($geo['map']['mahajangaurban']), true);

echo "Alertes\n";
$al = (new OutbreakAlertService($ds))->districtAlerts();
t('référence = dernière notification', $al['reference_date'], $exp['last']);
t('foyer détecté en premier', $al['alerts'][0]['district'] ?? null, 'Mahajanga I');
t('cas récents du foyer', $al['alerts'][0]['recent'] ?? null, $exp['mahajanga_recent']);
t('niveau critique', $al['alerts'][0]['level'] ?? null, 3);
t('poisson P(X>=1 | 1) ≈ 0.632', round(OutbreakAlertService::poissonTail(1, 1.0), 3), 0.632);
t('poisson P(X>=5 | 2) ≈ 0.053', round(OutbreakAlertService::poissonTail(5, 2.0), 3), 0.053);

echo "Qualité\n";
$q = (new DataQualityService($ds))->report();
t('date illisible détectée', $q['fields']['date']['missing'], 1);
t('statut inconnu détecté', $q['fields']['status']['missing'], 1);

echo "Filtres\n";
$f = $ds->filter(['district' => 'Mahajanga I']);
t('filtre district', count(array_filter($f->cases, fn($c) => $c['district'] !== 'Mahajanga I')), 0);
t('filtre période', count($ds->filter(['from' => '2026-03-25'])->cases) > 0, true);

echo "\n$n tests, $fails échec(s)\n";
exit($fails ? 1 : 0);
