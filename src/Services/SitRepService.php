<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use DateTimeImmutable;

/**
 * SitRep (rapport de situation) : calcule, pour une période choisie (semaine ISO, mois ou dates libres),
 * tous les chiffres du modèle « SitRep mpox » : pastilles, tableau d'indicateurs (période courante + 2 périodes
 * précédentes de même durée + cumul), points saillants, courbe hebdomadaire, cartes, âge/sexe.
 * Les indicateurs absents du Sheet (vaccination dose 1, complétude, PCR/GeneXpert) ne sont jamais inventés :
 * ils sont renvoyés à null et saisis à la main dans la page.
 */
final class SitRepService
{
    private const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function __construct(private MpoxDataset $full) {}

    public static function weekCode(string $date): string {
        $d = new DateTimeImmutable($date); return 'S' . $d->format('W') . '_' . $d->format('o');
    }
    private static function mondayOf(string $date): string { return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d'); }
    private static function longDate(string $iso, bool $year = true): string {
        $d = new DateTimeImmutable($iso); return $d->format('d') . ' ' . self::MOIS[(int) $d->format('n') - 1] . ($year ? ' ' . $d->format('Y') : '');
    }

    /** Libellé « 26 octobre au 01 novembre 2026 » / « 26 au 31 octobre 2026 » / « 29 décembre 2025 au 04 janvier 2026 ». */
    public static function rangeLabel(string $a, string $b): string {
        $da = new DateTimeImmutable($a); $db = new DateTimeImmutable($b);
        if ($a === $b) { return self::longDate($a); }
        if ($da->format('Y-m') === $db->format('Y-m')) { return $da->format('d') . ' au ' . self::longDate($b); }
        if ($da->format('Y') === $db->format('Y')) { return self::longDate($a, false) . ' au ' . self::longDate($b); }
        return self::longDate($a) . ' au ' . self::longDate($b);
    }

    /**
     * Résout la période demandée.
     * @return array{mode:string,from:string,to:string,prev:array<int,array{from:string,to:string,label:string}>}|null
     */
    public function resolve(string $mode, ?string $week, ?string $month, ?string $from, ?string $to): ?array {
        $first = $this->full->firstDate(); $last = $this->full->lastDate();
        if ($first === null || $last === null) { return null; }
        $iso = static fn(?string $s) => $s !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 && strtotime($s) !== false;
        if ($mode === 'month' && $month !== null && preg_match('/^(\d{4})-(\d{2})$/', $month, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $a = new DateTimeImmutable("$m[1]-$m[2]-01"); $f = $a->format('Y-m-d'); $t = $a->modify('last day of this month')->format('Y-m-d');
        } elseif ($mode === 'custom' && $iso($from) && $iso($to)) {
            [$f, $t] = $from <= $to ? [$from, $to] : [$to, $from];
        } else {
            $mode = 'week';
            if ($week !== null && preg_match('/^(\d{4})-W(\d{2})$/', $week, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 53) {
                $a = (new DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2]);
            } else {                                              // défaut : semaine ISO de la dernière notification
                $a = new DateTimeImmutable(self::mondayOf($last));
            }
            $f = $a->format('Y-m-d'); $t = $a->modify('+6 days')->format('Y-m-d');
        }
        $prev = [];
        $len = (int) (new DateTimeImmutable($f))->diff(new DateTimeImmutable($t))->days + 1;
        for ($i = 1; $i <= 2; $i++) {
            if ($mode === 'month') {
                $a = (new DateTimeImmutable($f))->modify("-$i month"); $pf = $a->format('Y-m-d'); $pt = $a->modify('last day of this month')->format('Y-m-d');
            } else {
                $pf = (new DateTimeImmutable($f))->modify('-' . ($len * $i) . ' days')->format('Y-m-d');
                $pt = (new DateTimeImmutable($pf))->modify('+' . ($len - 1) . ' days')->format('Y-m-d');
            }
            $prev[] = ['from' => $pf, 'to' => $pt, 'label' => $this->colLabel($mode, $pf, $pt)];
        }
        return ['mode' => $mode, 'from' => $f, 'to' => $t, 'prev' => $prev];
    }

    private function colLabel(string $mode, string $f, string $t): string {
        if ($mode === 'week') { return explode('_', self::weekCode($f))[0]; }                       // S44
        if ($mode === 'month') { $d = new DateTimeImmutable($f); return ucfirst(self::MOIS[(int) $d->format('n') - 1]) . ' ' . $d->format('Y'); }
        return (new DateTimeImmutable($f))->format('d/m') . '–' . (new DateTimeImmutable($t))->format('d/m');
    }

    /**
     * @param array<string,mixed> $opt mode, week, month, from, to, cum_from, num
     * @return array<string,mixed>
     */
    public function build(array $opt = []): array {
        $first = $this->full->firstDate(); $last = $this->full->lastDate();
        $p = $this->resolve((string) ($opt['mode'] ?? 'week'), $opt['week'] ?? null, $opt['month'] ?? null, $opt['from'] ?? null, $opt['to'] ?? null);
        if ($p === null) { return ['empty' => true]; }
        $cumFrom = (isset($opt['cum_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opt['cum_from']) && $opt['cum_from'] <= $p['to']) ? (string) $opt['cum_from'] : $first;
        $cumFrom = min($cumFrom, $p['from']);
        [$f, $t, $mode] = [$p['from'], $p['to'], $p['mode']];

        $sel = fn(string $a, string $b) => $this->full->filter(['from' => $a, 'to' => $b]);
        $cur = $sel($f, $t); $cum = $sel($cumFrom, $t);
        $curC = $this->counts($cur->cases);

        $cols = [['label' => $this->colLabel($mode, $f, $t), 'from' => $f, 'to' => $t, 'c' => $curC]];
        foreach ($p['prev'] as $pp) { $cols[] = ['label' => $pp['label'], 'from' => $pp['from'], 'to' => $pp['to'], 'c' => $this->counts($sel($pp['from'], $pp['to'])->cases)]; }
        $cols = array_reverse($cols);                                          // ordre chronologique (S42, S43, S44)
        $cumC = $this->counts($cum->cases);

        // numéro du SitRep : nombre de semaines depuis la 1re semaine de notification (inclus) — modifiable
        $weeks = intdiv((int) ((strtotime(self::mondayOf($t)) - strtotime(self::mondayOf($first))) / 86400), 7) + 1;
        $num = isset($opt['num']) && ctype_digit((string) $opt['num']) && (int) $opt['num'] > 0 ? (int) $opt['num'] : $weeks;

        // districts (confirmés)
        $dist = static function (array $cases): array {
            $m = [];
            foreach ($cases as $c) {
                if ($c['status'] !== 'confirmed' || $c['district_key'] === '') { continue; }
                $m[$c['district_key']] ??= ['name' => $c['district'], 'confirmed' => 0]; $m[$c['district_key']]['confirmed']++;
            }
            return $m;
        };
        $mapCur = $dist($cur->cases); $mapPrev = $dist($sel($p['prev'][0]['from'], $p['prev'][0]['to'])->cases); $mapCum = $dist($cum->cases);

        $deaths = array_values(array_filter($cur->cases, fn($c) => $c['outcome'] === 'deceased'));
        $deathsCum = array_filter($cum->cases, fn($c) => $c['outcome'] === 'deceased');
        $deathsConfCum = count(array_filter($deathsCum, fn($c) => $c['status'] === 'confirmed'));

        $age = $this->ageSex($cum->cases);
        $periodTxt = $mode === 'week' ? 'Semaine du ' . self::rangeLabel($f, $t) : ($mode === 'month' ? ucfirst(self::MOIS[(int) (new DateTimeImmutable($f))->format('n') - 1]) . ' ' . (new DateTimeImmutable($f))->format('Y') : 'Période du ' . self::rangeLabel($f, $t));
        $cumLabel = self::weekCode($cumFrom) . ' à ' . self::weekCode($t);

        $res = [
            'empty' => false, 'mode' => $mode, 'from' => $f, 'to' => $t, 'cum_from' => $cumFrom,
            'data_first' => $first, 'data_last' => $last,
            'num' => $num, 'period_text' => $periodTxt, 'cum_label' => $cumLabel,
            'periodicity' => ['week' => 'Hebdomadaire', 'month' => 'Mensuelle', 'custom' => 'Personnalisée'][$mode],
            'circles' => ['notified' => $curC['total'], 'confirmed' => $curC['confirmed'], 'probable' => $curC['probable'], 'suspect' => $curC['suspect'], 'negative' => $curC['negative'], 'deaths' => count($deaths)],
            'cols' => array_map(fn($c) => ['label' => $c['label'], 'from' => $c['from'], 'to' => $c['to']] + $this->indicators($c['from'], $c['to'], $c['c']), $cols),
            'cumul' => $this->indicators($cumFrom, $t, $cumC) + ['label' => $cumLabel],
            'weekly' => $this->weekly($cum->cases, $cumFrom, $t),
            'map_cur' => $mapCur, 'map_prev' => $mapPrev, 'map_cum' => $mapCum,
            'districts_cur' => count($mapCur), 'districts_prev' => count($mapPrev), 'districts_cum' => count($mapCum),
            'age_sex' => $age,
            'deaths_conf_cum' => $deathsConfCum,
        ];
        $res['narrative'] = $this->narrative($res, $cumC, $cur->cases);
        return $res;
    }

    /** @param array<string,int> $c */
    private function indicators(string $from, string $to, array $c): array {
        $cases = $this->full->filter(['from' => $from, 'to' => $to])->cases;
        $hosp = 0; $rec = 0; $dec = 0;
        foreach ($cases as $x) {
            if ($x['status'] === 'confirmed' && $x['hospitalised']) { $hosp++; }
            if ($x['outcome'] === 'favorable') { $rec++; }
            if ($x['outcome'] === 'deceased') { $dec++; }
        }
        return [
            'notified' => $c['total'], 'sampled' => $c['sampled'], 'tested' => $c['tested'], 'confirmed' => $c['confirmed'],
            'suspect' => $c['suspect'], 'probable' => $c['probable'], 'negative' => $c['negative'],
            'positivity' => $c['tested'] ? round(100 * $c['confirmed'] / $c['tested'], 2) : null,
            'hospitalised' => $hosp, 'recovered' => $rec, 'deaths' => $dec,
            'cfr' => $c['confirmed'] ? round(100 * count(array_filter($cases, fn($x) => $x['outcome'] === 'deceased' && $x['status'] === 'confirmed')) / $c['confirmed'], 2) : null,
        ];
    }

    /** @param array<int,array<string,mixed>> $cases */
    public static function counts(array $cases): array { return ReportService::counts($cases); }

    /** Série hebdomadaire (semaines ISO, lundi → dimanche) : notifiés et confirmés. */
    private function weekly(array $cases, string $from, string $to): array {
        $n = []; $c = [];
        foreach ($cases as $x) {
            if ($x['date'] === null) { continue; }
            $w = self::weekCode($x['date']); $n[$w] = ($n[$w] ?? 0) + 1;
            if ($x['status'] === 'confirmed') { $c[$w] = ($c[$w] ?? 0) + 1; }
        }
        $out = ['weeks' => [], 'notified' => [], 'confirmed' => []];
        for ($d = new DateTimeImmutable(self::mondayOf($from)), $end = new DateTimeImmutable($to); $d <= $end; $d = $d->modify('+7 days')) {
            $w = self::weekCode($d->format('Y-m-d'));
            $out['weeks'][] = $w; $out['notified'][] = $n[$w] ?? 0; $out['confirmed'][] = $c[$w] ?? 0;
        }
        return $out;
    }

    private function ageSex(array $cases): array {
        $conf = array_filter($cases, fn($c) => $c['status'] === 'confirmed');
        $g = []; foreach (MpoxDataset::AGE_GROUPS as $k) { $g[$k] = ['M' => 0, 'F' => 0]; }
        $m = 0; $f = 0; $ages = []; $minLabel = null; $maxLabel = null; $minV = null; $maxV = null;
        foreach ($conf as $c) {
            if ($c['sex'] === 'M') { $m++; } elseif ($c['sex'] === 'F') { $f++; }
            if ($c['age'] !== null) {
                $ages[] = $c['age'];
                if (isset($g[$c['age_group']]) && $c['sex'] !== 'U') { $g[$c['age_group']][$c['sex']]++; }
                if ($minV === null || $c['age'] < $minV) { $minV = $c['age']; $minLabel = $c['age_label']; }
                if ($maxV === null || $c['age'] > $maxV) { $maxV = $c['age']; $maxLabel = $c['age_label']; }
            }
        }
        sort($ages); $n = count($ages);
        $median = $n ? ($n % 2 ? $ages[intdiv($n, 2)] : ($ages[$n / 2 - 1] + $ages[$n / 2]) / 2) : null;
        return ['groups' => $g, 'male' => $m, 'female' => $f, 'n' => count($conf), 'sex_ratio' => $f ? round($m / $f, 2) : null,
                'median_age' => $median, 'min_label' => $minLabel, 'max_label' => $maxLabel];
    }

    /** @return array{p1:string,p2:string} Points saillants rédigés automatiquement à partir des chiffres. */
    private function narrative(array $r, array $cum, array $curCases): array {
        $c = $r['circles'];
        $pl = fn(int $n, string $s, ?string $p = null) => $n . ' ' . ($n > 1 ? ($p ?? $s . 's') : $s);
        $head = $r['mode'] === 'week' ? 'Au cours de la semaine ' . explode('_', self::weekCode($r['from']))[0] . ' (' . self::rangeLabel($r['from'], $r['to']) . ')' : 'Au cours de la période du ' . self::rangeLabel($r['from'], $r['to']);
        $p1 = $c['notified'] <= 1 ? $head . ', ' . $c['notified'] . ' cas a été notifié' : $head . ', ' . $c['notified'] . ' cas ont été notifiés';
        $bits = [];
        if ($c['confirmed']) { $bits[] = $pl($c['confirmed'], 'confirmé'); }
        if ($c['probable']) { $bits[] = $pl($c['probable'], 'probable'); }
        if ($c['suspect']) { $bits[] = $pl($c['suspect'], 'suspect'); }
        if ($c['negative']) { $bits[] = $pl($c['negative'], 'négatif'); }
        $p1 .= $bits ? ' dont ' . implode(', ', $bits) : '';
        $p1 .= '. ' . ($c['deaths'] ? $pl($c['deaths'], 'décès a été enregistré', 'décès ont été enregistrés') . '.' : 'Aucun décès n’a été enregistré.');
        $p1 .= ' Les cas confirmés proviennent de ' . $pl($r['districts_cur'], 'district', 'districts') . ' (' . $r['districts_prev'] . ' la période précédente).';
        $age = $r['age_sex'];
        $p2 = 'Depuis la semaine ' . explode(' à ', $r['cum_label'])[0] . ', ' . number_format($cum['total'], 0, ',', ' ') . ' cas ont été notifiés dont '
            . number_format($cum['confirmed'], 0, ',', ' ') . ' confirmés dans ' . $r['districts_cum'] . ' districts'
            . ($r['cumul']['positivity'] !== null ? ' (taux de positivité ' . number_format($r['cumul']['positivity'], 2, ',', ' ') . ' %)' : '')
            . '. ' . $r['deaths_conf_cum'] . ' décès parmi les cas confirmés'
            . '.';
        if ($age['sex_ratio'] !== null) { $p2 .= ' Le sex-ratio H/F est de ' . number_format($age['sex_ratio'], 2, ',', ' ') . ($age['median_age'] !== null ? ' et l’âge médian de ' . (int) round($age['median_age']) . ' ans.' : '.'); }
        return ['p1' => $p1, 'p2' => $p2];
    }
}
