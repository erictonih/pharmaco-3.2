<?php
declare(strict_types=1);
namespace Pharmaco\Services;

/** Contrôle qualité du Sheet : complétude, valeurs non reconnues, dates incohérentes. */
final class DataQualityService
{
    public function __construct(private MpoxDataset $ds) {}

    public function report(): array {
        $c = $this->ds->cases; $n = count($c);
        $miss = ['date' => 0, 'district' => 0, 'region' => 0, 'status' => 0, 'age' => 0, 'sex' => 0, 'outcome' => 0];
        $future = $old = $weekMismatch = 0; $unknownClass = []; $today = date('Y-m-d');
        foreach ($c as $x) {
            if ($x['date'] === null) { $miss['date']++; if ($x['raw_date'] !== '') { $unknownClass['(date illisible) ' . $x['raw_date']] = ($unknownClass['(date illisible) ' . $x['raw_date']] ?? 0) + 1; } }
            if ($x['district'] === 'Non renseigné') { $miss['district']++; }
            if ($x['region'] === 'Non renseigné') { $miss['region']++; }
            if ($x['status'] === 'unknown') { $miss['status']++; $k = $x['raw_classification'] ?: '(vide)'; $unknownClass[$k] = ($unknownClass[$k] ?? 0) + 1; }
            if ($x['age'] === null) { $miss['age']++; }
            if ($x['sex'] === 'U') { $miss['sex']++; }
            if ($x['outcome'] === 'unknown') { $miss['outcome']++; }
            if ($x['date'] !== null) {
                if ($x['date'] > $today) { $future++; }
                if ($x['date'] < '2022-01-01') { $old++; }
                if ($x['raw_se'] !== '' && $x['week'] !== null && (int) $x['raw_se'] !== (int) substr($x['week'], -2)) { $weekMismatch++; }
            }
        }
        $fields = [];
        foreach ($miss as $k => $v) { $fields[$k] = ['missing' => $v, 'completeness' => $n ? round((1 - $v / $n) * 100, 1) : null]; }
        $score = $n ? round(array_sum(array_column($fields, 'completeness')) / count($fields), 1) : null;
        arsort($unknownClass);
        $issues = [];
        if ($this->ds->meta['missing_columns'] ?? []) { $issues[] = ['level' => 'error', 'text' => 'Colonnes absentes du Sheet : ' . implode(', ', $this->ds->meta['missing_columns'])]; }
        if ($future) { $issues[] = ['level' => 'warning', 'text' => "$future date(s) de notification dans le futur"]; }
        if ($old) { $issues[] = ['level' => 'warning', 'text' => "$old date(s) antérieure(s) à 2022 (erreur de saisie probable)"]; }
        if ($weekMismatch) { $issues[] = ['level' => 'info', 'text' => "$weekMismatch ligne(s) où « SE Notification » diffère de la semaine ISO de la date"]; }
        if ($fields['district']['missing'] > 0) { $issues[] = ['level' => 'warning', 'text' => $fields['district']['missing'] . ' cas sans district (exclus de la carte et des alertes)']; }
        return ['rows' => $n, 'score' => $score, 'fields' => $fields, 'unrecognized' => array_slice($unknownClass, 0, 10, true),
                'future_dates' => $future, 'old_dates' => $old, 'week_mismatch' => $weekMismatch, 'issues' => $issues, 'meta' => $this->ds->meta];
    }
}
