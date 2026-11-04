<?php
// Génère l'entrée JSON d'un compte pour la variable APP_USERS_JSON.
// Usage : php tools/make_user.php <identifiant> "Super Admin|Analyste|Visualiseur"   (le mot de passe est lu sur l'entrée standard)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
[$self, $u, $role] = $argv + [null, null, null];
if (!$u || !in_array($role, ['Super Admin', 'Analyste', 'Visualiseur'], true)) { fwrite(STDERR, "Usage: php tools/make_user.php <identifiant> \"Super Admin|Analyste|Visualiseur\"\n"); exit(1); }
fwrite(STDERR, "Mot de passe (12 caractères minimum) : ");
$p = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($p) < 12) { fwrite(STDERR, "Trop court.\n"); exit(1); }
echo json_encode(['username' => $u, 'hash' => password_hash($p, PASSWORD_DEFAULT), 'role' => $role], JSON_UNESCAPED_UNICODE), "\n";
fwrite(STDERR, "Ajoutez cet objet au tableau JSON de APP_USERS_JSON, par ex. [ {...}, {...} ]\n");
