# Audit de migration PHARMACO → CodeIgniter 4

Date : 3 août 2026. Cet audit compare les fichiers PHP historiques présents à la racine avec l'adaptation CI4.

| Module historique | Fonction | État CI4 | Action nécessaire |
|---|---|---|---|
| `Login.php`, `logout.php` | Connexion/session | Partiel | Flux migré ; porter précisément le visuel propre à la connexion. |
| `index.php` | Accueil/statistiques | Partiel | Statistiques migrées ; cartes d'accès rapide et alertes visuelles à compléter. |
| `formulaire.php`, `action.php`, `medicament.php`, `traitement-medoc.php` | Saisie client et médicaments | Migré | MVC/PDO/requêtes préparées. Test fonctionnel local requis. |
| `visualisation_Base.php` | 4 graphiques Chart.js | Absent | Recréer les graphiques à partir d'un modèle d'agrégats. |
| `visualisation_de_donnée.php` | Synthèse/tableau filtrable | Partiel | Tableau migré ; recherche/tri JS à intégrer. |
| `tdc.php`, `data.php` | Pivot dynamique/API JSON | Partiel | API JSON migrée ; interface Pivot à porter. |
| `analyse.php`, `medoc-risque.php` | Risques, carte, export | Partiel | Agrégat district migré ; Leaflet, GeoJSON, prédiction et exports restent à porter. |
| `analyse_mpox.php`, `carto_mpox.php` | Dashboard/carte MPOX Google Sheets | Absent | Créer un service Google Sheets et les vues/cartes. |
| `users.php` | Utilisateurs/rôles | Bloqué par schéma | Le fichier attend `users` (email, rôle, statut…) ; la BDD fournie contient `utilisateurs` (username, mdp). Décision de schéma nécessaire. |
| `parametre.php` | Rôles/paramètres | Absent | Dépend du schéma utilisateurs et doit être migré après celui-ci. |
| `setup_google_sheets.php` | Configuration Google Sheets | Absent | Ne pas migrer les secrets JSON dans `public`; à placer dans `writable/`. |
| `shp2geojson.php` | Outil de conversion SIG | Absent | À convertir en commande CI4, pas en page publique. |

## CSS et composants constatés

Le thème commun provient de `dvsser-theme.css` : sidebar, topbar, footer, cartes, formulaires, tableaux, responsive et thème clair/sombre. Il est désormais publié dans `public/assets/css/style.css` et le layout CI4 reprend les modules `sidebar.php` et `footer_shared.php`.

Cependant `Login.php`, `analyse.php`, `analyse_mpox.php`, `carto_mpox.php`, `tdc.php`, `users.php` et `parametre.php` possèdent aussi des blocs CSS propres. Ils doivent être portés écran par écran pour une identité visuelle exacte ; ils ne doivent pas être considérés comme déjà migrés.

## Test local à effectuer

Depuis le dossier CI4 :

```bash
php -S localhost:8000 -t public
```

Puis tester : connexion, création client, ajout médicaments, tableau, visualisations et analyse. Vérifier les erreurs dans `writable/logs/`.
