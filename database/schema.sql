-- ================================================================
--  PHARMACO / DVSSER — Schéma PostgreSQL (Neon)
--  Exécuter une fois. Idempotent (CREATE ... IF NOT EXISTS).
--  Aucun mot de passe ici : créer le Super Admin avec database/create_admin.php
-- ================================================================

-- Patients / clients saisis dans formulaire.php (action.php) — lus par index, analyse, visualisations, tdc, data.php
CREATE TABLE IF NOT EXISTS client (
  id                    bigint       PRIMARY KEY CHECK (id > 0),
  nom                   varchar(50)  NOT NULL,
  prenom                varchar(50)  NOT NULL,
  age                   integer      CHECK (age IS NULL OR age BETWEEN 0 AND 130),
  genre                 varchar(20)  NOT NULL DEFAULT '',
  type                  varchar(30),                      -- Ambulatoire | Hospitalisé
  residence             varchar(100),
  district              varchar(250) NOT NULL DEFAULT '', -- clé de jointure carte/analyse épidémique
  date_de_sousmission   timestamp    NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_client_type     ON client (type);
CREATE INDEX IF NOT EXISTS idx_client_district ON client (district);
CREATE INDEX IF NOT EXISTS idx_client_date     ON client (date_de_sousmission);

-- Achats de médicaments par client (medicament.php → traitement-medoc.php) — base du score de risque (medoc-risque.php)
CREATE TABLE IF NOT EXISTS medicament (
  id         integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  id_client  bigint      NOT NULL REFERENCES client(id) ON DELETE CASCADE,
  type       varchar(50) NOT NULL
             CHECK (type IN ('Antibiotique','Antipyrétique','Antalgique','Antidiarrhéique','VitamineC','Antihistaminique')),
  nombres    integer     NOT NULL DEFAULT 0 CHECK (nombres >= 0),
  date       timestamp   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_medicament_client ON medicament (id_client);
CREATE INDEX IF NOT EXISTS idx_medicament_type   ON medicament (type);
CREATE INDEX IF NOT EXISTS idx_medicament_date   ON medicament (date);

-- Comptes (Login.php → utilisateur.php, gestion dans users.php)
CREATE TABLE IF NOT EXISTS users (
  user_id             integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username            varchar(100) NOT NULL UNIQUE,
  prenom              varchar(100) NOT NULL DEFAULT '',
  nom                 varchar(100) NOT NULL DEFAULT '',
  email               varchar(190) NOT NULL UNIQUE,
  role                varchar(30)  NOT NULL DEFAULT 'Visiteur'
                      CHECK (role IN ('Super Admin','Saisie','Visiteur','Visualiseur','Analyste')),
  mdp                 varchar(255) NOT NULL,                 -- hash password_hash()
  statut              varchar(10)  NOT NULL DEFAULT 'Actif' CHECK (statut IN ('Actif','Inactif')),
  created_by          integer REFERENCES users(user_id) ON DELETE SET NULL,
  created_at          timestamp NOT NULL DEFAULT now(),
  derniere_connexion  timestamp
);

-- Sessions PHP (Vercel est sans état)
CREATE TABLE IF NOT EXISTS app_sessions (
  id          varchar(128) PRIMARY KEY,
  data        text    NOT NULL,
  expires_at  integer NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_exp ON app_sessions (expires_at);

-- Anti brute-force de la connexion
CREATE TABLE IF NOT EXISTS login_attempts (
  id        integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username  varchar(255) NOT NULL,
  ip        varchar(45)  NOT NULL,
  ts        integer      NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts_user ON login_attempts (username, ts);
CREATE INDEX IF NOT EXISTS idx_attempts_ip   ON login_attempts (ip, ts);
