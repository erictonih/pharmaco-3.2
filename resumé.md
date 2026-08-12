# 📋 Documentation Projet PHARMACO v3.2 — Pour IA

**Orientation** : Ce document permet une IA de reprendre le projet COMPLET sans connaissance du contexte initial.

---

## 🎯 Vue d'Ensemble

**Nom** : PHARMACO 3.2 (Plateforme de surveillance épidémiologique DVSSER)

**Auteur** : Direction Veille Sanitaire (DVSSER) — Ministère Santé Publique Madagascar

**Techs** : PHP 7.4+, MySQL 5.7+, CodeIgniter 4, Google Sheets API

**Buts Principaux** :
1. Enregistrement clients + médicaments prescrits
2. Analyse géographique (districts/heatmap)
3. Surveillance épidémiologique (alertes risque)
4. Export données → Google Sheets (sync API)
5. Tableaux croisés dynamiques (pivot)

---

## 📊 Architecture Base de Données

### Schema SQL (`pharmacie_corrige.sql` — 114 lignes)

**3 tables principales** :

#### 1️⃣ Table `client`
```sql
CREATE TABLE `client` (
  Id           BIGINT(20)   PRIMARY KEY,  -- Identifiant unique (ex: 110823005)
  Nom          VARCHAR(50)  NOT NULL,     -- Nom patient
  Prenom       VARCHAR(50)  NOT NULL,     -- Prénom
  Age          INT(11)      DEFAULT NULL, -- Âge (0-120)
  Genre        VARCHAR(20)  NOT NULL,     -- "Masculin" ou "Feminin"
  Type         VARCHAR(30)  DEFAULT NULL, -- "Ambulatoire" ou "Hospitalisé"
  Residence    VARCHAR(100) DEFAULT NULL, -- Adresse complète
  District     VARCHAR(250) NOT NULL,     -- District Madagascar (ex: "Ankazobe")
  date_de_sousmission DATETIME NOT NULL   -- Timestamp création
);
```
**Indices** : idx_type, idx_district, idx_date (optimise recherches)
**Données test** : 9 clients enregistrés (Id : 21, 12, 1200, 121412, etc.)

#### 2️⃣ Table `medicament`
```sql
CREATE TABLE `medicament` (
  id           INT(11)      PRIMARY KEY AUTO_INCREMENT,  -- PK auto (1, 2, 3...)
  id_client    BIGINT(20)   NOT NULL,                    -- FK vers client.Id
  Type         VARCHAR(50)  NOT NULL,                    -- Type drogue (Antibiotique, Antalgique, etc.)
  Nombres      INT(11)      NOT NULL DEFAULT 0,          -- Quantité unités
  Date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP  -- Timestamp prescription
);
```
**Types médicaments** : Antibiotique, Antalgique, Antidiarrhéique, Antipyrétique, VitamineC
**Indices** : idx_client, idx_type, idx_date (jointure rapide)
**Relation** : N médicaments → 1 client (N:1)

#### 3️⃣ Table `utilisateurs`
```sql
CREATE TABLE `utilisateurs` (
  user_id  INT(11)      PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL UNIQUE,
  mdp      VARCHAR(255) NOT NULL  -- Mot passe (stocké en clair dans cette version)
);
```
**Utilisateurs test** :
- username: "admin", mdp: "Rakotonirina24"
- username: "Test", mdp: "Test@!234"

**Charset** : utf8mb4_unicode_ci (supporte accents/emojis)
**Timezone** : UTC+3 (Madagascar)

---

## 🗂️ Structure Fichiers & Rôles

### Fichiers Entry Points (Frontend)

