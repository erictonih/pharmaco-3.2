<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use DateTimeImmutable;

/**
 * Service d'analyse épidémiologique (temps / lieu / personne / clinique / laboratoire).
 * Pur calcul : reçoit un MpoxDataset, retourne des tableaux prêts pour les graphiques.
 */
final class EpiAnalyticsService
{
    public function __construct(private MpoxDataset $ds) {}

    private static function pct(int|float $n, int|float $d, int $p = 1): ?float { return $d > 0 ? round($n / $d * 100, $p) : null; }
    private static function median(array $v): ?float {
        if (!$v) { return null; } sort($v); $n = count($v); $m = intdiv($n, 2);
        return $n % 2 ? (float) $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }

    /** Indicateurs clés. */
    public function overview(): array {
        $c = $this->ds->cases; $total = count($c);
        $st = ['confirmed' => 0, 'probable' => 0, 'suspect' => 0, 'negative' => 0, 'unknown' => 0];
        $deaths = $deathsConf = $hosp = $icu = $sampled = $districts = 0; $ages = []; $reg = []; $dist = [];
        foreach ($c as $x) {
            $st[$x['status']]++;
            if ($x['outcome'] === 'deceased') { $deaths++; if ($x['status'] === 'confirmed') { $deathsConf++; } }
            if ($x['hospitalised']) { $hosp++; } if ($x['icu']) { $icu++; } if ($x['sampled']) { $sampled++; }
            if ($x['age'] !== null) { $ages[] = $x['age']; }
            if ($x['region'] !== 'Non renseigné') { $reg[$x['region']] = true; }
            if ($x['district'] !== 'Non renseigné') { $dist[$x['district']] = true; }
        }
        $last = $this->ds->lastDate();
        $trend = $last ? $this->recentTrend($last) : null;
        return [
            'total' => $total, 'status' => $st,
            'deaths' => $deaths, 'cfr_confirmed' => self::pct($deathsConf, $st['confirmed']),
            'hospitalised' => $hosp, 'hospitalisation_rate' => self::pct($hosp, $total), 'icu' => $icu,
            'sampled' => $sampled, 'sampling_rate' => self::pct($sampled, $total),
            'positivity' => self::pct($st['confirmed'], $st['confirmed'] + $st['negative']),
            'age_median' => self::median($ages), 'age_mean' => $ages ? round(array_sum($ages) / count($ages), 1) : null,
            'regions' => count($reg), 'districts' => count($dist),
            'first_date' => $this->ds->firstDate(), 'last_date' => $last, 'trend' => $trend,
        ];
    }

    /** Cas des 7 derniers jours vs 7 jours précédents (référence = dernière notification). */
    private function recentTrend(string $last): array {
        $d = new DateTimeImmutable($last);
        $a = $d->modify('-6 days')->format('Y-m-d'); $b = $d->modify('-13 days')->format('Y-m-d');
        $cur = $prev = 0;
        foreach ($this->ds->cases as $x) {
            if ($x['date'] === null) { continue; }
            if ($x['date'] >= $a && $x['date'] <= $last) { $cur++; } elseif ($x['date'] >= $b && $x['date'] < $a) { $prev++; }
        }
        return ['current_7d' => $cur, 'previous_7d' => $prev, 'change_pct' => $prev > 0 ? round(($cur - $prev) / $prev * 100, 0) : null];
    }

