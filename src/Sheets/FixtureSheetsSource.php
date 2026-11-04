<?php
declare(strict_types=1);
namespace Pharmaco\Sheets;

/** Source de test/développement local : lit un fichier JSON {headers:[], rows:[[...]]}. Jamais utilisée sans APP_ENV=local. */
final class FixtureSheetsSource implements SheetsSource
{
    public function __construct(private string $file) {}

    public function fetch(array $wantedHeaders, bool $force = false): array {
        $d = json_decode((string) file_get_contents($this->file), true);
        $index = []; foreach ($d['headers'] as $i => $h) { $index[GoogleSheetsSource::norm($h)] ??= $i; }
        $cols = []; $missing = [];
        foreach ($wantedHeaders as $w) { $n = GoogleSheetsSource::norm($w); isset($index[$n]) ? $cols[$w] = $index[$n] : $missing[] = $w; }
        $rows = [];
        foreach ($d['rows'] as $r) {
            $o = []; $any = false;
            foreach ($cols as $w => $i) { $o[$w] = trim((string) ($r[$i] ?? '')); if ($o[$w] !== '') { $any = true; } }
            if ($any) { $rows[] = $o; }
        }
        return ['rows' => $rows, 'missing' => $missing, 'fetched_at' => time(), 'source' => 'fixture', 'stale' => false];
    }
}
