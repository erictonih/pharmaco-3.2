<?php
// Usage (CLI uniquement) :
//   DATABASE_URL='postgresql://user:pass@host/db?sslmode=require' php database/create_admin.php <username> <email>
// Le mot de passe est demandé en entrée standard (jamais passé en argument ni stocké).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/db.php';
[$self, $u, $e] = $argv + [null, null, null];
if (!$u || !$e) { fwrite(STDERR, "Usage: php create_admin.php <username> <email>\n"); exit(1); }
fwrite(STDERR, "Mot de passe (min. 12 caractères) : ");
$p = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($p) < 12) { fwrite(STDERR, "Trop court.\n"); exit(1); }
$h = password_hash($p, PASSWORD_DEFAULT);
$c = db();
$st = $c->prepare("INSERT INTO users (username, prenom, nom, email, role, mdp, statut) VALUES (?, 'Admin', '', ?, 'Super Admin', ?, 'Actif')
                   ON CONFLICT (username) DO UPDATE SET mdp = EXCLUDED.mdp, role = 'Super Admin', statut = 'Actif'");
$st->bind_param('sss', $u, $e, $h);
echo $st->execute() ? "Super Admin « $u » créé/mis à jour.\n" : "Erreur : " . $st->error . "\n";
