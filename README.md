# pharmaco-3.2 (DVSSER) — déploiement Vercel

PHP 8.x + MySQL, exécuté sur Vercel via `vercel-php@0.9.0`.

## Structure
- `app/` scripts PHP (non servis directement) · `api/index.php` routeur unique (liste blanche)
- `public/` fichiers statiques (css, js, images, Districts.geojson) · `database/` schéma SQL

## 1. Avant tout : révoquer les secrets qui étaient dans le dépôt
- Clé de compte de service Google (`private_key_id` 3a112f25…, projet `wired-ripsaw-471110-p6`) : la supprimer dans Google Cloud > IAM > Comptes de service > Clés, en créer une nouvelle.
- Mot de passe MySQL InfinityFree et mots de passe applicatifs de l'ancien fichier SQL (`admin`, `Test`) : à changer/considérer comme compromis.
- Ils restent visibles dans l'historique Git : si le dépôt est public, rendez-le privé ou purgez l'historique (`git filter-repo`).

## 2. Base de données MySQL cloud
1. Créez une base MySQL chez un fournisseur cloud (offre gratuite à vérifier : Aiven, TiDB Cloud, …).
2. Importez `database/schema.sql`, puis vos vraies données exportées depuis phpMyAdmin (InfinityFree). `database/seed_demo_data.sql` est facultatif (données de test).
3. Créez le Super Admin : `DB_HOST=… DB_USER=… DB_PASSWORD=… DB_NAME=… php database/create_admin.php admin vous@exemple.mg`

## 3. Vercel
Import du dépôt (Framework : *Other*, pas de build). Variables d'environnement (Production + Preview) : voir `.env.example`
(`DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, DB_SSL, GOOGLE_SA_JSON_B64`).

## Sécurité mise en place
Aucun secret dans le code · requêtes préparées · CSRF sur tous les POST · rôles vérifiés côté serveur sur chaque page/endpoint ·
mots de passe `password_hash` + anti brute-force (5 échecs/15 min) · `session_regenerate_id` · cookies Secure/HttpOnly/SameSite ·
sessions en base · erreurs masquées · TLS cURL activé · en-têtes (HSTS, CSP, X-Frame-Options…) · outils de diagnostic supprimés.

## Limites connues
- `parametre.php` : la matrice de permissions n'est pas persistée (comportement d'origine).
- La CSP autorise `unsafe-inline` (scripts inline existants) ; à durcir avec des nonces.
