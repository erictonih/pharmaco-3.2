# PHARMACO — migration CodeIgniter 4

Le dossier est une application CI4 MVC. Les contrôleurs ne contiennent pas de SQL : les accès aux données sont regroupés dans `app/Models`, au moyen d'une unique connexion PDO (`app/Libraries/PdoConnection.php`) et de requêtes préparées.

## Démarrage

1. Importer `../pharmacie_corrige.sql` dans MySQL.
2. Modifier les paramètres `database.default.*` dans `.env` (aucun identifiant distant n'est repris dans le projet).
3. Vérifier que les extensions PHP `pdo` et `pdo_mysql` sont actives.
4. Depuis ce dossier, lancer : `php -S localhost:8000 -t public`.
5. Ouvrir `http://localhost:8000/connexion`.

Les comptes du SQL historique utilisent des mots de passe en clair ; l'authentification les accepte temporairement pour préserver le fonctionnement, tout en acceptant les mots de passe bcrypt. Une migration des mots de passe vers `password_hash()` est recommandée avant mise en production.

## Routes principales

`/`, `/connexion`, `/patients/nouveau`, `/medicaments/nouveau`, `/tableau-de-bord`, `/visualisations`, `/analyse`, `/api/donnees`.
