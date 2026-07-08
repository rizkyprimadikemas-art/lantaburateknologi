-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 08 Jul 2026 pada 06.14
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
-- Struktur dari tabel `ai_daily_insights`
--

CREATE TABLE `ai_daily_insights` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `generated_date` date NOT NULL,
  `insights_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`insights_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `severity` varchar(20) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `ai_insights` text DEFAULT NULL,
  `ai_recommendations` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `alerts`
--

INSERT INTO `alerts` (`id`, `user_id`, `device_id`, `type`, `message`, `severity`, `is_read`, `created_at`, `ai_insights`, `ai_recommendations`) VALUES
(1, 4, 11, 'leakage', 'BAHAYA! Arus bocor kritis terdeteksi pada perangkat \'Mesin Pengemas Feeder\' sebesar **1.279A**. Ini berisiko tinggi menyebabkan sengatan listrik atau kebakaran. **MATIKAN SUMBER DAYA UTAMA SEGERA** dan hubungi teknisi listrik!', 'critical', 0, '2026-07-03 15:11:33', NULL, NULL),
(2, 4, 11, 'relay_failure', 'GAGAL RELAY: Perangkat \'Mesin Pengemas Feeder\' terdeteksi masih mengonsumsi daya sebesar **288.51W** meskipun relay dalam posisi OFF (batas toleransi 20W). Ini menunjukkan relay tidak berfungsi dengan baik. Mohon periksa perangkat.', 'critical', 0, '2026-07-03 15:11:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `device_name` varchar(100) NOT NULL DEFAULT 'Device',
  `device_type` varchar(225) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `power_threshold` decimal(8,2) DEFAULT 10.00,
  `tarif_per_kwh` decimal(10,2) DEFAULT 1500.00,
  `last_seen` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `api_key` varchar(255) NOT NULL,
  `relay_state` enum('on','off') DEFAULT 'off',
  `auto_shutdown_standby` tinyint(1) DEFAULT 0,
  `standby_threshold_watt` decimal(10,2) DEFAULT 5.00,
  `standby_detection_duration_minutes` int(11) DEFAULT 10,
  `auto_shutdown_overload` tinyint(1) DEFAULT 0,
  `last_relay_command_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated_standby_power_w` decimal(10,2) DEFAULT 0.50,
  `efficiency_rating` varchar(50) DEFAULT 'Medium',
  `purchase_date` date DEFAULT NULL,
  `anomaly_threshold_percent` decimal(5,2) DEFAULT 30.00,
  `negative_anomaly_threshold_percent` decimal(5,2) DEFAULT -50.00,
  `spike_threshold_percent` decimal(5,1) DEFAULT 180.0 COMMENT 'Threshold for spike detection in percent of baseline'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `devices`
--

INSERT INTO `devices` (`id`, `device_id`, `device_name`, `device_type`, `user_id`, `location`, `description`, `power_threshold`, `tarif_per_kwh`, `last_seen`, `is_online`, `is_active`, `is_deleted`, `api_key`, `relay_state`, `auto_shutdown_standby`, `standby_threshold_watt`, `standby_detection_duration_minutes`, `auto_shutdown_overload`, `last_relay_command_at`, `created_at`, `updated_at`, `estimated_standby_power_w`, `efficiency_rating`, `purchase_date`, `anomaly_threshold_percent`, `negative_anomaly_threshold_percent`, `spike_threshold_percent`) VALUES
(10, '10:00:3B:D4:20:E4', 'Mesin Pengemas Liquid', 'Other', 4, 'Gedung Timur', 'Mesin pengemas liquid', 10.00, 1500.00, '2026-05-29 16:23:50', 1, 1, 0, '4718a8ac11f524eccbb20ab0249b71c0d34aeea2b6a403753577bb0c96b9ac1d', 'off', 0, 5.00, 10, 0, NULL, '2026-05-26 09:09:10', '2026-05-29 16:23:50', 0.50, 'Medium', NULL, 30.00, -50.00, 180.0),
(11, '1C:DB:D4:E2:BA:D4', 'Mesin Pengemas Feeder', 'Other', 4, 'Gedung Barat', 'Mesin Pengemas Feeder', 10.00, 1500.00, '2026-07-08 11:11:37', 1, 1, 0, '9d08466c92ab197a454dca887768ec55fd37d14371f36e33595b08652f07623a', 'off', 1, 5.00, 5, 1, '2026-07-08 10:53:34', '2026-05-26 13:46:02', '2026-07-08 11:11:37', 0.50, 'Medium', NULL, 30.00, -50.00, 180.0);

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
-- Struktur dari tabel `device_schedules`
--

CREATE TABLE `device_schedules` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_name` varchar(255) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `action` enum('on','off') NOT NULL,
  `repeat_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `device_schedules`
--

INSERT INTO `device_schedules` (`id`, `device_id`, `user_id`, `schedule_name`, `start_time`, `end_time`, `action`, `repeat_days`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 11, 4, 'Tes jadwal nyala', '14:50:00', '14:51:00', 'off', 16, 1, '2026-07-03 14:49:03', '2026-07-03 14:49:03'),
(4, 11, 4, 'Tes jadwal Mati', '14:51:00', '14:52:00', 'on', 16, 1, '2026-07-03 14:49:27', '2026-07-03 14:49:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `energy_data`
--

CREATE TABLE `energy_data` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `power` decimal(12,6) NOT NULL,
  `voltage` decimal(12,6) NOT NULL,
  `current` decimal(12,6) NOT NULL,
  `energy` decimal(12,6) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `machine_status` varchar(10) DEFAULT 'OFF' COMMENT 'Status mesin: ON/OFF/UNKNOWN',
  `device_status` varchar(20) DEFAULT 'offline' COMMENT 'Status perangkat: online/offline',
  `relay_state` varchar(10) DEFAULT 'off',
  `max_power` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `energy_data`
--

INSERT INTO `energy_data` (`id`, `device_id`, `power`, `voltage`, `current`, `energy`, `created_at`, `machine_status`, `device_status`, `relay_state`, `max_power`) VALUES
(2252, 11, 0.000000, 23.942350, 0.000000, 1.297045, '2026-07-01 10:33:29', 'OFF', 'online', 'off', 0),
(2253, 11, 0.000000, 5.805924, 0.000000, 1.297045, '2026-07-01 10:34:29', 'OFF', 'online', 'off', 0),
(2254, 11, 18.190610, 214.625000, 0.083416, 1.297342, '2026-07-01 10:35:29', 'ON', 'online', 'on', 39.0909),
(2255, 11, 22.042020, 218.095600, 0.101039, 1.297709, '2026-07-01 10:36:29', 'ON', 'online', 'on', 26.6611),
(2256, 11, 21.419930, 217.369800, 0.098545, 1.298066, '2026-07-01 10:37:29', 'ON', 'online', 'on', 26.0036),
(2257, 11, 19.713550, 218.660800, 0.090113, 1.298395, '2026-07-01 10:38:29', 'ON', 'online', 'on', 25.0125),
(2258, 11, 15.539950, 219.964600, 0.070324, 1.298647, '2026-07-01 10:39:29', 'ON', 'online', 'on', 24.3189),
(2259, 11, 21.163590, 218.749600, 0.096744, 1.299000, '2026-07-01 10:40:29', 'ON', 'online', 'on', 22.5737),
(2260, 11, 21.004450, 217.857800, 0.096405, 1.299350, '2026-07-01 10:41:29', 'ON', 'online', 'on', 22.8031),
(2261, 11, 20.958550, 219.615200, 0.095433, 1.299700, '2026-07-01 10:42:30', 'ON', 'online', 'on', 21.9296),
(2262, 11, 17.669690, 219.013700, 0.080536, 1.299995, '2026-07-01 10:43:30', 'OFF', 'online', 'on', 22.4478),
(2263, 11, 18.548240, 217.378100, 0.087308, 1.300308, '2026-07-03 14:36:25', 'ON', 'online', 'on', 39.2941),
(2264, 11, 64.027610, 217.733900, 0.302689, 1.301837, '2026-07-03 14:37:42', 'OFF', 'online', 'off', 352.21),
(2265, 11, 59.969650, 219.257800, 0.275025, 1.302982, '2026-07-03 14:38:50', 'ON', 'online', 'on', 186.133),
(2266, 11, 322.806800, 219.129700, 1.472853, 1.308618, '2026-07-03 14:39:52', 'ON', 'online', 'on', 353.866),
(2267, 11, 332.126800, 218.951900, 1.516799, 1.314281, '2026-07-03 14:40:53', 'ON', 'online', 'on', 356.164),
(2268, 11, 300.382900, 219.274200, 1.370125, 1.319709, '2026-07-03 14:41:59', 'ON', 'online', 'on', 352.534),
(2269, 11, 298.059800, 220.492200, 1.351703, 1.325011, '2026-07-03 14:43:06', 'ON', 'online', 'on', 355.658),
(2270, 11, 308.690500, 221.776000, 1.391602, 1.331788, '2026-07-03 14:44:21', 'ON', 'online', 'on', 352.805),
(2271, 11, 342.102300, 221.678000, 1.543233, 1.338187, '2026-07-03 14:45:28', 'ON', 'online', 'on', 357.279),
(2272, 11, 341.646100, 221.607700, 1.541657, 1.345286, '2026-07-03 14:46:43', 'ON', 'online', 'on', 359.844),
(2273, 11, 339.754400, 223.051200, 1.523271, 1.362981, '2026-07-03 14:49:50', 'ON', 'online', 'on', 357.286),
(2274, 11, 54.556860, 223.822200, 0.285389, 1.363890, '2026-07-03 14:51:12', 'ON', 'online', 'on', 349.933),
(2275, 11, 113.012600, 223.836900, 0.504806, 1.366297, '2026-07-03 14:52:25', 'ON', 'online', 'on', 354.583),
(2276, 11, 322.928600, 223.174700, 1.447967, 1.371788, '2026-07-03 14:53:29', 'ON', 'online', 'on', 351.361),
(2277, 11, 340.007200, 224.354400, 1.515454, 1.377747, '2026-07-03 14:54:29', 'ON', 'online', 'on', 358.615),
(2278, 11, 339.310300, 223.464600, 1.518396, 1.384020, '2026-07-03 14:55:36', 'ON', 'online', 'on', 356.177),
(2279, 11, 340.601600, 222.770700, 1.529006, 1.389918, '2026-07-03 14:56:38', 'ON', 'online', 'on', 355.352),
(2280, 11, 344.189300, 224.034200, 1.536391, 1.403656, '2026-07-03 14:59:01', 'ON', 'online', 'on', 361.187),
(2281, 11, 343.356500, 223.081100, 1.539150, 1.409660, '2026-07-03 15:00:05', 'ON', 'online', 'on', 359.792),
(2282, 11, 340.260900, 223.868900, 1.519931, 1.416183, '2026-07-03 15:01:13', 'ON', 'online', 'on', 359.45),
(2283, 11, 341.952000, 225.721600, 1.514935, 1.425542, '2026-07-03 15:02:52', 'ON', 'online', 'on', 359.311),
(2284, 11, 342.922100, 224.555800, 1.527181, 1.431696, '2026-07-03 15:03:56', 'ON', 'online', 'on', 357.066),
(2285, 11, 339.864200, 223.325700, 1.521819, 1.437635, '2026-07-03 15:04:59', 'ON', 'online', 'on', 359.696),
(2286, 11, 288.509200, 225.805500, 1.279250, 1.442857, '2026-07-03 15:06:04', 'OFF', 'online', 'off', 365.621),
(2287, 11, 0.000000, 221.553400, 0.000000, 1.442857, '2026-07-07 14:32:44', 'OFF', 'online', 'off', 0),
(2288, 11, 0.000000, 221.428100, 0.000000, 1.442857, '2026-07-07 14:33:44', 'OFF', 'online', 'off', 0),
(2289, 11, 0.000000, 222.464000, 0.000000, 1.442857, '2026-07-07 14:34:45', 'OFF', 'online', 'off', 0),
(2290, 11, 0.000000, 223.369000, 0.000000, 1.442857, '2026-07-07 14:35:45', 'OFF', 'online', 'off', 0),
(2291, 11, 0.000000, 221.112100, 0.000000, 1.442857, '2026-07-07 14:36:45', 'OFF', 'online', 'off', 0),
(2292, 11, 0.000000, 222.040400, 0.000000, 1.442857, '2026-07-07 14:37:44', 'OFF', 'online', 'off', 0),
(2293, 11, 14.138630, 221.438200, 0.064032, 1.443072, '2026-07-07 14:38:45', 'ON', 'online', 'on', 72.32),
(2294, 11, 0.000000, 211.639600, 0.000000, 1.443072, '2026-07-07 16:03:02', 'OFF', 'online', 'off', 0),
(2295, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:05:08', 'OFF', 'online', 'off', 0),
(2296, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:06:08', 'OFF', 'online', 'off', 0),
(2297, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:08:12', 'OFF', 'online', 'off', 0),
(2298, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:11:24', 'OFF', 'online', 'off', 0),
(2299, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:53:22', 'OFF', 'online', 'off', 0),
(2300, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:54:22', 'OFF', 'online', 'off', 0),
(2301, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:55:23', 'OFF', 'online', 'off', 0),
(2302, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:56:22', 'OFF', 'online', 'off', 0),
(2303, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:57:23', 'OFF', 'online', 'off', 0),
(2304, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:58:23', 'OFF', 'online', 'off', 0),
(2305, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 10:59:24', 'OFF', 'online', 'off', 0),
(2306, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:00:24', 'OFF', 'online', 'off', 0),
(2307, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:01:25', 'OFF', 'online', 'off', 0),
(2308, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:02:25', 'OFF', 'online', 'off', 0),
(2309, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:03:25', 'OFF', 'online', 'off', 0),
(2310, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:04:25', 'OFF', 'online', 'off', 0),
(2311, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:05:26', 'OFF', 'online', 'off', 0),
(2312, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:06:26', 'OFF', 'online', 'off', 0),
(2313, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:07:25', 'OFF', 'online', 'off', 0),
(2314, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:08:25', 'OFF', 'online', 'off', 0),
(2315, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:09:36', 'OFF', 'online', 'off', 0),
(2316, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:10:37', 'OFF', 'online', 'off', 0),
(2317, 11, 0.000000, 0.000000, 0.000000, 0.000000, '2026-07-08 11:11:37', 'OFF', 'online', 'off', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `energy_data_daily`
--

CREATE TABLE `energy_data_daily` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_energy_kwh` decimal(10,3) NOT NULL,
  `avg_power_w` decimal(10,3) NOT NULL,
  `min_power_w` decimal(10,3) NOT NULL,
  `max_power_w` decimal(10,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `energy_data_daily`
--

INSERT INTO `energy_data_daily` (`id`, `device_id`, `date`, `total_energy_kwh`, `avg_power_w`, `min_power_w`, `max_power_w`) VALUES
(1, 11, '2026-06-10', 0.038, 40.551, 0.000, 120.384),
(88, 11, '2026-06-11', 0.059, 26.841, 0.000, 124.428),
(106, 11, '2026-06-12', 0.189, 24.290, 0.000, 131.659),
(155, 11, '2026-06-15', 0.218, 34.823, 0.000, 131.898),
(203, 11, '2026-06-17', 0.134, 28.583, 0.000, 111.000),
(240, 11, '2026-06-18', 0.150, 59.755, 0.000, 141.617),
(637, 11, '2026-07-01', 0.003, 16.155, 0.000, 22.042),
(727, 11, '2026-07-03', 0.136, 293.704, 18.548, 344.189);

-- --------------------------------------------------------

--
-- Struktur dari tabel `energy_data_hourly`
--

CREATE TABLE `energy_data_hourly` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `total_energy_kwh` decimal(10,3) NOT NULL,
  `avg_power_w` decimal(10,3) NOT NULL,
  `min_power_w` decimal(10,3) NOT NULL,
  `max_power_w` decimal(10,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `energy_data_hourly`
--

INSERT INTO `energy_data_hourly` (`id`, `device_id`, `timestamp`, `total_energy_kwh`, `avg_power_w`, `min_power_w`, `max_power_w`) VALUES
(1, 11, '2026-06-10 09:00:00', 0.010, 116.246, 108.268, 120.384),
(2, 11, '2026-06-10 10:00:00', 0.000, 0.000, 0.000, 0.000),
(3, 11, '2026-06-10 11:00:00', 0.000, 0.000, 0.000, 0.000),
(8, 11, '2026-06-10 12:00:00', 0.009, 113.408, 112.343, 115.269),
(12, 11, '2026-06-10 13:00:00', 0.000, 0.000, 0.000, 0.000),
(23, 11, '2026-06-10 14:00:00', 0.003, 39.746, 25.877, 61.746),
(35, 11, '2026-06-10 15:00:00', 0.003, 27.932, 20.645, 32.536),
(41, 11, '2026-06-10 16:00:00', 0.013, 27.076, 16.632, 47.272),
(59, 11, '2026-06-11 13:00:00', 0.055, 58.738, 0.000, 124.428),
(75, 11, '2026-06-11 14:00:00', 0.002, 23.667, 22.229, 25.737),
(93, 11, '2026-06-11 15:00:00', 0.002, 24.958, 22.354, 26.052),
(102, 11, '2026-06-11 16:00:00', 0.000, 0.000, 0.000, 0.000),
(103, 11, '2026-06-12 07:00:00', 0.000, 0.000, 0.000, 0.000),
(104, 11, '2026-06-12 08:00:00', 0.000, 0.000, 0.000, 0.000),
(111, 11, '2026-06-12 09:00:00', 0.095, 95.291, 0.000, 131.659),
(117, 11, '2026-06-12 10:00:00', 0.002, 25.568, 24.157, 26.600),
(123, 11, '2026-06-12 11:00:00', 0.002, 20.474, 19.817, 21.072),
(130, 11, '2026-06-12 12:00:00', 0.002, 22.389, 21.593, 22.943),
(136, 11, '2026-06-12 13:00:00', 0.011, 19.407, 17.378, 22.907),
(139, 11, '2026-06-12 14:00:00', 0.011, 11.300, 0.000, 32.481),
(142, 11, '2026-06-12 15:00:00', 0.034, 34.068, 0.000, 112.321),
(148, 11, '2026-06-12 16:00:00', 0.032, 33.337, 17.295, 80.864),
(149, 11, '2026-06-12 17:00:00', 0.000, 5.351, 0.000, 10.701),
(157, 11, '2026-06-15 08:00:00', 0.016, 93.637, 0.000, 131.898),
(158, 11, '2026-06-15 09:00:00', 0.059, 61.510, 21.279, 130.822),
(164, 11, '2026-06-15 10:00:00', 0.021, 21.667, 18.919, 33.587),
(170, 11, '2026-06-15 11:00:00', 0.021, 22.061, 20.267, 25.206),
(177, 11, '2026-06-15 12:00:00', 0.022, 22.150, 20.514, 25.006),
(183, 11, '2026-06-15 13:00:00', 0.024, 23.901, 19.965, 56.161),
(189, 11, '2026-06-15 14:00:00', 0.022, 22.110, 20.203, 24.367),
(195, 11, '2026-06-15 15:00:00', 0.020, 20.678, 18.275, 23.650),
(198, 11, '2026-06-15 16:00:00', 0.013, 25.696, 0.000, 70.283),
(206, 11, '2026-06-17 08:00:00', 0.051, 81.467, 0.000, 111.000),
(210, 11, '2026-06-17 09:00:00', 0.029, 29.561, 22.865, 57.962),
(216, 11, '2026-06-17 10:00:00', 0.002, 27.084, 25.479, 28.041),
(222, 11, '2026-06-17 11:00:00', 0.002, 24.375, 23.409, 25.507),
(228, 11, '2026-06-17 12:00:00', 0.001, 21.879, 19.845, 23.616),
(234, 11, '2026-06-17 13:00:00', 0.022, 24.137, 19.627, 38.704),
(240, 11, '2026-06-17 14:00:00', 0.025, 25.464, 21.771, 47.973),
(246, 11, '2026-06-17 15:00:00', 0.002, 23.280, 22.399, 24.815),
(247, 11, '2026-06-17 16:00:00', 0.000, 0.000, 0.000, 0.000),
(248, 11, '2026-06-18 11:00:00', 0.000, 0.000, 0.000, 0.000),
(262, 11, '2026-06-18 14:00:00', 0.055, 132.502, 112.290, 141.617),
(268, 11, '2026-06-18 15:00:00', 0.076, 77.419, 27.342, 129.070),
(276, 11, '2026-06-18 16:00:00', 0.019, 29.097, 0.000, 35.905),
(489, 11, '2026-07-01 10:00:00', 0.003, 16.155, 0.000, 22.042),
(614, 11, '2026-07-03 14:00:00', 0.103, 254.596, 18.548, 344.189),
(616, 11, '2026-07-03 15:00:00', 0.033, 332.811, 288.509, 343.357),
(661, 11, '2026-07-07 14:00:00', 0.000, 2.020, 0.000, 14.139),
(666, 11, '2026-07-07 16:00:00', 0.000, 0.000, 0.000, 0.000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'kwh_rate', '1444.70', NULL, '2026-05-19 03:41:45', '2026-05-19 03:41:45'),
(2, 'fixed_monthly_charge', '0', NULL, '2026-05-19 03:41:45', '2026-05-19 03:41:45'),
(3, 'has_time_of_use_tariff', 'false', NULL, '2026-05-19 03:41:45', '2026-05-19 03:41:45'),
(4, 'peak_hour_rate_multiplier', '1.5', NULL, '2026-05-19 03:41:45', '2026-05-19 03:41:45'),
(5, 'peak_hour_start', '08:00', NULL, '2026-05-19 03:41:45', '2026-05-19 03:42:11'),
(6, 'peak_hour_end', '16:00', NULL, '2026-05-19 03:41:45', '2026-05-19 03:43:12');

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
(4, 'prima', 'prima@tes.com', '$2y$10$NVj.FwoPxgtMu8F3cHUi9u0jS6EOHV3Lg5ocyzGsRah677iB9DXLK', 'prima', 'prima', '123', 1, '2026-04-18 23:02:14', '2026-07-08 10:53:02');

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
(4, 4, 'cf86587571213081d372c31d146d98638d8135817dea6bbad23f241325d9d264', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 10:59:08', '2026-04-26 05:59:08', 1),
(5, 4, '2d73345567dd34243c19afbae25107bc8e43fc15aeaf09f9bbe50b491d4841c7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 10:53:42', '2026-05-14 05:53:42', 0),
(6, 4, '1934de4068f00e9141e6cc98d7edf677a0feefcc8c5cc7823aaec29aac37280d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-08 13:20:35', '2026-05-15 08:20:35', 0),
(7, 4, 'c805f0260bd170779710c0c85020f35e9748594c7d8591d5ce37e31ea6801b6f', '192.168.0.119', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-05-08 13:40:35', '2026-05-15 08:40:35', 1),
(8, 4, '4ec1f996ab1afc967ea6871c21df3180e3c640d6195f0d270b372c085d18ea56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-11 12:59:03', '2026-05-18 07:59:03', 0),
(9, 4, '6ec78507ef42134d57b07be2cd7f7123e07d6d100be2e024f693cd92cbf502fa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 10:23:38', '2026-05-19 05:23:38', 0),
(10, 4, '1850aa051ba45e6823f1b369a0289966bf30633ef4a2cd45d31458b37fb70d81', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 13:16:52', '2026-05-20 08:16:52', 0),
(11, 4, 'f9800c3e022cd4d49c1fcc31bb5afe8b1079efe3dcac840e5f544d201602284c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 13:14:12', '2026-05-25 08:14:12', 1),
(12, 4, '9014d2e65afef0d149ba76dd23b652a8b5951f8766cc2dc3ca0c3fa999c19dc4', '192.168.0.125', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-05-18 14:12:23', '2026-05-25 09:12:23', 1),
(13, 4, '0a9069dd66451e5041939b411f6e1f9e1ec729189e724acc994cf8c89ed55c3d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 16:36:10', '2026-05-25 11:36:10', 0),
(14, 4, 'aa9f43e318ddd121ef4adc21f622b5afad1137dc95af62298615287df55b9218', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 08:37:34', '2026-05-26 03:37:34', 0),
(15, 4, '5ae3919023155078070421addd422a5ff15d4bccf2504318fac3f6a26e5e807d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 12:22:56', '2026-05-26 07:22:56', 1),
(16, 4, '85b14cda89cfa891c519e18db832396c2ff41d70d4190d553e1931e1c2bda603', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 13:17:17', '2026-05-26 08:17:17', 1),
(17, 4, 'd4445cf34dd54d90add08c2e9c58a8ce61b75d79e827d23659a12a211c5262dc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-20 07:43:04', '2026-05-27 07:43:04', 1),
(18, 4, '262789376a0ed70a122201dd548f6f235ac5e798f95e815ffecb5be3bebdeb67', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-25 14:30:49', '2026-06-01 14:30:49', 0),
(19, 4, 'ef4ac36ae778cab685b81828372260411d6b18772a62c4817fd878978e12a139', '192.168.0.125', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-05-25 14:38:44', '2026-06-01 14:38:44', 1),
(20, 4, '8cf45fa6259e2d1bf858eb5ccc5ce279d7bc1bf9415b565bc7388eb6bca90993', '192.168.0.125', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-25 16:08:32', '2026-06-01 16:08:32', 1),
(21, 4, '5aadf9e0e6e8214681542eec344f9a68e99f80c2aa28139facf135ed8ad52b6a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-26 07:45:09', '2026-06-02 07:45:09', 1),
(22, 4, 'a909e6284137e33b52236e8a001a488cee725ba65ae938db4fcb2aea46f0c19e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-28 09:06:27', '2026-06-04 09:06:27', 1),
(23, 4, 'c31ea8f3bb937e0c62afd9b8cf8721af9ae3c6a53ae914fe2368045f583a1207', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-29 08:25:52', '2026-06-05 08:25:52', 1),
(24, 4, 'b129830d90d5344805fb63ec4f7c28c6a8f6d98834219101597f58de41ab4521', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-06-02 16:14:03', '2026-06-09 11:14:03', 1),
(25, 4, 'f7d3d933423e474a3303c7b747eb0e660041c288784d71af95624a68ca0aa7e8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-06-03 09:51:31', '2026-06-10 04:51:31', 1),
(26, 4, 'e660bd511aa22bc12c77873d4ad204d38687eb6431529be372df4d3b99b1812d', '192.168.1.117', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '2026-06-03 10:20:43', '2026-06-10 05:20:43', 1),
(27, 4, 'bceeb1f778644f7fc2032e9c780ade5f8c0f98c719796d0d56c80329f2134e74', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-06-05 10:35:07', '2026-06-12 05:35:07', 0),
(28, 4, '314a3e99df2f22c1e8441545fa966e4430830d31d76b35de628fbe8ae21209a9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-10 09:33:48', '2026-06-17 04:33:48', 0),
(29, 4, 'eafc933a41ed74e740bdf166410ab2388fdaf977d83f19a0ebe646b7834217e7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-10 13:46:50', '2026-06-17 08:46:50', 0),
(30, 4, '8caf0065b064001b076a178733dd165f4e8e7c552fc59f0a28a1bf5834b6e3a8', '192.168.1.117', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '2026-06-10 15:30:25', '2026-06-17 10:30:25', 1),
(31, 4, '8eec53abf611cf64d10438259a487129d964d0a27d75d742adcb15375c6dd89a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-11 12:44:05', '2026-06-18 07:44:05', 0),
(32, 4, 'c0e3136e5683d20088216bd840dbcd14475281de0c53251b0eb7fdff2ea5b84a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-13 19:19:12', '2026-06-20 14:19:12', 1),
(33, 4, 'f3da5bd0012365d4e15843f8aca5078f97bc06cf6664cbce445fbb4b913d452c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-06-13 19:44:46', '2026-06-20 14:44:46', 1),
(34, 4, '7687a239d6c190576cd82f975b2a46b626b72fd3316b4ceb2777ffd0314e929e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-06-25 14:20:35', '2026-07-02 09:20:35', 0),
(35, 4, '71d7f0d40faa1353e9a8ce655342991642f6069bb7bbbdf9dc9a15e3c668758b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-07-01 08:53:05', '2026-07-08 03:53:05', 1),
(36, 4, 'd5423a82f3b9dee7d28cc1655cdee1557f0db1ae71f1692c5bd610501398d411', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-07-03 08:45:12', '2026-07-10 03:45:12', 1),
(37, 4, '9801cc751264b49f77d679510b90fce74f6f603beb4420f7f0300eb30b3ec4e6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', '2026-07-08 10:53:02', '2026-07-15 05:53:02', 1);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `ai_daily_insights`
--
ALTER TABLE `ai_daily_insights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_date_unique` (`user_id`,`generated_date`);

--
-- Indeks untuk tabel `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indeks untuk tabel `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `idx_device_id` (`device_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idxuser_id` (`user_id`);

--
-- Indeks untuk tabel `device_registration_codes`
--
ALTER TABLE `device_registration_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_code` (`registration_code`),
  ADD KEY `idx_registration_code` (`registration_code`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indeks untuk tabel `device_schedules`
--
ALTER TABLE `device_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indeks untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_device_created` (`device_id`,`created_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `id_device_created` (`device_id`,`created_at`),
  ADD KEY `id_created_at` (`created_at`),
  ADD KEY `idx_energy_data_created_at` (`created_at`),
  ADD KEY `idx_energy_data_device_id_created_at` (`device_id`,`created_at`);

--
-- Indeks untuk tabel `energy_data_daily`
--
ALTER TABLE `energy_data_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_date` (`device_id`,`date`);

--
-- Indeks untuk tabel `energy_data_hourly`
--
ALTER TABLE `energy_data_hourly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_hour` (`device_id`,`timestamp`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

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
-- AUTO_INCREMENT untuk tabel `ai_daily_insights`
--
ALTER TABLE `ai_daily_insights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `device_registration_codes`
--
ALTER TABLE `device_registration_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `device_schedules`
--
ALTER TABLE `device_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2318;

--
-- AUTO_INCREMENT untuk tabel `energy_data_daily`
--
ALTER TABLE `energy_data_daily`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=780;

--
-- AUTO_INCREMENT untuk tabel `energy_data_hourly`
--
ALTER TABLE `energy_data_hourly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=675;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `ai_daily_insights`
--
ALTER TABLE `ai_daily_insights`
  ADD CONSTRAINT `ai_daily_insights_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alerts_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL;

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
-- Ketidakleluasaan untuk tabel `device_schedules`
--
ALTER TABLE `device_schedules`
  ADD CONSTRAINT `device_schedules_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `energy_data`
--
ALTER TABLE `energy_data`
  ADD CONSTRAINT `energy_data_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `energy_data_daily`
--
ALTER TABLE `energy_data_daily`
  ADD CONSTRAINT `energy_data_daily_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `energy_data_hourly`
--
ALTER TABLE `energy_data_hourly`
  ADD CONSTRAINT `energy_data_hourly_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
