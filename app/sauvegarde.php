<?php
// Sauvegarde automatique quotidienne (Vercel Cron). Protégée par CRON_SECRET : Vercel envoie « Authorization: Bearer <CRON_SECRET> ».
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Users\{UserService, BackupService};
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$secret = (string) env_get('CRON_SECRET', '');
if ($secret === '' || strlen($secret) < 16) { http_response_code(503); exit(json_encode(['ok' => false, 'error' => 'CRON_SECRET non configuré'])); }
$sent = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!hash_equals('Bearer ' . $secret, $sent)) { http_response_code(401); exit(json_encode(['ok' => false, 'error' => 'non autorisé'])); }
$st = UserService::store();
if (!$st) { http_response_code(503); exit(json_encode(['ok' => false, 'error' => 'stockage non configuré'])); }
try { $c = BackupService::snapshot($st, 'automatique'); }
catch (\Throwable $e) { error_log('[pharmaco][backup] cron : ' . $e->getMessage()); http_response_code(500); exit(json_encode(['ok' => false, 'error' => 'échec de la sauvegarde'])); }
error_log('[pharmaco][backup] sauvegarde automatique : ' . json_encode($c));
echo json_encode(['ok' => true] + $c);
