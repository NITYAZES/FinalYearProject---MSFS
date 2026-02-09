-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 08, 2026 at 04:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `multimediasecurefilesharing`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` varchar(100) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `category` enum('security','user','file','system','audit') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `dismissed` tinyint(1) DEFAULT 0,
  `action_url` varchar(512) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `admin_id`, `notification_id`, `notification_type`, `title`, `message`, `priority`, `category`, `is_read`, `dismissed`, `action_url`, `metadata_json`, `created_at`, `updated_at`) VALUES
(1, 1, 'notif_69875ba83ca484.46517691_1', 'user_registered', '👤 New User Registration', 'New user \'Nitya01\' (nityasathu123@gmail.com) has registered to the system.', 'normal', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":9,\"username\":\"Nitya01\",\"email\":\"nityasathu123@gmail.com\",\"phone\":\"+60125176084\"}', '2026-02-07 23:35:04', '2026-02-07 23:35:04'),
(2, 1, 'notif_69875bb6096843.53129770_1', 'email_verified', '✅ Email Verified', 'User \'Nitya01\' (nityasathu123@gmail.com) has successfully verified their email address.', 'low', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":9,\"username\":\"Nitya01\",\"email\":\"nityasathu123@gmail.com\"}', '2026-02-07 23:35:18', '2026-02-07 23:35:18'),
(3, 1, 'notif_69875c414641e3.23667695_1', 'user_2fa_changed', '✅ 2FA Status Changed', 'User \'Nitya01\' has enabled Two-Factor Authentication.', 'low', 'security', 0, 0, 'admin_manage_users.html', '{\"user_id\":9,\"username\":\"Nitya01\",\"2fa_enabled\":true}', '2026-02-07 23:37:37', '2026-02-07 23:37:37'),
(4, 1, 'notif_69875cccdbe022.04204813_1', 'user_registered', '👤 New User Registration', 'New user \'Danya09\' (danya@gmail.com) has registered to the system.', 'normal', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":10,\"username\":\"Danya09\",\"email\":\"danya@gmail.com\",\"phone\":\"+60123456702\"}', '2026-02-07 23:39:56', '2026-02-07 23:39:56'),
(5, 1, 'notif_69875cd801f0f2.19122573_1', 'email_verified', '✅ Email Verified', 'User \'Danya09\' (danya@gmail.com) has successfully verified their email address.', 'low', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":10,\"username\":\"Danya09\",\"email\":\"danya@gmail.com\"}', '2026-02-07 23:40:08', '2026-02-07 23:40:08'),
(6, 1, 'notif_69875cf46b9bb7.86439608_1', 'user_2fa_changed', '✅ 2FA Status Changed', 'User \'Danya09\' has enabled Two-Factor Authentication.', 'low', 'security', 0, 0, 'admin_manage_users.html', '{\"user_id\":10,\"username\":\"Danya09\",\"2fa_enabled\":true}', '2026-02-07 23:40:36', '2026-02-07 23:40:36'),
(7, 1, 'notif_69875d383cae19.32044120_1', 'user_registered', '👤 New User Registration', 'New user \'danwong\' (daniel.wong@gmail.com) has registered to the system.', 'normal', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":11,\"username\":\"danwong\",\"email\":\"daniel.wong@gmail.com\",\"phone\":\"+60123456703\"}', '2026-02-07 23:41:44', '2026-02-07 23:41:44'),
(8, 1, 'notif_69875d40ee7726.20321284_1', 'email_verified', '✅ Email Verified', 'User \'danwong\' (daniel.wong@gmail.com) has successfully verified their email address.', 'low', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":11,\"username\":\"danwong\",\"email\":\"daniel.wong@gmail.com\"}', '2026-02-07 23:41:52', '2026-02-07 23:41:52'),
(9, 1, 'notif_69875d631c0740.31725434_1', 'user_2fa_changed', '✅ 2FA Status Changed', 'User \'danwong\' has enabled Two-Factor Authentication.', 'low', 'security', 0, 0, 'admin_manage_users.html', '{\"user_id\":11,\"username\":\"danwong\",\"2fa_enabled\":true}', '2026-02-07 23:42:27', '2026-02-07 23:42:27'),
(10, 1, 'notif_69875daf4de849.28042728_1', 'user_registered', '👤 New User Registration', 'New user \'syafiqan\' (syafiqa.nur@gmail.com) has registered to the system.', 'normal', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":12,\"username\":\"syafiqan\",\"email\":\"syafiqa.nur@gmail.com\",\"phone\":\"+60123456704\"}', '2026-02-07 23:43:43', '2026-02-07 23:43:43'),
(11, 1, 'notif_69875dba3f0fb1.06028605_1', 'email_verified', '✅ Email Verified', 'User \'syafiqan\' (syafiqa.nur@gmail.com) has successfully verified their email address.', 'low', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":12,\"username\":\"syafiqan\",\"email\":\"syafiqa.nur@gmail.com\"}', '2026-02-07 23:43:54', '2026-02-07 23:43:54'),
(12, 1, 'notif_69875dd967be19.36355367_1', 'user_2fa_changed', '✅ 2FA Status Changed', 'User \'syafiqan\' has enabled Two-Factor Authentication.', 'low', 'security', 0, 0, 'admin_manage_users.html', '{\"user_id\":12,\"username\":\"syafiqan\",\"2fa_enabled\":true}', '2026-02-07 23:44:25', '2026-02-07 23:44:25'),
(13, 1, 'notif_69875e2b699585.35797879_1', 'user_registered', '👤 New User Registration', 'New user \'ahmadfaiz\' (ahmad.faiz@gmail.com) has registered to the system.', 'normal', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":13,\"username\":\"ahmadfaiz\",\"email\":\"ahmad.faiz@gmail.com\",\"phone\":\"+60123456705\"}', '2026-02-07 23:45:47', '2026-02-07 23:45:47'),
(14, 1, 'notif_69875e37aa7a98.73612872_1', 'email_verified', '✅ Email Verified', 'User \'ahmadfaiz\' (ahmad.faiz@gmail.com) has successfully verified their email address.', 'low', 'user', 0, 0, 'admin_manage_users.html', '{\"user_id\":13,\"username\":\"ahmadfaiz\",\"email\":\"ahmad.faiz@gmail.com\"}', '2026-02-07 23:45:59', '2026-02-07 23:45:59'),
(15, 1, 'notif_69875e4f48c098.80072974_1', 'user_2fa_changed', '✅ 2FA Status Changed', 'User \'ahmadfaiz\' has enabled Two-Factor Authentication.', 'low', 'security', 0, 0, 'admin_manage_users.html', '{\"user_id\":13,\"username\":\"ahmadfaiz\",\"2fa_enabled\":true}', '2026-02-07 23:46:23', '2026-02-07 23:46:23'),
(16, 1, 'notif_6987617c351761.75526678_1', 'security_failed_login', '🔒 Multiple Failed Login Attempts', 'User \'Nitya01\' has 3 failed login attempts from IP: ::1', 'high', 'security', 0, 0, 'admin_security_audit.html', '{\"user_id\":9,\"username\":\"Nitya01\",\"attempts\":3,\"ip_address\":\"::1\"}', '2026-02-07 23:59:56', '2026-02-07 23:59:56');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `code_hash` varbinary(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_verification_codes`
--

