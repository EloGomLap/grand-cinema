-- ============================================================
-- Grand Cinéma - Script de création de la base de données
-- SGBD : PostgreSQL 16
-- =========================================================

DROP TABLE IF EXISTS billet, reservation, seance, place, salle, film, utilisateur CASCADE;

-- ---------- Utilisateurs (clients et personnel) ----------
CREATE TABLE utilisateur (
    id              SERIAL PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    email           VARCHAR(180) NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,          -- hash (bcrypt/argon2), jamais en clair
    role            VARCHAR(20)  NOT NULL DEFAULT 'CLIENT'
                    CHECK (role IN ('CLIENT', 'GUICHETIER', 'CONTROLEUR', 'PROGRAMMATEUR', 'ADMIN')),
    date_creation   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Films ----------
CREATE TABLE film (
    id              SERIAL PRIMARY KEY,
    titre           VARCHAR(200) NOT NULL,
    synopsis        TEXT,
    duree_minutes   INT          NOT NULL CHECK (duree_minutes > 0),
    realisateur     VARCHAR(150),
    genre           VARCHAR(50),
    classification  VARCHAR(20),                     -- Tous publics, -12, -16...
    affiche_url     VARCHAR(255),
    date_sortie     DATE
);

-- ---------- Salles ----------
CREATE TABLE salle (
    id              SERIAL PRIMARY KEY,
    nom             VARCHAR(50)  NOT NULL UNIQUE,
    capacite        INT          NOT NULL CHECK (capacite > 0)
);

-- ---------- Places (plan de chaque salle) ----------
CREATE TABLE place (
    id              SERIAL PRIMARY KEY,
    salle_id        INT          NOT NULL REFERENCES salle(id) ON DELETE CASCADE,
    rang            VARCHAR(2)   NOT NULL,           -- A, B, C...
    numero          INT          NOT NULL,
    type_place      VARCHAR(20)  NOT NULL DEFAULT 'STANDARD'
                    CHECK (type_place IN ('STANDARD', 'PMR', 'PREMIUM')),
    UNIQUE (salle_id, rang, numero)
);

-- ---------- Séances ----------
CREATE TABLE seance (
    id              SERIAL PRIMARY KEY,
    film_id         INT          NOT NULL REFERENCES film(id),
    salle_id        INT          NOT NULL REFERENCES salle(id),
    date_heure_debut TIMESTAMP   NOT NULL,
    langue          VARCHAR(10)  NOT NULL DEFAULT 'VF',  -- VF, VOSTFR
    prix            NUMERIC(6,2) NOT NULL CHECK (prix >= 0),
    UNIQUE (salle_id, date_heure_debut)
);

-- ---------- Réservations (une commande d'un client) ----------
CREATE TABLE reservation (
    id              SERIAL PRIMARY KEY,
    utilisateur_id  INT          REFERENCES utilisateur(id),   -- NULL possible pour une vente au guichet sans compte
    seance_id       INT          NOT NULL REFERENCES seance(id),
    canal           VARCHAR(10)  NOT NULL CHECK (canal IN ('EN_LIGNE', 'GUICHET')),
    statut          VARCHAR(15)  NOT NULL DEFAULT 'EN_ATTENTE'
                    CHECK (statut IN ('EN_ATTENTE', 'PAYEE', 'ANNULEE')),
    montant_total   NUMERIC(8,2) NOT NULL DEFAULT 0,
    date_reservation TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Billets (une place réservée pour une séance) ----------
CREATE TABLE billet (
    id              SERIAL PRIMARY KEY,
    reservation_id  INT          NOT NULL REFERENCES reservation(id) ON DELETE CASCADE,
    seance_id       INT          NOT NULL REFERENCES seance(id),
    place_id        INT          NOT NULL REFERENCES place(id),
    code_qr         VARCHAR(64)  NOT NULL UNIQUE,
    scanne          BOOLEAN      NOT NULL DEFAULT FALSE,
    -- Empêche de vendre deux fois la même place pour la même séance
    CONSTRAINT uq_billet_seance_place UNIQUE (seance_id, place_id)
);

CREATE INDEX idx_seance_date ON seance(date_heure_debut);
CREATE INDEX idx_reservation_utilisateur ON reservation(utilisateur_id);

-- ---------- Jeu de données minimal ----------
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES
 ('Admin', 'Cinema', 'admin@grandcinema.fr', '$2y$13$hashAremplacer', 'ADMIN'),
 ('Durand', 'Lea', 'client@grandcinema.fr', '$2y$13$hashAremplacer', 'CLIENT');

INSERT INTO salle (nom, capacite) VALUES ('Salle 1', 120), ('Salle 2', 80);

INSERT INTO film (titre, duree_minutes, genre, classification) VALUES
 ('Round Midnight', 133, 'Drame musical', 'Tous publics');

INSERT INTO seance (film_id, salle_id, date_heure_debut, langue, prix) VALUES
 (1, 1, '2026-12-01 20:30', 'VOSTFR', 9.50);
