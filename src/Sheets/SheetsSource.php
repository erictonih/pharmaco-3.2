<?php
declare(strict_types=1);
namespace Pharmaco\Sheets;

interface SheetsSource
{
    /**
     * Retourne uniquement les colonnes demandées (jamais les colonnes nominatives).
     * @param string[] $wantedHeaders  en-têtes attendus (comparaison insensible à la casse/espaces)
     * @return array{rows: array<int,array<string,string>>, missing: string[], fetched_at: int, source: string, stale: bool}
     */
    public function fetch(array $wantedHeaders, bool $force = false): array;
}
