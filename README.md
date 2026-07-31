# Pharmaco 3.2 — CodeIgniter 4

Application migrée vers CodeIgniter 4, avec une logique métier conservée.

## Démarrage

1. Copier `env` vers `.env` et renseigner les paramètres MySQL.
2. Importer `public/pharmacie_corrige.sql` dans la base configurée.
3. Lancer `php spark serve` puis ouvrir `http://localhost:8080`.

Les ressources statiques sont dans `public/assets`; les anciennes pages procédurales sont conservées dans `legacy/` à titre de référence pendant la recette de migration.
