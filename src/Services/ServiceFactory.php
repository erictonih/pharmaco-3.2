<?php
declare(strict_types=1);
namespace Pharmaco\Services;

use Pharmaco\Sheets\{FixtureSheetsSource, GoogleSheetsSource, SheetsSource};

/** Point d'accès unique des pages : construit la source, charge le dataset, applique les filtres validés. */
final class ServiceFactory
{
    public static function source(): SheetsSource {
        $fx = env_get('SHEETS_FIXTURE');
        if ($fx && env_get('APP_ENV') === 'local' && is_file($fx)) { return new FixtureSheetsSource($fx); }   // développement/tests uniquement
        return GoogleSheetsSource::fromEnv();
    }

    /** @return array{filters:array,error:?string,full:?MpoxDataset,ds:?MpoxDataset,regions:array} */
    public static function context(array $query = [], bool $force = false): array {
        $f = [];
        foreach (['from', 'to'] as $k) { if (!empty($query[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $query[$k])) { $f[$k] = (string) $query[$k]; } }
        $ctx = ['filters' => [], 'error' => null, 'full' => null, 'ds' => null, 'regions' => []];
        try {
            $full = MpoxDataset::load(self::source(), $force);
        } catch (\Throwable $e) {
            $ctx['error'] = 'Impossible de lire le Google Sheet. Vérifiez la configuration (GOOGLE_SA_JSON_B64, partage du Sheet avec le compte de service, onglet).';
            error_log('[pharmaco] ' . $e->getMessage());
            return $ctx;
        }
        $regions = $full->distinct('region');
        if (!empty($query['region']) && in_array((string) $query['region'], $regions, true)) { $f['region'] = (string) $query['region']; }
        $ctx['filters'] = $f; $ctx['full'] = $full; $ctx['regions'] = $regions; $ctx['ds'] = $f ? $full->filter($f) : $full;
        return $ctx;
    }
}