    /** Courbe épidémique hebdomadaire (semaines ISO, sans trous) + mensuelle. */
    public function timeSeries(): array {
        $weeks = []; $months = [];
        foreach ($this->ds->cases as $x) {
            if ($x['week'] === null) { continue; }
            $weeks[$x['week']] ??= ['total' => 0, 'confirmed' => 0, 'deaths' => 0];
            $months[$x['ym']] ??= ['total' => 0, 'confirmed' => 0, 'deaths' => 0];
            foreach ([&$weeks[$x['week']], &$months[$x['ym']]] as &$b) {
                $b['total']++; if ($x['status'] === 'confirmed') { $b['confirmed']++; } if ($x['outcome'] === 'deceased') { $b['deaths']++; }
            }
            unset($b);
        }
        ksort($weeks); ksort($months);
        // combler les semaines sans cas
        if ($weeks) {
            $k = array_keys($weeks);
            [$y1, $w1] = array_map('intval', explode('-W', $k[0])); [$y2, $w2] = array_map('intval', explode('-W', end($k)));
            $cur = (new DateTimeImmutable())->setISODate($y1, $w1); $end = (new DateTimeImmutable())->setISODate($y2, $w2);
            $filled = [];
            for ($i = 0; $cur <= $end && $i < 600; $i++, $cur = $cur->modify('+1 week')) {
                $key = $cur->format('o') . '-W' . $cur->format('W');
                $filled[$key] = $weeks[$key] ?? ['total' => 0, 'confirmed' => 0, 'deaths' => 0];
            }
            $weeks = $filled;
        }
        $labels = array_keys($weeks); $tot = array_column($weeks, 'total'); $ma = [];
        foreach ($tot as $i => $_) { $s = array_slice($tot, max(0, $i - 3), min(4, $i + 1)); $ma[] = round(array_sum($s) / count($s), 1); }
        $cum = []; $run = 0; foreach ($tot as $v) { $run += $v; $cum[] = $run; }
        return [
            'weeks' => ['labels' => $labels, 'total' => $tot, 'confirmed' => array_column($weeks, 'confirmed'),
                        'deaths' => array_column($weeks, 'deaths'), 'moving_avg_4w' => $ma, 'cumulative' => $cum],
            'months' => ['labels' => array_keys($months), 'total' => array_column($months, 'total'),
                         'confirmed' => array_column($months, 'confirmed'), 'deaths' => array_column($months, 'deaths')],
        ];
    }

    /** Répartition géographique. */
    public function geography(): array {
        $by = static function (array $cases, string $f): array {
            $o = [];
            foreach ($cases as $x) {
                $k = $x[$f]; $o[$k] ??= ['total' => 0, 'confirmed' => 0, 'deaths' => 0];
                $o[$k]['total']++; if ($x['status'] === 'confirmed') { $o[$k]['confirmed']++; } if ($x['outcome'] === 'deceased') { $o[$k]['deaths']++; }
            }
            uasort($o, fn($a, $b) => $b['total'] <=> $a['total']);
            return $o;
        };
        $districts = $by($this->ds->cases, 'district');
        // clé carte : district_key -> compteurs (+ nom d'origine)
        $map = [];
        foreach ($this->ds->cases as $x) {
            $k = $x['district_key']; if ($k === '') { continue; }
            $map[$k] ??= ['name' => $x['district'], 'region' => $x['region'], 'total' => 0, 'confirmed' => 0, 'deaths' => 0];
            $map[$k]['total']++; if ($x['status'] === 'confirmed') { $map[$k]['confirmed']++; } if ($x['outcome'] === 'deceased') { $map[$k]['deaths']++; }
        }
        $last = $this->ds->lastDate(); $recent = [];
        if ($last) {
            $from = (new DateTimeImmutable($last))->modify('-27 days')->format('Y-m-d');
            $recent = $by(array_filter($this->ds->cases, fn($x) => $x['date'] !== null && $x['date'] >= $from), 'district');
        }
        return ['regions' => $by($this->ds->cases, 'region'), 'districts' => $districts, 'districts_28d' => $recent, 'map' => $map];
    }

