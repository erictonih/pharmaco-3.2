<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use DateTimeImmutable;

/**
 * « Point de situation » : calcule, pour une période [from, to], toutes les données des 9 diapositives
 * (situation du jour et cumulée, décès, courbes journalières, tableau par région, âge/sexe, carte et districts « hot spot »).
 * « Dernières semaines » = les 3 dernières semaines ISO se terminant à `to` (semaine en cours incluse), bornées par `from`.
 */
final class ReportService
{
    public const RECENT_WEEKS = 3;

    public function __construct(private MpoxDataset $full) {}

    /** Code semaine ISO : S41_2026. */
    public static function weekCode(string $date): string {
        $d = new DateTimeImmutable($date); return 'S' . $d->format('W') . '_' . $d->format('o');
    }

    /** @return array<string,mixed> */
    public function build(?string $from = null, ?string $to = null): array {
        $first = $this->full->firstDate(); $last = $this->full->lastDate();
        if ($first === null || $last === null) { return ['empty' => true]; }
        $from = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) ? max($from, $first) : $first;
        $to   = ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   ? min($to, $last)   : $last;
        if ($from > $to) { [$from, $to] = [$to, $from]; }

        $monday = (new DateTimeImmutable($to))->modify('monday this week')->modify('-' . (self::RECENT_WEEKS - 1) . ' weeks')->format('Y-m-d');
        $recentFrom = max($monday, $from);

        $ds     = $this->full->filter(['from' => $from, 'to' => $to]);
        $recent = $this->full->filter(['from' => $recentFrom, 'to' => $to]);
        $day    = $this->full->filter(['from' => $to, 'to' => $to]);

        $cum = self::counts($ds->cases);
        $deaths = array_values(array_filter($ds->cases, fn($c) => $c['outcome'] === 'deceased'));
        usort($deaths, fn($a, $b) => strcmp((string) $a['death_date'], (string) $b['death_date']));
        $confDeaths = array_filter($deaths, fn($c) => $c['status'] === 'confirmed');
        $nd = count($confDeaths);
        $pvvih = count(array_filter($confDeaths, fn($c) => $c['hiv'] === 'Positif'));
        $u5 = count(array_filter($confDeaths, fn($c) => $c['age'] !== null && $c['age'] < 5));

        // districts
        $touched = []; $active = []; $hot = [];
        foreach ($ds->cases as $c) { if ($c['status'] === 'confirmed' && $c['district_key'] !== '') { $touched[$c['district_key']] = true; } }
        foreach ($recent->cases as $c) {
            if ($c['status'] === 'confirmed' && $c['district_key'] !== '') {
                $active[$c['district_key']] = true; $hot[$c['district']] = ($hot[$c['district']] ?? 0) + 1;
            }
        }
        arsort($hot); $rc = $this->counts($recent->cases)['confirmed'];
        $hotspots = []; foreach (array_slice($hot, 0, 5, true) as $name => $n) { $hotspots[] = ['district' => $name, 'cases' => $n, 'pct' => $rc ? round(100 * $n / $rc) : 0]; }

        $mk = static function (array $cases): array {
            $m = [];
            foreach ($cases as $c) {
                if ($c['status'] !== 'confirmed' || $c['district_key'] === '') { continue; }
                $m[$c['district_key']] ??= ['name' => $c['district'], 'confirmed' => 0]; $m[$c['district_key']]['confirmed']++;
            }
            return $m;
        };
        $map = $mk($ds->cases); $mapDay = $mk($day->cases);

