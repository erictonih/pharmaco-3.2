# Pharmaco / Mpox — analyse directe du Google Sheet

Aucune base de données : des **services PHP** lisent le Google Sheet (API v4, lecture seule, colonnes nominatives jamais téléchargées), calculent tous les indicateurs, et les pages de visualisation les appellent directement.

## Architecture
- `src/Sheets/` : sources de données (Google Sheets, fixture de test), cache disque + repli sur cache périmé.
- `src/Services/` : `MpoxDataset` (normalisation), `EpiAnalyticsService` (indicateurs, courbes, géographie, démographie, clinique, laboratoire), `OutbreakAlertService` (alertes Poisson par district), `DataQualityService`.
- `app/` : pages (`index`, `situation`, `carte`, `alertes`, `actualiser`), routées par `api/index.php`.
- Authentification sans état : cookie signé HMAC, rôles Super Admin / Analyste / Visualiseur, CSRF.

## Configuration
Voir `.env.example`. Générer un utilisateur : `echo 'MotDePasse' | php tools/make_user.php admin "Super Admin"`.
Partager le Sheet (lecture) avec l'e-mail du **nouveau** compte de service. Recommandé : règle de rate-limit WAF Vercel sur `/Login`.

## Point de situation (PDF / PowerPoint)
Page `rapport.php` : filtre temporel (dates + périodes prédéfinies) et aperçu des 9 diapositives du point de situation (couverture, situation du jour et cumulée, décès, courbes de notification sur la période et sur les 3 dernières semaines, tableau par région, âge/sexe et sex-ratio, carte et districts « hot spot », remerciements). Les exports sont générés dans le navigateur (PptxGenJS : objets natifs modifiables ; html2canvas + jsPDF pour le PDF) : aucune donnée ne quitte le serveur de l'application. Calculs : `src/Services/ReportService.php`.

## Gestion des utilisateurs (Super Admin)
Page `utilisateurs.php` : liste, ajout et suppression de comptes. Les comptes sont stockés dans **Turso** (libSQL, API HTTP ; variables `TURSO_DATABASE_URL` et `TURSO_AUTH_TOKEN` fournies par l'intégration Vercel), table `app_users` créée automatiquement, mots de passe hachés (`password_hash`).
Les comptes de `APP_USERS_JSON` restent des comptes de secours (non supprimables depuis la page, utilisables même si la base est indisponible). Un compte supprimé perd l'accès en 30 s au plus. Interdits : supprimer son propre compte, le dernier Super Admin.

## Tests
`php tests/run.php` (services vs calculs Python indépendants) ; `bash tests/google_source_test.sh` (chemin Google contre un faux serveur) ; `bash tests/users_test.sh` (utilisateurs : faux Turso + parcours HTTP) ; `bash tests/report_test.sh` (point de situation, recalcul indépendant en Python).

## Pages supprimées
Modules pharmacie / base de données : récupérables dans la branche `fix/vercel-security-hardening`.
