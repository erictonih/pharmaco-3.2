<?php
declare(strict_types=1);
namespace Pharmaco\Sheets;

use RuntimeException;

/**
 * Lecture du Google Sheet via un compte de service (JWT RS256), lecture seule.
 * - Ne télécharge que les colonnes utiles (batchGet par colonne) → pas de données nominatives.
 * - Cache disque (TTL), avec repli sur le cache périmé si Google est injoignable.
 */
final class GoogleSheetsSource implements SheetsSource
{
    public function __construct(
        private string $spreadsheetId,
        private string $tab = 'Base',
        private int $ttl = 300,
        private int $maxRows = 30000,
        private string $apiBase = 'https://sheets.googleapis.com',
        private string $tokenUrl = 'https://oauth2.googleapis.com/token',
    ) {}

    public static function fromEnv(): self {
        $local = env_get('APP_ENV') === 'local';   // URL de l'API surchargeables UNIQUEMENT en local (tests avec faux serveur)
        return new self(
            env_get('MPOX_SHEET_ID', '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE'),
            env_get('MPOX_SHEET_TAB', 'Base'),
            (int) env_get('SHEETS_CACHE_TTL', '300'),
            30000,
            $local ? env_get('SHEETS_API_BASE', 'https://sheets.googleapis.com') : 'https://sheets.googleapis.com',
            $local ? env_get('OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token') : 'https://oauth2.googleapis.com/token'
        );
    }

