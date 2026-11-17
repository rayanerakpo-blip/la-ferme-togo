-- =====================================================================
-- La Ferme — Schéma de base de données (MySQL / MariaDB)
-- Exécuté par install.php (la base est créée par l'installateur).
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS recommandations;
DROP TABLE IF EXISTS irrigations;
DROP TABLE IF EXISTS recoltes;
DROP TABLE IF EXISTS cultures;
DROP TABLE IF EXISTS taches;
DROP TABLE IF EXISTS transformations;
DROP TABLE IF EXISTS stocks;
DROP TABLE IF EXISTS transactions_financieres;
DROP TABLE IF EXISTS parcelles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Utilisateurs ---------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(120) NOT NULL,
    email         VARCHAR(160) NOT NULL UNIQUE,
    mot_de_passe  VARCHAR(255) NOT NULL,
    role          ENUM('proprietaire','collaborateur') NOT NULL DEFAULT 'collaborateur',
    langue        ENUM('fr','en') NOT NULL DEFAULT 'fr',
    actif         TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcelles ------------------------------------------------------------
CREATE TABLE parcelles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(120) NOT NULL,
    zone        VARCHAR(120) DEFAULT NULL,
    superficie  DECIMAL(6,2) NOT NULL DEFAULT 0,
    statut      ENUM('libre','en_culture','repos') NOT NULL DEFAULT 'libre',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cultures (semis / cycle de vie) --------------------------------------
CREATE TABLE cultures (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    parcelle_id         INT NOT NULL,
    nom                 VARCHAR(120) NOT NULL,
    variete             VARCHAR(120) DEFAULT NULL,
    date_semis          DATE NOT NULL,
    stade               ENUM('semis','levee','croissance','floraison','maturation','recolte') NOT NULL DEFAULT 'semis',
    date_recolte_prevue DATE DEFAULT NULL,
    quantite_semee      DECIMAL(10,2) DEFAULT NULL,
    unite               VARCHAR(20) DEFAULT 'kg',
    user_id             INT DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_culture_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelles(id) ON DELETE CASCADE,
    CONSTRAINT fk_culture_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Récoltes -------------------------------------------------------------
CREATE TABLE recoltes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    culture_id    INT NOT NULL,
    parcelle_id   INT NOT NULL,
    date_recolte  DATE NOT NULL,
    quantite      DECIMAL(10,2) NOT NULL,
    unite         VARCHAR(20) DEFAULT 'kg',
    qualite       ENUM('bonne','moyenne','faible') NOT NULL DEFAULT 'bonne',
    user_id       INT DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recolte_culture  FOREIGN KEY (culture_id)  REFERENCES cultures(id)  ON DELETE CASCADE,
    CONSTRAINT fk_recolte_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelles(id) ON DELETE CASCADE,
    CONSTRAINT fk_recolte_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Irrigations ----------------------------------------------------------
CREATE TABLE irrigations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    parcelle_id      INT NOT NULL,
    date_irrigation  DATE NOT NULL,
    volume_litres    DECIMAL(10,2) DEFAULT NULL,
    notes            TEXT DEFAULT NULL,
    user_id          INT DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_irrig_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelles(id) ON DELETE CASCADE,
    CONSTRAINT fk_irrig_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tâches / personnel ---------------------------------------------------
CREATE TABLE taches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    titre         VARCHAR(180) NOT NULL,
    description   TEXT DEFAULT NULL,
    user_id       INT DEFAULT NULL,
    parcelle_id   INT DEFAULT NULL,
    date_echeance DATE DEFAULT NULL,
    priorite      ENUM('basse','moyenne','haute') NOT NULL DEFAULT 'moyenne',
    statut        ENUM('a_faire','en_cours','termine') NOT NULL DEFAULT 'a_faire',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tache_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL,
    CONSTRAINT fk_tache_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stocks ---------------------------------------------------------------
CREATE TABLE stocks (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    produit       VARCHAR(150) NOT NULL,
    categorie     ENUM('intrant','recolte','transforme') NOT NULL DEFAULT 'intrant',
    quantite      DECIMAL(12,2) NOT NULL DEFAULT 0,
    unite         VARCHAR(20) DEFAULT 'kg',
    emplacement   VARCHAR(120) DEFAULT NULL,
    seuil_alerte  DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transformations du manioc -------------------------------------------
CREATE TABLE transformations (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    date_transformation DATE NOT NULL,
    produit            ENUM('gari','tapioca','attieke') NOT NULL,
    quantite_manioc    DECIMAL(10,2) NOT NULL,
    quantite_produite  DECIMAL(10,2) NOT NULL,
    unite              VARCHAR(20) DEFAULT 'kg',
    user_id            INT DEFAULT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transfo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions financières --------------------------------------------
CREATE TABLE transactions_financieres (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    type        ENUM('achat','vente') NOT NULL,
    activite    ENUM('soja','manioc','maraichage','elevage','autre') NOT NULL DEFAULT 'autre',
    libelle     VARCHAR(180) NOT NULL,
    montant     DECIMAL(14,2) NOT NULL DEFAULT 0,
    quantite    DECIMAL(10,2) DEFAULT NULL,
    unite       VARCHAR(20) DEFAULT NULL,
    date_op     DATE NOT NULL,
    user_id     INT DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommandations IA ---------------------------------------------------
CREATE TABLE recommandations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    parcelle_id  INT NOT NULL,
    type         ENUM('plantation','irrigation') NOT NULL,
    contenu      TEXT NOT NULL,
    priorite     ENUM('basse','moyenne','haute') NOT NULL DEFAULT 'moyenne',
    statut       ENUM('nouvelle','vue','appliquee') NOT NULL DEFAULT 'nouvelle',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reco_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de démonstration (parcelles, cultures, stocks, finances) -----
INSERT INTO parcelles (nom, zone, superficie, statut) VALUES
('Parcelle A1', 'Site principal', 1.00, 'en_culture'),
('Parcelle A2', 'Site principal', 0.50, 'en_culture'),
('Parcelle B1', 'Site d''Amdane', 1.00, 'en_culture'),
('Parcelle S1', 'Serres', 0.50, 'libre');

INSERT INTO stocks (produit, categorie, quantite, unite, emplacement, seuil_alerte) VALUES
('Engrais NPK', 'intrant', 120.00, 'kg', 'Entrepôt', 50.00),
('Semences de soja', 'intrant', 30.00, 'kg', 'Entrepôt', 20.00),
('Gari', 'transforme', 45.00, 'kg', 'Entrepôt', 25.00);
