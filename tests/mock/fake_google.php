<?php
// Faux serveur Google (tests) : php -S 127.0.0.1:8095 tests/mock/fake_google.php   — jeu de données : tests/fixtures/mpox_synthetic.json
$fx = json_decode(file_get_contents(__DIR__ . '/../fixtures/mpox_synthetic.json'), true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); $qs = $_SERVER['QUERY_STRING'] ?? '';
header('Content-Type: application/json');
if ($path === '/token') {
    // le JWT doit être bien formé (3 segments) et avoir la portée lecture seule
    parse_str(file_get_contents('php://input'), $b);
    $seg = explode('.', $b['assertion'] ?? ''); $claims = json_decode(base64_decode(strtr($seg[1] ?? '', '-_', '+/')), true);
    if (count($seg) !== 3 || ($claims['scope'] ?? '') !== 'https://www.googleapis.com/auth/spreadsheets.readonly') { http_response_code(400); echo json_encode(['error' => 'invalid_grant']); exit; }
    echo json_encode(['access_token' => 'tok123', 'expires_in' => 3600]); exit;
}
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer tok123') { http_response_code(401); echo json_encode(['error' => ['message' => 'unauthorized']]); exit; }
if (file_exists('/tmp/fake_google_down')) { http_response_code(503); echo json_encode(['error' => ['message' => 'down']]); exit; }
$colName = function (int $i) { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) { $s = chr(65 + ($i - 1) % 26) . $s; } return $s; };
if (str_ends_with($path, ':batchGet')) {
    $ranges = []; foreach (explode('&', $qs) as $kv) { [$k, $v] = array_pad(explode('=', $kv, 2), 2, ''); if ($k === 'ranges') { $ranges[] = urldecode($v); } }
    $out = [];
    foreach ($ranges as $r) {
        preg_match("/!([A-Z]+)2:/", $r, $m); $letters = $m[1]; $idx = -1;
        foreach ($fx['headers'] as $i => $_) { if ($colName($i) === $letters) { $idx = $i; } }
        $col = array_map(fn($row) => (string) ($row[$idx] ?? ''), array_slice($fx['rows'], 0));
        while ($col && end($col) === '') { array_pop($col); }      // Google supprime les cellules vides en fin de colonne
        $out[] = ['range' => $r, 'majorDimension' => 'COLUMNS', 'values' => [$col]];
    }
    echo json_encode(['valueRanges' => $out]); exit;
}
if (str_contains($path, '/values/')) { echo json_encode(['values' => [array_map(fn($h) => $h, $fx['headers'])]]); exit; }
http_response_code(404); echo json_encode(['error' => ['message' => 'not found']]);
