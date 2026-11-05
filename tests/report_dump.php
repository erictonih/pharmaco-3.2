<?php
// Sortie JSON du point de situation sur le fixture : php tests/report_dump.php <from> <to> (utilisé par report_check.py)
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Sheets\FixtureSheetsSource; use Pharmaco\Services\{MpoxDataset, ReportService};
$ds = MpoxDataset::load(new FixtureSheetsSource(__DIR__ . '/fixtures/mpox_synthetic.json'));
echo json_encode((new ReportService($ds))->build($argv[1] ?? null, $argv[2] ?? null), JSON_UNESCAPED_UNICODE);