    public function fetch(array $wantedHeaders, bool $force = false): array {
        $key = md5($this->spreadsheetId . '|' . $this->tab . '|' . implode('|', $wantedHeaders));
        $file = cache_dir() . "/sheet_$key.json";
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!$force && is_array($cached) && (time() - ($cached['fetched_at'] ?? 0)) < $this->ttl) {
            return $cached + ['stale' => false];
        }
        try {
            $data = $this->download($wantedHeaders);
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            return $data + ['stale' => false];
        } catch (\Throwable $e) {
            error_log('[pharmaco][sheets] ' . $e->getMessage());
            if (is_array($cached)) { return ['stale' => true] + $cached; }
            throw $e;
        }
    }

    // ── Téléchargement ────────────────────────────────────────────────────────
    private function download(array $wanted): array {
        $token = $this->token();
        $base  = $this->apiBase . '/v4/spreadsheets/' . rawurlencode($this->spreadsheetId) . '/values';
        $tab   = "'" . str_replace("'", "''", $this->tab) . "'";

        $head = $this->get("$base/" . rawurlencode("$tab!1:1"), $token);
        $headers = $head['values'][0] ?? [];
        if (!$headers) { throw new RuntimeException("En-têtes introuvables (onglet « {$this->tab} » ?)"); }

        $index = [];
        foreach ($headers as $i => $hname) { $index[self::norm((string) $hname)] ??= $i; }

        $cols = []; $missing = [];
        foreach ($wanted as $w) {
            $n = self::norm($w);
            if (isset($index[$n])) { $cols[$w] = $index[$n]; } else { $missing[] = $w; }
        }
        if (!$cols) { throw new RuntimeException('Aucune colonne attendue trouvée dans le Sheet.'); }

        $q = 'majorDimension=COLUMNS&valueRenderOption=FORMATTED_VALUE';
        foreach ($cols as $w => $i) {
            $L = self::letter($i);
            $q .= '&ranges=' . rawurlencode("$tab!{$L}2:{$L}{$this->maxRows}");
        }
        $res = $this->get("$base:batchGet?$q", $token);
        $ranges = $res['valueRanges'] ?? [];

        $names = array_keys($cols); $columns = []; $n = 0;
        foreach ($names as $k => $w) {
            $columns[$w] = $ranges[$k]['values'][0] ?? [];
            $n = max($n, count($columns[$w]));
        }
        $rows = [];
        for ($r = 0; $r < $n; $r++) {
            $row = []; $any = false;
            foreach ($names as $w) {
                $v = isset($columns[$w][$r]) ? trim((string) $columns[$w][$r]) : '';
                $row[$w] = $v; if ($v !== '') { $any = true; }
            }
            if ($any) { $rows[] = $row; }
        }
        return ['rows' => $rows, 'missing' => $missing, 'fetched_at' => time(), 'source' => 'google_sheets'];
    }

    public static function norm(string $h): string {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $h)), 'UTF-8');
    }
    private static function letter(int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) { $s = chr(65 + ($i - 1) % 26) . $s; }
        return $s;
    }

    /** Lit GOOGLE_SA_JSON_B64 de façon tolérante : base64 (standard/URL-safe, espaces, guillemets) ou JSON brut. */
    private static function serviceAccount(): ?array {
        $raw = trim((string) env_get('GOOGLE_SA_JSON_B64', ''));
        if ($raw === '') { error_log('[pharmaco][sheets] GOOGLE_SA_JSON_B64 absente ou vide (vérifier l\'environnement Production)'); return null; }
        if (strlen($raw) > 1 && ($raw[0] === '"' || $raw[0] === "'") && $raw[-1] === $raw[0]) { $raw = trim(substr($raw, 1, -1)); }
        $key = null; $how = 'json brut';
        if ($raw[0] === '{') { $key = json_decode($raw, true); }
        if (!is_array($key)) {
            $how = 'base64';
            $clean = preg_replace('/\s+/', '', strtr($raw, '-_', '+/'));
            $dec = base64_decode($clean . str_repeat('=', (4 - strlen($clean) % 4) % 4), true);
            $key = $dec !== false ? json_decode($dec, true) : null;
        }
        if (!is_array($key)) {
            error_log('[pharmaco][sheets] GOOGLE_SA_JSON_B64 illisible : ni JSON ni base64 de JSON (longueur ' . strlen($raw) . ', 1er caractère « ' . $raw[0] . ' »)');
            return null;
        }
        if (($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || empty($key['private_key'])) {
            error_log('[pharmaco][sheets] JSON décodé (' . $how . ') mais ce n\'est pas une clé de compte de service complète. Champs présents : ' . implode(',', array_keys($key)));
            return null;
        }
        $key['private_key'] = str_replace('\\n', "\n", (string) $key['private_key']);
        return $key;
    }

    // ── HTTP / OAuth ──────────────────────────────────────────────────────────
    private function token(): string {
        $f = cache_dir() . '/gtoken.json';
        $c = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (is_array($c) && ($c['exp'] ?? 0) > time() + 60) { return (string) $c['token']; }

        $key = self::serviceAccount();
        if ($key === null) {
            throw new RuntimeException('Compte de service Google non configuré (GOOGLE_SA_JSON_B64).');
        }
        $now = time();
        $b = fn(string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $h = $b((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $p = $b((string) json_encode([
            'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (!openssl_sign("$h.$p", $sig, $key['private_key'], 'SHA256')) { throw new RuntimeException('Signature JWT impossible.'); }
        $resp = $this->http('POST', $this->tokenUrl, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$h.$p.{$b($sig)}",
        ]);
        $t = json_decode($resp, true);
        if (empty($t['access_token'])) { throw new RuntimeException('OAuth2 : ' . ($t['error_description'] ?? $t['error'] ?? 'échec')); }
        file_put_contents($f, json_encode(['token' => $t['access_token'], 'exp' => $now + (int) ($t['expires_in'] ?? 3500)]), LOCK_EX);
        @chmod($f, 0600);
        return $t['access_token'];
    }
    private function get(string $url, string $token): array {
        $r = json_decode($this->http('GET', $url, null, ["Authorization: Bearer $token"]), true);
        if (!is_array($r)) { throw new RuntimeException('Réponse Google invalide.'); }
        if (isset($r['error'])) { throw new RuntimeException('Sheets API : ' . ($r['error']['message'] ?? 'erreur')); }
        return $r;
    }
    private function http(string $method, string $url, ?array $form = null, array $headers = []): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array) $form)); }
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) { throw new RuntimeException("cURL : $err"); }
        return (string) $out;
    }
}