| Fichier | Ligne | Rôle |
|---------|-------|------|
| **index.php** | 30 | Dashboard accueil — stats rapides |
| **formulaire.php** | 12 | Saisie nouveau client + médicaments |
| **visualisation_Base.php** | 12 | Graphiques (BarChart, PieChart) |
| **visualisation_de_donnée.php** | 12 | Tableau Bord (recherche, tri) |
| **analyse.php** | 40 | Heatmap districts, alertes épidémiologiques |
| **analyse_mpox.php** | 60 | Analyse MPOX spécifique |
| **carto_mpox.php** | 40 | Cartographie interactive MPOX |
| **tdc.php** | 16 | Tableau Croisé Dynamique (pivot) |
| **users.php** | 32 | Gestion utilisateurs (admin) |
| **parametre.php** | 28 | Configuration système |
| **Login.php** | 12 | Authentification (form login) |
| **logout.php** | 4 | Déconnexion (destroy session) |

### Fichiers Backend (API & Logique)

| Fichier | Rôle |
|---------|------|
| **auth_check.php** | Vérifie session user — redirect Login.php si absent |
| **action.php** | Route POST — traite soumission formulaire |
| **data.php** | API JSON — retourne données (GET/POST) |
| **traitement-medoc.php** | Logique gestion médicaments |
| **medicament.php** | CRUD médicaments |
| **utilisateur.php** | CRUD utilisateurs |
| **shp2geojson.php** | Convertit Shapefile → GeoJSON (districts) |
| **setup_google_sheets.php** | Config API Google Sheets |

### Fichiers Inclus (Templates)

| Fichier | Rôle |
|---------|------|
| **sidebar.php** | Menu navigation (gauche) |
| **footer_shared.php** | Footer commun (copyrights) |

### Dossiers

