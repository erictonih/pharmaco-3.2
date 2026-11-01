# pharmaco-3.2 (DVSSER) — déploiement Vercel

PHP 8.x + PostgreSQL (Neon), exécuté sur Vercel via `vercel-php@0.9.0`.

## Structure
- `app/` scripts PHP (non servis directement) · `api/index.php` routeur unique (liste blanche)
- `public/` fichiers statiques (css, js, images, Districts.geojson) · `database/` schéma SQL

## 1. Avant tout : révoquer les secrets qui étaient dans le dépôt
- Clé de compte de service Google (`private_key_id` 3a112f25…, projet `wired-ripsaw-471110-p6`) : la supprimer dans Google Cloud > IAM > Comptes de service > Clés, en créer une nouvelle.
- Mot de passe MySQL InfinityFree et mots de passe applicatifs de l'ancien fichier SQL (`admin`, `Test`) : à changer/considérer comme compromis.
- Ils restent visibles dans l'historique Git : si le dépôt est public, rendez-le privé ou purgez l'historique (`git filter-repo`).

## 2. Base de données : Neon (PostgreSQL) via Vercel
1. Vercel > votre projet > **Storage** > *Create Database* > **Neon** (offre gratuite) > connecter au projet (Production + Preview). Vercel injecte `DATABASE_URL` automatiquement.
2. Dans la console SQL de Neon, exécutez `database/schema.sql` (5 tables : client, medicament, users, app_sessions, login_attempts), puis `database/seed_demo_data.sql` si vous voulez des données de test.
3. Créez le Super Admin : `DATABASE_URL='postgresql://…' php database/create_admin.php admin vous@exemple.mg`
4. Migration des données existantes (InfinityFree/MySQL) : exportez `client` et `medicament` en CSV depuis phpMyAdmin, puis importez-les dans Neon (colonnes en minuscules : id, nom, prenom, age, genre, type, residence, district, date_de_sousmission / id_client, type, nombres, date). Ne migrez pas les mots de passe : recréez les comptes.

## 3. Vercel
Import du dépôt (Framework : *Other*, pas de build). Variables : `DATABASE_URL` (auto via Neon) et `GOOGLE_SA_JSON_B64` (pages Mpox, nouvelle clé).

## Sécurité mise en place
Aucun secret dans le code · requêtes préparées · CSRF sur tous les POST · rôles vérifiés côté serveur sur chaque page/endpoint ·
mots de passe `password_hash` + anti brute-force (5 échecs/15 min) · `session_regenerate_id` · cookies Secure/HttpOnly/SameSite ·
sessions en base · erreurs masquées · TLS cURL activé · en-têtes (HSTS, CSP, X-Frame-Options…) · outils de diagnostic supprimés.

## Limites connues
- `parametre.php` : la matrice de permissions n'est pas persistée (comportement d'origine).
- La CSP autorise `unsafe-inline` (scripts inline existants) ; à durcir avec des nonces.