    /** Âge, sexe, VIH, vaccination, grossesse. */
    public function demographics(): array {
        $pyr = []; foreach (MpoxDataset::AGE_GROUPS as $g) { $pyr[$g] = ['M' => 0, 'F' => 0, 'U' => 0]; }
        $sex = ['M' => 0, 'F' => 0, 'U' => 0]; $hiv = []; $vac = ['yes' => 0, 'no' => 0, 'unknown' => 0]; $preg = 0; $unknownAge = 0; $link = 0;
        foreach ($this->ds->cases as $x) {
            $sex[$x['sex']]++;
            if ($x['age'] === null) { $unknownAge++; } else { $pyr[$x['age_group']][$x['sex']]++; }
            $hiv[$x['hiv']] = ($hiv[$x['hiv']] ?? 0) + 1; $vac[$x['vaccinated']]++;
            if ($x['pregnant']) { $preg++; } if ($x['epi_link']) { $link++; }
        }
        $total = count($this->ds->cases);
        $ratioByAge = [];
        foreach ($pyr as $g => $c) { $ratioByAge[$g] = ['M' => $c['M'], 'F' => $c['F'], 'ratio' => $c['F'] > 0 ? round($c['M'] / $c['F'], 2) : null]; }
        return ['age_sex' => $pyr, 'sex_ratio_by_age' => $ratioByAge, 'sex' => $sex, 'sex_ratio_mf' => $sex['F'] > 0 ? round($sex['M'] / $sex['F'], 2) : null,
                'unknown_age' => $unknownAge, 'hiv' => $hiv, 'vaccination' => $vac, 'pregnant' => $preg,
                'epi_link_pct' => self::pct($link, $total)];
    }

    /** Évolution clinique, hospitalisation, létalité par tranche d'âge. */
    public function clinical(): array {
        $out = ['favorable' => 0, 'deceased' => 0, 'other' => 0, 'unknown' => 0]; $byAge = [];
        foreach (MpoxDataset::AGE_GROUPS as $g) { $byAge[$g] = ['cases' => 0, 'deaths' => 0, 'hospitalised' => 0]; }
        $raw = [];
        foreach ($this->ds->cases as $x) {
            $out[$x['outcome']]++;
            if ($x['raw_evolution'] !== '') { $raw[$x['raw_evolution']] = ($raw[$x['raw_evolution']] ?? 0) + 1; }
            if (isset($byAge[$x['age_group']])) {
                $byAge[$x['age_group']]['cases']++;
                if ($x['outcome'] === 'deceased') { $byAge[$x['age_group']]['deaths']++; }
                if ($x['hospitalised']) { $byAge[$x['age_group']]['hospitalised']++; }
            }
        }
        foreach ($byAge as &$b) { $b['cfr'] = self::pct($b['deaths'], $b['cases']); $b['hosp_rate'] = self::pct($b['hospitalised'], $b['cases']); }
        unset($b); arsort($raw);
        return ['outcomes' => $out, 'by_age' => $byAge, 'evolution_values' => $raw];
    }

    /** Laboratoire : taux de prélèvement, positivité, délai, clades. */
    public function laboratory(): array {
        $labs = []; $clades = []; $delays = []; $conf = $neg = $sampled = 0;
        foreach ($this->ds->cases as $x) {
            if ($x['sampled']) { $sampled++; }
            if ($x['status'] === 'confirmed') { $conf++; } elseif ($x['status'] === 'negative') { $neg++; }
            if ($x['lab'] !== '') { $labs[$x['lab']] ??= ['tested' => 0, 'confirmed' => 0]; $labs[$x['lab']]['tested']++; if ($x['status'] === 'confirmed') { $labs[$x['lab']]['confirmed']++; } }
            if ($x['clade'] !== '') { $clades[$x['clade']] = ($clades[$x['clade']] ?? 0) + 1; }
            if ($x['date'] && $x['diag_date']) {
                $d = (strtotime($x['diag_date']) - strtotime($x['date'])) / 86400;
                if ($d >= 0 && $d <= 60) { $delays[] = $d; }
            }
        }
        arsort($clades);
        return ['sampled' => $sampled, 'sampling_rate' => self::pct($sampled, count($this->ds->cases)),
                'positivity' => self::pct($conf, $conf + $neg), 'labs' => $labs, 'clades' => $clades,
                'delay_median_days' => self::median($delays), 'delay_n' => count($delays)];
    }
}