| Dossier | Contenu |
|---------|---------|
| **css/** | Bootstrap 3.3.7 + dvsser-theme.css (style custom) |
| **js/** | Bootstrap JS + jquery.js (v2.1.1), pivot.min.js, d3_renderers.min.js |
| **fonts/** | Glyphicons (Bootstrap icons) |
| **sary/** | Images (Logo, icones) |
| **config/** | wired-ripsaw-471110-p6-3a112f2506dd.json (Google API key?) |
| **writable/** | Logs, cache, sessions (CodeIgniter) |
| **vendor/** | CodeIgniter 4, PHPUnit, Faker (dependencies) |

---

## 🔌 Connexion Base de Données

**Détails hardcodés dans PHP** (RISQUE SÉCURITÉ) :

```php
// Exemple index.php:9
$conn = @new mysqli(
    'sql209.infinityfree.com',           // Host
    'if0_41511897',                      // User
    'jR9A6jlS7c8f8r',                    // Password (⚠️ EXPOSÉ)
    'if0_41511897_pharmacie'             // Database
);
$conn->set_charset('utf8mb4');
```

**⚠️ SÉCURITÉ** : Les credentials sont en dur dans le code PHP.
**Recommandation IA** : Déplacer en `.env` avec `dotenv` PHP.

---

## 📱 Fonctionnalités Clés

### 1. Authentification & Session
- **Login.php** : Formulaire POST (username + mdp)
- **Vérification** : Requête SQL simple (PAS HASH)
- **Session PHP** : `$_SESSION["user_id"]` définie après login réussi
- **Protection** : `auth_check.php` inclus en début page (redirect si pas authentifié)

### 2. Saisie Données (formulaire.php)
**Flow** :
1. Utilisateur remplit form (Nom, Prénom, Age, Genre, Type patient, Residence, District)
2. POST vers `action.php`
3. INSERT client + 1-N INSERT médicaments
4. Redirect index.php avec message

**Validation** : Minimaliste (vérifier non-empty seulement)

### 3. Visualisations (Libraries)
- **Pivot (pivot.min.js)** : Tableau Croisé → agrégation dynamique
- **D3 (d3_renderers.min.js)** : Graphiques avancés
- **Bootstrap Charts** : Graphiques simples (built-in)

### 4. Cartographie Districts (GeoJSON)
- **Fichiers GeoJSON** : `Districts.geojson` (1.2M), `Districts2.geojson` (9.9M), `dist2.geojson` (28M)
- **Shapefile source** : `DISTRICT.shp/dbf/prj/shx`
- **Conversion** : Faite par `shp2geojson.php` (Shapefile → GeoJSON)
- **Utilisation** : Rendue dans `analyse.php` et `carto_mpox.php` (Leaflet ou Mapbox)

### 5. API Google Sheets (setup_google_sheets.php)
**Objectif** : Sync données MySQL → Google Sheets en temps réel

**Implémentation** :
1. Upload JSON "Service Account" (credentials Google Cloud)
2. Script signe JWT avec clé privée (OpenSSL)
3. GET access token Google OAuth 2.0
4. PUT/APPEND données vers Sheets API

**Fichiers Config** :
- `config/google_service_account.json` (créé au premier upload)
- Cache tokens dans `cache/` (durée : 1 heure)

**Flows Sync** :
- **À la soumission formulaire** : Ajouter ligne Sheets
- **À chaque UPDATE** : Mettre à jour Sheets
- **Batch** : Exporter tout les jours (cron?)

---

## 🔄 Algorithmes & Logiques Principales

### A. Authentification (auth_check.php + Login.php)
```
1. Utilisateur accède index.php
2. auth_check.php vérifie session["user_id"]
   - Si absent → redirect Login.php
   - Si présent → charge page
3. Login.php reçoit POST (username, mdp)
4. SELECT utilisateurs WHERE username = ?
5. Vérifier mdp (comparaison STRING — pas hash)
6. Si OK → session["user_id"] = user_id
7. Redirect index.php
```

### B. Analyse Épidémiologique (analyse.php)
```
1. SELECT COUNT(*) FROM medicament 
   GROUP BY District 
   WHERE Date > (maintenant - 6 mois)
2. Calculer TAS (Taux Attaque Secondaire)? Incidence?
   → Logique dans PHP (cf. analyse.php:40)
3. Identifier districts "ALERTE" (dépassent seuil?)
4. Rendre heatmap (couleurs : vert < jaune < rouge)
5. Afficher liste alertes et districts actifs
```

### C. Pivot Table (tdc.php)
```
1. Charger library pivot.min.js
2. Query DB → JSON (toutes lignes medicament + client)
3. pivot() agrège :
   - Lignes : Type médicament
   - Colonnes : District
   - Valeurs : SUM(Nombres)
4. User peut changer axes dynamiquement (client-side)
```

### D. Google Sheets Sync
```
Service Account Flow :
1. IA charge config/google_service_account.json
2. Extrait client_email, private_key
3. Génère JWT (Header.Payload.Signature via OpenSSL)
4. POST JWT → Google OAuth token endpoint
5. Reçoit access_token (1h validité)
6. Cache token dans cache/ (fichier + TTL)
7. PUT/APPEND Sheets API avec access_token

Chaque INSERT/UPDATE client → append Sheets automatique?
(Détails implémentation dans setup_google_sheets.php:50+)
```

---

## 📝 Flux de Données Complet

### Cas d'usage : "Ajouter nouveau patient"

```
UTILISATEUR
    ↓ accès formulaire.php
FORMULAIRE
    ↓ saisi Nom, Prénom, Age, Genre, Type, Residence, District
    ↓ clic "Enregistrer"
    POST /action.php
        ↓ reçoit $_POST (nom, prenom, age, genre, type, residence, district)
        ↓ validations simples
        ↓ genère Id (ex: timestamp + rand)
        ↓ INSERT INTO client (Id, Nom, Prenom, ...)
        ↓
        (Boucle sur chaque médicament)
        ↓ INSERT INTO medicament (id_client, Type, Nombres, Date)
        ↓
        (API Google Sheets)
        ↓ append client + médicaments vers Sheets
        ↓
    REDIRECT /index.php?success=1
UTILISATEUR voit "Patient ajouté avec succès"
```

### Cas d'usage : "Afficher alertes épidémiologiques"

```
UTILISATEUR
    ↓ clic "Analyse épidémiologique"
ANALYSE.PHP
    ↓ SELECT medicament WHERE Date > (6 mois avant)
    ↓ GROUP BY District
    ↓ Calculer metrics (TAS, incidence, etc.)
    ↓ Identifier alertes (seuil défini?)
    ↓ Requête GeoJSON (Districts.geojson)
    ↓ Rendre heatmap (Leaflet? Mapbox?)
    ↓ Afficher tableau alertes
UTILISATEUR voit carte colorée + liste districts actifs
```

---

## 🛠️ Technologies & Dépendances

### Backend
- **PHP** 7.4+ (array_key_exists, null coalescing, etc.)
- **MySQL** 5.7+ (InnoDB, UTC+3)
- **CodeIgniter 4** (dans vendor/) — MVC framework
- **cURL** (pour Google API)
- **OpenSSL** (pour JWT signing)

### Frontend
- **Bootstrap 3.3.7** (css/bootstrap.min.css)
- **jQuery 2.1.1** (jquery.js)
- **Pivot.js** (pivot.min.js) — Tableau croisé
- **D3.js** (d3_renderers.min.js) — Graphiques
- **Leaflet.js?** ou **Mapbox GL?** (pour cartographie)

### Google Services
- **Google Sheets API v4** (append/update rows)
- **Google OAuth 2.0** (JWT bearer token)
- **Service Account** (JSON key avec private_key)

---

## ⚙️ Configuration & Setup

### 1. Installation Base de Données
```bash
# Via PhpMyAdmin / CLI MySQL
mysql -u [user] -p [password] if0_41511897_pharmacie < pharmacie_corrige.sql
```

### 2. Setup Google Sheets API
```
1. Google Cloud Console → Créer Project
2. Enable Google Sheets API
3. Create Service Account → Download JSON
4. Share Google Sheet avec client_email (SA)
5. Accès setup_google_sheets.php
6. Upload JSON
7. Tester sync
```

### 3. Configuration PHP
```php
// À déplacer en .env :
define('DB_HOST', 'sql209.infinityfree.com');
define('DB_USER', 'if0_41511897');
define('DB_PASS', 'jR9A6jlS7c8f8r');
define('DB_NAME', 'if0_41511897_pharmacie');
define('GOOGLE_SHEET_ID', 'xxxxx...');
```

---

## 🔐 Sécurité & Recommandations IA

### Risques Identifiés
1. **SQL Injection** : Pas de prepared statements (`mysqli_prepare`)
2. **Credentials en dur** : DB password dans PHP source
3. **Pas de CSRF** : Forms sans token CSRF
4. **Mots de passe** : Stockés en clair (pas bcrypt)
5. **XSS** : Peu de `htmlspecialchars()`

### Actions Recommandées
```
1. Ajouter mysqli_prepare pour toutes queries
   - Avant : $conn->query("SELECT * FROM client WHERE Id = $id")
   - Après : $stmt = $conn->prepare("SELECT * FROM client WHERE Id = ?")
                $stmt->bind_param("i", $id)

2. Hacher mots de passe (password_hash + password_verify)
   - Stockage : MD5/SHA1 → bcrypt (password_hash PHP native)

3. Utiliser .env pour secrets (composer install phpdotenv)
   - Charger via : $dotenv = Dotenv\Dotenv::createImmutable(__DIR__)

4. Ajouter CSRF tokens dans forms
   - Générer token → stocker session
   - Valider chaque POST

5. Utiliser htmlspecialchars() partout dans output
   - Avant : echo $row['Nom']
   - Après : echo htmlspecialchars($row['Nom'], ENT_QUOTES, 'UTF-8')

6. Logging d'audit
   - Log chaque INSERT/UPDATE/DELETE avec user_id + timestamp
```

---

## 📈 Cas de Modification Futurs

### Ajouter une nouvelle visualisation
```php
// 1. Créer fichier : mon_graphique.php
// 2. Session check + include auth_check.php
// 3. Query DB → fetch données
// 4. Passer à JS (JSON)
// 5. Renderer (Pivot, D3, Chart.js)
```

### Modifier la structure DB
```sql
-- 1. Créer nouvelle colonne :
ALTER TABLE client ADD COLUMN telephone VARCHAR(20) DEFAULT NULL;

-- 2. Backfill données
UPDATE client SET telephone = '000-00-00' WHERE telephone IS NULL;

-- 3. Updater PHP forms & queries
-- 4. Tester avec données test
-- 5. Exporter Sheets
```

### Connecter nouvelle source de données
```php
// 1. Créer table intermédiaire (import_data)
// 2. Parser source (CSV, API) → INSERT
// 3. Réconcilier avec client (Id matching)
// 4. Sync Sheets
```

---

## 🚀 Déploiement Production

### Checklist IA
- [ ] Déplacer credentials en .env
- [ ] Ajouter prepared statements MySQL
- [ ] Hasher tous mots de passe
- [ ] HTTPS forcé (redirige HTTP → HTTPS)
- [ ] Headers sécurité (X-Frame-Options, CSP, etc.)
- [ ] Logging audit complet
- [ ] Backup BDD quotidien
- [ ] Rate limiting authentification
- [ ] Monitoring Google Sheets sync
- [ ] Tests unitaires (PHPUnit via vendor/)

---

## 📞 Points de Contact IA

**Si tu dois modifier le code** :

### Pour Authentification
- Fichier : `Login.php` (ligne 1-50)
- Lire : Table `utilisateurs`, column `mdp`
- Modifier : Ajouter hash check (`password_verify`)

### Pour Saisie Formulaire
- Fichier : `formulaire.php` + `action.php`
- Lire : Variables POST, INSERT queries
- Modifier : Valider inputs, gérer erreurs

### Pour Cartographie
- Fichier : `analyse.php`, `carto_mpox.php`
- Lire : GeoJSON load, heatmap render
- Modifier : Ajouter couches, ajuster seuils alertes

### Pour Google Sheets
- Fichier : `setup_google_sheets.php` (sync config)
- Lire : JWT generation, OAuth token, Sheets API append
- Modifier : Ajouter champs, gérer authentification renouvellement

### Pour Pivot Table
- Fichier : `tdc.php`
- Lire : pivot.min.js loader, data JSON
- Modifier : Axes par défaut, formatage nombres

---

## 📚 Fichiers Essentiels à Lire en Priorité

1. **pharmacie_corrige.sql** (114 lignes) — Comprendre schema
2. **index.php** (207 lignes) — Comprendre flow accueil
3. **formulaire.php** + **action.php** — Flux saisie données
4. **analyse.php** (40 lignes) — Logique alertes
5. **setup_google_sheets.php** (100+ lignes) — Google API integration
6. **auth_check.php** — Session management

---

## ✅ Résumé Pour IA

**Tu dois savoir** :
- 3 tables SQL : client, medicament, utilisateurs
- Flow saisie : formulaire.php → action.php → INSERT
- Auth : Login.php check mdp → session["user_id"]
- Visualisations : Pivot (tdc.php), Heatmap (analyse.php), Charts (visualisation_Base.php)
- Google Sheets : Service Account JWT → OAuth token → Sheets API append
- Sécurité : Ajouter prepared statements, hashing, CSRF tokens
- Structures fichiers : Frontend (PHP pages), Backend (action.php, data.php), Config (setup_google_sheets.php)

**Pour modifier le code** :
1. Lire le fichier concerné
2. Identifier queries SQL → Utiliser prepared statements
3. Comprendre flow (input → process → output)
4. Tester avec données test
5. Vérifier sync Sheets après

---

**Document généré** : Août 2026  
**Version Projet** : 3.2  
**Dernière mise à jour code** : Mai 2025  
**Dépôt** : git (branches: main, prod, Graphe_dechet)