-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Nov 12, 2026 at 06:53 PM
-- Server version: 13.0.2-MariaDB-ubu2604
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `web-sp`
--

-- --------------------------------------------------------

--
-- Table structure for table `foods`
--

CREATE TABLE `foods` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(80) NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `foods`
--

INSERT INTO `foods` (`id`, `name`, `description`, `category`, `price`, `image_url`, `created_at`) VALUES
(1, 'Pistachio Pesto Farfalle', '<p><strong>Creamy</strong> pistachio sauce with farfalle pasta, <em>soft edges with al dente core</em></p>', 'Pasta', 14.90, '?image=5f6e413135284e3991a433e62fb394be.jpg', '2026-11-09 22:33:42'),
(2, 'Deluxe Beef Cheesburger', '<p><strong><em>Juicy </em></strong>beef patty, with Irish cheddar and <em>delicious </em><strong><u>House</u><em> sauce</em></strong></p>', 'Burgers', 19.90, '?image=4432655173a2ce8a137513e50a3a0869.jpg', '2026-11-09 22:33:42'),
(3, 'Fresh Summer Salad', '<h1>Nom </h1><h2>Nom</h2><h3>Nom</h3><ol><li>Olive</li><li>Salad</li><li>Everything good</li></ol>', 'Salads', 10.90, '?image=05f2efe976b25ae3ad3940ad2a8f5b63.jpg', '2026-11-09 22:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `score` tinyint(3) UNSIGNED NOT NULL,
  `comment` varchar(500) NOT NULL DEFAULT '',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `food_id`, `user_id`, `score`, `comment`, `created_at`) VALUES
(4, 1, 7, 5, 'Soooooo creamy and perfect', '2026-11-12 18:46:14'),
(5, 2, 7, 1, 'IT HAS MEAT!!! animal killers', '2026-11-12 18:47:48'),
(6, 3, 7, 5, 'Me likey salat nom nom', '2026-11-12 18:48:15'),
(7, 1, 6, 4, 'Food tasted really good. Just wish it would be served warmer', '2026-11-12 18:49:32'),
(8, 2, 6, 3, 'Taste was ok, but why is it sooooo expensive?!', '2026-11-12 18:51:29'),
(10, 3, 6, 2, '', '2026-11-12 18:51:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('consumer','manager','admin','superadmin') NOT NULL DEFAULT 'consumer',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Super Admin', 'superadmin@flavourtown.test', '$2y$12$.4I8Tkv9U1YQL9dkfJBgteKQHo/db6V6vG7GFGI5mIC2o49OOEXe2', 'superadmin', '2026-11-09 22:33:41'),
(2, 'Restaurant Admin', 'admin@flavourtown.test', '$2y$12$9fcz5WWBnGeU82Qf1mG0eeZmeG.Ky3CxgxqPSFbbQSFLbKQzzDWzK', 'admin', '2026-11-09 22:33:41'),
(3, 'Kitchen Manager', 'manager@flavourtown.test', '$2y$12$SUqMp8958s5mC0iSMkOdfeifeMThhFkb5C89JF2g5VMEsFlYy7cLa', 'manager', '2026-11-09 22:33:42'),
(6, 'Consumer 2', 'consumer2@flavourtown.test', '$2y$12$UC3Zs0LElNUPtz/nISqQGO0JFChTi0aN0W6Em4pM7ps15/9rOTsrS', 'consumer', '2026-11-10 16:48:53'),
(7, 'Consumer', 'consumer@flavourtown.test', '$2y$12$UFNHyLbsV7Cs/UuN9cN/mu4rJF50IlpjQVQAjJ2tuBMTP2Ggg0Ff2', 'consumer', '2026-11-10 16:49:12'),
(8, 'Admin 2', 'admin2@flavourtown.test', '$2y$12$zPs7Vx8JLHOLveRgnYUe/udvkcjCrm3/xNHNVJJjUWu8uN2mLGBAq', 'admin', '2026-11-10 20:50:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `foods`
--
ALTER TABLE `foods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_rating_per_consumer` (`food_id`,`user_id`),
  ADD KEY `ratings_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `foods`
--
ALTER TABLE `foods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
