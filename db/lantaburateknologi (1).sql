-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Apr 2026 pada 01.15
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lantaburateknologi`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `device_name` varchar(100) NOT NULL DEFAULT 'Device',
  `device_type` enum('PZEM-004T','OTHER') DEFAULT 'PZEM-004T',
  `user_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `power_threshold` decimal(8,2) DEFAULT 10.00,
  `tarif_per_kwh` decimal(10,2) DEFAULT 1500.00,
  `last_seen` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `api_key` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `devices`
--

INSERT INTO `devices` (`id`, `device_id`, `device_name`, `device_type`, `user_id`, `location`, `description`, `power_threshold`, `tarif_per_kwh`, `last_seen`, `is_online`, `is_active`, `api_key`, `created_at`, `updated_at`) VALUES
(6, '14:2B:2F:DB:12:EC', 'Charger HP', 'PZEM-004T', 4, 'Kamar', '', 10.00, 1500.00, '2026-04-19 16:37:44', 1, 1, 'f601246f3870c064b92eb4febb09d5ae527ee637557d965aa09b362591697e38', '2026-04-19 10:51:03', '2026-04-19 16:37:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `device_registration_codes`
--

CREATE TABLE `device_registration_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_code` varchar(20) NOT NULL,
  `device_name_temp` varchar(255) DEFAULT NULL,
  `device_id` varchar(50) DEFAULT NULL,
  `device_name` varchar(100) DEFAULT 'New Device',
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `energy_data`
--

CREATE TABLE `energy_data` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `power` decimal(10,3) NOT NULL,
  `voltage` decimal(10,3) NOT NULL,
  `current` decimal(10,3) NOT NULL,
  `energy` decimal(10,3) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `energy_data`
--

INSERT INTO `energy_data` (`id`, `device_id`, `power`, `voltage`, `current`, `energy`, `created_at`) VALUES
(21, 6, 48.000, 224.000, 0.214, 0.050, '2026-04-19 11:06:51'),
(22, 6, 43.000, 228.000, 0.189, 0.050, '2026-04-19 11:10:07'),
(23, 6, 92.000, 225.000, 0.409, 0.050, '2026-04-19 11:10:09'),
(24, 6, 48.000, 222.000, 0.216, 0.050, '2026-04-19 11:17:34'),
(25, 6, 59.000, 221.000, 0.267, 0.050, '2026-04-19 11:17:37'),
(26, 6, 40.000, 226.000, 0.177, 0.050, '2026-04-19 11:25:07'),
(27, 6, 96.000, 226.000, 0.425, 0.050, '2026-04-19 11:34:38'),
(28, 6, 96.000, 223.000, 0.430, 0.050, '2026-04-19 15:59:14'),
(29, 6, 76.000, 226.000, 0.336, 0.050, '2026-04-19 15:59:17'),
(30, 6, 77.000, 223.000, 0.345, 0.050, '2026-04-19 15:59:41'),
(31, 6, 14.000, 221.000, 0.063, 0.050, '2026-04-19 15:59:44'),
(32, 6, 36.000, 228.000, 0.158, 0.050, '2026-04-19 15:59:50'),
(33, 6, 48.000, 224.000, 0.214, 0.050, '2026-04-19 15:59:50'),
(34, 6, 88.000, 220.000, 0.400, 0.050, '2026-04-19 16:00:01'),
(35, 6, 53.000, 221.000, 0.240, 0.050, '2026-04-19 16:00:02'),
(36, 6, 14.000, 222.000, 0.063, 0.050, '2026-04-19 16:00:04'),
(37, 6, 10.000, 225.000, 0.044, 0.050, '2026-04-19 16:00:15'),
(38, 6, 12.000, 224.000, 0.054, 0.050, '2026-04-19 16:00:19'),
(39, 6, 92.000, 221.000, 0.416, 0.050, '2026-04-19 16:00:22'),
(40, 6, 34.000, 222.000, 0.153, 0.050, '2026-04-19 16:00:25'),
(41, 6, 72.000, 228.000, 0.316, 0.050, '2026-04-19 16:03:06'),
(42, 6, 59.000, 220.000, 0.268, 0.050, '2026-04-19 16:31:55'),
(43, 6, 92.000, 229.000, 0.402, 0.050, '2026-04-19 16:32:34'),
(44, 6, 70.000, 220.000, 0.318, 0.050, '2026-04-19 16:32:59'),
(45, 6, 73.000, 228.000, 0.320, 0.050, '2026-04-19 16:33:08'),
(46, 6, 45.000, 223.000, 0.202, 0.050, '2026-04-19 16:33:20'),
(47, 6, 12.000, 228.000, 0.053, 0.050, '2026-04-19 16:33:23'),
(48, 6, 73.000, 220.000, 0.332, 0.050, '2026-04-19 16:33:29'),
(49, 6, 18.000, 220.000, 0.082, 0.050, '2026-04-19 16:33:29'),
(50, 6, 64.000, 227.000, 0.282, 0.050, '2026-04-19 16:33:42'),
(51, 6, 78.000, 226.000, 0.345, 0.050, '2026-04-19 16:34:29'),
(52, 6, 37.000, 226.000, 0.164, 0.050, '2026-04-19 16:34:29'),
(53, 6, 36.000, 229.000, 0.157, 0.050, '2026-04-19 16:34:35'),
(54, 6, 44.000, 229.000, 0.192, 0.050, '2026-04-19 16:34:40'),
(55, 6, 35.000, 226.000, 0.155, 0.050, '2026-04-19 16:34:51'),
(56, 6, 58.000, 223.000, 0.260, 0.050, '2026-04-19 16:35:08'),
(57, 6, 51.000, 223.000, 0.229, 0.050, '2026-04-19 16:35:18'),
(58, 6, 76.000, 228.000, 0.333, 0.050, '2026-04-19 16:35:21'),
(59, 6, 37.000, 224.000, 0.165, 0.050, '2026-04-19 16:35:22'),
(60, 6, 18.000, 226.000, 0.080, 0.050, '2026-04-19 16:35:56'),
(61, 6, 71.000, 224.000, 0.317, 0.050, '2026-04-19 16:36:10'),
(62, 6, 93.000, 225.000, 0.413, 0.050, '2026-04-19 16:36:20'),
(63, 6, 60.000, 228.000, 0.263, 0.050, '2026-04-19 16:36:59'),
(64, 6, 60.000, 229.000, 0.262, 0.050, '2026-04-19 16:37:04'),
(65, 6, 31.000, 226.000, 0.137, 0.050, '2026-04-19 16:37:15'),
(66, 6, 57.000, 221.000, 0.258, 0.050, '2026-04-19 16:37:33'),
(67, 6, 95.000, 224.000, 0.424, 0.050, '2026-04-19 16:37:43'),
(68, 6, 58.000, 225.000, 0.258, 0.050, '2026-04-19 16:37:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `notification_type` enum('DEVICE_OFFLINE','HIGH_POWER','DAILY_REPORT') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `company`, `phone`, `is_active`, `created_at`, `last_login`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$YourHashHere', 'Administrator', NULL, NULL, 1, '2026-04-18 22:30:37', NULL),
(3, 'tes', 'tes@tes.com', '$2y$10$WTX2JnNRoIgBwj4XirnxjevmqDNFRyfgi89g27bYfD8gOLhgjo47i', 'tes', 'tes', '123', 1, '2026-04-18 22:57:03', NULL),
(4, 'prima', 'prima@tes.com', '$2y$10$NVj.FwoPxgtMu8F3cHUi9u0jS6EOHV3Lg5ocyzGsRah677iB9DXLK', 'prima', 'prima', '123', 1, '2026-04-18 23:02:14', '2026-04-19 10:59:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `is_active`) VALUES
(1, 4, '3e7bd70d94212a81f15dcbf4fd5601a3e9e16e40eab88613f59235c0cf78577b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 23:02:25', '2026-04-25 18:02:25', 0),
(2, 4, '93dd075d24f4fc071e9844d116b478b358b0b48173f1a058b088ee1b9baa9666', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 23:20:05', '2026-04-25 18:20:05', 0),
(3, 4, 'da412265a7805da5f68e3ac62c0617243ae8fb6303f1bcdf43818dcf4335f218', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 10:52:25', '2026-04-26 05:52:25', 0),
(4, 4, 'cf86587571213081d372c31d146d98638d8135817dea6bbad23f241325d9d264', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 10:59:08', '2026-04-26 05:59:08', 1);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `idx_device_id` (`device_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indeks untuk tabel `device_registration_codes`
--
ALTER TABLE `device_registration_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_code` (`registration_code`),
  ADD KEY `idx_registration_code` (`registration_code`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indeks untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_sent` (`is_sent`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Indeks untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `device_registration_codes`
--
ALTER TABLE `device_registration_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `device_registration_codes`
--
ALTER TABLE `device_registration_codes`
  ADD CONSTRAINT `device_registration_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  ADD CONSTRAINT `energy_data_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
