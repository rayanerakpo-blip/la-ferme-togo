-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Hôte : sql113.infinityfree.com
-- Généré le :  sam. 10 oct. 2026 à 03:09
-- Version du serveur :  11.4.13-MariaDB
-- Version de PHP :  7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `if0_42464685_la_ferme`
--

-- --------------------------------------------------------

--
-- Structure de la table `cultures`
--

CREATE TABLE `cultures` (
  `id` int(11) NOT NULL,
  `parcelle_id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `variete` varchar(120) DEFAULT NULL,
  `date_semis` date NOT NULL,
  `stade` enum('semis','levee','croissance','floraison','maturation','recolte') NOT NULL DEFAULT 'semis',
  `date_recolte_prevue` date DEFAULT NULL,
  `quantite_semee` decimal(10,2) DEFAULT NULL,
  `unite` varchar(20) DEFAULT 'kg',
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cultures`
--

INSERT INTO `cultures` (`id`, `parcelle_id`, `nom`, `variete`, `date_semis`, `stade`, `date_recolte_prevue`, `quantite_semee`, `unite`, `user_id`, `created_at`) VALUES
(1, 1, 'Maïs', 'Jaune', '2026-04-10', 'recolte', NULL, '25.00', 'kg', 1, '2026-10-09 19:13:01');

-- --------------------------------------------------------

--
-- Structure de la table `irrigations`
--

CREATE TABLE `irrigations` (
  `id` int(11) NOT NULL,
  `parcelle_id` int(11) NOT NULL,
  `date_irrigation` date NOT NULL,
  `volume_litres` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `irrigations`
--

INSERT INTO `irrigations` (`id`, `parcelle_id`, `date_irrigation`, `volume_litres`, `notes`, `user_id`, `created_at`) VALUES
(1, 1, '2026-10-09', '3000.00', 'Arrosage', 1, '2026-10-09 19:56:27');

-- --------------------------------------------------------

--
-- Structure de la table `parcelles`
--

CREATE TABLE `parcelles` (
  `id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `zone` varchar(120) DEFAULT NULL,
  `superficie` decimal(6,2) NOT NULL DEFAULT 0.00,
  `statut` enum('libre','en_culture','repos') NOT NULL DEFAULT 'libre',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `parcelles`
--

INSERT INTO `parcelles` (`id`, `nom`, `zone`, `superficie`, `statut`, `created_at`) VALUES
(1, 'Parcelle A1', 'Site principal', '1.00', 'libre', '2026-10-09 16:46:58'),
(2, 'Parcelle A2', 'Site principal', '0.50', 'en_culture', '2026-10-09 16:46:58'),
(3, 'Parcelle B1', 'Site d\'Amdane', '1.00', 'en_culture', '2026-10-09 16:46:58'),
(4, 'Parcelle S1', 'Serres', '0.50', 'libre', '2026-10-09 16:46:58');

-- --------------------------------------------------------

--
-- Structure de la table `recoltes`
--

CREATE TABLE `recoltes` (
  `id` int(11) NOT NULL,
  `culture_id` int(11) NOT NULL,
  `parcelle_id` int(11) NOT NULL,
  `date_recolte` date NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `unite` varchar(20) DEFAULT 'kg',
  `qualite` enum('bonne','moyenne','faible') NOT NULL DEFAULT 'bonne',
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recoltes`
--

INSERT INTO `recoltes` (`id`, `culture_id`, `parcelle_id`, `date_recolte`, `quantite`, `unite`, `qualite`, `user_id`, `created_at`) VALUES
(1, 1, 1, '2026-10-09', '120.00', 'kg', 'bonne', 1, '2026-10-09 20:11:53');

-- --------------------------------------------------------

--
-- Structure de la table `recommandations`
--

CREATE TABLE `recommandations` (
  `id` int(11) NOT NULL,
  `parcelle_id` int(11) NOT NULL,
  `type` enum('plantation','irrigation') NOT NULL,
  `contenu` text NOT NULL,
  `priorite` enum('basse','moyenne','haute') NOT NULL DEFAULT 'moyenne',
  `statut` enum('nouvelle','vue','appliquee') NOT NULL DEFAULT 'nouvelle',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recommandations`
--

INSERT INTO `recommandations` (`id`, `parcelle_id`, `type`, `contenu`, `priorite`, `statut`, `created_at`) VALUES
(1, 1, 'plantation', 'La parcelle « Parcelle A1 » (1.00 ha) est libre. Saison des pluies : c\'est une bonne période pour préparer le sol et lancer un nouveau semis (maïs, soja ou manioc selon votre rotation).', 'moyenne', 'nouvelle', '2026-10-09 14:53:36'),
(2, 1, 'irrigation', 'Saison des pluies : l\'apport hydrique naturel est généralement suffisant pour Parcelle A1. Surveillez les épisodes secs de plus de 5 jours.', 'basse', 'nouvelle', '2026-10-09 14:53:36'),
(3, 2, 'plantation', 'La parcelle « Parcelle A2 » (0.50 ha) est libre. Saison des pluies : c\'est une bonne période pour préparer le sol et lancer un nouveau semis (maïs, soja ou manioc selon votre rotation).', 'moyenne', 'nouvelle', '2026-10-09 14:53:36'),
(4, 2, 'irrigation', 'Saison des pluies : l\'apport hydrique naturel est généralement suffisant pour Parcelle A2. Surveillez les épisodes secs de plus de 5 jours.', 'basse', 'nouvelle', '2026-10-09 14:53:36'),
(5, 3, 'plantation', 'La parcelle « Parcelle B1 » (1.00 ha) est libre. Saison des pluies : c\'est une bonne période pour préparer le sol et lancer un nouveau semis (maïs, soja ou manioc selon votre rotation).', 'moyenne', 'nouvelle', '2026-10-09 14:53:36'),
(6, 3, 'irrigation', 'Saison des pluies : l\'apport hydrique naturel est généralement suffisant pour Parcelle B1. Surveillez les épisodes secs de plus de 5 jours.', 'basse', 'nouvelle', '2026-10-09 14:53:36'),
(7, 4, 'plantation', 'La parcelle « Parcelle S1 » (0.50 ha) est libre. Saison des pluies : c\'est une bonne période pour préparer le sol et lancer un nouveau semis (maïs, soja ou manioc selon votre rotation).', 'moyenne', 'nouvelle', '2026-10-09 14:53:36'),
(8, 4, 'irrigation', 'Saison des pluies : l\'apport hydrique naturel est généralement suffisant pour Parcelle S1. Surveillez les épisodes secs de plus de 5 jours.', 'basse', 'nouvelle', '2026-10-09 14:53:36');

-- --------------------------------------------------------

--
-- Structure de la table `stocks`
--

CREATE TABLE `stocks` (
  `id` int(11) NOT NULL,
  `produit` varchar(150) NOT NULL,
  `categorie` enum('intrant','recolte','transforme') NOT NULL DEFAULT 'intrant',
  `quantite` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unite` varchar(20) DEFAULT 'kg',
  `emplacement` varchar(120) DEFAULT NULL,
  `seuil_alerte` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `stocks`
--

INSERT INTO `stocks` (`id`, `produit`, `categorie`, `quantite`, `unite`, `emplacement`, `seuil_alerte`, `updated_at`) VALUES
(1, 'Engrais NPK', 'intrant', '120.00', 'kg', 'Entrepôt', '50.00', '2026-10-09 16:46:58'),
(2, 'Semences de soja', 'intrant', '30.00', 'kg', 'Entrepôt', '20.00', '2026-10-09 16:46:58'),
(3, 'Gari', 'transforme', '80.00', 'kg', 'Entrepôt', '25.00', '2026-10-09 20:14:45'),
(4, 'Maïs', 'recolte', '120.00', 'kg', 'Entrepôt', '0.00', '2026-10-09 20:11:53');

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

CREATE TABLE `taches` (
  `id` int(11) NOT NULL,
  `titre` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `parcelle_id` int(11) DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `priorite` enum('basse','moyenne','haute') NOT NULL DEFAULT 'moyenne',
  `statut` enum('a_faire','en_cours','termine') NOT NULL DEFAULT 'a_faire',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `taches`
--

INSERT INTO `taches` (`id`, `titre`, `description`, `user_id`, `parcelle_id`, `date_echeance`, `priorite`, `statut`, `created_at`) VALUES
(1, 'Sarclage parcelle A1', 'Retirer adventices', 2, 1, '2026-10-15', 'haute', 'a_faire', '2026-10-09 20:06:46');

-- --------------------------------------------------------

--
-- Structure de la table `transactions_financieres`
--

CREATE TABLE `transactions_financieres` (
  `id` int(11) NOT NULL,
  `type` enum('achat','vente') NOT NULL,
  `activite` enum('soja','manioc','maraichage','elevage','autre') NOT NULL DEFAULT 'autre',
  `libelle` varchar(180) NOT NULL,
  `montant` decimal(14,2) NOT NULL DEFAULT 0.00,
  `quantite` decimal(10,2) DEFAULT NULL,
  `unite` varchar(20) DEFAULT NULL,
  `date_op` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transformations`
--

CREATE TABLE `transformations` (
  `id` int(11) NOT NULL,
  `date_transformation` date NOT NULL,
  `produit` enum('gari','tapioca','attieke') NOT NULL,
  `quantite_manioc` decimal(10,2) NOT NULL,
  `quantite_produite` decimal(10,2) NOT NULL,
  `unite` varchar(20) DEFAULT 'kg',
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transformations`
--

INSERT INTO `transformations` (`id`, `date_transformation`, `produit`, `quantite_manioc`, `quantite_produite`, `unite`, `user_id`, `created_at`) VALUES
(1, '2026-10-09', 'gari', '100.00', '35.00', 'kg', 1, '2026-10-09 20:14:45');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('proprietaire','collaborateur') NOT NULL DEFAULT 'collaborateur',
  `langue` enum('fr','en') NOT NULL DEFAULT 'fr',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `mot_de_passe`, `role`, `langue`, `actif`, `created_at`) VALUES
(1, 'Propriétaire', 'proprietaire@laferme.tg', '$2y$10$CapdDCuttv/jThA/0ASKGO7K4xKQpwtBgEoemxJcAVae6ebFHXxQO', 'proprietaire', 'fr', 1, '2026-10-09 16:46:58'),
(2, 'Collaborateur', 'collaborateur@laferme.tg', '$2y$10$7bHA/y3QuHB6vvMbkhSr.es3mWZNvcZslUOCy70BWEK9iCs9y9YMG', 'collaborateur', 'fr', 1, '2026-10-09 16:46:58');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `cultures`
--
ALTER TABLE `cultures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_culture_parcelle` (`parcelle_id`),
  ADD KEY `fk_culture_user` (`user_id`);

--
-- Index pour la table `irrigations`
--
ALTER TABLE `irrigations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_irrig_parcelle` (`parcelle_id`),
  ADD KEY `fk_irrig_user` (`user_id`);

--
-- Index pour la table `parcelles`
--
ALTER TABLE `parcelles`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `recoltes`
--
ALTER TABLE `recoltes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_recolte_culture` (`culture_id`),
  ADD KEY `fk_recolte_parcelle` (`parcelle_id`),
  ADD KEY `fk_recolte_user` (`user_id`);

--
-- Index pour la table `recommandations`
--
ALTER TABLE `recommandations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reco_parcelle` (`parcelle_id`);

--
-- Index pour la table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tache_user` (`user_id`),
  ADD KEY `fk_tache_parcelle` (`parcelle_id`);

--
-- Index pour la table `transactions_financieres`
--
ALTER TABLE `transactions_financieres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tx_user` (`user_id`);

--
-- Index pour la table `transformations`
--
ALTER TABLE `transformations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transfo_user` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `cultures`
--
ALTER TABLE `cultures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `irrigations`
--
ALTER TABLE `irrigations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `parcelles`
--
ALTER TABLE `parcelles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `recoltes`
--
ALTER TABLE `recoltes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `recommandations`
--
ALTER TABLE `recommandations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `transactions_financieres`
--
ALTER TABLE `transactions_financieres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transformations`
--
ALTER TABLE `transformations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `cultures`
--
ALTER TABLE `cultures`
  ADD CONSTRAINT `fk_culture_parcelle` FOREIGN KEY (`parcelle_id`) REFERENCES `parcelles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_culture_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `irrigations`
--
ALTER TABLE `irrigations`
  ADD CONSTRAINT `fk_irrig_parcelle` FOREIGN KEY (`parcelle_id`) REFERENCES `parcelles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_irrig_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `recoltes`
--
ALTER TABLE `recoltes`
  ADD CONSTRAINT `fk_recolte_culture` FOREIGN KEY (`culture_id`) REFERENCES `cultures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recolte_parcelle` FOREIGN KEY (`parcelle_id`) REFERENCES `parcelles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recolte_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `recommandations`
--
ALTER TABLE `recommandations`
  ADD CONSTRAINT `fk_reco_parcelle` FOREIGN KEY (`parcelle_id`) REFERENCES `parcelles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `taches`
--
ALTER TABLE `taches`
  ADD CONSTRAINT `fk_tache_parcelle` FOREIGN KEY (`parcelle_id`) REFERENCES `parcelles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tache_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `transactions_financieres`
--
ALTER TABLE `transactions_financieres`
  ADD CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `transformations`
--
ALTER TABLE `transformations`
  ADD CONSTRAINT `fk_transfo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
