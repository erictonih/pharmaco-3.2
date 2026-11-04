<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use DateTimeImmutable;

/**
 * Détection de foyers : compare les 14 derniers jours (référence = dernière notification du Sheet)
 * à la moyenne hebdomadaire des 8 semaines précédentes, avec test de Poisson (queue supérieure).
 *
 * Niveaux : 3 Critique · 2 Élevé · 1 Surveillance · 0 Stable. « Nouveau foyer » = district sans cas sur la période de base.
 */
final class OutbreakAlertService
{
    public const RECENT_DAYS = 14;
    public const BASELINE_WEEKS = 8;

    public function __construct(private MpoxDataset $ds) {}

    /** P(X >= k) pour X ~ Poisson(lambda). */
    public static function poissonTail(int $k, float $lambda): float {
        if ($k <= 0) { return 1.0; }
        if ($lambda <= 0) { return 0.0; }
        $term = exp(-$lambda); $cdf = $term;
        for ($i = 1; $i < $k; $i++) { $term *= $lambda / $i; $cdf += $term; }
        return max(0.0, min(1.0, 1 - $cdf));
    }

    public function districtAlerts(): array {
        $last = $this->ds->lastDate();
        if (!$last) { return ['reference_date' => null, 'alerts' => [], 'summary' => ['critical' => 0, 'high' => 0, 'watch' => 0, 'new' => 0]]; }
        $end = new DateTimeImmutable($last);
        $recentFrom = $end->modify('-' . (self::RECENT_DAYS - 1) . ' days')->format('Y-m-d');
        $baseTo = $end->modify('-' . self::RECENT_DAYS . ' days')->format('Y-m-d');
        $baseFrom = $end->modify('-' . (self::RECENT_DAYS + self::BASELINE_WEEKS * 7 - 1) . ' days')->format('Y-m-d');

        $d = [];
        foreach ($this->ds->cases as $x) {
            if ($x['date'] === null || $x['district'] === 'Non renseigné' || $x['date'] > $last) { continue; }
            $k = $x['district'];
            $d[$k] ??= ['district' => $k, 'region' => $x['region'], 'recent' => 0, 'recent_confirmed' => 0, 'recent_deaths' => 0, 'baseline' => 0];
            if ($x['date'] >= $recentFrom) {
                $d[$k]['recent']++;
                if ($x['status'] === 'confirmed') { $d[$k]['recent_confirmed']++; }
                if ($x['outcome'] === 'deceased') { $d[$k]['recent_deaths']++; }
            } elseif ($x['date'] >= $baseFrom && $x['date'] <= $baseTo) { $d[$k]['baseline']++; }
        }
        $perWeek = self::BASELINE_WEEKS;
        $alerts = []; $sum = ['critical' => 0, 'high' => 0, 'watch' => 0, 'new' => 0];
        foreach ($d as $a) {
            if ($a['recent'] === 0) { continue; }
            $weekly = $a['baseline'] / $perWeek;
            $expected = max(0.25, $weekly * (self::RECENT_DAYS / 7));   // plancher : évite les ratios infinis
            $ratio = round($a['recent'] / $expected, 1);
            $p = self::poissonTail($a['recent'], $expected);
            $isNew = $a['baseline'] === 0;
            $level = 0;
            if ($a['recent'] >= 5 && $p < 0.01 || $a['recent_deaths'] > 0 && $p < 0.05) { $level = 3; }
            elseif ($a['recent'] >= 3 && $p < 0.05) { $level = 2; }
            elseif ($a['recent'] >= 2 && $p < 0.20 || $isNew) { $level = 1; }
            if ($level === 0) { continue; }
            $a += ['expected' => round($expected, 1), 'ratio' => $ratio, 'p_value' => round($p, 4), 'level' => $level, 'is_new' => $isNew];
            $alerts[] = $a;
            if ($level === 3) { $sum['critical']++; } elseif ($level === 2) { $sum['high']++; } else { $sum['watch']++; }
            if ($isNew) { $sum['new']++; }
        }
        usort($alerts, fn($a, $b) => [$b['level'], $b['recent']] <=> [$a['level'], $a['recent']]);
        return ['reference_date' => $last, 'recent_from' => $recentFrom, 'alerts' => $alerts, 'summary' => $sum];
    }
}