INSERT INTO `email_verification_codes` (`id`, `user_id`, `code_hash`, `expires_at`, `consumed_at`, `created_at`) VALUES
(33, 9, 0x2432792431302473645668742f667a38394552354963463639314566754a797845514b6d69386b343071425170583731632f42547365305951667436, '2026-02-07 16:44:58', '2026-02-07 23:35:18', '2026-02-07 23:34:58'),
(34, 10, 0x243279243130242e59395a4c36694735304b62684e436b2e43514b784f45734645476d4c5a6d78567a797530464d6e475a366f746532636536717047, '2026-02-07 16:49:51', '2026-02-07 23:40:07', '2026-02-07 23:39:51'),
(35, 11, 0x2432792431302475634461466b556553546a786f6c7036504b456738656667322f7569496e2f3066506270336a455a71344e66503551734346736c47, '2026-02-07 16:51:38', '2026-02-07 23:41:52', '2026-02-07 23:41:38'),
(36, 12, 0x243279243130246b774170754a67522e4c2f4c57486775576172684f2e65494357616e6e4e74736473576d67484c6c4f785a6a6b55793161566f572e, '2026-02-07 16:53:38', '2026-02-07 23:43:54', '2026-02-07 23:43:38'),
(37, 13, 0x24327924313024344a6353556f6a534b776535463945502f7676714a655647323038437148346f2e5a58645156684134764f45344977744a57764b36, '2026-02-07 16:55:42', '2026-02-07 23:45:59', '2026-02-07 23:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `encryption_metrics`
--

CREATE TABLE `encryption_metrics` (
  `metric_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` varchar(32) NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `receiver_id` bigint(20) UNSIGNED NOT NULL,
  `encryption_score` int(3) NOT NULL,
  `encryption_rating` varchar(20) NOT NULL,
  `encryption_percentage` decimal(5,2) NOT NULL,
  `rsa_key_size` int(11) DEFAULT 2048,
  `aes_key_size` int(11) DEFAULT 256,
  `iv_length` int(11) DEFAULT 12,
  `encryption_algorithm` varchar(50) DEFAULT 'AES-GCM',
  `key_exchange_algorithm` varchar(50) DEFAULT 'RSA-OAEP',
  `hash_algorithm` varchar(50) DEFAULT 'SHA-256',
  `authenticated_encryption` tinyint(1) DEFAULT 1,
  `e2ee_enabled` tinyint(1) DEFAULT 1,
  `expiry_enabled` tinyint(1) DEFAULT 1,
  `download_limit_enabled` tinyint(1) DEFAULT 1,
  `encryption_time_ms` int(11) DEFAULT NULL,
  `original_size` bigint(20) DEFAULT NULL,
  `encrypted_size` bigint(20) DEFAULT NULL,
  `size_overhead_bytes` bigint(20) DEFAULT NULL,
  `size_overhead_percent` decimal(5,2) DEFAULT NULL,
  `cipher_entropy_bits_per_byte` decimal(5,3) DEFAULT NULL,
  `cipher_sample_size` bigint(20) DEFAULT NULL,
  `score_breakdown_json` text DEFAULT NULL,
  `recommendations_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `encryption_metrics`
--

INSERT INTO `encryption_metrics` (`metric_id`, `file_id`, `sender_id`, `receiver_id`, `encryption_score`, `encryption_rating`, `encryption_percentage`, `rsa_key_size`, `aes_key_size`, `iv_length`, `encryption_algorithm`, `key_exchange_algorithm`, `hash_algorithm`, `authenticated_encryption`, `e2ee_enabled`, `expiry_enabled`, `download_limit_enabled`, `encryption_time_ms`, `original_size`, `encrypted_size`, `size_overhead_bytes`, `size_overhead_percent`, `cipher_entropy_bits_per_byte`, `cipher_sample_size`, `score_breakdown_json`, `recommendations_json`, `created_at`) VALUES
(58, '05dd13f45757f0d1949bcd02af59403e', 9, 10, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 5, 53682, 53710, 28, 0.05, 7.996, 53710, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.996}}', '[]', '2026-02-08 00:03:33'),
(59, 'd78db46fad22a99de090ba2e257fd837', 10, 12, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 162, 4907356, 4907384, 28, 0.00, 7.999, 250000, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.999}}', '[]', '2026-02-08 00:34:11'),
(60, '694dbe5e26e22d6c1f1b74529f679dd2', 10, 13, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 162, 4907356, 4907384, 28, 0.00, 7.999, 250000, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.999}}', '[]', '2026-02-08 00:34:20'),
(61, 'c638713ebbffe6e0a1a2c45b03b1fce1', 11, 9, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 21, 578783, 578811, 28, 0.00, 7.999, 250000, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.999}}', '[]', '2026-02-08 00:37:38'),
(62, '7793005c95f21a9eeeb35616488b7991', 11, 12, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 1, 8969, 8997, 28, 0.31, 7.983, 8997, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.983}}', '[]', '2026-02-08 00:38:20'),
(63, 'a74b933d942c0b3d60acb136e2a3a1ef', 11, 12, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 131, 6132688, 6132716, 28, 0.00, 7.999, 250000, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.999}}', '[]', '2026-02-08 00:38:58'),
(64, 'cce6563f47ef1b91df59593bfcbc9d50', 12, 13, 100, 'Excellent', 100.00, 2048, 256, 12, 'AES-GCM', 'RSA-OAEP', 'SHA-256', 1, 1, 1, 1, 64, 2531316, 2531344, 28, 0.00, 7.999, 250000, '{\"rsaKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"aesKey\":{\"score\":15,\"max\":15,\"status\":\"excellent\"},\"algorithm\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"keyExchange\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"hash\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"authEncryption\":{\"score\":5,\"max\":5,\"status\":\"excellent\"},\"ivQuality\":{\"score\":10,\"max\":10,\"status\":\"excellent\"},\"expiry\":{\"score\":8,\"max\":8,\"status\":\"enabled\"},\"downloadLimit\":{\"score\":7,\"max\":7,\"status\":\"enabled\"},\"e2ee\":{\"score\":5,\"max\":5,\"status\":\"enabled\"},\"cipherEntropy\":{\"score\":15,\"max\":15,\"status\":\"excellent\",\"entropyBitsPerByte\":7.999}}', '[]', '2026-02-08 00:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `file_access_log`
--

CREATE TABLE `file_access_log` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` enum('viewed','downloaded','decrypted','access_denied','expired','deleted') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL COMMENT 'Additional context',
  `accessed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `file_access_log`
--

INSERT INTO `file_access_log` (`log_id`, `file_id`, `user_id`, `action`, `ip_address`, `user_agent`, `metadata_json`, `accessed_at`) VALUES
(93, '05dd13f45757f0d1949bcd02af59403e', 9, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":10}', '2026-02-08 00:03:33'),
(94, 'd78db46fad22a99de090ba2e257fd837', 10, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":12}', '2026-02-08 00:34:11'),
(95, '694dbe5e26e22d6c1f1b74529f679dd2', 10, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":13}', '2026-02-08 00:34:20'),
(96, 'c638713ebbffe6e0a1a2c45b03b1fce1', 11, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":9}', '2026-02-08 00:37:39'),
(97, '7793005c95f21a9eeeb35616488b7991', 11, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":12}', '2026-02-08 00:38:20'),
(98, 'a74b933d942c0b3d60acb136e2a3a1ef', 11, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":12}', '2026-02-08 00:38:58'),
(99, 'cce6563f47ef1b91df59593bfcbc9d50', 12, 'viewed', NULL, NULL, '{\"action\":\"uploaded\",\"receiver_id\":13}', '2026-02-08 00:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `file_recipients`
--

CREATE TABLE `file_recipients` (
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `permission_level` enum('view','download') NOT NULL DEFAULT 'view',
  `access_count` int(11) DEFAULT 0,
  `last_accessed_at` datetime DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `file_recipients`
--

INSERT INTO `file_recipients` (`recipient_id`, `file_id`, `user_id`, `permission_level`, `access_count`, `last_accessed_at`, `status`, `added_at`) VALUES
(57, '05dd13f45757f0d1949bcd02af59403e', 10, 'download', 0, NULL, 'active', '2026-02-08 00:03:33'),
(58, 'd78db46fad22a99de090ba2e257fd837', 12, 'download', 0, NULL, 'active', '2026-02-08 00:34:11'),
(59, '694dbe5e26e22d6c1f1b74529f679dd2', 13, 'download', 0, NULL, 'active', '2026-02-08 00:34:20'),
(60, 'c638713ebbffe6e0a1a2c45b03b1fce1', 9, 'download', 0, NULL, 'active', '2026-02-08 00:37:38'),
(61, '7793005c95f21a9eeeb35616488b7991', 12, 'download', 0, NULL, 'active', '2026-02-08 00:38:20'),
(62, 'a74b933d942c0b3d60acb136e2a3a1ef', 12, 'download', 0, NULL, 'active', '2026-02-08 00:38:58'),
(63, 'cce6563f47ef1b91df59593bfcbc9d50', 13, 'download', 0, NULL, 'active', '2026-02-08 00:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_attempts`
--

CREATE TABLE `password_reset_attempts` (
  `id` int(11) NOT NULL,
  `ip_hash` varbinary(32) NOT NULL,
  `identity_hash` varbinary(32) NOT NULL,
  `fail_count` int(11) NOT NULL DEFAULT 0,
  `first_fail_at` datetime NOT NULL,
  `last_fail_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_pending`
--

CREATE TABLE `password_reset_pending` (
  `pending_id` bigint(20) UNSIGNED NOT NULL,
  `reset_request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `confirmation_token_hash` varbinary(64) NOT NULL,
  `new_kek_enc` varbinary(512) NOT NULL,
  `new_kek_iv` varbinary(12) NOT NULL,
  `new_pwkdf_salt` varbinary(16) NOT NULL,
  `new_pwkdf_iterations` int(10) UNSIGNED NOT NULL DEFAULT 150000,
  `new_password_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `reset_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `reset_token_hash` varbinary(64) NOT NULL,
  `reset_token_expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

CREATE TABLE `security_audit_log` (
  `audit_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_category` enum('auth','crypto','file','admin','security') NOT NULL,
  `severity` enum('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `description` text NOT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_audit_log`
--

INSERT INTO `security_audit_log` (`audit_id`, `user_id`, `event_type`, `event_category`, `severity`, `description`, `user_agent`, `metadata_json`, `created_at`) VALUES
(1, 9, 'EMAIL_VERIFICATION_OTP_SENT', 'auth', 'info', 'Email verification OTP created and stored (hashed)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"expires_at\":\"2026-02-07 16:44:58\"}', '2026-02-07 23:34:58'),
(2, 9, 'USER_REGISTERED', 'auth', 'info', 'New user registered (account inactive until email verified)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"user_id\":9,\"username\":\"Nitya01\",\"email\":\"nityasathu123@gmail.com\"}', '2026-02-07 23:35:04'),
(3, 9, 'EMAIL_VERIFICATION_SUCCESS', 'auth', 'info', 'User email verified successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"email\":\"nityasathu123@gmail.com\"}', '2026-02-07 23:35:18'),
(4, 9, 'LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:37:21'),
(5, 9, 'TOTP_ENABLED', 'security', 'info', 'User enabled 2FA (TOTP) successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"method\":\"totp\",\"has_backup_codes\":true}', '2026-02-07 23:37:37'),
(6, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:37:49'),
(7, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-07 23:37:55'),
(8, 10, 'EMAIL_VERIFICATION_OTP_SENT', 'auth', 'info', 'Email verification OTP created and stored (hashed)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"expires_at\":\"2026-02-07 16:49:51\"}', '2026-02-07 23:39:51'),
(9, 10, 'USER_REGISTERED', 'auth', 'info', 'New user registered (account inactive until email verified)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"user_id\":10,\"username\":\"Danya09\",\"email\":\"danya@gmail.com\"}', '2026-02-07 23:39:56'),
(10, 10, 'EMAIL_VERIFICATION_SUCCESS', 'auth', 'info', 'User email verified successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"email\":\"danya@gmail.com\"}', '2026-02-07 23:40:08'),
(11, 10, 'LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:40:17'),
(12, 10, 'TOTP_ENABLED', 'security', 'info', 'User enabled 2FA (TOTP) successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"method\":\"totp\",\"has_backup_codes\":true}', '2026-02-07 23:40:36'),
(13, 10, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:40:43'),
(14, 10, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-07 23:40:50'),
(15, 11, 'EMAIL_VERIFICATION_OTP_SENT', 'auth', 'info', 'Email verification OTP created and stored (hashed)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"expires_at\":\"2026-02-07 16:51:38\"}', '2026-02-07 23:41:38'),
(16, 11, 'USER_REGISTERED', 'auth', 'info', 'New user registered (account inactive until email verified)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"user_id\":11,\"username\":\"danwong\",\"email\":\"daniel.wong@gmail.com\"}', '2026-02-07 23:41:44'),
(17, 11, 'EMAIL_VERIFICATION_SUCCESS', 'auth', 'info', 'User email verified successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"email\":\"daniel.wong@gmail.com\"}', '2026-02-07 23:41:52'),
(18, 11, 'LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:42:11'),
(19, 11, 'TOTP_ENABLED', 'security', 'info', 'User enabled 2FA (TOTP) successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"method\":\"totp\",\"has_backup_codes\":true}', '2026-02-07 23:42:27'),
(20, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:42:33'),
(21, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-07 23:42:37'),
(22, 12, 'EMAIL_VERIFICATION_OTP_SENT', 'auth', 'info', 'Email verification OTP created and stored (hashed)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"expires_at\":\"2026-02-07 16:53:38\"}', '2026-02-07 23:43:38'),
(23, 12, 'USER_REGISTERED', 'auth', 'info', 'New user registered (account inactive until email verified)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"user_id\":12,\"username\":\"syafiqan\",\"email\":\"syafiqa.nur@gmail.com\"}', '2026-02-07 23:43:43'),
(24, 12, 'EMAIL_VERIFICATION_SUCCESS', 'auth', 'info', 'User email verified successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"email\":\"syafiqa.nur@gmail.com\"}', '2026-02-07 23:43:54'),
(25, 12, 'LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:44:10'),
(26, 12, 'TOTP_ENABLED', 'security', 'info', 'User enabled 2FA (TOTP) successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"method\":\"totp\",\"has_backup_codes\":true}', '2026-02-07 23:44:25'),
(27, 12, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:44:35'),
(28, 12, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-07 23:44:43'),
(29, 13, 'EMAIL_VERIFICATION_OTP_SENT', 'auth', 'info', 'Email verification OTP created and stored (hashed)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"expires_at\":\"2026-02-07 16:55:42\"}', '2026-02-07 23:45:42'),
(30, 13, 'USER_REGISTERED', 'auth', 'info', 'New user registered (account inactive until email verified)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"user_id\":13,\"username\":\"ahmadfaiz\",\"email\":\"ahmad.faiz@gmail.com\"}', '2026-02-07 23:45:47'),
(31, 13, 'EMAIL_VERIFICATION_SUCCESS', 'auth', 'info', 'User email verified successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"email\":\"ahmad.faiz@gmail.com\"}', '2026-02-07 23:45:59'),
(32, 13, 'LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:46:09'),
(33, 13, 'TOTP_ENABLED', 'security', 'info', 'User enabled 2FA (TOTP) successfully', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"method\":\"totp\",\"has_backup_codes\":true}', '2026-02-07 23:46:23'),
(34, 13, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:46:31'),
(35, 13, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-07 23:46:36'),
(36, 9, 'LOGIN_FAILED', 'auth', '', 'Failed login attempt (bad password)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"attempts\":1,\"max_attempts\":5}', '2026-02-07 23:59:30'),
(37, 9, 'LOGIN_FAILED', 'auth', '', 'Failed login attempt (bad password)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"attempts\":2,\"max_attempts\":5}', '2026-02-07 23:59:43'),
(38, 9, 'LOGIN_FAILED', 'auth', '', 'Failed login attempt (bad password)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"attempts\":3,\"max_attempts\":5}', '2026-02-07 23:59:56'),
(39, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-07 23:59:59'),
(40, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 00:00:38'),
(41, 9, 'password_changed', 'security', 'info', 'User changed password and rotated KEK', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"kek_rotated\":true,\"kek_version_new\":2,\"ip\":\"::1\",\"timestamp\":\"2026-02-07 17:00:51\"}', '2026-02-08 00:00:51'),
(42, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\"}', '2026-02-08 00:01:47'),
(43, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\",\"type\":\"totp\"}', '2026-02-08 00:02:06'),
(44, 9, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'IMG_0306.jpeg\' to danya@gmail.com', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"file_id\":\"05dd13f45757f0d1949bcd02af59403e\",\"file_name\":\"IMG_0306.jpeg\",\"file_size\":53682,\"receiver_id\":10,\"receiver_email\":\"danya@gmail.com\"}', '2026-02-08 00:03:33'),
(45, 10, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 00:06:46'),
(46, 10, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 00:06:55'),
(47, 10, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '{\"count\":1,\"ip\":\"::1\"}', '2026-02-08 00:07:16'),
(48, 10, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":1,\"ip\":\"::1\"}', '2026-02-08 00:07:35'),
(49, 9, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"count\":0,\"ip\":\"192.168.1.8\"}', '2026-02-08 00:09:27'),
(50, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\"}', '2026-02-08 00:15:23'),
(51, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\",\"type\":\"totp\"}', '2026-02-08 00:15:45'),
(52, 9, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"count\":0,\"ip\":\"192.168.1.8\"}', '2026-02-08 00:17:49'),
(53, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\"}', '2026-02-08 00:21:04'),
(54, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\",\"type\":\"totp\"}', '2026-02-08 00:21:23'),
(55, 9, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\"}', '2026-02-08 00:31:48'),
(56, 9, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15', '{\"ip\":\"192.168.1.8\",\"type\":\"totp\"}', '2026-02-08 00:32:01'),
(57, 10, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'Lovely(PagaiWorld.com) (2) (4).mp3\' to syafiqa.nur@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"d78db46fad22a99de090ba2e257fd837\",\"file_name\":\"Lovely(PagaiWorld.com) (2) (4).mp3\",\"file_size\":4907356,\"receiver_id\":12,\"receiver_email\":\"syafiqa.nur@gmail.com\"}', '2026-02-08 00:34:11'),
(58, 10, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'Lovely(PagaiWorld.com) (2) (4).mp3\' to ahmad.faiz@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"694dbe5e26e22d6c1f1b74529f679dd2\",\"file_name\":\"Lovely(PagaiWorld.com) (2) (4).mp3\",\"file_size\":4907356,\"receiver_id\":13,\"receiver_email\":\"ahmad.faiz@gmail.com\"}', '2026-02-08 00:34:20'),
(59, 10, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":1,\"ip\":\"::1\"}', '2026-02-08 00:34:33'),
(60, 10, 'FILE_POLICY_UPDATED', 'file', 'info', 'User updated policy for file \'Lovely(PagaiWorld.com) (2) (4).mp3\' (ID: 694dbe5e26e22d6c1f1b74529f679dd2)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"694dbe5e26e22d6c1f1b74529f679dd2\",\"file_name\":\"Lovely(PagaiWorld.com) (2) (4).mp3\",\"recipients_affected\":1,\"changes\":{\"expiry_time\":{\"old\":\"2026-02-09 00:33:00\",\"new\":\"2026-03-10 15:30:00\"},\"max_decrypt_count\":{\"old\":5,\"new\":5}},\"timestamp\":\"2026-02-07 17:35:27\"}', '2026-02-08 00:35:27'),
(61, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 00:36:34'),
(62, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 00:36:45'),
(63, 11, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'Internship Report.pdf\' to nityasathu123@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"c638713ebbffe6e0a1a2c45b03b1fce1\",\"file_name\":\"Internship Report.pdf\",\"file_size\":578783,\"receiver_id\":9,\"receiver_email\":\"nityasathu123@gmail.com\"}', '2026-02-08 00:37:39'),
(64, 11, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'billy eilish.jfif\' to syafiqa.nur@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"file_size\":8969,\"receiver_id\":12,\"receiver_email\":\"syafiqa.nur@gmail.com\"}', '2026-02-08 00:38:20'),
(65, 11, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'11054198-hd_1080_1920_25fps.mp4\' to syafiqa.nur@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"a74b933d942c0b3d60acb136e2a3a1ef\",\"file_name\":\"11054198-hd_1080_1920_25fps.mp4\",\"file_size\":6132688,\"receiver_id\":12,\"receiver_email\":\"syafiqa.nur@gmail.com\"}', '2026-02-08 00:38:58'),
(66, 12, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 00:39:59'),
(67, 12, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 00:40:15'),
(68, 12, 'file_uploaded', 'file', 'info', 'Uploaded encrypted file \'Ethical Hacking.pdf\' to ahmad.faiz@gmail.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"file_id\":\"cce6563f47ef1b91df59593bfcbc9d50\",\"file_name\":\"Ethical Hacking.pdf\",\"file_size\":2531316,\"receiver_id\":13,\"receiver_email\":\"ahmad.faiz@gmail.com\"}', '2026-02-08 00:40:55'),
(69, 1, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 22:42:24'),
(70, 1, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 22:42:36'),
(71, 1, 'admin_viewed_user_details', 'admin', 'info', 'Admin viewed details for user \'ahmadfaiz\' (ID: 13)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"viewed_user_id\":13,\"viewed_username\":\"ahmadfaiz\",\"timestamp\":\"2026-02-08 15:42:41\"}', '2026-02-08 22:42:41'),
(72, 1, 'admin_viewed_user_details', 'admin', 'info', 'Admin viewed details for user \'syafiqan\' (ID: 12)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"viewed_user_id\":12,\"viewed_username\":\"syafiqan\",\"timestamp\":\"2026-02-08 15:42:55\"}', '2026-02-08 22:42:55'),
(73, 1, 'admin_user_updated', 'admin', 'info', 'Admin updated user \'ahmadfaiz\' (ID: 13)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"target_user_id\":13,\"target_username\":\"ahmadfaiz\",\"admin_id\":1,\"email_change_requested\":false,\"timestamp\":\"2026-02-08 15:43:12\"}', '2026-02-08 22:43:12'),
(74, 1, 'admin_user_updated', 'admin', 'info', 'Admin updated user \'danwong\' (ID: 11)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"target_user_id\":11,\"target_username\":\"danwong\",\"admin_id\":1,\"email_change_requested\":true,\"timestamp\":\"2026-02-08 15:44:25\"}', '2026-02-08 22:44:25'),
(75, 11, 'LOGIN_BLOCKED_INACTIVE', 'auth', '', 'Login blocked: user inactive', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"status\":\"inactive\"}', '2026-02-08 22:44:57'),
(76, 11, 'user_email_verified', 'security', 'info', 'User verified new login email', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"user_id\":11,\"timestamp\":\"2026-02-08 15:45:13\"}', '2026-02-08 22:45:13'),
(77, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 22:45:18'),
(78, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 22:45:28'),
(79, 11, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":0,\"ip\":\"::1\"}', '2026-02-08 22:45:32'),
(80, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"active\",\"new_status\":\"deleted\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 15:46:37\"}', '2026-02-08 22:46:37'),
(81, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"active\",\"new_status\":\"deleted\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 15:59:40\"}', '2026-02-08 22:59:40'),
(82, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"active\",\"new_status\":\"deleted\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 16:01:19\"}', '2026-02-08 23:01:19'),
(83, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"active\",\"new_status\":\"revoked\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 16:07:23\"}', '2026-02-08 23:07:23'),
(84, 12, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:08:06'),
(85, 12, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:08:18'),
(86, 12, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":2,\"ip\":\"::1\"}', '2026-02-08 23:08:20'),
(87, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:08:44'),
(88, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:08:55'),
(89, 11, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":0,\"ip\":\"::1\"}', '2026-02-08 23:08:58'),
(90, 11, 'FILE_SHARE_REACTIVATED', 'file', 'info', 'User reactivated sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"revoked\",\"new_status\":\"active\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 16:09:13\"}', '2026-02-08 23:09:13'),
(91, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'11054198-hd_1080_1920_25fps.mp4\' (ID: a74b933d942c0b3d60acb136e2a3a1ef)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"a74b933d942c0b3d60acb136e2a3a1ef\",\"file_name\":\"11054198-hd_1080_1920_25fps.mp4\",\"previous_status\":\"active\",\"new_status\":\"revoked\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 16:34:11\"}', '2026-02-08 23:34:11'),
(92, 12, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:35:54'),
(93, 12, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:36:07'),
(94, 12, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":2,\"ip\":\"::1\"}', '2026-02-08 23:36:11'),
(95, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:36:43'),
(96, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:36:57'),
(97, 11, 'FILE_SHARE_REACTIVATED', 'file', 'info', 'User reactivated sharing for file \'11054198-hd_1080_1920_25fps.mp4\' (ID: a74b933d942c0b3d60acb136e2a3a1ef)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"a74b933d942c0b3d60acb136e2a3a1ef\",\"file_name\":\"11054198-hd_1080_1920_25fps.mp4\",\"previous_status\":\"revoked\",\"new_status\":\"active\",\"recipients_affected\":1,\"expiry_time\":\"2026-03-10 10:00:00\",\"timestamp\":\"2026-02-08 16:37:04\"}', '2026-02-08 23:37:04'),
(98, 11, 'FILE_SHARE_REVOKED', 'file', 'warning', 'User revoked sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"active\",\"new_status\":\"revoked\",\"recipients_affected\":1,\"timestamp\":\"2026-02-08 16:37:54\"}', '2026-02-08 23:37:54'),
(99, 12, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:40:46'),
(100, 12, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:41:05'),
(101, 12, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":2,\"ip\":\"::1\"}', '2026-02-08 23:41:07'),
(102, 11, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:41:28'),
(103, 11, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:41:38'),
(104, 11, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":0,\"ip\":\"::1\"}', '2026-02-08 23:41:41'),
(105, 11, 'FILE_SHARE_REACTIVATED', 'file', 'info', 'User reactivated sharing for file \'billy eilish.jfif\' (ID: 7793005c95f21a9eeeb35616488b7991)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"file_id\":\"7793005c95f21a9eeeb35616488b7991\",\"file_name\":\"billy eilish.jfif\",\"previous_status\":\"revoked\",\"new_status\":\"active\",\"recipients_affected\":1,\"expiry_time\":\"2026-02-09 17:00:00\",\"timestamp\":\"2026-02-08 16:41:49\"}', '2026-02-08 23:41:49'),
(106, 11, 'INBOX_LIST_VIEWED', 'file', 'info', 'User viewed inbox list', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"count\":0,\"ip\":\"::1\"}', '2026-02-08 23:53:52'),
(107, 1, 'LOGIN_PASSWORD_OK_TOTP_REQUIRED', 'auth', 'info', 'Password verified; TOTP required to complete login', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\"}', '2026-02-08 23:54:05'),
(108, 1, 'TOTP_LOGIN_SUCCESS', 'auth', 'info', 'User logged in successfully with 2FA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '{\"ip\":\"::1\",\"type\":\"totp\"}', '2026-02-08 23:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `shared_files`
--

CREATE TABLE `shared_files` (
  `file_id` varchar(32) NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `receiver_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(127) DEFAULT NULL,
  `storage_path` varchar(512) NOT NULL,
  `enc_file_key` text NOT NULL COMMENT 'File key encrypted with receiver public key',
  `hash_enc` text NOT NULL COMMENT 'File hash encrypted with file key',
  `policy_json` text NOT NULL,
  `expiry_time` datetime NOT NULL,
  `max_decrypt_count` int(11) NOT NULL,
  `decrypt_count` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `last_accessed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shared_files`
--

INSERT INTO `shared_files` (`file_id`, `sender_id`, `receiver_id`, `file_name`, `file_size`, `mime_type`, `storage_path`, `enc_file_key`, `hash_enc`, `policy_json`, `expiry_time`, `max_decrypt_count`, `decrypt_count`, `status`, `uploaded_at`, `last_accessed_at`) VALUES
('05dd13f45757f0d1949bcd02af59403e', 9, 10, 'IMG_0306.jpeg', 53682, 'image/jpeg', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/05dd13f45757f0d1949bcd02af59403e.enc', 'Skdv4jNIpk7YrGH6u8Wl6IEydL7+AZfZOsVlLUR7h9RC8FuXAePWmo+L0lCk4KR1x7eDVmJySflxTlN9kmAyMnt6YUGmIezOu3Rj8pxCbjhEj7emn1GKnZ1feMTD3ofIhJ6gskv+q1wX+QLauZHou6MvS1NqymRJT+HOnm0wym8QbVGAX4398mI5c4WXv1INPqr24cC5Se0Ue//KFM20z24ISmwY+RK+IY1vEX3AYFkWvLYS8j8pfOfw5qsOl/hukYYDthifVqTjP/zfGu5zzFvKOrJattEyf3eIqtqIyLEHHqHPbHF06z01AWBhtjKIs39AlBu2goIiR/iskypYpw==', 'DAioqDwirwHdcQWV0chYVmgYwrzW03iOwDu36kdP6oJawzHW3ZCx9D5ggvqqtr3HNdrIYShxI9CBNYxs', '{\"expiryTime\":1772157600,\"maxDecryptCount\":5}', '2026-02-27 10:00:00', 5, 0, 'active', '2026-02-08 00:03:33', NULL),
('694dbe5e26e22d6c1f1b74529f679dd2', 10, 13, 'Lovely(PagaiWorld.com) (2) (4).mp3', 4907356, 'audio/mpeg', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/694dbe5e26e22d6c1f1b74529f679dd2.enc', 'TWufj0JKRk8UN4Ye3Tm97SZqtyt/Z6nfy4i1OcmF4TQXJh8ueuxGFNlYieUanBY68s8TNXpV63Hg+9qZr3nNXHjpQKHXh/kuGUf8oXGdtVakrxqHvB75SOPUl7so/OAokF1WG+h819lDNP7eszId3OVYPvI/AAKClptc6K9+ajSxhE+o/OvqFpqF8GdNXXTE9o80QOgeNclvOcWUCc8sfA2fXRhWIXRY52h/Id1zi3AhSeZc+cCuQhgUM/87hjAIyGPiXunWyLyF8bW98D1yQxMVUwNioaMVmMe4kmFnTapZcfe2+cYmXFMa6DDys+f1J9LRDjHoC4WPzo9YN1Jstw==', 'OPou6DLRC/QQCH4O+0MGxT6nzcwlJf9NyW9FsyFZH/O+9FoPjSsmQbCE/iJyMZbJMIg+g7hSMnWh+ko4', '{\"expiryTime\": 1770568380, \"maxDecryptCount\": 5, \"expiry_time\": \"2026-03-10 15:30:00\", \"max_decrypt_count\": \"5\"}', '2026-03-10 15:30:00', 5, 0, 'active', '2026-02-08 00:34:20', NULL),
('7793005c95f21a9eeeb35616488b7991', 11, 12, 'billy eilish.jfif', 8969, 'image/jpeg', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/7793005c95f21a9eeeb35616488b7991.enc', 'V/givH64MCkaKkFkjYqyWTarL9Uk5hbQdXMzN2VDVS/tSq3TkWJRoMS4+GFjHc6M7L00KibWWSggLWlNRzhBesA95LkXJmIUWKEYLlBtKuDS1rlxjmb+U182a+pON5qfFpDT/7NCcARaF7NhBa77k5biwwFlUBOHZcd8pkk0i8R79FJeVt7peV3913ijYljaNuLG5JymmG9lSI4cLq8z1h0Q6KIht0MHwgkaiCAq3R1jpp705rvNmplyFKjZR8JYCKDxJ2dsYgx5wtMJaIva2ZieYJX1ao/1AEx5qcvPQOMpYaPDCKON6Wim1MTgs5+AjuRkcYpQoRVJRAVbdnMo9A==', 'Rxk5ipn1EMk8MYSzHKqciXz5BsUhBrpUv+dVOc5bIBhKfPih2R6b2obwm9U+4lYpyC2pcrFeqwHRX3Y0', '{\"expiryTime\":1770627600,\"maxDecryptCount\":3}', '2026-02-09 17:00:00', 3, 0, 'active', '2026-02-08 00:38:20', NULL),
('a74b933d942c0b3d60acb136e2a3a1ef', 11, 12, '11054198-hd_1080_1920_25fps.mp4', 6132688, 'video/mp4', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/a74b933d942c0b3d60acb136e2a3a1ef.enc', 'ILlsn2o2Ay5dsfGdXvzlHltS64TMIuNsMwP41roPaG7AE9fZrhwf2TyrSaaoMOHZlU2l3i3nssVhkdFk/BdKOTBQFhGcFpv3kxxPSH2Yp9Vo922onfNhSNmKUzUOEGTRBpws5id+4dFJVgNV7MZeFPhs+tPVwTDyh1dvloOIsFSQSYrMtPtjNpsfeNZsqr9rzbdfMceh6tFmjXQeSQ76It2Pbne1wA4EgJIkmsiDGv43BD+m86EioR2JjdqehwkJvdkukIuWt0Tm3UBldxoaXAJb9thIfSSQWfjTt1lwHiYfvYfjUYVW+PZcdkVS3kkt5QKrGssoiJypOIHBX7QETg==', 'tKQaxm03aekvhLXtMpMwgP2/uAVH6TXU4d7/ljnB8jI9WfD5Id3bIdJZC08nDh3ac+Xc4NV7/GX+3i4B', '{\"expiryTime\":1773108000,\"maxDecryptCount\":3}', '2026-03-10 10:00:00', 3, 0, 'active', '2026-02-08 00:38:58', NULL),
('c638713ebbffe6e0a1a2c45b03b1fce1', 11, 9, 'Internship Report.pdf', 578783, 'application/pdf', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/c638713ebbffe6e0a1a2c45b03b1fce1.enc', 'gBQqndOJJfcv3mEKDZt2vXD6EF2V018eAowL0//o3b/HA4u1h3QF79lnob56MI4gZmGse3oPcnQo083XqQ7BsQds2KxIN7WHTJoxUfFHbiDtxa6ST+RQ9QfBqXrOt+H7qmtnBVMBqkaxmXusA3ptXIUvV4Uxzln+2f9F7ecr4apjGj3dKxS3o1Y1vyjBJXBbS/dsQnEmv1csPsIJgaew9XcUZKU5J0DFOeu4mNOY35rJ5DwVZydEfFij2kUqXPWsfsMaUzyWLESx5w9AbwfyZC/MoZaiywtpbxf7jn00i+Q+fwILEA1HKasvlMbVDaUX7NaLOgOfN8B49IJvzBoP4Q==', '5oHEcympjDAYUSUPTGFxzxwt54C4E11ul325ZMJmhuPTv86tsS07omtEEkXzYNDbHE+SLPSzwhDyvX9a', '{\"expiryTime\":1772523180,\"maxDecryptCount\":3}', '2026-03-03 15:33:00', 3, 0, 'active', '2026-02-08 00:37:38', NULL),
('cce6563f47ef1b91df59593bfcbc9d50', 12, 13, 'Ethical Hacking.pdf', 2531316, 'application/pdf', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/cce6563f47ef1b91df59593bfcbc9d50.enc', 'PaoBXbq9jDiFe/zbOLt78ZY8j3Elmtd4ypBYn4X4opWI3awU8cOLQqqJ0pFcwHCakT8ZMDg1JeFZWZ+VgXxzxaBsO2+q6ZAAmiWpDvGAcl7euKahzGKURIj43d4M+shVgSJft67FTOv2715WCEp/usiAj37iIFTmlGBsMggyxJEJAJU+zRGqPboo4O6YyyV0grHT3YgcPyjZ9r704e5E7MDg6KdpG3tKCPS1tydy5mbPdSDIJX098CKUMGzdR4yOvuHe4nb9TobLWdi0ZoaF1xUE+0IXak3JZt86rF45dCJxGZdDZvxMY6Cr/fp5lTVtC255x88qpRQUNl7O6aQI+w==', 'apD7ORWWwE0aecyLlIxBijuVqn3aJMqzFbsStn2mjKl4JhHo+vtyfADsw0aTmp/yu6JwMmoHabI/iHwY', '{\"expiryTime\":1773559800,\"maxDecryptCount\":5}', '2026-03-15 15:30:00', 5, 0, 'active', '2026-02-08 00:40:55', NULL),
('d78db46fad22a99de090ba2e257fd837', 10, 12, 'Lovely(PagaiWorld.com) (2) (4).mp3', 4907356, 'audio/mpeg', 'C:\\xampp\\htdocs\\FinalYearProject\\api/../uploads/d78db46fad22a99de090ba2e257fd837.enc', 'DAAC/KFnrlWbYYGrayuAz1PfU1MWEBGGewcuHj4RgtwdnJQopmCNE+kndYoy9oCtf2RLdGq8Hw6EVV6KtAp1k0HBPwuubiyCtDMrScV2BHHQWMt/+vPMBkU60TKIZwTmO5y1Wl/nhD0NxSINv3rsobnhGFyQbAQOC4DmbTf0aNpGcndkuoXV17Vqvl/PiNX/CWMa0IWSpT0Cr2YdwmSl9asgyT/gxdYCjX+qzN33n4gBnhrlJMwjrcUAAPlBkQtxD+As/rfGiTQqry7gaPsDoAOOONv3jKTWbjRVSsCPLXu03oFOxZg6v3tan7XP2lgLe2WJIrhqVegpzOqAHHH2GQ==', 'OPou6DLRC/QQCH4O+0MGxT6nzcwlJf9NyW9FsyFZH/O+9FoPjSsmQbCE/iJyMZbJMIg+g7hSMnWh+ko4', '{\"expiryTime\":1770568380,\"maxDecryptCount\":5}', '2026-02-09 00:33:00', 5, 0, 'active', '2026-02-08 00:34:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_fullname` varchar(100) NOT NULL,
  `user_phone` varchar(20) NOT NULL,
  `user_email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `totp_enabled` tinyint(1) DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_fullname`, `user_phone`, `user_email`, `username`, `user_password`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`, `totp_enabled`, `last_login_at`, `login_attempts`, `account_locked_until`) VALUES
(1, 'System Administrator', '+60123456789', 'admin01@gmail.com', 'Admin01', '$argon2id$v=19$m=65536,t=4,p=1$ME0udk5JYnhPWkhYaWQyUw$wjP82ADaIPjJM0KAGGfr4l7ipEp6n+e3wXexKaD/UTc', 'admin', 'active', '2026-01-18 15:32:59', '2026-01-18 15:32:59', '2026-02-08 23:54:05', 1, '2026-02-08 23:54:05', 0, NULL),
(9, 'Nitya Sathu Om', '+60125176084', 'nityasathu123@gmail.com', 'Nitya01', '$argon2id$v=19$m=65536,t=4,p=1$N1lKTGxkcWt3bTFYVVNJRw$mndnOnr15fk13wvzTLjUBsOdqBeM9nNIphCVfP02zso', 'user', 'active', '2026-02-07 23:35:18', '2026-02-07 23:34:58', '2026-02-08 00:31:48', 1, '2026-02-08 00:31:48', 0, NULL),
(10, 'Danya Viki', '+60123456702', 'danya@gmail.com', 'Danya09', '$argon2id$v=19$m=65536,t=4,p=1$TndZQmZuVi96a2lvbmd2bQ$TWrd0QhU/8YWNy17IcblaCzIsUftrxOn+7jFdmd74jI', 'user', 'active', '2026-02-07 23:40:07', '2026-02-07 23:39:51', '2026-02-08 00:06:46', 1, '2026-02-08 00:06:46', 0, NULL),
(11, 'Daniel Wong', '+60123456703', 'daniel.wong123@gmail.com', 'danwong', '$argon2id$v=19$m=65536,t=4,p=1$TENROGdEMlFnTHouakRQMg$4H4DAc+1kex38xKQKVifFr3pMqA87jDdwESn8HX5q5g', 'user', 'active', '2026-02-08 22:45:13', '2026-02-07 23:41:38', '2026-02-08 23:41:28', 1, '2026-02-08 23:41:28', 0, NULL),
(12, 'Nur Syafiqa', '+60123456704', 'syafiqa.nur@gmail.com', 'syafiqan', '$argon2id$v=19$m=65536,t=4,p=1$anRZRWtCMHFGYzMzUDMuVw$14X/41612NsR8BGe0ElkVhyhhd/YzlxvAEbBoQpQ4Bw', 'user', 'active', '2026-02-07 23:43:54', '2026-02-07 23:43:38', '2026-02-08 23:40:46', 1, '2026-02-08 23:40:46', 0, NULL),
(13, 'Ahmad', '+60123456705', 'ahmad.faiz@gmail.com', 'ahmadfaiz', '$argon2id$v=19$m=65536,t=4,p=1$enN4TFRCR1czY3M2Q0FuaQ$TTJVUEOp7hY+rLkhOWszSGjRWYBqZSvzFrtOcVCUjIM', 'user', 'active', '2026-02-07 23:45:59', '2026-02-07 23:45:42', '2026-02-08 22:43:12', 1, '2026-02-07 23:46:31', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`activity_id`, `user_id`, `activity_type`, `description`, `created_at`) VALUES
(128, 9, 'file_uploaded', 'Uploaded \'IMG_0306.jpeg\' (52.42 KB) to Danya Viki', '2026-02-08 00:03:33'),
(129, 10, 'file_received', 'Received \'IMG_0306.jpeg\' (52.42 KB) from Nitya Sathu Om', '2026-02-08 00:03:33'),
(130, 10, 'file_uploaded', 'Uploaded \'Lovely(PagaiWorld.com) (2) (4).mp3\' (4.68 MB) to Nur Syafiqa', '2026-02-08 00:34:11'),
(131, 12, 'file_received', 'Received \'Lovely(PagaiWorld.com) (2) (4).mp3\' (4.68 MB) from Danya Viki', '2026-02-08 00:34:11'),
(132, 10, 'file_expiring_soon', '\"Lovely(PagaiWorld.com) (2) (4).mp3\" expires in 23 hours', '2026-02-08 00:34:11'),
(133, 12, 'file_expiring_soon', '\"Lovely(PagaiWorld.com) (2) (4).mp3\" expires in 23 hours', '2026-02-08 00:34:11'),
(134, 10, 'file_uploaded', 'Uploaded \'Lovely(PagaiWorld.com) (2) (4).mp3\' (4.68 MB) to Ahmad Faiz', '2026-02-08 00:34:20'),
(135, 13, 'file_received', 'Received \'Lovely(PagaiWorld.com) (2) (4).mp3\' (4.68 MB) from Danya Viki', '2026-02-08 00:34:20'),
(136, 10, 'file_expiring_soon', '\"Lovely(PagaiWorld.com) (2) (4).mp3\" expires in 23 hours', '2026-02-08 00:34:20'),
(137, 13, 'file_expiring_soon', '\"Lovely(PagaiWorld.com) (2) (4).mp3\" expires in 23 hours', '2026-02-08 00:34:20'),
(138, 11, 'file_uploaded', 'Uploaded \'Internship Report.pdf\' (565.22 KB) to Nitya Sathu Om', '2026-02-08 00:37:39'),
(139, 9, 'file_received', 'Received \'Internship Report.pdf\' (565.22 KB) from Daniel Wong', '2026-02-08 00:37:39'),
(140, 11, 'file_uploaded', 'Uploaded \'billy eilish.jfif\' (8.76 KB) to Nur Syafiqa', '2026-02-08 00:38:20'),
(141, 12, 'file_received', 'Received \'billy eilish.jfif\' (8.76 KB) from Daniel Wong', '2026-02-08 00:38:20'),
(142, 11, 'file_uploaded', 'Uploaded \'11054198-hd_1080_1920_25fps.mp4\' (5.85 MB) to Nur Syafiqa', '2026-02-08 00:38:58'),
(143, 12, 'file_received', 'Received \'11054198-hd_1080_1920_25fps.mp4\' (5.85 MB) from Daniel Wong', '2026-02-08 00:38:58'),
(144, 12, 'file_uploaded', 'Uploaded \'Ethical Hacking.pdf\' (2.41 MB) to Ahmad Faiz', '2026-02-08 00:40:55'),
(145, 13, 'file_received', 'Received \'Ethical Hacking.pdf\' (2.41 MB) from Nur Syafiqa', '2026-02-08 00:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_crypto_keys`
--

CREATE TABLE `user_crypto_keys` (
  `key_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `public_key_jwk` longtext NOT NULL COMMENT 'Public RSA key in JWK format',
  `private_key_enc` blob NOT NULL,
  `private_key_iv` varbinary(12) NOT NULL,
  `key_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `key_status` enum('active','rotated','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rotated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_crypto_keys`
--

INSERT INTO `user_crypto_keys` (`key_id`, `user_id`, `public_key_jwk`, `private_key_enc`, `private_key_iv`, `key_version`, `key_status`, `created_at`, `rotated_at`) VALUES
(33, 9, '{\"alg\":\"RSA-OAEP-256\",\"e\":\"AQAB\",\"ext\":true,\"key_ops\":[\"encrypt\"],\"kty\":\"RSA\",\"n\":\"wOwv_fUHBunAfheFyP3a0roRFF6qnKUAIXEuTg7_fg5Ad2CfE3ptry-zcfE6sVZWTfWFwEihKmNKUlkZzibsBelh-Zdds9Db0eVD4PqgTbxGFzdJv6iYzQJp_pOs-jNytdz3od4HMvnviMNAj7zKwmEMTUcybjzta_gt53TNFejCXEKiZWOuep9N2NovjDANeI4-k8n5lylYRCTJdqIKxuxJcp3EzWEZILcMSkk6xvc91qzf7PRQfIYIevWc5yZl_eMdN3emjLuhvRnNqYuKRYzvZpTrb8zaDyz1X7F_SW4XJKDFOyVccbbJAfy1lVxZBS1cTQmHqbJ2YEC31qL4Hw\"}', 0x7a141963da17d311480ff71f0d250bd0c34aaa14d2a9cad420f06a2a81d3f98f856620ce8b15aa4dc1f99b92dbee550d339595c0c7d5d8f6707d911400fc08e330856308904655e1815b276dda02dbb4b34d1e133103758749e155d618113862ba9e99d7607fc152b4ca20c82654396f5e6353d60e8f503e615e9daea79f0d1a5f0a418092782765238a0c1c343fc1c0e4f90bf1d7b390ea25fe55db4573e3210b05f27139b3bd489c23514cabdf87ecede8d2e30ee0596e208f95ba7e742a9d44ce665233300ebdc9751f144a73cd7125df233a056df3e601da1ae968b34b0eaacda60acba984f6b5980f1f02b4258280595ea2ddee2ab1f40b93fa3a76c54fc57f80c0bc2a2edd5d538b9c42a0e2a26ad7975997f1122676710bb57f8fca6652607c21fabffe79ef6cbbfbe4d6472914253487d2541ad21b39714a7114b1473c053d46de528adbc897afb4d5e1cd054542119ab034b95343e93ea79f46ca47fa11e1c001355fcee5785d65169a8fd6275838549f385ab6c93d1b9f98acdf92c53912ecf34ed7ce586462033987025c4e22bc4794e58194b62387fa46749383f946237b28d32021956a293e141326e85a9b57f8b3dbe122c7300ed327f12f4ca1cab57a4805aa15c49f4d2cb31c4fe03b5188593a50333a91d38de4c3201ec023a26d1813498cd16e6c87db9e5b6292e1629ec3ac30bc88a2d591b03040dfb1e8f5a783cf4abd9d963ee75e9be4d9e9e92c766a4638b4a64d38b78aa346881fe27407ce186581025776f8bcf2921c2ed2c607c767754feadd3dc03d54237eab29ec3241ebb1dec357aef0e351c6b07efb18ab78b1e6ffec33f157e1724d508e85fd12a4c81fa67dbda798e993c5cd4f47411047a440324258a66697c3061df04f191d1db4f11947dae410cf6bbf01097aeac8f72927adbf073450a67aa22209278fc6599736c3d99b386af99cc61db1a1fbdeb7b45ed748ab312314335b8f00f2f31d1ed57d1dbdbf7e2215e09bc7273100b9f2c33613f63961889a6d3362cbbaf28614d02fd1afee9128e24b4a58f6ec4c97fd43b2cd7f89688022412978614136ed8b37a3c379be8af1c5f535aa90b6d16a0caf49498f38ab76b23323eb7c8374449e3ce73b6d1057d51ff5cd0ed6d9b78f22a65d216dffcf367760bebd3ce8b0762667cc77578076263b13679333f633001a7caf3a457b231e3665dd0afd4586b016eac668f353346e3c239d468a0faaee99eff3ae566f94d4062445c633ac6369b55320d58285e5f49f93ea1ef4ed1105284369ca545ea6b2e7dbcb80c8e9042e84bd4cdc052eb59bdf75b6539f6a1d6c0723fbcdee86dbeca363c2ad8fbe09bd9e7e8fd5737eb784283401f3999a9f3dcfa97048306bd7921cb91256118451be0e792d46fe8bd96d11153130e0253d694f4d23d95fd6c8d9571ef9557f746dbe3b55a4075820166cc9487552090a6fb4ea5a38f0b283263abe7b1dcac18651e8e0ed9b5fdbc531f3a16bbfbf5ff95f2623e928cc43ca616f2cbc76286c7d4f9c4ed838abe9adf54066bc830ddabbba4f7f82af94bff78c166a18f5042fc277586be798ac0ce6e4c5dd5059e97ee6c62bd44d4dc1a9f48da89d0788b5aba6a9aca7c3e710160d609228685e7d04cf0899318f304b3ef5f1d58b2cf3b94e004400dd3cfa750fba4cc69d42bf31fc20ccd56bcbdd6e641b98e9adbdc7c2cc256b6792167faf65f0c3a223305544166b, 0xd1932cc5dfc9c640558dc9ed, 1, 'active', '2026-02-07 23:34:58', NULL),
(34, 10, '{\"alg\":\"RSA-OAEP-256\",\"e\":\"AQAB\",\"ext\":true,\"key_ops\":[\"encrypt\"],\"kty\":\"RSA\",\"n\":\"tsnKIImuJ3kUQIkrapBn_pBqgJ1uS8m1HGaz-xlhwTWtBXKfHq811lWR2T97dytDDLcgm7yZu3II9fdBmcWux1xiwvFw6e40rjVi3QYR_9k4hI_DVlgfGXUTs3cPqxjXfIpYc2y2ecfkeDNLt9y3cnNO1nWvRgg1IOOsjRUgJCIxanGrasrabYtrOf_MinY7oVp9-GLdaJTyjUxgiTDtxRtplxPKUX27cCdSzYPGAdiwQLzGSwBWBPL76A2T1rDZdyI2xpQ7ZYDBgmJufKPQO3Xg-ppIlksgoQBe-DOgI2YbwuqyY5msdrHjfhy-rpYYLIBLDWwBtorsZzP3_ShzEQ\"}', 0xc938e42f369ddad17c70d640726730b25b57e0685f6d6c6189fa2aa1712690be02876dcb53ee62e7a6f8309e9a83ed91b4224e7b926b8dde2299a9eef1b4be8e92ac7f70ad87ebdabe91f8b0437b083d20a1ef00a51a963e49019c8042eef7feb0aa1175acd50ce175faa5abfdb7731a81e537863c63d031291736cffc4b3c31a75dd2006d8db170864ab93ad917f94fbd8ec039d2c9dddde75857b8d7575373c29e2abf355e5a45a93ea42dd3d2dc5c8a50c3c5ee656d85dd2b680b2d3ef79d62216d9383ec1f4bbcc7c4b562efb35599d2da517b59eef824bf16fd5c2ead9dfac58e553f6a37d8f97efaa23458930e8be58feda52165c3c15b7f61901a1671da6b0e0298ea34e076bfd5e10fa81c17e1eafefa7a5a59b2e275fa2ce9af42b7c9169cc5fd3a845a9227ca22ed323a9b2dcb65e45d4463471b9258a9a53fe66244f7ab2831f2841782085151ae4445144ccd8039932f849dabe2b6beefe548d0ddc916cc1f32ac3e0cc8fa15daccce83b1d0457695841a57fbe423e90fa9b949d99085d849a20f14a6f08b17ce874ce7a09dd57d2b7fccdeb1ba0b4afa617b519f3812db0df2a30bf23d553fea2879365064c44fff674b2e1c1308f0dda1133b22682eae6d01552b7a169841d93bb221bea73de9807e370224af14f1e392a554814604d907f852e7f794a0c4771da9c34eb02db07d3310a07626af5fca0501b349a18aecce6a4dffc0d4ff7b50bdc083b0c496946b015c96641deb81eb773e6ebc21017a1f24b3ed51b1699badc7e3aa018aed13a3f4f469ebaa7e9874ac2acf0b5e70e90b01bd3146a5330d8d224a07455bf20d4d75c8e333d2ded39d4143f0b0e54560c228f6940654789943ac1c2a4bbd14125c469696c72941aefc115123a25bac32c6884767c343b7a87e40ea7dbd20b3884d33e5b2ee0a9ade1f856c3607dd513950289b8c05362d8543a1dec258a9f830a1fa1f1836eef40f539215d56049e4e82d79803b3461fc1ee692c4322a9e304490c3d17e7c8ca0e78ea65949db91e43f4c2eeda201b4011b7db3a3ab141f11e80bda1905871fcce58772cec046f4a0f25f178320ec39078dbf6b6e6f8c324ebe2b315c5a8e108da366c04d045eb1172c371f71c9d8d420c904ad5ee47abe04d7e7fe0f386f27a6753bd00079e36284cb86d1e91cbdc868bdea14016599430c9bab278fa5035dcc33e295989fd293ed8c8c8d88437e3f18ac8713f6031824f2947c98badef98e649702c2056b70d9d38d97629fabd7cdbe24d8f69a3d4c0778538fdf650a0229928f62dc237042ed3e3b0bd703439bcc7c63974eeb600ce6e9fef49cccf9b7e93b83239169505e0ca2d34ca3b2726567f34893d46182ed1fff89319a2a254d759354036c128c47aff7df7a42b135bae714c0f7e70eebb488706194800f094b6055f85f7cc2d2806927fd575875f8483968de765d0b22d9fa32daa27d20af7fd989c32e322c9c965804f6154e4f48c267610459415842644deab2fa9552335e1967847bb0a9a8e4f1a67cb8c5b4227a1c8e721f2e3ad3756486003b5af929fca3406b50a532cd3b1c78d450f6dbbe36c1b62e1588de33cfa3bdc43ea3b657d24d0ebddad21665158dbdab6b04adc0b91b7a5098e607be474e0eb4b7b018c3db45d7bc8879ef63fac9a2632180e4335abec2c90bb7af62288be38db4235be87d6e7a8ce6a52780b063de8a11ea4c4aebb705caf1c421adf5, 0x42bebeecf7474d02ae50ae93, 1, 'active', '2026-02-07 23:39:51', NULL),
(35, 11, '{\"alg\":\"RSA-OAEP-256\",\"e\":\"AQAB\",\"ext\":true,\"key_ops\":[\"encrypt\"],\"kty\":\"RSA\",\"n\":\"zZdvdfeSC9BsbJ62zT1uYa--TE1jVkmhd_TEHHHpFUgCGGPHFvu5d4upUrJZ5TA3Bw1Ff9dIPG2WfNYDVn-lduJuwmtp2bCniOjLe3Gy-ZWoOd_CWbtWknLg0TWMOOdipQZYflgMk6nfp1dxzA5me3wq-An0ZnGOk4jmePbhPKrYf4boR-QE0hh0EdnJq4a78t_ZBU-7URmYW1Wgokrfare7lrr2IkJJgf2T22fWti5cXwblgqiDqfRzrMr_bnF_KLPnuqGWme7K4WPGabZTk6sv2a1-NzWZl3W6FaV-n2E3CnfyPNhPOBPjIGVQbghQelVxm_7LZtWmcT_GMfPt9Q\"}', 0x7280ac84ce5fc8aa44fbd916bbdf53803ad3c2b3ef10d087722439ee9d0401668f41cb0ee54f5ac84dbc737f99799d72f66533d7a7fc977a4ffefeb3f3814f79a26daf44c501920c2740c73e9b7c364895f393ed871928c33e16633b6371c66c5b507ebd1728a6ac787aeca7d25ff1c38f5d5d88358b74847123f8a1c176b558f409c74cbdad61091592432e375191db31aa6557adb41afb541bd777db960f839791a7bd616cc31a7af1c208431e97149c52be4294b5de3f4054753ddad91023401881bec5ec637a292433a7aadc01a5cc4f9aaa61fbd5ee742f25f7f8818bebe1f4e0fcc35852592b958eb4fe9bf34ac1aa146ecffeda42dc291a5d924cb0364cf21be350c4e156ef1ca2a1fc219d19cbb292a3bc988a2fec7ee7fdb00693514d530689f96c13af5ab49f585a546ade02b7b411e7520833a64797dc53ff3c2dc53249239f918760ab34307fa19631304eb67c60fa5825e14761d74e0c4c9b7dd2bef001470c0c2441303100762aa0dafee64e344c6989d6ce0bd1cdccdfc4d6e6dea2745f7c9ea038c4401009487c586e7594ab449c5aae109ec8fcf7cf8c8a4650e4517afccb630c46fe74e43f129f02604acfeed3bcc31a9edca16f31d173880759baa9d113466136ff94ea89fa7883eb798d35e91bb670f879f68e33dd53e71e81f0b2615e84f93cb3443b313768385a3f43800c337ac686f9df3c9c760db9f51f2ab313120e2630ca7058fa2969031b36dbace11406a41d5bff4a7dac587cb1d7a23c902b44051cdafc1debd2a01c8897649ad1b255302ab478cf9fb577f01f336d198af3883c38afeb278f56d66ebcf1a6c9201704a7388e579af5e47beaa54b1cd3a9a1e761c0604f7e3fa3ac87ded8dede1982bd0188d024d2a86f8225823b02424df93cbf257dc710bc59883d623d1485c00147b6d1eb4221d809ff78cb8c091ace064241377bc361ad84bf65b4b54982c10c2e4151a0246c4c257c4afaa61bd5461dcbbb0c9838f5ca3d793798353526c557ab82294b2bbfdef561b481e8b72db9ea65131dd936f3adca5d43cf45beb55cd67558299530a260f91055209898a12db12a881f55e3747d267d253173e7566a2488b24f6a34f5acfba35b2c2ee2965a7d2511ea9e971718ba18e0b3a0f0f753d464259b01f6d73c42e561e79a449d543bd8e84a168d9d1ea63cf3141f6cfe103003ad24066cb16a1a2a26a05940d5877456e319944969e5ce00d24127f9581284bd5edd5078f01c51f5740d36f4314c2397bfa4f872e5271622ab576c29556b218b2a8a6bbf02affae38a73f634fc6f4982a6075e955658c1f7a7b759ae73179d7c6be8adf84fb4456854baaf0cc1e20cc349bdc916398c317d5e6896491b276beab68006b0926b8339577eb636ace98b2c2df3377ff49ca2bdb7bb555ff7e0e951c081f7dd324db6bf89673c9b95235371fb88ba3329da0236a789523f19c67947a444e334242517266e3486d3bd18d668b4a8df5589181444069ca17a1479cdb3350239e54f2b0bf484d9d6a8ccf38f3031e369dd8ab60efe0c0564b9ac130bf2781e24488172aa63255370932f7fc8b6d5bd52204814f0de07f0789997451344ef1e1ba3aa2de8334f4eb3bdb7ba064579439ec5a95d2b65f5b4753a1edefb1377ed82d32344fadedf2867ee44fb294a19e6b4d1b24794ef939f620715d76b2c5bcf44a94dd931c6d123a6f328d88cb5b13d95e0c9dbf5adf6, 0x3ad9111b0553fbd83b02cb8f, 1, 'active', '2026-02-07 23:41:38', NULL),
(36, 12, '{\"alg\":\"RSA-OAEP-256\",\"e\":\"AQAB\",\"ext\":true,\"key_ops\":[\"encrypt\"],\"kty\":\"RSA\",\"n\":\"i4yFY00bDdBPExxi56yuhHb_CNsw_gq1YjE3qO8fLeaHS3NUZtwL7AOipsjad1nHGg1s4I-bSFLKx5GfHoEZjg4aYFgrYIUsMpVJP6P7dhHXGPVjXvapdlLQ24BNvbm5xi7X848INBeauRsgayLYNYoXw1hpS0jdWjDkEdBprdSQ0oxwSDV4lFk9DShDu7SwuGDunKpezfTl45Dxs_WFVOXVfQa5h3042auWgzSkxpnQSgM6LujHwkaKLDlcVcCQWHV46MQflpzsBPypeBx4pFTMKb_bIQf6kp22PQ_uP0fG6DTpawO1g0brzkuZ0E-uiLpHagXFV01YU9vl3pQuaw\"}', 0x22d9c788969c8b58c9affa4e53190b98171a883cd5bca4d3bd6087a4ddad174b2fc7b0f46bd196865066fbf730092d4bf04074b8d4bb78a03167c9c712d80e7a50c8c36f848b5198ae89746dc3cbad83961ef7f0799a2c9031e4aae32634c29d6870ea8fee64c1630e78209e0be1f7f09d48329cf6cead7e8bc5c3f2fa0323823dadd0cf92d3e5de76e521eed175ed1d6911279517e441a4771dddc7151d43d6d65a0229c886a242f319be6999dd518ad7741d11c297156adfcbb5f125edcb517684c1b3eea9abf140bdc87a9d989fcc30b71d2aba9efa188492504d39fd6b0631e15f9885117d3e5c8af60b83167fa43d20cc4922f680af76fc361ea289ef57ebd6832021d9edf678136b5cc1635ccccfe67c15aaff7e9b4938c7c4ca991a0f819309c10949b6cfa2229557f8fa001122ad8616610addc38e8d115bf8bac4ec86405c60455628b2285fa78c5742f70bd48bc0b3860216b367d4310dd82d55cd2c3138af8a524f760060dd8c0d8361b47fb549ef98f09c9f84793d3669c7b04e5b7f11b3ab611c8ae0bea67dbd440c6dca46a18873e444f4a056756bf296aa3c951b1c5fd6871f446a0d05369ac48f4cef7308ad89a699098eb6d2106927fda90e843e1177ef0c3acd7bb2cd47da993afcd3c469e438c6b6e44fab4a6de9c4ad553b98f6222b314c85369457ab27d30d3898ddfa17b7f72170dcbde285e4aefe2272c5887dcd56251515941d785f3649707f0cf4d78bb97439462183c1f1dc887a0b0b9624fae25030f5e6990df3c135510cd4c35fa2cb79d31d545597f608cf8c43d0bbd5c3941c4e2b299ce587dd9b84a98d45aae93aa9b2011d917a84d6ef72fb59df07ef257116bf473998d54568ff7657a2d43962b2c4baecf41fe2a148160d733a8a37600ba9bcf9d709f36f038813c555959f9e5e539d81626342101ca549e5e99ae786957262206dbb6e8b3d2aafa16cd34a67e3cfda453cc71f67a0f433af1bfd5bac0fed07a365c5b58f32c9ebed5c44a0512a6ebedd48631582800859ecd107b0b1277f020fad724f9e8f6ce7b692377090607e7ea3f134f3378a760a9d4f9e5b71abd1691a61be0a223c5e6a7b0f2ea05d7c82e37d2a790d5a29386b12b651119c9a521b10c8254170d5763180204ada6a2f50e2132af6368e880e5f967753bda105c834fd4b85b770bb452e021d73bac3baf8c65fbff1e8cbaf56f02517768f52e6e87028c17d9ee803fc0afd5e2def36840876e8e11dceee3e903fa21b2d2140991c8b56594e72fc51c976deb49eec74519f47df3c5b565eed05ae460451182bd9462e55f693daf589e84b77662e76e41fac167ed2e8f1875a79f83643b19921570fd24c899626b6895bf7fd3d302d1898ec46c3481dd2cbc1182069c9c96a85e4e25a0163b6cc0102d1e7df13971df63e295bec5be2efbecfbe06e13df4873cfda9d6e3a2475372b1ed75ee26625267a4badcd71dc3448454b8a9f4ef277dd3bcf6aebe6b40e1f054a6f6f9cbfee07e0a56a97c79ca5c6a392dbcd2ef9e15639aacfdc3773061b526814c059b4b117b4a90e1b2f849dced56f6d224878c2452b863d414f47894952394edaa40d2cf5ec4bdc8a97037bb369b048af3fb08db5f0f3820be67ece0f57dc44e4326e843a1caeb871913d88e87ee35d1ec34dc7b36eb1dd3ee3c7ef8dbf9b88ce2d025e863a75fe910c1d404916abead4d14a05bbd0a25eff0248fca8b, 0x038eeee9209596cfb5f21caf, 1, 'active', '2026-02-07 23:43:38', NULL),
(37, 13, '{\"alg\":\"RSA-OAEP-256\",\"e\":\"AQAB\",\"ext\":true,\"key_ops\":[\"encrypt\"],\"kty\":\"RSA\",\"n\":\"21SsNvPS_bCVUMZdEOFqDRuYDei2J23VgMQ7cVKDd7eXOszL-O7f8uWEcquNti_wiAyG2sGVxYp6Vj1WvGKlnot4GCAo1DuuAdKFFeT00xUUQ7nzQ8LT0dcV28RvpPlzQcJnPnyAIEHTgK9Jm3EnjCqVNm8nxCQeTtSs7oIX2-uBQE8MPCCFUErp9cqF40wg4QeIFdK3lWZZg1Z9h7QQNoEeD0o2qKowwDAWBqDEhHpopGTzp_YXQ5Rh-MDNtZEVxC_1oPsz-AAlwMtjuO7veJF8JISsg9uqjIvRuu0U-cKaaqujS1HJFUksoxP3NDA1KtNCaTzRPqQ19xFyHR4JDw\"}', 0x02425c619998700b4162ed1212d3354a25f0a510dd334b98662cd4b3c8da04fabd2c909a6892b9a4443a04f366505f6e9900b19d945290183063326287c2c4a4d29ec8dbe3d824170191890e803411f08ff1857c5027111206ec768c09ba0f2ddf94b3977498787f503774a82f16f34ecce462f54a45d293f406be9e212e96eb76b391e850d33d0b0541d15e6dc8be761b9c68f20e2b9a570e82ef2562cea1625fdf7812c3b1c1b231c40d99a7a4955811445d6c911b3f61993e68a9828fe6a00c68e74f01bb520728ffe733f96d8a527ae9e28289f9e0d3894ecee0d7ecc1dadab576c9cb5607ed000c3a45249607b81feaaaa60279e1aead0c167b7b0adcc55150af75f402ab87094c84b97c57ea58676feec3dde3d8a213f85d904cb28f3d583bd1230670f987e08db53c19a50d5f7981ffbca6d4d1eb676af0d3d669d5bf00d8088a3a0013f2fa873143944925f19ba861faf6820dd5e5804d5db9071bf56febfa8ac8143829917327333bd532eae281642d88d3b547e05dc143e40a53894599d55692a2a2d6f8025747b296287a7cd723cbaf24d912742e085fda8ff011e7dcaa7575dde7a4ea9d159004d26519a7318259ee87f495ec56381a5372a641bf113bd43a82d0149e196be3c68bf557c13d4adc5e6c108056b3a81c30d9e07ca0cfc81d03f6bcea49421b2a37284d8708ea6fd754f491b3d6b817994f95b21e75d3f48ecabca84e28f2107ef8de964be6fdd43f91f5f8f01c8938a013e09831ab430329171e68aaecc3c5ab8192e327ad9e0007a4316d7d573ba1d9f401dd177da992c5f41e82c54d36464c248dae34535df22435739dd91ff59cd4992552c075c27fe75364c3b94a24356ac18c01ba6a364f4b70575d05b7d069256fbc19aa3c3ff59eba014f3c359257865c02b92ff6821edcdf4a162dbca418c509512b3e61eff191f5d9ff9205852db2f1173452945fbf4be92a945f13323f121ae55722052927323a54126e9ddf28d7ebaad432f8e78c58f889bf6b61ccaeac958f3ff0fd19053030826b532a8a6564aa13b99b00a47f5f166e0275ad82065238e10bda25c632f9989da2eddeb3c270b300a2097dd86890a46c19b95daa0e9ad0cfd0f5f39df973ceb699efa73acb02278385f211ea5188fe579cb7a2c87ac6dc5f921bfc9f07762af4fa64d6056def00e5c7cf242cd552d6a191f1dd39528b77123edab7f05e7dfc0209ec564662b02b8d7f308c54ab0e9d7a79384e4bc3c07720ba4268ec35a6dd048ae4777ce57849c2efef6070ee536043f79cd26f5afc1b15526560e7dda0f142eb45b121ba3be41e88b803fe9523c092bc397b72f8655c88d63c64164cc98400c8aae8ce2044a813c1931ce869cc40e7dd5e9fa34f35612690a0b92d8b169b813df12178474e91dd07806cc64b067ab277fd3f22b59ed97c8b9cacfc1ae39aec9932e1ab78db931df6a2bfef1ca8481f495e54d6331709ec3c606da62ff0cb3efa8e826f4b246633a755ac283ea076a2731f73cdb0de5a62c2dbe735054e0a99eb683a6de32d43978ef4cbaced829a5c7b32791aa81a3086a2e4ac1c77df973cc5665f044de5e27e110d32746032d7a3577a840776be8be96d1d805b72e54efa34acb67ab754c7f8e13db4e173adf7f4d1e66cd0f545764be2f258e60742b8538c30f338c10c34746ba95c5da29208982e288e3670ec64b15a21db925d0ba234065cb91882eab646735d, 0x337491aafc38e5b653caccbe, 1, 'active', '2026-02-07 23:45:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_kek`
--

CREATE TABLE `user_kek` (
  `kek_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `kek_enc` blob NOT NULL,
  `kek_iv` varbinary(12) NOT NULL,
  `pwkdf_salt` varbinary(16) NOT NULL,
  `pwkdf_iterations` int(10) UNSIGNED NOT NULL DEFAULT 150000,
  `kek_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rotated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_kek`
--

INSERT INTO `user_kek` (`kek_id`, `user_id`, `kek_enc`, `kek_iv`, `pwkdf_salt`, `pwkdf_iterations`, `kek_version`, `is_active`, `created_at`, `rotated_at`) VALUES
(41, 9, 0x9ca80f5de0e56e0559cd0322c32de0cff925420e5d45cc10bc1f5386fa8e471b999f52bae45fa5931c651eb7b25db1f9, 0x06a662f86ba1024e0de85a79, 0x7bd9fa8daee4adc27c86260f40bbbf28, 150000, 1, 0, '2026-02-07 23:34:58', '2026-02-08 00:00:51'),
(42, 10, 0x2d4d663b2beaa7848e33b18830380184cf48f12d769127b58d5d5b26f1b491621a38740d42f3b6cb0e81483d24fb0886, 0x34d17c77ad776b6c6e661bae, 0xa50a70e3a1267fbba7736fd952fd5e1c, 150000, 1, 1, '2026-02-07 23:39:51', NULL),
(43, 11, 0x8acaf4dd285572c6d6d0adb80410591bfee517b51dca17863e0b51342b0d4dcae4c8f19a58a99c9cde3327fc0fc1735b, 0x7656e176e48bc9ee0f069349, 0x29a13e89692507d256ee6f345b5156be, 150000, 1, 1, '2026-02-07 23:41:38', NULL),
(44, 12, 0x3d14391c0ebf62c0d9e2d77c974b1e09f26acc2568b29894c3e0cedc7ae9c82804d7ed01a6df742e55c4823b13ff728e, 0x5bff5667bb023dcc22729635, 0x6ccfc300aeb2a91c1161c042cca13fbf, 150000, 1, 1, '2026-02-07 23:43:38', NULL),
(45, 13, 0x7128f2cc5a3b521a57df4ca2d4fc53e22b559bf18342435e978b3dbe6987c6a1b18ab1ae03e0b8f82f1249dd2504176c, 0xe1da9dcb5b06087d8ec9e1e2, 0xa8b7ec237af376576cd41edf4a112b77, 150000, 1, 1, '2026-02-07 23:45:42', NULL),
(46, 9, 0xf9a29e7203f150ecee25376a80234f0a4c01f41c8954a2c900885056bb5a3af1a420e958936ac7c0eff8292928aebbca, 0x0e426b28e3af13ea5be4a9ce, 0x17aa48a24c8ee5efff98767275886aa1, 150000, 2, 1, '2026-02-08 00:00:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_mfa_totp`
--

CREATE TABLE `user_mfa_totp` (
  `mfa_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `backup_codes` text DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `confirmed_at` datetime DEFAULT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `totp_secret_enc` blob DEFAULT NULL COMMENT 'AES-256-GCM encrypted TOTP secret (ciphertext + 16-byte tag)',
  `totp_secret_iv` binary(12) DEFAULT NULL COMMENT '12-byte IV for GCM encryption'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_mfa_totp`
--

INSERT INTO `user_mfa_totp` (`mfa_id`, `user_id`, `backup_codes`, `is_enabled`, `confirmed_at`, `enabled_at`, `created_at`, `updated_at`, `totp_secret_enc`, `totp_secret_iv`) VALUES
(23, 1, '[\"$2y$10$CROsYIyE6f9a8BKJc9Ncn.YBK6wFAG8Q5eeWu82i9fkrd04uuVC4i\",\"$2y$10$BsmlPVhh8Y9K1guHjP/jq.0rKGRTVuVpbwTsO7HfBTYlTqNTxSzIu\",\"$2y$10$8bEz5u3uGcU7D6HX.WGF3.Ml2eoT2xkOkScZd3CnV/3pCSYkXRItS\",\"$2y$10$OWff.QJMMXKAG2ocnvGuEeubUVO4B4Z6GumfqT01xk7S/37uZzfsa\",\"$2y$10$HFI/bcITknL43Si4Ecmi1ugc5UrgQwOsQiD2bMKyx1Xc2VOAwfuFm\",\"$2y$10$Apql2A2ClgXfHRaQZns5tetCTrZLRzFEqLoWzTOGlpYVLKKP/tD0e\",\"$2y$10$9cJ0X3jVy6Nqp2QRKiEnHOy6BO4bd5.mfoohMlvX8EEiCqW7FqbLO\",\"$2y$10$d0T01QwWtiz47BcR.RhAA.e1vlwp1KyTe6h23DSftsZgyWiUFWi.2\"]', 1, '2026-01-25 15:21:21', '2026-01-25 15:21:21', '2026-01-25 15:21:21', '2026-01-25 15:21:21', 0x5c350453f34f720383ee2fdfacc7046bb90a230d2e3b068fc282ed49f199a77639b3d600a43208a807c5ea7197237630, 0x26d169c833500200ea011c33),
(36, 9, '[\"$2y$10$qflRECkpz6li4mGGnMDgS.BmorrwLlXzS2IKEKKxeLuzyjoDLft3W\",\"$2y$10$/c9o7yHBwB8QAE/DASumI.TtIINl.xhMLqY2YO25QC1OJ97o/8ciK\",\"$2y$10$d/65rhjVNQgMvCRje.25beMcAuQrJfuUKjr27q0eA8Lhq4zxRfufC\",\"$2y$10$pfJIP1.MhnIALfyWTwxxj.oQ6nrpvUUuefOb1GljaiIvCfn31uc62\",\"$2y$10$S1i8QxTm3rSSL/plLTAqZeAGkYQ/BLvfwtOMYYVbNeeaonxgwXZk6\",\"$2y$10$88E73H5dqFOew37DEzL7YuWAV7jkQpuFLVY6XuSuLhWjWf8Asn6tq\",\"$2y$10$XO.Il/8b3/ds4kBU7Z6yt.T5dMWTGJW6RBNwDBH.vfS6gnNb2WlzC\",\"$2y$10$KRKYjPmdxccjZfCjrmNZP.cf0huF/GYsbTak.PKXO.dmXJ2/qRtD6\"]', 1, '2026-02-07 23:37:37', '2026-02-07 23:37:37', '2026-02-07 23:37:37', '2026-02-07 23:37:37', 0xab1e25506cb240728f7e6aecba4fcf29f14e769eb024e13716f6e521b60d1f142503bfb167dbda8e03417a50a0f1fafe, 0x6bde6232fb69999a5a343598),
(37, 10, '[\"$2y$10$vTobrjUhy52T5rz/67FHJex9eu2xjS22z8.nrxa5ag0XV073UKVDG\",\"$2y$10$7o9Emkw1yByxdRm2AoLm3esrnXQ0.4vl9EPnS8MtJT3k3eKfqr.76\",\"$2y$10$wGgYWdhzQIU/2zW1MuWccOCvX/XpPgCFCV9O65Jff3bJUh9QFGvYu\",\"$2y$10$Qsfvcgxi4UtRIAw9BiDEGem1snhMLkWH946XEO689b4EkeiJkN/Vq\",\"$2y$10$6szR90z2zztCASK/AMG.xus89KLd0HQ10q3.SNi7zLQrVvB1QadDO\",\"$2y$10$u0g2u51ePrLsGqW6CrmEnOyuYPvsaNsBrc2y2CkMEgUZrVk/ie3F6\",\"$2y$10$s3EnYra4XjQyLZ2IHQZt3eWpC3vGOprAgHV8yUAmsB0rYmmOp8TOW\",\"$2y$10$ZHBXunDl4IWeGG1cl6F7VuGueJpxO/Xle8TAr/VvBu/P7XW22b1ea\"]', 1, '2026-02-07 23:40:36', '2026-02-07 23:40:36', '2026-02-07 23:40:36', '2026-02-07 23:40:36', 0x714f5670b62aa141cb9bf0d19cf3be9c079fd69f950af7d44a2b968ea1b271c2c915a48995e01cb2e8cc7bfc00ecf061, 0xd4e313be160c2600054b82b7),
(38, 11, '[\"$2y$10$8NZrRhkLseOWCbpMIu5vveCiMyw/tzFjxCaYyoKWkhL.6iJ19qoby\",\"$2y$10$JZ1uZHQc8iS8wu1Ld8qIKeNoIp4971QTDmyQdP2x.we7M2vPQof/2\",\"$2y$10$cEN0kt8sAwJ0u9vK0QmlyOkIHRM07uxFnbf7u74eM7a7wMbjZMfPG\",\"$2y$10$29ys.Mcn4bJV7orBrX2Y3.BF17IOdjbpQbfzUMYHvf2/HyxIhV3RG\",\"$2y$10$A8V3DrrrdC6qk9dZvLkED.g6GRefOS.R470jeon4OAKJoQToScRm2\",\"$2y$10$yPyLxPYC0uLbGmkYWln.3.uLKhxHpqISvEfdbofZGvSBhhvUhRKxa\",\"$2y$10$aJPC.fx9S7NuH.yuI2jDqeRa8yT2G1TcMahhGa/LA995AGbhae/D6\",\"$2y$10$72pp53K7Ij6/WrUGTguF..CLxxVvRM2d.S2ZZpLc8xWqWXX2DOJZC\"]', 1, '2026-02-07 23:42:27', '2026-02-07 23:42:27', '2026-02-07 23:42:27', '2026-02-07 23:42:27', 0x90aef06f5ff430e9a0b4b6b1f2fa420c2997547cd6c8fde6e9ee706fbca18076f5d1f927075dfde510793ba5c0762879, 0xe6814fd8db6896878eee87be),
(39, 12, '[\"$2y$10$SHj/pL7kpdFOo8pbHlDjlufyaDh6K6RmRO8LFptg04RJb2vm3GzC6\",\"$2y$10$Zfw20d7A8NN8q7gE6QyMuurBHvtLyVlfFQ6vpDyoZlvoQUsRrz8De\",\"$2y$10$4B6FBvLNFCKzSvts1nzVROgheo1XcIj9iCsX64qDYA15kTmAq9D4G\",\"$2y$10$QUOMSqoiBj2dOyL7Jk6cZeMoTHN6HEuMbN9h/DqFr5JfzHbwlcR2K\",\"$2y$10$m6.KREAI9tVZikbfPDu2fuEu3UUOoxRK89KnCbAukqY7mTiQkDai.\",\"$2y$10$kl6Z6vAyKb1bTLuI1SQUMePEh4oebGrMzTQOZDeaqgp984.2hUhae\",\"$2y$10$u2XYPyd.o3hqsLYYu8Rxeeww1irusDrssT/X5p7nyLquRSk4PbzR2\",\"$2y$10$2wsy601r3ZIIHXAh.7/1nuuv64iPtr38dU.JR383IMidcXHm70m8i\"]', 1, '2026-02-07 23:44:25', '2026-02-07 23:44:25', '2026-02-07 23:44:25', '2026-02-07 23:44:25', 0x6ecac8814203c2ef70ebf97c6cf7e2c28aa28a5b9ff5dab67fd7adb962689d7d9c157e783822323169b371cb04afa4bb, 0x1f6e8e5f91fd404bb6939252),
(40, 13, '[\"$2y$10$SSpKS/E6HdfpYKtj9r3A/OnOcpmM5/irYerntbZv4C5bzNqBWvQom\",\"$2y$10$ayMp5AQiIM8HrpcGd1ajEulPyb9hqVpCFbFRGob4MjDtYhbQJsewq\",\"$2y$10$rcrjMOqS8sRN/YVQcJuvvu2hES4Kzl2cDu.ZdESyzK4JU8hrYpH/C\",\"$2y$10$jBOx1jrBDtK1HGYW/m5Wp.Pkrbk8zhN3wLz7VmXAmrcRnOvHsTx7C\",\"$2y$10$PGNLeGvF.aKWB7dYpE9LtO2xGU/dxGnrR/p./EaMWW.wl4zTDsFc.\",\"$2y$10$PT2bxJCReKA0nB88sFTOZuHRwqjPOM/NRZHTy.533xLrYEhPpJgYa\",\"$2y$10$cjKXCPlj8bU8DqG9BNDLOe04lBHckCYJ7jRGk5NDpFipbz1eB5JvW\",\"$2y$10$.vpeo5GybC1Ej0nxVmiv..feMyKJI3oEjI2qsC0uW1gCmnsGU57rW\"]', 1, '2026-02-07 23:46:23', '2026-02-07 23:46:23', '2026-02-07 23:46:23', '2026-02-07 23:46:23', 0x91d3baa5a1dbb6f51d90039db98113425d656a358f5f3d39c9e9cb7653cb4468a2634c476e29787dfd8136fcf0b5f3e7, 0xb6aebe7f01524317d3ab5192);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` char(32) DEFAULT NULL,
  `notification_id` varchar(100) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_read` tinyint(1) DEFAULT 0,
  `dismissed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `file_id`, `notification_id`, `notification_type`, `priority`, `is_read`, `dismissed`, `created_at`, `updated_at`) VALUES
(226, 9, NULL, '2fa_status_1770478657', '2fa_enabled', 'normal', 0, 0, '2026-02-07 23:37:37', '2026-02-07 23:37:37'),
(227, 10, NULL, '2fa_status_1770478836', '2fa_enabled', 'normal', 0, 0, '2026-02-07 23:40:36', '2026-02-07 23:40:36'),
(228, 11, NULL, '2fa_status_1770478947', '2fa_enabled', 'normal', 0, 0, '2026-02-07 23:42:27', '2026-02-07 23:42:27'),
(229, 12, NULL, '2fa_status_1770479065', '2fa_enabled', 'normal', 0, 0, '2026-02-07 23:44:25', '2026-02-07 23:44:25'),
(230, 13, NULL, '2fa_status_1770479183', '2fa_enabled', 'normal', 0, 0, '2026-02-07 23:46:23', '2026-02-07 23:46:23'),
(231, 9, NULL, 'suspicious_login_1770480107', 'suspicious_login', '', 0, 0, '2026-02-08 00:01:47', '2026-02-08 00:01:47'),
(232, 10, NULL, 'shared_05dd13f45757f0d1949bcd02af59403e', 'file_received', 'normal', 1, 0, '2026-02-08 00:03:33', '2026-02-08 00:07:35'),
(233, 9, NULL, 'upload_success_05dd13f45757f0d1949bcd02af59403e_1770480213', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:03:33', '2026-02-08 00:03:33'),
(235, 9, NULL, 'suspicious_login_1770480923', 'suspicious_login', '', 0, 0, '2026-02-08 00:15:23', '2026-02-08 00:15:23'),
(236, 9, NULL, 'suspicious_login_1770481264', 'suspicious_login', '', 0, 0, '2026-02-08 00:21:04', '2026-02-08 00:21:04'),
(237, 9, NULL, 'suspicious_login_1770481908', 'suspicious_login', '', 0, 0, '2026-02-08 00:31:48', '2026-02-08 00:31:48'),
(238, 12, NULL, 'shared_d78db46fad22a99de090ba2e257fd837', 'file_received', 'normal', 0, 0, '2026-02-08 00:34:11', '2026-02-08 00:34:11'),
(239, 10, NULL, 'upload_success_d78db46fad22a99de090ba2e257fd837_1770482051', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:34:11', '2026-02-08 00:34:11'),
(240, 10, NULL, 'expiring_d78db46fad22a99de090ba2e257fd837', 'file_expiring_soon', 'high', 0, 0, '2026-02-08 00:34:11', '2026-02-08 00:34:11'),
(241, 12, NULL, 'expiring_receiver_d78db46fad22a99de090ba2e257fd837', 'file_expiring_soon', 'high', 0, 0, '2026-02-08 00:34:11', '2026-02-08 00:34:11'),
(242, 13, NULL, 'shared_694dbe5e26e22d6c1f1b74529f679dd2', 'file_received', 'normal', 0, 0, '2026-02-08 00:34:20', '2026-02-08 00:34:20'),
(243, 10, NULL, 'upload_success_694dbe5e26e22d6c1f1b74529f679dd2_1770482060', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:34:20', '2026-02-08 00:34:20'),
(244, 10, NULL, 'expiring_694dbe5e26e22d6c1f1b74529f679dd2', 'file_expiring_soon', 'high', 0, 0, '2026-02-08 00:34:20', '2026-02-08 00:34:20'),
(245, 13, NULL, 'expiring_receiver_694dbe5e26e22d6c1f1b74529f679dd2', 'file_expiring_soon', 'high', 0, 0, '2026-02-08 00:34:20', '2026-02-08 00:34:20'),
(246, 13, NULL, 'policy_changed_694dbe5e26e22d6c1f1b74529f679dd2_1770482127', 'policy_updated', 'high', 0, 0, '2026-02-08 00:35:27', '2026-02-08 00:35:27'),
(247, 10, NULL, 'policy_updated_confirm_694dbe5e26e22d6c1f1b74529f679dd2_1770482127', 'policy_updated_success', 'low', 0, 0, '2026-02-08 00:35:27', '2026-02-08 00:35:27'),
(248, 9, NULL, 'shared_c638713ebbffe6e0a1a2c45b03b1fce1', 'file_received', 'normal', 0, 0, '2026-02-08 00:37:39', '2026-02-08 00:37:39'),
(249, 11, NULL, 'upload_success_c638713ebbffe6e0a1a2c45b03b1fce1_1770482259', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:37:39', '2026-02-08 00:37:39'),
(250, 12, NULL, 'shared_7793005c95f21a9eeeb35616488b7991', 'file_received', 'normal', 0, 0, '2026-02-08 00:38:20', '2026-02-08 00:38:20'),
(251, 11, NULL, 'upload_success_7793005c95f21a9eeeb35616488b7991_1770482300', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:38:20', '2026-02-08 00:38:20'),
(252, 12, NULL, 'shared_a74b933d942c0b3d60acb136e2a3a1ef', 'file_received', 'normal', 0, 0, '2026-02-08 00:38:58', '2026-02-08 00:38:58'),
(253, 11, NULL, 'upload_success_a74b933d942c0b3d60acb136e2a3a1ef_1770482338', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:38:58', '2026-02-08 00:38:58'),
(254, 13, NULL, 'shared_cce6563f47ef1b91df59593bfcbc9d50', 'file_received', 'normal', 0, 0, '2026-02-08 00:40:55', '2026-02-08 00:40:55'),
(255, 12, NULL, 'upload_success_cce6563f47ef1b91df59593bfcbc9d50_1770482455', 'file_uploaded_success', 'low', 0, 0, '2026-02-08 00:40:55', '2026-02-08 00:40:55'),
(256, 12, NULL, 'file_revoked_7793005c95f21a9eeeb35616488b7991_1770561997', 'file_access_revoked', '', 0, 0, '2026-02-08 22:46:37', '2026-02-08 22:46:37'),
(257, 11, NULL, 'revoke_confirm_7793005c95f21a9eeeb35616488b7991_1770561997', 'file_revoked_success', 'low', 0, 0, '2026-02-08 22:46:37', '2026-02-08 22:46:37'),
(258, 12, NULL, 'file_revoked_7793005c95f21a9eeeb35616488b7991_1770562780', 'file_access_revoked', '', 0, 0, '2026-02-08 22:59:40', '2026-02-08 22:59:40'),
(259, 11, NULL, 'revoke_confirm_7793005c95f21a9eeeb35616488b7991_1770562780', 'file_revoked_success', 'low', 0, 0, '2026-02-08 22:59:40', '2026-02-08 22:59:40'),
(260, 12, NULL, 'file_revoked_7793005c95f21a9eeeb35616488b7991_1770562879', 'file_access_revoked', '', 0, 0, '2026-02-08 23:01:19', '2026-02-08 23:01:19'),
(261, 11, NULL, 'revoke_confirm_7793005c95f21a9eeeb35616488b7991_1770562879', 'file_revoked_success', 'low', 0, 0, '2026-02-08 23:01:19', '2026-02-08 23:01:19'),
(262, 12, NULL, 'file_revoked_7793005c95f21a9eeeb35616488b7991_1770563243', 'file_access_revoked', '', 0, 0, '2026-02-08 23:07:23', '2026-02-08 23:07:23'),
(263, 11, NULL, 'revoke_confirm_7793005c95f21a9eeeb35616488b7991_1770563243', 'file_revoked_success', 'low', 0, 0, '2026-02-08 23:07:23', '2026-02-08 23:07:23'),
(264, 12, NULL, 'file_reactivated_7793005c95f21a9eeeb35616488b7991_1770563353', 'file_reactivated', 'normal', 0, 0, '2026-02-08 23:09:13', '2026-02-08 23:09:13'),
(265, 11, NULL, 'reactivate_confirm_7793005c95f21a9eeeb35616488b7991_1770563353', 'file_reactivated_success', 'low', 0, 0, '2026-02-08 23:09:13', '2026-02-08 23:09:13'),
(266, 12, NULL, 'file_revoked_a74b933d942c0b3d60acb136e2a3a1ef_1770564851', 'file_access_revoked', '', 0, 0, '2026-02-08 23:34:11', '2026-02-08 23:34:11'),
(267, 11, NULL, 'revoke_confirm_a74b933d942c0b3d60acb136e2a3a1ef_1770564851', 'file_revoked_success', 'low', 0, 0, '2026-02-08 23:34:11', '2026-02-08 23:34:11'),
(268, 12, NULL, 'file_reactivated_a74b933d942c0b3d60acb136e2a3a1ef_1770565024', 'file_reactivated', 'normal', 0, 0, '2026-02-08 23:37:04', '2026-02-08 23:37:04'),
(269, 11, NULL, 'reactivate_confirm_a74b933d942c0b3d60acb136e2a3a1ef_1770565024', 'file_reactivated_success', 'low', 0, 0, '2026-02-08 23:37:04', '2026-02-08 23:37:04'),
(270, 12, NULL, 'file_revoked_7793005c95f21a9eeeb35616488b7991_1770565074', 'file_access_revoked', '', 0, 0, '2026-02-08 23:37:54', '2026-02-08 23:37:54'),
(271, 11, NULL, 'revoke_confirm_7793005c95f21a9eeeb35616488b7991_1770565074', 'file_revoked_success', 'low', 0, 0, '2026-02-08 23:37:54', '2026-02-08 23:37:54'),
(272, 12, NULL, 'file_reactivated_7793005c95f21a9eeeb35616488b7991_1770565309', 'file_reactivated', 'normal', 0, 0, '2026-02-08 23:41:49', '2026-02-08 23:41:49'),
(273, 11, NULL, 'reactivate_confirm_7793005c95f21a9eeeb35616488b7991_1770565309', 'file_reactivated_success', 'low', 0, 0, '2026-02-08 23:41:49', '2026-02-08 23:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `user_recovery_keys`
--

CREATE TABLE `user_recovery_keys` (
  `recovery_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `kek_enc_rk` blob NOT NULL,
  `kek_rk_iv` varbinary(16) NOT NULL,
  `rkdf_salt` varbinary(16) NOT NULL,
  `rkdf_iterations` int(11) NOT NULL DEFAULT 150000,
  `recovery_key_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rotated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_recovery_keys`
--

INSERT INTO `user_recovery_keys` (`recovery_id`, `user_id`, `kek_enc_rk`, `kek_rk_iv`, `rkdf_salt`, `rkdf_iterations`, `recovery_key_version`, `is_active`, `created_at`, `rotated_at`) VALUES
(38, 9, 0xa4f9320ff20d9816ae4771c591d2b924d455a6debb864f02acb87fa0709476d907259be4c5ca43a6808f8995a1da2b57, 0x2fc3a8898aa1028c0a58e5aad99d6c7e, 0xdd3e50ebb5c0af4ae4c293ce4b1ab262, 150000, 1, 1, '2026-02-07 23:34:58', NULL),
(39, 10, 0x193a05a53c14ba7eb302d7ee9fa0174dfb59cd550afec6e74ac49b4c0830b0d7ca4d91293ecc27d5cf6780f1f1d0a758, 0xca9dc59f3df0c862ddfe21926869761f, 0xacf8723a42d536132c47b34879b760bf, 150000, 1, 1, '2026-02-07 23:39:51', NULL),
(40, 11, 0x4f12ba0d2b83a49451297a1dd090f6758490100528a4587b8497c684d3ab42d3d23d992a157fd235be822ecfeb680af8, 0x083f0ebd76bce58426b96842503f548f, 0x4ce1e38c31a29ced3de8b5709b3fdee0, 150000, 1, 1, '2026-02-07 23:41:38', NULL),
(41, 12, 0x4c2f146052ce20d33fb0a2e677a01e89d148559e689795ccca58d721760c6508bb319c67621553e45c3ea513ba479566, 0x7836dc90501ca88379f2a06665037430, 0xd241a07349dc91e797ecae0c337f08cd, 150000, 1, 1, '2026-02-07 23:43:38', NULL),
(42, 13, 0x1acf2d3d27b2185395beda7696ae907621c6cb423e1890f61c8bf705366e4505b4d916827aa1032c6d154deeb96dd59e, 0x2eeda0617d1b6f16870e21bcd151f1df, 0xb33ef4bdd03046ca1893b953640c3276, 150000, 1, 1, '2026-02-07 23:45:42', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_notification` (`admin_id`,`notification_id`),
  ADD KEY `idx_admin_unread` (`admin_id`,`is_read`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_admin_priority` (`admin_id`,`priority`,`is_read`);

--
-- Indexes for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_expiry` (`user_id`,`expires_at`),
  ADD KEY `idx_cleanup` (`consumed_at`,`expires_at`);

--
-- Indexes for table `encryption_metrics`
--
ALTER TABLE `encryption_metrics`
  ADD PRIMARY KEY (`metric_id`),
  ADD UNIQUE KEY `uniq_file_metrics` (`file_id`),
  ADD KEY `idx_score` (`encryption_score`),
  ADD KEY `idx_rating` (`encryption_rating`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `file_access_log`
--
ALTER TABLE `file_access_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_accessed` (`accessed_at`),
  ADD KEY `idx_file_user` (`file_id`,`user_id`);

--
-- Indexes for table `file_recipients`
--
ALTER TABLE `file_recipients`
  ADD PRIMARY KEY (`recipient_id`),
  ADD UNIQUE KEY `unique_file_recipient` (`file_id`,`user_id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ip_identity` (`ip_hash`,`identity_hash`),
  ADD KEY `idx_last_fail` (`last_fail_at`);

--
-- Indexes for table `password_reset_pending`
--
ALTER TABLE `password_reset_pending`
  ADD PRIMARY KEY (`pending_id`),
  ADD KEY `idx_token` (`confirmation_token_hash`),
  ADD KEY `idx_cleanup` (`expires_at`,`confirmed_at`),
  ADD KEY `reset_request_id` (`reset_request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `idx_token` (`reset_token_hash`,`reset_token_expires_at`),
  ADD KEY `idx_cleanup` (`used_at`,`reset_token_expires_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_category` (`event_category`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `shared_files`
--
ALTER TABLE `shared_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_time`,`status`),
  ADD KEY `idx_cleanup` (`status`,`expiry_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `user_email` (`user_email`),
  ADD UNIQUE KEY `user_phone_unique` (`user_phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email_verified` (`email_verified_at`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`activity_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `user_crypto_keys`
--
ALTER TABLE `user_crypto_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD UNIQUE KEY `uniq_user_active_key` (`user_id`,`key_version`,`key_status`),
  ADD KEY `idx_user_status` (`user_id`,`key_status`);

--
-- Indexes for table `user_kek`
--
ALTER TABLE `user_kek`
  ADD PRIMARY KEY (`kek_id`),
  ADD UNIQUE KEY `uniq_user_active_kek` (`user_id`,`is_active`),
  ADD KEY `idx_user_version` (`user_id`,`kek_version`);

--
-- Indexes for table `user_mfa_totp`
--
ALTER TABLE `user_mfa_totp`
  ADD PRIMARY KEY (`mfa_id`),
  ADD UNIQUE KEY `uniq_user_mfa` (`user_id`),
  ADD KEY `idx_enabled` (`is_enabled`),
  ADD KEY `idx_totp_secret_enc` (`user_id`,`totp_secret_enc`(32));

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification` (`user_id`,`notification_id`),
  ADD UNIQUE KEY `uniq_user_file_type` (`user_id`,`file_id`,`notification_type`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `user_recovery_keys`
--
ALTER TABLE `user_recovery_keys`
  ADD PRIMARY KEY (`recovery_id`),
  ADD UNIQUE KEY `uniq_user_active_recovery` (`user_id`,`is_active`),
  ADD KEY `idx_user_version` (`user_id`,`recovery_key_version`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `encryption_metrics`
--
ALTER TABLE `encryption_metrics`
  MODIFY `metric_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `file_access_log`
--
ALTER TABLE `file_access_log`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `file_recipients`
--
ALTER TABLE `file_recipients`
  MODIFY `recipient_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_pending`
--
ALTER TABLE `password_reset_pending`
  MODIFY `pending_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `reset_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  MODIFY `audit_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `activity_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `user_crypto_keys`
--
ALTER TABLE `user_crypto_keys`
  MODIFY `key_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `user_kek`
--
ALTER TABLE `user_kek`
  MODIFY `kek_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `user_mfa_totp`
--
ALTER TABLE `user_mfa_totp`
  MODIFY `mfa_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=274;

--
-- AUTO_INCREMENT for table `user_recovery_keys`
--
ALTER TABLE `user_recovery_keys`
  MODIFY `recovery_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD CONSTRAINT `email_verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `encryption_metrics`
--
ALTER TABLE `encryption_metrics`
  ADD CONSTRAINT `encryption_metrics_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `shared_files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `encryption_metrics_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `encryption_metrics_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `file_access_log`
--
ALTER TABLE `file_access_log`
  ADD CONSTRAINT `file_access_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `file_recipients`
--
ALTER TABLE `file_recipients`
  ADD CONSTRAINT `file_recipients_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `shared_files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_pending`
--
ALTER TABLE `password_reset_pending`
  ADD CONSTRAINT `password_reset_pending_ibfk_1` FOREIGN KEY (`reset_request_id`) REFERENCES `password_reset_requests` (`reset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `password_reset_pending_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD CONSTRAINT `security_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `shared_files`
--
ALTER TABLE `shared_files`
  ADD CONSTRAINT `shared_files_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_files_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_crypto_keys`
--
ALTER TABLE `user_crypto_keys`
  ADD CONSTRAINT `user_crypto_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_kek`
--
ALTER TABLE `user_kek`
  ADD CONSTRAINT `user_kek_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_mfa_totp`
--
ALTER TABLE `user_mfa_totp`
  ADD CONSTRAINT `user_mfa_totp_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_recovery_keys`
--
ALTER TABLE `user_recovery_keys`
  ADD CONSTRAINT `user_recovery_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
