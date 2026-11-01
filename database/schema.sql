-- ================================================================
--  PHARMACO / DVSSER — Schéma MySQL 8 / MariaDB 10.5+  (utf8mb4)
--  À exécuter UNE fois sur la base MySQL cloud.
--  Aucun mot de passe en clair ici : créez le premier Super Admin
--  avec  php database/create_admin.php  (voir README).
-- ================================================================
SET NAMES utf8mb4;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS `client` (
  `Id`                  bigint       NOT NULL,
  `Nom`                 varchar(50)  NOT NULL,
  `Prenom`              varchar(50)  NOT NULL,
  `Age`                 int          DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `medicament` (
  `id`        int         NOT NULL AUTO_INCREMENT,
  `id_client` bigint      NOT NULL,
  `Type`      varchar(50) NOT NULL,
  `Nombres`   int         NOT NULL DEFAULT 0,
  `Date`      datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`id_client`),
  KEY `idx_type`   (`Type`),
  KEY `idx_date`   (`Date`),
  CONSTRAINT `fk_medicament_client` FOREIGN KEY (`id_client`) REFERENCES `client`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table lue/écrite par users.php et utilisateur.php (absente de l'ancien SQL : bug corrigé)
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`           int          NOT NULL AUTO_INCREMENT,
  `username`          varchar(100) NOT NULL,
  `prenom`            varchar(100) NOT NULL DEFAULT '',
  `nom`               varchar(100) NOT NULL DEFAULT '',
  `email`             varchar(190) NOT NULL,
  `role`              varchar(30)  NOT NULL DEFAULT 'Visiteur',
  `mdp`               varchar(255) NOT NULL,            -- hash bcrypt/argon2 (password_hash)
  `statut`            enum('Actif','Inactif') NOT NULL DEFAULT 'Actif',
  `created_by`        int          DEFAULT NULL,
  `created_at`        timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` datetime    DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions PHP (Vercel est sans état : pas de disque partagé)
CREATE TABLE IF NOT EXISTS `app_sessions` (
  `id`         varchar(128)    NOT NULL,
  `data`       mediumblob      NOT NULL,
  `expires_at` int unsigned    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anti brute-force de la connexion
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`       int          NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `ip`       varchar(45)  NOT NULL,
  `ts`       int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_ts` (`username`, `ts`),
  KEY `idx_ip_ts`   (`ip`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
