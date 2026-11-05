<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use DateTimeImmutable;
use Pharmaco\Sheets\SheetsSource;

/**
 * Charge le Google Sheet et normalise chaque ligne en « cas » exploitable.
 * Règles d'interprétation (validées sur le Sheet réel) :
 *  - Statut : Classification (Positif→confirmé, Probable, Suspect, Négatif, « Trace d'ADN » → suspect) ; à défaut « Conclusion 1 »
 *  - Décès  : « Décès oui/non » = Oui, OU Evolution contient « déc », OU « Date de décès » renseignée
 *  - Evolution « Bonne »/guéri → favorable
 *  - Semaine épidémiologique : ISO (année-semaine) calculée sur la date de notification (la colonne « SE Notification » n'a pas d'année)
 *  - Tranches d'âge recalculées depuis « Age (année) » (la colonne tranche_d_age est un code numérique)
 */
final class MpoxDataset
{
    public const COLUMNS = [
        'region' => 'Region', 'district' => 'District', 'commune' => 'Nom Commune',
        'se' => 'SE Notification', 'date_notif' => 'Date de notification', 'date_diag' => 'Date de diagnostic',
        'classification' => 'Classification', 'conclusion' => 'Conclusion 1',
        'age' => 'Age (année)', 'sexe' => 'Sexe', 'vih' => 'Statut VIH( confirmé, Negatif, NON RENSEIGNE)',
        'vaccin' => 'Vaccin MPOX', 'enceinte' => 'Enceinte Non , Oui)',
        'hospit' => 'Hospitalisation', 'icu' => 'PEC Intensive', 'evolution' => 'Evolution',
        'deces' => 'Décès oui/non', 'date_deces' => 'Date de décès',
        'lien' => 'Lien épidémiologique (Oui, Non)', 'prelev' => 'Prélèvement (Oui ou Non)',
        'labo' => 'Laboratoire (IPM ou LA2M)', 'clade' => 'Clade', 'genomique' => 'Charactères génomiques',
    ];
    public const AGE_GROUPS = ['<1 an', '1-4 ans', '5-14 ans', '15-49 ans', '50+ ans'];

    /** @var array<int,array<string,mixed>> */
    public array $cases = [];
    public array $meta = [];

    public static function load(SheetsSource $source, bool $force = false): self {
        $res = $source->fetch(array_values(self::COLUMNS), $force);
        $ds = new self();
        $inv = array_flip(self::COLUMNS);
        foreach ($res['rows'] as $raw) {
            $r = [];
            foreach ($raw as $header => $v) { $r[$inv[$header] ?? $header] = $v; }
            $ds->cases[] = self::normalize($r);
        }
        $ds->meta = [
            'rows' => count($ds->cases), 'fetched_at' => $res['fetched_at'], 'stale' => (bool) $res['stale'],
            'source' => $res['source'], 'missing_columns' => $res['missing'],
        ];
        return $ds;
    }

    public static function fromCases(array $cases, array $meta = []): self {
        $d = new self(); $d->cases = array_values($cases); $d->meta = $meta + ['rows' => count($cases)]; return $d;
    }

    /** @param array{from?:string,to?:string,region?:string,district?:string,status?:string} $f */
    public function filter(array $f): self {
        $out = array_filter($this->cases, static function (array $c) use ($f): bool {
            if (!empty($f['from']) && ($c['date'] === null || $c['date'] < $f['from'])) { return false; }
            if (!empty($f['to'])   && ($c['date'] === null || $c['date'] > $f['to']))   { return false; }
            if (!empty($f['region'])   && $c['region']   !== $f['region'])   { return false; }
            if (!empty($f['district']) && $c['district'] !== $f['district']) { return false; }
            if (!empty($f['status'])   && $c['status']   !== $f['status'])   { return false; }
            return true;
        });
        return self::fromCases($out, $this->meta);
    }

    /** Dernière date de notification connue (référence temporelle des analyses, indépendante de « aujourd'hui »). */
    public function lastDate(): ?string {
        $m = null; foreach ($this->cases as $c) { if ($c['date'] !== null && ($m === null || $c['date'] > $m)) { $m = $c['date']; } }
        return $m;
    }
    public function firstDate(): ?string {
        $m = null; foreach ($this->cases as $c) { if ($c['date'] !== null && ($m === null || $c['date'] < $m)) { $m = $c['date']; } }
        return $m;
    }
    /** @return string[] */
    public function distinct(string $field): array {
        $s = []; foreach ($this->cases as $c) { if (($c[$field] ?? '') !== '' && $c[$field] !== 'Non renseigné') { $s[$c[$field]] = true; } }
        $k = array_keys($s); sort($k, SORT_NATURAL | SORT_FLAG_CASE); return $k;
    }

