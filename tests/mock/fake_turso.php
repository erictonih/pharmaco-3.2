<?php
// Faux serveur Turso (API HTTP /v2/pipeline) adossé à SQLite — tests uniquement.
header('Content-Type: application/json');
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer test-token') { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/v2/pipeline') { http_response_code(404); exit; }
if (file_exists('/tmp/fake_turso_down')) { http_response_code(503); echo json_encode(['error' => 'down']); exit; }
$db = new PDO('sqlite:/tmp/fake_turso.db'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$req = json_decode(file_get_contents('php://input'), true); $results = [];
foreach ($req['requests'] ?? [] as $r) {
    if ($r['type'] === 'close') { $results[] = ['type' => 'ok', 'response' => ['type' => 'close']]; continue; }
    try {
        $st = $db->prepare($r['stmt']['sql']);
        $st->execute(array_map(fn($a) => $a['value'] ?? null, $r['stmt']['args'] ?? []));
        $rows = $st->columnCount() ? array_map(fn($row) => array_map(fn($v) => $v === null ? ['type' => 'null'] : ['type' => 'text', 'value' => (string) $v], array_values($row)), $st->fetchAll(PDO::FETCH_ASSOC)) : [];
        $results[] = ['type' => 'ok', 'response' => ['type' => 'execute', 'result' => ['cols' => [], 'rows' => $rows, 'affected_row_count' => $st->rowCount()]]];
    } catch (Throwable $e) { $results[] = ['type' => 'error', 'error' => ['message' => $e->getMessage()]]; }
}
echo json_encode(['baton' => null, 'base_url' => null, 'results' => $results]);
