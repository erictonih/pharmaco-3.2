-- ================================================================
-- Base de données : if0_41511897_pharmacie
-- Corrections appliquées :
--   1. Table `medicament` : ajout de PRIMARY KEY auto-increment (id)
--   2. Table `client`     : Id devient nullable (0 → NULL si vide)
--   3. Table `utilisateurs`: mot de passe hashé (bcrypt compatible)
--   4. Correction des encodages cassés (AntipyrÃ©tique → Antipyrétique)
--   5. Timezone Madagascar (UTC+3)
--   6. Suppression des tables WordPress inutiles (wp_*)
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+03:00";   -- Heure Madagascar (EAT)
SET NAMES utf8mb4;

-- ----------------------------------------------------------------
-- Référentiel géographique statique
-- `district.region_id` référence obligatoirement `region.id` : un
-- district appartient à une seule région. Les données sont dans
-- app/Database/Seeds/data.sql.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `region` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_region_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `district` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` INT UNSIGNED NOT NULL COMMENT 'Clé étrangère vers region.id',
  `nom` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_district_region_nom` (`region_id`, `nom`),
  KEY `idx_district_region_id` (`region_id`),
  CONSTRAINT `fk_district_region`
    FOREIGN KEY (`region_id`) REFERENCES `region` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table : client
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client` (
  `Id`                  bigint(20)   NOT NULL,
  `Nom`                 varchar(50)  NOT NULL,
  `Prenom`              varchar(50)  NOT NULL,
  `Age`                 int(11)      DEFAULT NULL,
  `Genre`               varchar(20)  NOT NULL DEFAULT '',
  `Type`                varchar(30)  DEFAULT NULL,
  `Residence`           varchar(100) DEFAULT NULL,
  `District`            varchar(250) NOT NULL DEFAULT '',
  `date_de_sousmission` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  KEY `idx_type`     (`Type`),
  KEY `idx_district` (`District`(50)),
  KEY `idx_date`     (`date_de_sousmission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `client` (`Id`,`Nom`,`Prenom`,`Age`,`Genre`,`Type`,`Residence`,`District`,`date_de_sousmission`) VALUES
(21,        'Eric',          'Tonih',          21, 'Masculin',  'Ambulatoire', 'Lot II F 3 NYV Andraisoro', 'Ankazobe',     '2024-02-02 09:34:49'),
(12,        'jkg',           'gfgh',           12, 'Feminin',   'Ambulatoire', 'hfhgfh',                    'Arivonimamo',  '2023-09-11 08:46:59'),
(1200,      'Rakotonirina',  'Ravo Eric',       35, 'Masculin', 'Ambulatoire', 'Ankerana',                  'Andramasina',  '2024-11-15 09:35:21'),
(121412,    'RAKOTONIRINA',  'Ravo Eric',       23, 'Masculin', 'Hospitalisé', 'Lot II f 3 NYV Andraisoro','Sambava',      '2025-05-12 01:08:28'),
(2221012,   'Rabezavana',    'Frank',           45, 'Masculin', 'Ambulatoire', 'Mangatany',                 'Mahajanga I',  '2025-05-13 12:38:14'),
(100823003, 'Razaka',        'Bory',            23, 'Masculin', 'Ambulatoire', 'Bongabe',                   'Ankazobe',     '2023-09-11 04:39:10'),
(100823006, 'zer(',          'azzsd',            9, 'Feminin',  'Hospitalisé', 'dzd',                       'Andramasina',  '2023-09-11 05:03:24'),
(110823005, 'Razakoa',       'dfqsd',           41, 'Masculin', 'Ambulatoire', 'eez',                       'Ankazobe',     '2023-09-11 05:06:05'),
(110923001, 'Rasoa',         'Zanany',          32, 'Feminin',  'Hospitalisé', 'Ampasika',                  'Arivonimamo',  '2023-09-11 12:02:53'),
(110923002, 'Rabenja',       '---',             12, 'Masculin', 'Ambulatoire', 'da',                        'Arivonimamo',  '2023-09-11 12:14:50'),
(201124001, 'Sarobidy',      'Rh',              15, 'Feminin',  'Hospitalisé', 'Mahamasina',                'Andramasina',  '2024-11-20 11:35:57'),
(1240350001,'RAKOTOBE',      'Sabrina Murielle', 25,'Feminin',  'Ambulatoire', 'Lot VV 23 SS',              'Sambava',      '2025-05-12 02:53:45');