    // ── Normalisation ────────────────────────────────────────────────────────
    private static function normalize(array $r): array {
        $g = static fn(string $k): string => trim((string) ($r[$k] ?? ''));
        $lo = static fn(string $s): string => mb_strtolower($s, 'UTF-8');

        $classif = $lo($g('classification')); $concl = $lo($g('conclusion'));
        $status = self::status($classif) ?? self::status($concl) ?? 'unknown';

        $evol = $lo($g('evolution')); $dc = $lo($g('deces'));
        $dead = str_starts_with($dc, 'oui') || str_contains($evol, 'déc') || str_contains($evol, 'dec') || $g('date_deces') !== '';
        if ($dead) { $outcome = 'deceased'; }
        elseif ($evol === '') { $outcome = 'unknown'; }
        elseif (preg_match('/bonne|gu[ée]ri|favorable|am[ée]lior|stable/u', $evol)) { $outcome = 'favorable'; }
        else { $outcome = 'other'; }

        $sx = $lo($g('sexe'));
        $sex = ($sx === 'm' || str_starts_with($sx, 'masc') || str_starts_with($sx, 'h')) ? 'M'
             : (($sx === 'f' || str_starts_with($sx, 'fém') || str_starts_with($sx, 'fem')) ? 'F' : 'U');

        $age = is_numeric(str_replace(',', '.', $g('age'))) ? (int) round((float) str_replace(',', '.', $g('age'))) : null;
        if ($age !== null && ($age < 0 || $age > 120)) { $age = null; }

        $date = self::parseDate($g('date_notif'));
        $iso = null; $ym = null;
        if ($date) { $d = new DateTimeImmutable($date); $iso = $d->format('o') . '-W' . $d->format('W'); $ym = $d->format('Y-m'); }

        $yes = static fn(string $s): bool => str_starts_with($s, 'oui');
        $vih = $lo($g('vih'));
        $vaccin = $lo($g('vaccin'));
        $region = $g('region'); $district = $g('district');

        return [
            'date' => $date, 'ym' => $ym, 'week' => $iso,
            'region' => $region !== '' ? $region : 'Non renseigné',
            'district' => $district !== '' ? $district : 'Non renseigné',
            'district_key' => self::key($district), 'commune' => $g('commune'),
            'status' => $status, 'outcome' => $outcome, 'sex' => $sex,
            'age' => $age, 'age_group' => self::ageGroup($age),
            'hospitalised' => $yes($lo($g('hospit'))), 'icu' => $yes($lo($g('icu'))),
            'sampled' => $yes($lo($g('prelev'))), 'lab' => $g('labo'),
            'clade' => $g('clade') !== '' ? $g('clade') : $g('genomique'),
            'hiv' => str_contains($vih, 'pos') || str_contains($vih, 'confirm') ? 'Positif' : (str_contains($vih, 'neg') || str_contains($vih, 'nég') ? 'Négatif' : 'Non renseigné'),
            'vaccinated' => $yes($vaccin) ? 'yes' : (str_starts_with($vaccin, 'non') ? 'no' : 'unknown'),
            'pregnant' => $yes($lo($g('enceinte'))), 'epi_link' => $yes($lo($g('lien'))),
            'diag_date' => self::parseDate($g('date_diag')),
            // valeurs brutes pour le contrôle qualité
            'raw_classification' => $g('classification'), 'raw_evolution' => $g('evolution'), 'raw_se' => $g('se'),
            'raw_date' => $g('date_notif'),
        ];
    }

    private static function status(string $s): ?string {
        if ($s === '') { return null; }
        if (str_contains($s, 'pos') || str_contains($s, 'confirm')) { return 'confirmed'; }
        if (str_contains($s, 'nég') || str_contains($s, 'neg')) { return 'negative'; }
        if (str_contains($s, 'probab')) { return 'probable'; }
        if (str_contains($s, 'suspect') || str_contains($s, 'trace')) { return 'suspect'; }
        return null;
    }

    public static function ageGroup(?int $a): string {
        if ($a === null) { return 'Non renseigné'; }
        return match (true) { $a < 1 => '<1 an', $a < 5 => '1-4 ans', $a < 15 => '5-14 ans', $a < 50 => '15-49 ans', default => '50+ ans' };
    }

    /** Accepte jj/mm/aaaa (1 ou 2 chiffres), aaaa-mm-jj et les numéros de série Sheets/Excel. Retourne Y-m-d ou null. */
    public static function parseDate(string $s): ?string {
        $s = trim($s); if ($s === '') { return null; }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})#', $s, $m)) {
            $y = (int) $m[3]; if ($y < 100) { $y += 2000; }
            return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m)) { return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : null; }
        if (is_numeric($s) && (float) $s > 30000 && (float) $s < 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $s . ' days')->format('Y-m-d');
        }
        return null;
    }

    /** Alias « nom du Sheet (normalisé) → nom du fond de carte (normalisé) ». I = commune urbaine, II = rurale. */
    private const DISTRICT_ALIASES = [
        'antsirabei' => 'antsiraberurban', 'antsirabeii' => 'antsiraberural',
        'antsirananai' => 'antsirananaurban', 'antsirananaii' => 'antsirananarural',
        'fianarantsoai' => 'fianarantsoaurban', 'fianarantsoaii' => 'fianarantsoarural',
        'mahajangai' => 'mahajangaurban', 'mahajangaii' => 'mahajangarural',
        'toamasinai' => 'toamasinaurban', 'toamasinaii' => 'toamasinarural',
        'toliarai' => 'toliaryurban', 'toliaraii' => 'toliary', 'toliara' => 'toliary',
        'nosybe' => 'nosibe', 'sainte marie' => 'nosyborahastmarie', 'saintemarie' => 'nosyborahastmarie', 'nosyboraha' => 'nosyborahastmarie',
        'vohemar' => 'vohimarinaiharana', 'iharana' => 'vohimarinaiharana', 'fortdauphin' => 'taolagnaro',
        'antananarivo renivohitra' => 'antananarivorenivohitra',
    ];

    /** Clé de comparaison (carte) : minuscules sans accents ni ponctuation, alias de districts appliqués. */
    public static function key(string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $t !== false && $t !== '' ? $t : $s;
        $k = (string) preg_replace('/[^a-z0-9]+/', '', $s);
        $k = (string) preg_replace('/(?<=[a-z])(1)$/', 'i', $k);       // « Mahajanga 1 » → « mahajangai »
        $k = (string) preg_replace('/(?<=[a-z])(2)$/', 'ii', $k);
        return self::DISTRICT_ALIASES[$k] ?? $k;
    }
}