        return [
            'empty' => false, 'from' => $from, 'to' => $to, 'data_first' => $first, 'data_last' => $last,
            'recent_from' => $recentFrom,
            'label' => ['from' => self::weekCode($from), 'to' => self::weekCode($to), 'recent_from' => self::weekCode($recentFrom)],
            'day'   => ['date' => $to, 'counts' => self::counts($day->cases), 'deaths' => count(array_filter($deaths, fn($c) => $c['death_date'] === $to)), 'deaths_confirmed' => count(array_filter($deaths, fn($c) => $c['death_date'] === $to && $c['status'] === 'confirmed'))],
            'cumul' => $cum + [
                'recovered' => count(array_filter($ds->cases, fn($c) => $c['outcome'] === 'favorable')),
                'deaths_confirmed' => $nd,
                'cfr' => $cum['confirmed'] ? round(100 * $nd / $cum['confirmed'], 2) : null,
                'deaths_pvvih_pct' => $nd ? round(100 * $pvvih / $nd, 1) : null,
                'deaths_u5_pct' => $nd ? round(100 * $u5 / $nd, 1) : null,
                'positivity' => $cum['tested'] ? round(100 * $cum['confirmed'] / $cum['tested'], 2) : null,
            ],
            'deaths' => array_map(fn($c) => [
                'date' => $c['death_date'], 'district' => $c['district'], 'n' => 1, 'age' => $c['age_label'],
                'sex' => $c['sex'] === 'U' ? '' : $c['sex'], 'comorbidity' => $c['comorbidity'], 'confirmed' => $c['status'] === 'confirmed',
            ], $deaths),
            'daily_period' => self::daily($ds->cases, $from, $to),
            'daily_recent' => self::daily($recent->cases, $recentFrom, $to),
            'recent' => self::counts($recent->cases),
            'regions' => $this->regionTable($recent->cases),
            'age_sex' => $this->ageSex($recent->cases),
            'map' => $map, 'map_day' => $mapDay,
            'kpi' => ['recent_confirmed' => $rc, 'cumul_confirmed' => $cum['confirmed'], 'districts_active' => count($active), 'districts_touched' => count($touched), 'day_confirmed' => self::counts($day->cases)['confirmed']],
            'hotspots' => $hotspots,
        ];
    }

    /** @param array<int,array<string,mixed>> $cases */
    public static function counts(array $cases): array {
        $o = ['total' => 0, 'sampled' => 0, 'tested' => 0, 'confirmed' => 0, 'negative' => 0, 'inconclusive' => 0, 'suspect' => 0, 'probable' => 0];
        foreach ($cases as $c) {
            $o['total']++;
            if ($c['sampled']) { $o['sampled']++; }
            match (true) {
                $c['status'] === 'confirmed' => $o['confirmed']++,
                $c['status'] === 'negative'  => $o['negative']++,
                $c['status'] === 'suspect'   => $o['suspect']++,
                $c['status'] === 'probable'  => $o['probable']++,
                $c['inconclusive']           => $o['inconclusive']++,
                default => null,
            };
        }
        $o['tested'] = $o['confirmed'] + $o['negative'] + $o['inconclusive'];
        return $o;
    }

    /** Série continue jour par jour (jours sans cas = 0). */
    private static function daily(array $cases, string $from, string $to): array {
        $n = []; $c = [];
        foreach ($cases as $x) { if ($x['date'] === null) { continue; } $n[$x['date']] = ($n[$x['date']] ?? 0) + 1; if ($x['status'] === 'confirmed') { $c[$x['date']] = ($c[$x['date']] ?? 0) + 1; } }
        $out = ['dates' => [], 'notified' => [], 'confirmed' => []];
        for ($d = new DateTimeImmutable($from), $end = new DateTimeImmutable($to); $d <= $end; $d = $d->modify('+1 day')) {
            $k = $d->format('Y-m-d'); $out['dates'][] = $k; $out['notified'][] = $n[$k] ?? 0; $out['confirmed'][] = $c[$k] ?? 0;
        }
        return $out;
    }

    private function regionTable(array $cases): array {
        $rows = [];
        foreach ($this->full->distinct('region') as $r) { $rows[$r] = ['region' => $r] + self::counts([]); }
        foreach ($cases as $c) { if ($c['region'] === 'Non renseigné') { $rows['Non renseigné'] ??= ['region' => 'Non renseigné'] + self::counts([]); } }
        $by = [];
        foreach ($cases as $c) { $by[$c['region']][] = $c; }
        foreach ($by as $r => $cs) { $rows[$r] = ['region' => $r] + self::counts($cs); }
        $list = array_values($rows);
        usort($list, fn($a, $b) => [$b['confirmed'], $b['total'], $a['region']] <=> [$a['confirmed'], $a['total'], $b['region']]);
        return ['rows' => $list, 'total' => ['region' => 'Total'] + self::counts($cases)];
    }

    private function ageSex(array $cases): array {
        $conf = array_filter($cases, fn($c) => $c['status'] === 'confirmed');
        $g = []; foreach (MpoxDataset::AGE_GROUPS as $k) { $g[$k] = ['M' => 0, 'F' => 0]; }
        $m = 0; $f = 0; $ages = [];
        foreach ($conf as $c) {
            if ($c['sex'] === 'M') { $m++; } elseif ($c['sex'] === 'F') { $f++; }
            if ($c['age'] !== null) { $ages[] = $c['age']; if (isset($g[$c['age_group']]) && $c['sex'] !== 'U') { $g[$c['age_group']][$c['sex']]++; } }
        }
        sort($ages); $n = count($ages);
        $median = $n ? ($n % 2 ? $ages[intdiv($n, 2)] : ($ages[$n / 2 - 1] + $ages[$n / 2]) / 2) : null;
        return ['groups' => $g, 'male' => $m, 'female' => $f, 'n' => count($conf),
                'sex_ratio' => $f ? round($m / $f, 2) : null, 'median_age' => $median, 'min_age' => $n ? $ages[0] : null, 'max_age' => $n ? $ages[$n - 1] : null];
    }
}
