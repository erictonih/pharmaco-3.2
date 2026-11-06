<?php
// Sortie JSON du SitRep sur le fixture : php tests/sitrep_dump.php <mode> <valeur|from> [to] [cum_from]
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Sheets\FixtureSheetsSource; use Pharmaco\Services\{MpoxDataset, SitRepService};
$ds = MpoxDataset::load(new FixtureSheetsSource(__DIR__ . '/fixtures/mpox_synthetic.json'));
$m = $argv[1] ?? 'week';
$o = ['mode' => $m, 'week' => $m === 'week' ? ($argv[2] ?? null) : null, 'month' => $m === 'month' ? ($argv[2] ?? null) : null,
      'from' => $m === 'custom' ? ($argv[2] ?? null) : null, 'to' => $m === 'custom' ? ($argv[3] ?? null) : null, 'cum_from' => $argv[4] ?? null];
echo json_encode((new SitRepService($ds))->build($o), JSON_UNESCAPED_UNICODE);
