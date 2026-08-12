-- Référentiel Madagascar : exécuter après Migrations/pharmacie_corrige.sql
SET NAMES utf8mb4;

INSERT INTO region (id, nom) VALUES
(1,'Analamanga'),(2,'Vakinankaratra'),(3,'Itasy'),(4,'Bongolava'),(5,'Diana'),(6,'Sava'),
(7,'Alaotra Mangoro'),(8,'Atsinanana'),(9,'Boeny'),(10,'Sofia'),(11,'Menabe'),(12,'Atsimo Andrefana'),
(13,'Androy'),(14,'Anosy'),(15,'Haute Matsiatra'),(16,'Amoron''i Mania'),(17,'Vatovavy'),(18,'Fitovinany'),
(19,'Atsimo Atsinanana'),(20,'Ihorombe'),(21,'Melaky'),(22,'Betsiboka')
ON DUPLICATE KEY UPDATE nom=VALUES(nom);

-- Utilisateurs de démonstration pour tester le module Administration.
-- Mot de passe de tous ces comptes : Test@12345
INSERT INTO utilisateurs (username, prenom, nom, email, role, statut, mdp) VALUES
('analyste.demo','Aina','Rasoanaivo','analyste.demo@example.mg','Analyste','Actif','$2y$10$2f6xp8srGbBNJsujmrpw9.KDeBJYo2myJi7R3z9OF10d80p3B16Ve'),
('visiteur.demo','Mamy','Rakoto','visiteur.demo@example.mg','Visiteur','Actif','$2y$10$2f6xp8srGbBNJsujmrpw9.KDeBJYo2myJi7R3z9OF10d80p3B16Ve'),
('saisie.demo','Sarah','Randria','saisie.demo@example.mg','Saisie','Inactif','$2y$10$2f6xp8srGbBNJsujmrpw9.KDeBJYo2myJi7R3z9OF10d80p3B16Ve')
ON DUPLICATE KEY UPDATE role=VALUES(role), statut=VALUES(statut), email=VALUES(email);

INSERT INTO district (region_id, nom) VALUES
(1,'Ambohidratrimo'),(1,'Andramasina'),(1,'Anjozorobe'),(1,'Ankazobe'),(1,'Antananarivo Atsimondrano'),(1,'Antananarivo Avaradrano'),(1,'Antananarivo Renivohitra'),(1,'Manjakandriana'),
(2,'Ambatolampy'),(2,'Antanifotsy'),(2,'Antsirabe I'),(2,'Antsirabe II'),(2,'Betafo'),(2,'Faratsiho'),(2,'Mandoto'),
(3,'Arivonimamo'),(3,'Miarinarivo'),(3,'Soavinandriana'),(4,'Fenoarivobe'),(4,'Tsiroanomandidy'),
(5,'Ambanja'),(5,'Ambilobe'),(5,'Antsiranana I'),(5,'Antsiranana II'),(5,'Nosy Be'),(6,'Andapa'),(6,'Antalaha'),(6,'Sambava'),(6,'Vohemar'),
(7,'Ambatondrazaka'),(7,'Amparafaravola'),(7,'Andilamena'),(7,'Anosibe An''ala'),(7,'Moramanga'),
(8,'Antanambao Manampotsy'),(8,'Brickaville'),(8,'Mahanoro'),(8,'Marolambo'),(8,'Toamasina I'),(8,'Toamasina II'),(8,'Vatomandry'),
(9,'Ambato Boeny'),(9,'Mahajanga I'),(9,'Mahajanga II'),(9,'Marovoay'),(9,'Mitsinjo'),(9,'Soalala'),
(10,'Analalava'),(10,'Antsohihy'),(10,'Bealanana'),(10,'Befandriana Nord'),(10,'Mampikony'),(10,'Mandritsara'),(10,'Port-Bergé'),
(11,'Belo sur Tsiribihina'),(11,'Mahabo'),(11,'Manja'),(11,'Miandrivazo'),(11,'Morondava'),
(12,'Ampanihy'),(12,'Ankazoabo'),(12,'Benenitra'),(12,'Beroroha'),(12,'Betioky'),(12,'Morombe'),(12,'Sakaraha'),(12,'Toliara I'),(12,'Toliara II'),
(13,'Ambovombe'),(13,'Bekily'),(13,'Beloha'),(13,'Tsihombe'),(14,'Amboasary Atsimo'),(14,'Betroka'),(14,'Taolagnaro'),
(15,'Ambalavao'),(15,'Ambohimahasoa'),(15,'Fianarantsoa I'),(15,'Ikalamavony'),(15,'Isandra'),(15,'Lalangina'),(15,'Vohibato'),
(16,'Ambatofinandrahana'),(16,'Ambositra'),(16,'Fandriana'),(16,'Manandriana'),(17,'Ifanadiana'),(17,'Mananjary'),(17,'Nosy Varika'),
(18,'Ikongo'),(18,'Manakara'),(18,'Vohipeno'),(19,'Befotaka'),(19,'Farafangana'),(19,'Midongy Atsimo'),(19,'Vangaindrano'),(19,'Vondrozo'),
(20,'Iakora'),(20,'Ihosy'),(20,'Ivohibe'),(21,'Ambatomainty'),(21,'Antsalova'),(21,'Besalampy'),(21,'Maintirano'),(21,'Morafenobe'),
(22,'Kandreho'),(22,'Maevatanana'),(22,'Tsaratanana')
ON DUPLICATE KEY UPDATE nom=VALUES(nom);