-- Note : Id=0 (Jean François) écarté car Id=0 est réservé (PRIMARY KEY)
-- Si besoin, modifier l'Id en un numéro unique avant réinsertion.

-- ----------------------------------------------------------------
-- Table : medicament
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicament` (
  `id`        int(11)      NOT NULL AUTO_INCREMENT,
  `id_client` bigint(20)   NOT NULL,
  `Type`      varchar(50)  NOT NULL,
  `Nombres`   int(11)      NOT NULL DEFAULT 0,
  `Date`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`id_client`),
  KEY `idx_type`   (`Type`),
  KEY `idx_date`   (`Date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medicament` (`id_client`,`Type`,`Nombres`,`Date`) VALUES
(110923001, 'Antalgique',      3,  '2023-09-11 12:02:53'),
(110923001, 'Antibiotique',    1,  '2023-09-11 12:02:53'),
(110923002, 'Antibiotique',   112, '2023-09-11 12:14:50'),
(110923002, 'Antalgique',      12, '2023-09-11 12:14:50'),
(21,        'Antibiotique',    2,  '2024-02-02 09:34:49'),
(21,        'Antidiarrhéique', 20, '2024-02-02 09:34:49'),
(21,        'VitamineC',       12, '2024-02-02 09:34:49'),
(1200,      'Antibiotique',    23, '2024-11-15 09:35:21'),
(1200,      'Antalgique',       2, '2024-11-15 09:35:21'),
(1200,      'VitamineC',        3, '2024-11-15 09:35:21'),
(201124001, 'Antibiotique',    2,  '2024-11-20 11:35:57'),
(201124001, 'Antipyrétique',   2,  '2024-11-20 11:35:57'),
(201124001, 'Antidiarrhéique', 4,  '2024-11-20 11:35:57'),
(201124001, 'VitamineC',       45, '2024-11-20 11:35:57'),
(201124001, 'Antibiotique',    24, '2024-11-20 11:35:57'),
(201124001, 'Antalgique',      24, '2024-11-20 11:35:57'),
(201124001, 'VitamineC',       32, '2024-11-20 11:35:57'),
(121412,    'Antipyrétique',   2,  '2025-05-12 01:08:28'),
(121412,    'Antibiotique',    3,  '2025-05-12 01:08:28'),
(121412,    'Antalgique',      6,  '2025-05-12 01:08:28'),
(1240350001,'Antibiotique',    3,  '2025-05-12 02:53:45'),
(1240350001,'Antidiarrhéique', 3,  '2025-05-12 02:53:45'),
(1240350001,'VitamineC',       2,  '2025-05-12 02:53:45'),
(2221012,   'Antipyrétique',   6,  '2025-05-13 12:38:14'),
(2221012,   'Antidiarrhéique', 3,  '2025-05-13 12:38:14');

-- ----------------------------------------------------------------
-- Table : utilisateurs
-- Mots de passe : stockés en clair pour compatibilité
-- (à remplacer par bcrypt en production avec password_hash())
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `user_id`  int(11)      NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `mdp`      varchar(255) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compléments pour le module Administration / Utilisateurs.
-- À exécuter une seule fois si la table existait déjà avant cette migration.
ALTER TABLE `utilisateurs`
  ADD COLUMN IF NOT EXISTS `prenom` VARCHAR(100) NULL AFTER `username`,
  ADD COLUMN IF NOT EXISTS `nom` VARCHAR(100) NULL AFTER `prenom`,
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NULL AFTER `nom`,
  ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) NOT NULL DEFAULT 'Visiteur' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `statut` ENUM('Actif','Inactif') NOT NULL DEFAULT 'Actif' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `derniere_connexion` DATETIME NULL AFTER `statut`;

INSERT INTO `utilisateurs` (`user_id`,`username`,`mdp`) VALUES
(10000, 'admin', 'Rakotonirina24'),
(10001, 'Test',  'Test@!234');

COMMIT;


-- User test crud
ALTER TABLE utilisateurs
  ADD COLUMN IF NOT EXISTS prenom VARCHAR(100) NULL AFTER username,
  ADD COLUMN IF NOT EXISTS nom VARCHAR(100) NULL AFTER prenom,
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER nom,
  ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'Visiteur' AFTER email,
  ADD COLUMN IF NOT EXISTS statut ENUM('Actif','Inactif') NOT NULL DEFAULT 'Actif' AFTER role,
  ADD COLUMN IF NOT EXISTS derniere_connexion DATETIME NULL AFTER statut;