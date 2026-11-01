# 📋 Guide d'installation — analyse_mpox.php
## Connexion Google Sheets API v4 (Service Account)

---

## 1. Créer un Service Account Google

1. Aller sur : https://console.cloud.google.com/
2. Créer un projet (ou sélectionner le projet existant)
3. Activer l'API : **Menu → APIs & Services → Activer des APIs** → chercher **"Google Sheets API"** → Activer
4. Créer les identifiants :
   - **Menu → IAM & Admin → Comptes de service**
   - Cliquer **"Créer un compte de service"**
   - Nom : `pharmaco-sheets-reader`
   - Cliquer **"Terminer"**
5. Dans la liste, cliquer sur le compte de service créé
6. Onglet **"Clés"** → **"Ajouter une clé"** → **"Créer une clé JSON"**
7. Le fichier `.json` est téléchargé automatiquement

---

## 2. Placer le fichier JSON dans votre projet

```bash
# Créer le dossier config s'il n'existe pas
mkdir -p /var/www/html/pharmaco/config

# Copier le fichier téléchargé
cp ~/Downloads/votre-projet-XXXX.json /var/www/html/pharmaco/config/google_service_account.json

# Sécuriser (lecture seule par le serveur web)
chmod 600 /var/www/html/pharmaco/config/google_service_account.json
```

**Important :** Ajouter `config/` dans `.gitignore` pour ne jamais pousser les clés sur GitHub :
```
# .gitignore
config/google_service_account.json
cache/
```

---

## 3. Partager la feuille Google Sheet avec le Service Account

1. Ouvrir votre feuille : https://docs.google.com/spreadsheets/d/1TNyOJaH16a5LNjuleNC8qN-eh6Lrqxkd/
2. Cliquer **"Partager"** (bouton en haut à droite)
3. Dans le champ email, coller l'adresse email du Service Account
   - Elle ressemble à : `pharmaco-sheets-reader@votre-projet.iam.gserviceaccount.com`
   - Elle est visible dans le fichier JSON à la clé `"client_email"`
4. Rôle : **Lecteur** (pas besoin de modifier)
5. Cliquer **"Envoyer"**

---

## 4. Créer le dossier de cache

```bash
mkdir -p /var/www/html/pharmaco/cache
chmod 775 /var/www/html/pharmaco/cache
chown www-data:www-data /var/www/html/pharmaco/cache
```

---

## 5. Vérifier le nom de l'onglet

Dans le fichier `analyse_mpox.php`, ligne :
```php
define('SHEET_RANGE', 'Feuille1!A1:BF5000');
```

⚠️ **Remplacer `Feuille1` par le nom exact de l'onglet** contenant vos données brutes.
Pour le trouver : regardez les onglets en bas de votre Google Sheet.

---

## 6. Ajouter le lien dans la sidebar

Dans votre fichier `sidebar.php`, ajouter dans le menu :

```php
<a href="analyse_mpox.php"
   class="nav-link <?= ($active_page === 'analyse_mpox') ? 'active' : '' ?>"
   title="Analyse Mpox">
    <i class="bi bi-virus"></i>
    <span>Analyse Mpox</span>
</a>
```

Et dans `$PAGE_ROLES` (partout où il est défini) :
```php
'analyse_mpox.php' => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
```

---

## 7. Ajouter la page dans parametre.php

Dans `$PAGE_ROLES` et `$PAGE_LABELS` :
```php
'analyse_mpox.php' => array('Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'),
```
```php
'analyse_mpox.php' => ['icon' => 'bi-virus', 'label' => 'Analyse Mpox'],
```

---

## 8. Structure des fichiers attendue

```
pharmaco/
├── analyse_mpox.php          ← page principale
├── config/
│   └── google_service_account.json   ← clé privée (NE PAS COMMITTER)
├── cache/
│   └── mpox_data.json        ← cache auto (5 min)
├── sidebar.php
├── footer_shared.php
└── .gitignore
```

---

## 9. Colonnes reconnues automatiquement

La page détecte automatiquement ces colonnes dans votre Google Sheet :

| Colonne utilisée | En-tête attendu dans Sheet |
|---|---|
| Date notification | `Date de notification` |
| Région | `Region` |
| District | `District` |
| Classification | `Classification de cas(...)` |
| Évolution | `Evolution` |
| Sexe | `Sexe` |
| Âge | `Age (année)` |
| Tranche d'âge | `tranche_d_age` |
| Semaine épidémique | `SE Notification` |
| Hospitalisation | `Hospitalisation` |
| Prélèvement | `Prélèvement (Oui ou Non)` |
| Statut VIH | `Statut VIH(...)` |
| Vaccin | `Vaccin MPOX` |
| Clade | `Charactères génomiques` |

---

## 10. Synchronisation automatique

- **Cache serveur PHP :** 5 minutes (configurable via `CACHE_TTL` dans le fichier)
- **Countdown visuel** en temps réel dans la page
- **Bouton "Actualiser"** pour forcer une sync immédiate
- En cas d'erreur réseau : le cache précédent est utilisé (pas de page blanche)

---

## Dépannage

| Problème | Solution |
|---|---|
| "Fichier Service Account introuvable" | Vérifier le chemin `config/google_service_account.json` |
| "403 Forbidden" depuis Google | L'email du SA n'a pas été partagé sur le Sheet |
| Données vides | Vérifier le nom de l'onglet dans `SHEET_RANGE` |
| Cache bloqué | Supprimer `cache/mpox_data.json` puis recharger |
| Erreur JWT | Vérifier que l'extension `openssl` est activée en PHP |
