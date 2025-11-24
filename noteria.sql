-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 09:35 PM
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
-- Database: `noteria`
--

-- --------------------------------------------------------

--
-- Table structure for table `abonimet`
--

CREATE TABLE `abonimet` (
  `id` int(11) NOT NULL,
  `emri` varchar(255) NOT NULL,
  `cmimi` decimal(10,2) NOT NULL,
  `kohezgjatja` int(11) NOT NULL COMMENT 'Në muaj',
  `pershkrimi` text DEFAULT NULL,
  `karakteristikat` text DEFAULT NULL,
  `status` enum('aktiv','joaktiv') NOT NULL DEFAULT 'aktiv',
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp(),
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `abonimet`
--

INSERT INTO `abonimet` (`id`, `emri`, `cmimi`, `kohezgjatja`, `pershkrimi`, `karakteristikat`, `status`, `krijuar_me`, `perditesuar_me`) VALUES
(5, 'Abonim Mujor', 150.00, 1, 'Abonim mujor me qasje të plotë në platformë', '[\"Qasje e plot\\u00eb n\\u00eb platform\\u00eb\",\"Dokumente t\\u00eb pakufizuara\",\"Mb\\u00ebshtetje prioritare 24\\/7\",\"T\\u00eb gjitha sh\\u00ebrbimet e platform\\u00ebs\",\"Mjete t\\u00eb avancuara p\\u00ebr noter\\u00eb\"]', 'aktiv', '2025-09-27 20:43:14', NULL),
(6, 'Abonim Vjetor', 1500.00, 12, 'Abonim vjetor me qasje të plotë në platformë', '[\"Qasje e plot\\u00eb n\\u00eb platform\\u00eb\",\"Dokumente t\\u00eb pakufizuara\",\"Mb\\u00ebshtetje prioritare 24\\/7\",\"T\\u00eb gjitha sh\\u00ebrbimet e platform\\u00ebs\",\"Mjete t\\u00eb avancuara p\\u00ebr noter\\u00eb\",\"Kurseni 300\\u20ac me pages\\u00ebn vjetore\",\"Trajnime personale\",\"K\\u00ebshillime ligjore mujore t\\u00eb p\\u00ebrfshira\"]', 'aktiv', '2025-09-27 20:43:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `log_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `log_type`, `user_id`, `status`, `message`, `created_at`) VALUES
(1, 'subscription', 1, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Arben Krasniqi. Shuma: 25.00 EUR. Ref: SUB2025092713472', '2025-09-27 10:36:59'),
(2, 'subscription', 2, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Lumnije Berisha. Shuma: 25.00 EUR. Ref: SUB2025092720768', '2025-09-27 10:36:59'),
(3, 'subscription', 3, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Blerim Hoxha. Shuma: 25.00 EUR. Ref: SUB2025092730345', '2025-09-27 10:36:59'),
(4, 'subscription', 1, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Arben Krasniqi. Shuma: 25.00 EUR. Ref: SUB2025092717639', '2025-09-27 12:50:17'),
(5, 'subscription', 2, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Lumnije Berisha. Shuma: 25.00 EUR. Ref: SUB2025092728694', '2025-09-27 12:50:17'),
(6, 'subscription', 3, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Blerim Hoxha. Shuma: 25.00 EUR. Ref: SUB2025092738421', '2025-09-27 12:50:17'),
(7, 'subscription', 1, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Arben Krasniqi. Shuma: 25.00 EUR. Ref: SUB2025092712718', '2025-09-27 12:50:26'),
(8, 'subscription', 2, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Lumnije Berisha. Shuma: 25.00 EUR. Ref: SUB2025092729820', '2025-09-27 12:50:26'),
(9, 'subscription', 3, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Blerim Hoxha. Shuma: 25.00 EUR. Ref: SUB2025092730516', '2025-09-27 12:50:26'),
(10, 'subscription', 1, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Arben Krasniqi. Shuma: 25.00 EUR. Ref: SUB2025092711428', '2025-09-27 13:02:54'),
(11, 'subscription', 2, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Lumnije Berisha. Shuma: 25.00 EUR. Ref: SUB2025092722849', '2025-09-27 13:02:54'),
(12, 'subscription', 3, 'info', '[TEST] Pagesa e abonimit u simulua për noterin Blerim Hoxha. Shuma: 25.00 EUR. Ref: SUB2025092734071', '2025-09-27 13:02:54');

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expired_at` timestamp NULL DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `token`, `created_at`, `expired_at`, `description`) VALUES
(1, 'f71de63c48c832da0a8e4ee46b9f406c7e8cc692470032b51dbcb4bcb349b1a1', '2025-09-27 08:07:49', '2026-09-27 08:07:49', 'Token fillestar për testim');

-- --------------------------------------------------------

--
-- Table structure for table `aplikimet_konkurs`
--

CREATE TABLE `aplikimet_konkurs` (
  `id` int(11) NOT NULL,
  `konkurs_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefoni` varchar(30) NOT NULL,
  `mesazhi` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aplikimet_konkurs`
--

INSERT INTO `aplikimet_konkurs` (`id`, `konkurs_id`, `user_id`, `emri`, `email`, `telefoni`, `mesazhi`, `created_at`) VALUES
(1, 3, 39, 'Valon', 'valonsadiku2018@gmail.com', '+38345213675', 'Për qejf të Karit', '2025-09-20 19:27:38'),
(2, 3, 39, 'Valon', 'valonsadiku2018@gmail.com', '+38345213675', 'Për qejf të Karit', '2025-09-20 19:30:53'),
(3, 3, 24, 'Valon', 'valonsadiku2018@gmail.com', '+38345213675', 'themë', '2025-10-08 14:39:35');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `created_at`, `ip_address`, `user_agent`) VALUES
(1, 3, 'Kyçje', 'Kyçje e suksesshme', '2025-08-05 00:25:41', NULL, NULL),
(2, 3, 'Kyçje', 'Kyçje e suksesshme', '2025-08-05 00:26:25', NULL, NULL),
(3, 7, 'Kyçje', 'Kyçje e suksesshme', '2025-08-05 00:26:50', NULL, NULL),
(4, 9, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 11:35:40', NULL, NULL),
(5, 9, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 11:41:15', NULL, NULL),
(6, 9, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 11:51:42', NULL, NULL),
(7, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 11:59:22', NULL, NULL),
(8, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 18:46:17', NULL, NULL),
(9, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 18:50:04', NULL, NULL),
(10, 11, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 18:53:01', NULL, NULL),
(11, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 18:58:14', NULL, NULL),
(12, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:02:13', NULL, NULL),
(13, 11, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:02:55', NULL, NULL),
(14, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:06:08', NULL, NULL),
(15, 11, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:15:46', NULL, NULL),
(16, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:24:15', NULL, NULL),
(17, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:27:23', NULL, NULL),
(18, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:29:08', NULL, NULL),
(19, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:43:12', NULL, NULL),
(20, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:46:17', NULL, NULL),
(21, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-05 19:48:01', NULL, NULL),
(22, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 15:48:29', NULL, NULL),
(23, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 15:58:30', NULL, NULL),
(24, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 16:11:39', NULL, NULL),
(25, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 16:39:35', NULL, NULL),
(26, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 16:58:56', NULL, NULL),
(27, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 17:56:09', NULL, NULL),
(28, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-07 18:32:18', NULL, NULL),
(29, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-08 10:42:12', NULL, NULL),
(30, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-08 11:04:30', NULL, NULL),
(31, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-08 11:06:27', NULL, NULL),
(32, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-08 11:16:30', NULL, NULL),
(33, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 11:00:37', NULL, NULL),
(34, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 11:20:26', NULL, NULL),
(35, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 11:27:33', NULL, NULL),
(36, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 11:28:36', NULL, NULL),
(37, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 17:00:08', NULL, NULL),
(38, 13, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 17:08:09', NULL, NULL),
(39, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 17:19:35', NULL, NULL),
(40, 17, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 17:32:51', NULL, NULL),
(41, 18, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 23:54:46', NULL, NULL),
(42, 19, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-10 23:58:50', NULL, NULL),
(43, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-11 10:20:17', NULL, NULL),
(44, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-12 11:12:18', NULL, NULL),
(45, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-12 16:50:18', NULL, NULL),
(46, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-13 17:32:22', NULL, NULL),
(47, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 11:42:20', NULL, NULL),
(48, 20, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 11:56:49', NULL, NULL),
(49, 19, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 16:41:14', NULL, NULL),
(50, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 17:01:37', NULL, NULL),
(51, 21, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 17:26:11', NULL, NULL),
(52, 21, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 17:32:41', NULL, NULL),
(53, 21, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 17:39:29', NULL, NULL),
(54, 21, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-14 18:06:11', NULL, NULL),
(55, 21, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-15 22:32:23', NULL, NULL),
(56, 14, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-15 22:40:05', NULL, NULL),
(57, 23, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-15 22:48:22', NULL, NULL),
(58, 24, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-18 16:19:18', NULL, NULL),
(59, 24, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-18 17:39:28', NULL, NULL),
(60, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-18 18:40:36', NULL, NULL),
(61, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-18 19:03:11', NULL, NULL),
(62, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-19 10:19:17', NULL, NULL),
(63, 25, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-19 16:48:48', NULL, NULL),
(64, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-19 23:37:25', NULL, NULL),
(65, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-20 11:01:26', NULL, NULL),
(66, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-20 11:44:15', NULL, NULL),
(67, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-20 17:02:46', NULL, NULL),
(68, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-20 17:09:32', NULL, NULL),
(69, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-21 00:39:05', NULL, NULL),
(70, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-27 18:41:56', NULL, NULL),
(71, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-27 22:11:38', NULL, NULL),
(72, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-27 22:19:51', NULL, NULL),
(73, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-28 23:08:39', NULL, NULL),
(74, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-29 09:30:00', NULL, NULL),
(75, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-29 15:38:04', NULL, NULL),
(76, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-08-29 23:16:21', NULL, NULL),
(77, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-07 17:53:44', NULL, NULL),
(78, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-07 21:12:16', NULL, NULL),
(79, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-10 17:59:31', NULL, NULL),
(80, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-11 12:11:27', NULL, NULL),
(81, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-13 21:57:36', NULL, NULL),
(82, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-18 23:55:29', NULL, NULL),
(83, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 16:15:55', NULL, NULL),
(84, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 18:04:13', NULL, NULL),
(85, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 22:05:33', NULL, NULL),
(86, 29, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 22:11:40', NULL, NULL),
(87, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 22:19:31', NULL, NULL),
(88, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-19 22:24:43', NULL, NULL),
(89, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 00:49:06', NULL, NULL),
(90, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 01:15:51', NULL, NULL),
(91, 29, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 10:13:29', NULL, NULL),
(92, 29, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 11:32:38', NULL, NULL),
(93, 39, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 21:23:14', NULL, NULL),
(94, 39, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-20 22:16:05', NULL, NULL),
(95, 39, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-22 10:36:00', NULL, NULL),
(96, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-22 16:20:42', NULL, NULL),
(97, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-22 17:48:18', NULL, NULL),
(98, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-22 21:29:08', NULL, NULL),
(99, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-26 17:25:24', NULL, NULL),
(100, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 01:44:06', NULL, NULL),
(101, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 09:59:40', NULL, NULL),
(102, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 14:49:56', NULL, NULL),
(103, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 18:01:50', NULL, NULL),
(104, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 22:19:27', NULL, NULL),
(105, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 23:27:06', NULL, NULL),
(106, 40, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-27 23:51:29', NULL, NULL),
(107, 40, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-28 00:10:52', NULL, NULL),
(108, 40, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-28 01:04:15', NULL, NULL),
(109, 40, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-28 01:58:47', NULL, NULL),
(110, 40, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-29 10:04:57', NULL, NULL),
(111, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-29 12:05:21', NULL, NULL),
(112, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-29 15:33:00', NULL, NULL),
(113, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-09-30 03:23:37', NULL, NULL),
(114, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-01 00:11:32', NULL, NULL),
(115, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-02 09:45:33', NULL, NULL),
(116, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-02 12:24:37', NULL, NULL),
(117, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-03 23:42:08', NULL, NULL),
(118, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-04 00:01:18', NULL, NULL),
(119, 10, 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal', '2025-10-04 10:35:41', NULL, NULL),
(120, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-04 10:51:26', NULL, NULL),
(121, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-04 22:21:11', NULL, NULL),
(122, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-04 22:44:42', NULL, NULL),
(123, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-08 11:55:47', NULL, NULL),
(124, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-08 14:54:03', NULL, NULL),
(125, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-08 15:02:47', NULL, NULL),
(126, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-08 16:45:07', NULL, NULL),
(127, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-14 15:46:51', NULL, NULL),
(128, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-14 15:58:39', NULL, NULL),
(129, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-16 23:26:04', NULL, NULL),
(130, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-17 16:46:44', NULL, NULL),
(131, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-22 17:03:44', NULL, NULL),
(132, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-23 22:17:06', NULL, NULL),
(133, 10, 'Kyçje', 'Kyçje e suksesshme me rol: admin, verifikim foto dhe numër personal', '2025-10-29 20:53:13', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `automatic_payments`
--

CREATE TABLE `automatic_payments` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_config`
--

CREATE TABLE `billing_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_config`
--

INSERT INTO `billing_config` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'billing_time', '07:00:00', 'Ora kur ekzekutohet faturimi automatik', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(2, 'billing_day', '1', 'Dita e muajit kur ekzekutohet faturimi (1-28)', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(3, 'standard_price', '150.00', 'Çmimi standard mujor në EUR', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(5, 'due_days', '2', 'Numri i ditëve për të paguar pas faturimit', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(6, 'email_notifications', '1', 'A të dërgohen njoftimet email (1=po, 0=jo)', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(7, 'auto_billing_enabled', '1', 'A është i aktivizuar faturimi automatik (1=po, 0=jo)', '2025-10-02 10:39:33', '2025-10-04 22:15:59'),
(14, 'auto_payment_enabled', '1', 'Pagesat automatike të aktivizuara', '2025-10-02 11:09:07', '2025-10-04 22:15:59'),
(15, 'developer_ids', '1,2,3', 'ID-të e adminëve zhvillues', '2025-10-02 11:49:12', '2025-10-04 23:30:48'),
(16, 'developer_emails', 'admin@noteria.com,developer@noteria.com,dev@noteria.com,support@noteria.com,newdev@noteria.com', 'Email-at e zhvilluesve', '2025-10-02 11:49:12', '2025-10-04 23:30:48'),
(41, 'admin_login_log', '2025-10-02 12:23:11 - Admin login: admin@noteria.com (ID: 1)\n2025-10-04 11:03:43 - Admin login: admin@noteria.com (ID: 1)\n2025-10-04 23:30:48 - Admin login: developer@noteria.com (ID: 2)', 'Log i kyçjes së adminëve', '2025-10-02 12:23:11', '2025-10-04 23:30:48');

-- --------------------------------------------------------

--
-- Table structure for table `billing_statistics`
--

CREATE TABLE `billing_statistics` (
  `id` int(11) NOT NULL,
  `billing_date` date NOT NULL,
  `total_noters_processed` int(11) DEFAULT 0,
  `successful_charges` int(11) DEFAULT 0,
  `failed_charges` int(11) DEFAULT 0,
  `total_amount_charged` decimal(12,2) DEFAULT 0.00,
  `processing_time_seconds` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `camera_access_logs`
--

CREATE TABLE `camera_access_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `access_time` datetime NOT NULL,
  `action` enum('view','export','configure','maintenance') NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `camera_access_logs`
--

INSERT INTO `camera_access_logs` (`id`, `user_id`, `camera_id`, `access_time`, `action`, `ip_address`, `user_agent`, `notes`, `created_at`) VALUES
(1, 1, 1, '2025-10-04 22:55:31', 'view', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-04 20:55:31'),
(2, 1, 1, '2025-10-04 22:56:01', 'view', 'unknown', 'unknown', NULL, '2025-10-04 20:56:01'),
(3, 1, 1, '2025-10-04 22:56:28', 'view', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-04 20:56:28'),
(4, 1, 1, '2025-10-04 22:57:01', 'view', 'unknown', 'unknown', NULL, '2025-10-04 20:57:01');

-- --------------------------------------------------------

--
-- Table structure for table `camera_configurations`
--

CREATE TABLE `camera_configurations` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `camera_configurations`
--

INSERT INTO `camera_configurations` (`id`, `camera_id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 1, 'test_setting', 'test_value_1759611421', '2025-10-04 20:55:31', '2025-10-04 20:57:01'),
(2, 1, 'test_setting_1759611361', 'test_value', '2025-10-04 20:56:01', '2025-10-04 20:56:01');

-- --------------------------------------------------------

--
-- Table structure for table `camera_recordings`
--

CREATE TABLE `camera_recordings` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `duration` int(11) DEFAULT 0,
  `recording_type` enum('continuous','motion','manual','scheduled') DEFAULT 'manual',
  `status` enum('recording','completed','error') DEFAULT 'recording',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_history`
--

CREATE TABLE `chat_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `role` enum('user','assistant') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_history`
--

INSERT INTO `chat_history` (`id`, `user_id`, `message`, `role`, `created_at`) VALUES
(1, 10, 'CREATE TABLE chat_history (     id INT AUTO_INCREMENT PRIMARY KEY,     user_id INT,     message TEXT,     role ENUM(\'user\',\'assistant\'),     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP );', 'user', '2025-09-07 16:27:47'),
(2, 10, 'Përshëndetje?', 'user', '2025-09-07 16:28:09'),
(3, 10, 'Përshëndetje?', 'user', '2025-09-07 16:28:25'),
(4, 10, 'Përshëndetje?', 'user', '2025-09-07 16:37:32'),
(5, 10, 'pyetje?', 'user', '2025-09-07 16:37:53'),
(6, 10, 'fatura?', 'user', '2025-09-07 16:38:11'),
(7, 10, 'zyrë?', 'user', '2025-09-07 16:41:49'),
(8, 10, 'Përshëndetje?', 'user', '2025-09-07 16:42:04'),
(9, 10, 'termin i lirë', 'user', '2025-09-07 16:42:35'),
(10, 10, 'termin i lirë', 'user', '2025-09-07 16:44:49'),
(11, 10, 'termin i lirë', 'user', '2025-09-07 16:46:41'),
(12, 10, 'termin i lirë', 'user', '2025-09-07 16:47:49'),
(13, 10, 'termin i lirë', 'user', '2025-09-07 16:49:39'),
(14, 10, 'termin i lirë', 'user', '2025-09-07 16:51:25'),
(15, 10, 'zyrë?', 'user', '2025-09-07 20:56:41'),
(16, 10, 'zyrë?', 'user', '2025-09-07 21:01:23'),
(17, 10, 'fatura?', 'user', '2025-09-07 21:46:17'),
(18, 10, 'fatura?', 'user', '2025-09-07 21:50:57');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `file_path`, `uploaded_at`) VALUES
(1, 24, 'uploads/doc_68a342f14eafe0.39403467.pdf', '2025-08-18 15:12:49'),
(2, 24, 'uploads/doc_68a342fdd37ef1.05314396.pdf', '2025-08-18 15:13:01'),
(3, 10, 'uploads/doc_68a435aca08722.90823633.pdf', '2025-08-19 08:28:28'),
(4, 10, 'uploads/doc_68a44001c95bd7.00530239.pdf', '2025-08-19 09:12:33'),
(5, 25, 'uploads/doc_68a48f40933005.85620310.pdf', '2025-08-19 14:50:40'),
(6, 25, 'uploads/doc_68a490f6e0ec97.34864709.pdf', '2025-08-19 14:57:58'),
(7, 10, 'uploads/doc_68a4eec6372762.27625449.pdf', '2025-08-19 21:38:14'),
(8, 10, 'uploads/doc_68a4eece6ca7a4.78294052.pdf', '2025-08-19 21:38:22'),
(9, 10, 'uploads/doc_68a4eed8473b75.84296199.pdf', '2025-08-19 21:38:32'),
(10, 10, 'uploads/doc_68a4fdd97da660.48867411.pdf', '2025-08-19 22:42:33'),
(11, 10, 'uploads/doc_68a58f2d9fbf26.05336039.pdf', '2025-08-20 09:02:37'),
(12, 10, 'uploads/doc_68af39c6e2c184.94834134.png', '2025-08-27 17:00:54'),
(13, 10, 'uploads/doc_68b21f5a029f11.47806149.png', '2025-08-29 21:44:58'),
(14, 10, 'uploads/doc_68b21f6536cb13.45620628.png', '2025-08-29 21:45:09'),
(15, 10, 'uploads/doc_68b2210df37a21.87693538.png', '2025-08-29 21:52:13'),
(16, 10, 'uploads/doc_68b22115203ad5.55433665.png', '2025-08-29 21:52:21'),
(17, 10, 'uploads/doc_68b2211a562db0.14241603.png', '2025-08-29 21:52:26'),
(18, 10, 'uploads/doc_68c1a134603aa8.74966775.pdf', '2025-09-10 16:03:00'),
(19, 10, 'uploads/doc_68c5d0e0162fd8.03304640.png', '2025-09-13 20:15:28'),
(20, 10, 'uploads/doc_68d1a499919930.37324982.pdf', '2025-09-22 19:33:45'),
(21, 10, 'uploads/doc_68d1a4a6466762.25310287.pdf', '2025-09-22 19:33:58'),
(22, 10, 'uploads/doc_68d1a4b45b5ed4.13192732.pdf', '2025-09-22 19:34:12'),
(23, 10, 'uploads/doc_68de2eb7368e92.72128241.png', '2025-10-02 07:50:15'),
(24, 10, 'uploads/doc_68de2ec1663c05.43506286.png', '2025-10-02 07:50:25'),
(25, 10, 'uploads/doc_68de2ec9f040a0.70975707.png', '2025-10-02 07:50:33'),
(26, 10, 'uploads/doc_68e636b6776b77.63838440.png', '2025-10-08 10:02:30'),
(27, 10, 'uploads/doc_68fa8e27c42e98.34992046.pdf', '2025-10-23 20:20:55'),
(28, 10, 'uploads/doc_68fa8e31ea67e3.92123485.pdf', '2025-10-23 20:21:05'),
(29, 10, 'uploads/doc_68fa8e374be6c7.44697333.pdf', '2025-10-23 20:21:11');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`) VALUES
(1, 'Si të regjistrohem?', 'Kliko te butoni Regjistrohu dhe plotëso të dhënat.'),
(2, 'Si të ndryshoj fjalëkalimin?', 'Shko te profili yt dhe zgjidh Ndrysho Fjalëkalimin.'),
(3, 'Si të regjistrohem si zyrë në platformë?', 'Klikoni te butoni \"Regjistro Zyrën\", plotësoni të gjitha të dhënat e kërkuara dhe kryeni pagesën për të aktivizuar llogarinë tuaj.'),
(4, 'A mund të ndryshoj të dhënat e zyrës sime?', 'Po, pasi të kyçeni në platformë, mund të ndryshoni të dhënat e zyrës nga profili juaj.'),
(5, 'Si bëhet pagesa për shfrytëzimin e platformës?', 'Pagesa bëhet gjatë regjistrimit përmes transferit bankar ose Paysera. Pas verifikimit të pagesës, zyra juaj do të aktivizohet.'),
(6, 'Çfarë ndodh nëse harroj fjalëkalimin?', 'Klikoni te \"Keni harruar fjalëkalimin?\" dhe ndiqni udhëzimet për të rikuperuar fjalëkalimin përmes emailit.'),
(7, 'A janë të sigurta të dhënat e mia?', 'Po, të gjitha të dhënat ruhen në mënyrë të sigurt dhe përpunohen sipas ligjit të Kosovës dhe GDPR.'),
(8, 'Si mund të rezervoj një termin?', 'Pasi të kyçeni, zgjidhni opsionin për rezervim, plotësoni të dhënat dhe konfirmoni rezervimin.'),
(9, 'Kush ka qasje në të dhënat e mia?', 'Vetëm ju dhe administrata e platformës keni qasje në të dhënat tuaja. Të dhënat nuk shpërndahen me palë të treta pa pëlqimin tuaj.'),
(10, 'Si mund të kontaktoj mbështetjen?', 'Për çdo pyetje ose problem, mund të na kontaktoni në emailin zyrtar të platformës ose përmes formularit të kontaktit.'),
(11, 'Çfarë role ekzistojnë në platformë?', 'Platforma ka role të ndryshme: admin, zyrtar dhe përdorues i thjeshtë. Secili rol ka të drejta të ndryshme aksesimi.'),
(12, 'A mund të fshij llogarinë time?', 'Po, mund të kërkoni fshirjen e llogarisë duke kontaktuar administratën e platformës.');

-- --------------------------------------------------------

--
-- Table structure for table `fatura`
--

CREATE TABLE `fatura` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `nr_fatures` varchar(50) NOT NULL,
  `data_fatures` date NOT NULL,
  `shuma` decimal(10,2) NOT NULL,
  `pershkrimi` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fatura`
--

INSERT INTO `fatura` (`id`, `reservation_id`, `zyra_id`, `nr_fatures`, `data_fatures`, `shuma`, `pershkrimi`, `created_at`) VALUES
(1, 7, 3, '121212122', '2025-08-25', 10.00, 'Vërtetim', '2025-08-07 15:02:16'),
(2, 7, 3, '121212122', '2025-08-08', 50.00, 'Vërtetim', '2025-08-07 16:42:13'),
(3, 17, 6, '12547894228', '2025-08-12', 150.00, 'Kontratë Prone', '2025-08-10 21:56:21'),
(4, 13, 3, '43158', '2025-08-20', 150.00, 'Kontratë Prone', '2025-08-20 09:10:21');

-- --------------------------------------------------------

--
-- Table structure for table `faturat`
--

CREATE TABLE `faturat` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `banka` varchar(64) NOT NULL,
  `shuma` decimal(10,2) NOT NULL,
  `data` datetime NOT NULL,
  `status` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faturat`
--

INSERT INTO `faturat` (`id`, `zyra_id`, `banka`, `shuma`, `data`, `status`) VALUES
(1, 14, 'Banka për Biznes', 130.00, '2025-09-19 21:42:18', 'Paguar'),
(2, 20, 'ProCredit Bank', 130.00, '2025-09-20 16:34:56', 'Paguar'),
(3, 21, 'Banka për Biznes', 130.00, '2025-09-20 17:39:42', 'Paguar'),
(4, 21, 'Banka për Biznes', 130.00, '2025-09-20 17:44:50', 'Paguar');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `vat` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `date_issued` date NOT NULL,
  `due_date` date NOT NULL,
  `service_period_start` date DEFAULT NULL,
  `service_period_end` date DEFAULT NULL,
  `status` enum('draft','issued','paid','cancelled','overdue') NOT NULL DEFAULT 'draft',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `titulli` varchar(255) NOT NULL,
  `pershkrimi` text NOT NULL,
  `afati` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefoni` varchar(20) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `mesazhi` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `konkurset`
--

CREATE TABLE `konkurset` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `pozita` varchar(255) NOT NULL,
  `pershkrimi` text NOT NULL,
  `afati` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `konkurset`
--

INSERT INTO `konkurset` (`id`, `zyra_id`, `pozita`, `pershkrimi`, `afati`, `created_at`) VALUES
(1, 22, 'Asistentë', 'Test', '2025-09-29', '2025-09-20 16:13:20'),
(2, 22, 'Asistentë', 'Test', '2025-09-29', '2025-09-20 16:15:27'),
(3, 22, 'Asistentë', 'Test', '2025-09-29', '2025-09-20 16:16:35'),
(4, 19, 'Asistentë', 'Nuk specifikohen', '2025-11-15', '2025-10-23 19:41:22'),
(5, 19, 'Asistentë', 'Nuk specifikohen', '2025-11-15', '2025-10-23 19:41:45');

-- --------------------------------------------------------

--
-- Table structure for table `lajme`
--

CREATE TABLE `lajme` (
  `id` int(11) NOT NULL,
  `titulli` varchar(255) DEFAULT NULL,
  `permbajtja` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lajme`
--

INSERT INTO `lajme` (`id`, `titulli`, `permbajtja`, `created_at`) VALUES
(1, 'Përshëndetje', 'Përshëndetje', '2025-08-18 17:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp(),
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `username_attempt` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `noteret`
--

CREATE TABLE `noteret` (
  `id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `nr_personal` varchar(50) NOT NULL,
  `nr_licences` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefoni` varchar(20) DEFAULT NULL,
  `adresa` text DEFAULT NULL,
  `qyteti` varchar(100) DEFAULT NULL,
  `shteti` varchar(100) DEFAULT 'Kosovë',
  `gjinia` enum('M','F') DEFAULT NULL,
  `data_lindjes` date DEFAULT NULL,
  `data_licencimit` date DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `statusi` enum('aktiv','joaktiv','pezulluar') DEFAULT 'aktiv',
  `verejtje` text DEFAULT NULL,
  `data_regjistrimit` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_perditesimit` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `noteret`
--

INSERT INTO `noteret` (`id`, `emri`, `mbiemri`, `nr_personal`, `nr_licences`, `email`, `telefoni`, `adresa`, `qyteti`, `shteti`, `gjinia`, `data_lindjes`, `data_licencimit`, `foto`, `statusi`, `verejtje`, `data_regjistrimit`, `data_perditesimit`) VALUES
(1, 'Arben', 'Krasniqi', '1234567890', 'LIC-2020-001', 'arben.krasniqi@noter.com', '044123456', 'Rr. Agim Ramadani nr. 23', 'Prishtinë', 'Kosovë', 'M', '1975-05-15', '2020-01-15', 'uploads/noter1.jpg', 'aktiv', '', '2025-09-27 15:24:56', '2025-09-27 15:24:56'),
(2, 'Vjosa', 'Berisha', '2345678901', 'LIC-2019-042', 'vjosa.berisha@noter.com', '045234567', 'Rr. Dardania nr. 5', 'Prizren', 'Kosovë', 'F', '1980-03-22', '2019-06-10', 'uploads/noter2.jpg', 'aktiv', '', '2025-09-27 15:24:56', '2025-09-27 15:24:56'),
(3, 'Driton', 'Hoxha', '3456789012', 'LIC-2018-118', 'driton.hoxha@noter.com', '049345678', 'Rr. Ilir Konushevci nr. 11', 'Pejë', 'Kosovë', 'M', '1972-11-08', '2018-11-20', 'uploads/noter3.jpg', 'aktiv', '', '2025-09-27 15:24:56', '2025-09-27 15:24:56'),
(4, 'Mimoza', 'Gashi', '4567890123', 'LIC-2021-033', 'mimoza.gashi@noter.com', '044456789', 'Rr. Qendra nr. 7', 'Gjakovë', 'Kosovë', 'F', '1983-07-19', '2021-03-05', 'uploads/noter4.jpg', 'aktiv', '', '2025-09-27 15:24:56', '2025-09-27 15:24:56'),
(5, 'Besnik', 'Rexhepi', '5678901234', 'LIC-2017-089', 'besnik.rexhepi@noter.com', '045567890', 'Rr. Lidhja e Prizrenit nr. 15', 'Ferizaj', 'Kosovë', 'M', '1970-02-28', '2017-09-12', 'uploads/noter5.jpg', 'aktiv', 'Licenca e pezulluar përkohësisht', '2025-09-27 15:24:56', '2025-09-27 15:25:21');

-- --------------------------------------------------------

--
-- Table structure for table `noteri`
--

CREATE TABLE `noteri` (
  `id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefoni` varchar(20) DEFAULT NULL,
  `adresa` text DEFAULT NULL,
  `qyteti` varchar(100) DEFAULT NULL,
  `shteti` varchar(100) DEFAULT NULL,
  `statusi` varchar(20) DEFAULT 'active',
  `custom_price` decimal(10,2) DEFAULT NULL,
  `subscription_status` varchar(20) DEFAULT 'active',
  `account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `subscription_type` enum('standard','premium','custom') DEFAULT 'standard',
  `data_regjistrimit` datetime DEFAULT current_timestamp(),
  `operator` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `noteri`
--

INSERT INTO `noteri` (`id`, `emri`, `mbiemri`, `email`, `telefoni`, `adresa`, `qyteti`, `shteti`, `statusi`, `custom_price`, `subscription_status`, `account_number`, `bank_name`, `created_at`, `updated_at`, `status`, `subscription_type`, `data_regjistrimit`, `operator`) VALUES
(1, 'Arben', 'Krasniqi', 'arben.krasniqi@noteria.al', '044123456', 'Rr. Adem Jashari, nr. 15', 'Prishtinë', 'Kosovë', 'active', NULL, 'active', 'AL12345678901234567890', 'BKT', '2025-09-27 12:32:43', '2025-09-27 16:07:59', 'active', 'standard', '2025-09-27 16:07:16', NULL),
(2, 'Lumnije', 'Berisha', 'lumnije.berisha@noteria.al', '045789012', 'Rr. Nëna Terezë, nr. 28', 'Prizren', 'Kosovë', 'active', NULL, 'active', 'AL09876543210987654321', 'Raiffeisen Bank', '2025-09-27 12:32:43', '2025-09-27 12:32:43', 'active', 'standard', '2025-09-27 16:07:16', NULL),
(3, 'Blerim', 'Hoxha', 'blerim.hoxha@noteria.al', '049567890', 'Rr. Fan Noli, nr. 7', 'Gjakovë', 'Kosovë', 'active', NULL, 'active', 'AL54321678901234509876', 'ProCredit Bank', '2025-09-27 12:32:43', '2025-09-27 12:32:43', 'active', 'standard', '2025-09-27 16:07:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `noteri_abonimet`
--

CREATE TABLE `noteri_abonimet` (
  `id` int(11) NOT NULL,
  `noter_id` int(11) NOT NULL,
  `abonim_id` int(11) NOT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date NOT NULL,
  `status` enum('aktiv','skaduar','anuluar') NOT NULL DEFAULT 'aktiv',
  `paguar` decimal(10,2) NOT NULL,
  `menyra_pageses` varchar(50) DEFAULT NULL,
  `transaksion_id` varchar(255) DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `noteri_abonimet`
--

INSERT INTO `noteri_abonimet` (`id`, `noter_id`, `abonim_id`, `data_fillimit`, `data_mbarimit`, `status`, `paguar`, `menyra_pageses`, `transaksion_id`, `krijuar_me`) VALUES
(1, 1, 5, '2025-09-04', '2026-04-23', 'aktiv', 150.00, 'PayPal', 'TRX202509273720', '2025-09-27 16:13:47'),
(2, 2, 5, '2025-09-22', '2026-08-03', 'aktiv', 150.00, 'Kartelë krediti', 'TRX202509273230', '2025-09-27 16:13:47'),
(3, 3, 5, '2025-09-03', '2026-08-08', 'aktiv', 150.00, 'PayPal', 'TRX202509277607', '2025-09-27 16:13:47'),
(4, 4, 6, '2025-09-11', '2026-08-24', 'aktiv', 1800.00, 'Kartelë krediti', 'TRX202509274954', '2025-09-27 16:13:47'),
(5, 5, 5, '2025-09-16', '2026-03-03', 'aktiv', 150.00, 'Kartelë krediti', 'TRX202509275952', '2025-09-27 16:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `client_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('card','bank_transfer','cash') NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reservation_id`, `client_name`, `amount`, `payment_method`, `status`, `transaction_id`, `payment_date`, `created_at`, `updated_at`) VALUES
(1, 1, 'Agim Berisha', 45.00, 'card', 'completed', 'TR-12345', '2025-09-25 10:30:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(2, 2, 'Vjollca Krasniqi', 120.00, 'bank_transfer', 'completed', 'TR-12346', '2025-09-26 12:00:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(3, 3, 'Burim Hoxha', 30.00, 'cash', 'completed', 'TR-12347', '2025-09-26 14:30:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(4, 4, 'Drita Gashi', 100.00, 'card', 'pending', 'TR-12348', NULL, '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(5, 5, 'Fatmir Shala', 25.00, 'cash', 'completed', 'TR-12349', '2025-09-27 13:15:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(6, 6, 'Mimoza Rexhepi', 80.00, 'card', 'refunded', 'TR-12350', '2025-09-28 11:00:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(7, 7, 'Blerim Morina', 90.00, 'bank_transfer', 'completed', 'TR-12351', '2025-09-28 15:45:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(8, 8, 'Teuta Hyseni', 50.00, 'cash', 'pending', 'TR-12352', NULL, '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(9, 9, 'Arben Kelmendi', 75.00, 'card', 'completed', 'TR-12353', '2025-09-29 17:00:00', '2025-09-27 15:47:31', '2025-09-27 15:47:31'),
(10, 10, 'Shpresa Ahmeti', 110.00, 'bank_transfer', 'pending', 'TR-12354', NULL, '2025-09-27 15:47:31', '2025-09-27 15:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `payment_audit_log`
--

CREATE TABLE `payment_audit_log` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `action` enum('created','verified','failed','cancelled','refunded') NOT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_logs`
--

CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL,
  `office_email` varchar(255) NOT NULL,
  `office_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `operator` varchar(50) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `phone_verified` tinyint(1) DEFAULT 0,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `numri_fiskal` varchar(20) DEFAULT NULL,
  `numri_biznesit` varchar(20) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('bank_transfer','paypal','card') DEFAULT 'bank_transfer',
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `verification_attempts` int(11) DEFAULT 0,
  `api_response` text DEFAULT NULL,
  `payment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_logs`
--

INSERT INTO `payment_logs` (`id`, `office_email`, `office_name`, `phone_number`, `operator`, `payment_amount`, `payment_details`, `verification_status`, `phone_verified`, `phone_verified_at`, `file_path`, `numri_fiskal`, `numri_biznesit`, `admin_notes`, `transaction_id`, `amount`, `payment_method`, `status`, `verification_attempts`, `api_response`, `payment_data`, `created_at`, `updated_at`, `verified_at`) VALUES
(1, 'avni.bobaj@gmail.com', NULL, NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 130.00, 'bank_transfer', '', 0, 'cURL Error: OpenSSL/3.1.3: error:0A000458:SSL routines::tlsv1 unrecognized name', '{\"transaction_id\":\"TXN_20250922_225911_972321bc\",\"amount\":130,\"method\":\"bank_transfer\",\"email\":\"avni.bobaj@gmail.com\",\"bank\":\"TEB Bank\",\"iban\":\"XK055001000137385385\",\"office_name\":\"Avni Bobaj\",\"city\":\"Prishtin\\u00eb\"}', '2025-09-22 21:07:41', '2025-09-22 21:07:41', NULL),
(3, 'avni.bobaj@gmail.com', NULL, NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 130.00, 'bank_transfer', '', 0, 'cURL Error: OpenSSL/3.1.3: error:0A000458:SSL routines::tlsv1 unrecognized name', '{\"transaction_id\":\"TXN_20250922_225911_972321bc\",\"amount\":130,\"method\":\"bank_transfer\",\"email\":\"avni.bobaj@gmail.com\",\"bank\":\"TEB Bank\",\"iban\":\"XK055001000137385385\",\"office_name\":\"Avni Bobaj\",\"city\":\"Prishtin\\u00eb\"}', '2025-09-22 21:08:34', '2025-09-22 21:08:34', NULL),
(4, 'avni.bobaj1@gmail.com', NULL, NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 130.00, 'card', 'failed', 0, NULL, '{\"transaction_id\":\"TXN_20250922_225911_972321bc\",\"amount\":130,\"method\":\"card\",\"email\":\"avni.bobaj1@gmail.com\",\"bank\":\"One For Kosovo\",\"iban\":\"XK055001000137385385\",\"office_name\":\"Avni Bobaj\",\"city\":\"Mitrovic\\u00eb\"}', '2025-09-22 21:10:57', '2025-09-22 21:10:57', NULL),
(5, 'avni.bobaj1@gmail.com', 'Avni Bobaj', NULL, NULL, 130.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-22 21:49:35', '2025-09-22 21:49:35', NULL),
(6, 'avni.bobaj1@gmail.com', 'Avni Bobaj', NULL, NULL, 130.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-22 21:49:58', '2025-09-22 21:49:58', NULL),
(7, 'avni.bobaj@gmail.com', 'Avni Bobaj', NULL, NULL, 130.00, 'IBAN: XK055001000137385385, Banka: TEB Bank, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-22 22:00:13', '2025-09-22 22:00:13', NULL),
(8, 'avni.bobaj1@gmail.com', 'Avni Bobaj', '+38345434711', NULL, 130.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-22 22:25:31', '2025-09-22 22:25:31', NULL),
(9, 'avni.bobaj1@gmail.com', 'Avni Bobaj', '+38345434711', NULL, 130.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-22 22:29:57', '2025-09-22 22:29:57', NULL),
(10, 'avni.bobaj1@gmail.com', 'Avni Bobaj', '+38345434711', NULL, 130.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_225911_972321bc.png', NULL, NULL, NULL, 'TXN_20250922_225911_972321bc', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-22 22:44:36', '2025-09-22 22:44:36', NULL),
(11, 'valonsadiku2018@gmail.com', 'Valon Sadiku', '+38345213675', NULL, 130.00, 'IBAN: XK055001000137385385, Banka: NLB Bank, Llogaria: 1702018603253707', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250922_004436_910183ff.pdf', NULL, NULL, NULL, 'TXN_20250922_004436_910183ff', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-22 22:49:37', '2025-09-22 22:49:37', NULL),
(12, 'shpresa.qamili@gmail.com', 'Shpresë Qamili', '+38344991789', NULL, 50.00, 'IBAN: XK055001000137385385, Banka: Raiffeisen Bank, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250923_100739_1644767e.png', NULL, NULL, NULL, 'TXN_20250923_100739_1644767e', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-23 08:10:11', '2025-09-23 08:10:11', NULL),
(13, 'valonsadiku2018@gmail.com', 'Avni Bobaj', '+38344991789', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: Credins Bank, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250923_162611_b5eda433.pdf', NULL, NULL, NULL, 'TXN_20250923_162611_b5eda433', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-23 14:37:41', '2025-09-23 14:37:41', NULL),
(14, 'dredhza.bickaj@gmail.com', 'Dredhëza Biçkaj', '+38345123456', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250925_163106_1d9659de.png', NULL, NULL, NULL, 'TXN_20250925_163106_1d9659de', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-25 15:17:59', '2025-09-25 15:17:59', NULL),
(15, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250926_222309_c42880df.png', NULL, NULL, NULL, 'TXN_20250926_222309_c42880df', 0.00, 'paypal', 'pending', 0, NULL, NULL, '2025-09-26 20:25:23', '2025-09-26 20:25:23', NULL),
(16, 'valonsadiku2018@gmail.com', 'Avni Bobaj', '+38344991789', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: Credins Bank, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250923_162611_b5eda433.pdf', NULL, NULL, NULL, 'TXN_20250923_162611_b5eda433', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-09-26 21:28:14', '2025-09-26 21:28:14', NULL),
(17, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, NULL, NULL, NULL, NULL, 'TXN_20250927_002549_a418d747', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-26 22:25:49', '2025-09-26 22:25:49', NULL),
(18, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_002737_327d0e6d.png', NULL, NULL, NULL, 'TXN_20250927_002737_327d0e6d', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-26 22:27:37', '2025-09-26 22:27:37', NULL),
(19, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', NULL, 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_002916_8a4383af.png', NULL, NULL, NULL, 'TXN_20250927_002916_8a4383af', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-26 22:29:16', '2025-09-26 22:29:16', NULL),
(20, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', '', 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235, Numri Fiskal: ', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_011555_eb125f6f.png', '', '', NULL, 'TXN_20250927_011555_eb125f6f', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-26 23:15:55', '2025-09-26 23:15:55', NULL),
(21, 'gezim.vushtrria@gmail.com', 'Noeria Gëzim Vushtrria', '+38345841111', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_094628_bb2b10ce.png', '123456789', 'ABC1234567', NULL, 'TXN_20250927_094628_bb2b10ce', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-27 07:46:28', '2025-09-27 07:46:28', NULL),
(22, 'gezim.vushtrria@gmail.com', 'Noeria Gëzim Vushtrria', '+38345841111', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_111904_6196d4e6.png', '123456789', 'ABC1234567', NULL, 'TXN_20250927_111904_6196d4e6', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-27 09:19:04', '2025-09-27 09:19:04', NULL),
(23, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', '', 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235, Numri Fiskal: ', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_124654_732a47ac.png', '', '', NULL, 'TXN_20250927_124654_732a47ac', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-27 10:46:54', '2025-09-27 10:46:54', NULL),
(24, 'valonsadiku2018@gmail.com', 'Dredhëza', '+38344994242', '', 150.00, 'IBAN: XK055001000137385385, Banka: One For Kosovo, Llogaria: 54323245232556235, Numri Fiskal: ', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250927_144926_7c01a7b7.png', '', '', NULL, 'TXN_20250927_144926_7c01a7b7', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-27 12:49:26', '2025-09-27 12:49:26', NULL),
(25, 'gezim.vushtrria@gmail.com', 'Noeria Gëzim Vushtrria', '+38345841111', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250929_104400_9b01746b.png', '123456789', 'ABC1234567', NULL, 'TXN_20250929_104400_9b01746b', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-29 08:44:00', '2025-09-29 08:44:00', NULL),
(26, 'gezim.vushtrria@gmail.com', 'Noeria Gëzim Vushtrria', '+38345841111', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250929_111833_f2ffae4d.png', '123456789', 'ABC1234567', NULL, 'TXN_20250929_111833_f2ffae4d', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-29 09:18:33', '2025-09-29 09:18:33', NULL),
(27, 'avni.bobaj@gmail.com', 'Avni Bobaj', '+38344269896', '', 130.00, 'IBAN: XK055001000137385385, Banka: TEB Bank, Llogaria: 54323245232556235, Numri Fiskal: ', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250930_031714_bfd4eebb.png', '', '', NULL, 'TXN_20250930_031714_bfd4eebb', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-30 01:17:14', '2025-09-30 01:17:14', NULL),
(28, 'avni.bobaj@gmail.com', 'Avni Bobaj', '+38344269896', '', 130.00, 'IBAN: XK055001000137385385, Banka: TEB Bank, Llogaria: 54323245232556235, Numri Fiskal: ', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20250930_031925_554c45b5.png', '', '', NULL, 'TXN_20250930_031925_554c45b5', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-09-30 01:19:25', '2025-09-30 01:19:25', NULL),
(29, 'zyra.valonsadiku@gmail.com', 'Valon Sadiku', '+38345213675', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789, Abonim ID: 5', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20251004_221332_2b7b3a23.png', '123456789', '1234567891', NULL, 'TXN_20251004_221332_2b7b3a23', 0.00, 'card', 'pending', 0, NULL, NULL, '2025-10-04 20:13:32', '2025-10-04 20:13:32', NULL),
(30, 'zyra.valonsadiku@gmail.com', 'Valon Sadiku', '+38345213675', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: TEB BANKA, Llogaria: 54323245232556235, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20251004_230518_99167cf7.png', '123456789', '1234567891', NULL, 'TXN_20251004_230518_99167cf7', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-10-04 21:05:18', '2025-10-04 21:05:18', NULL),
(31, 'valonsadiku2018@gmail.com', 'Valon Sadiku', '+38345213675', 'Vala', 150.00, 'IBAN: XK055001000137385385, Banka: NLB BANK, Llogaria: 1702018603253707, Numri Fiskal: 123456789', 'pending', 0, NULL, 'uploads/payment_proofs/TXN_20251005_224213_90e88bbb.png', '123456789', 'ABC1234567', NULL, 'TXN_20251005_224213_90e88bbb', 0.00, 'bank_transfer', 'pending', 0, NULL, NULL, '2025-10-05 20:42:13', '2025-10-05 20:42:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `phone_verification_codes`
--

CREATE TABLE `phone_verification_codes` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `verification_code` varchar(10) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `attempts` int(11) DEFAULT 0,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `phone_verification_codes`
--

INSERT INTO `phone_verification_codes` (`id`, `phone_number`, `verification_code`, `transaction_id`, `expires_at`, `attempts`, `is_used`, `created_at`) VALUES
(1, '+38345434711', '666899', 'TXN_20250922_225911_972321bc', '2025-09-22 22:32:57', 0, 0, '2025-09-22 22:29:57'),
(2, '+38344123456', '622449', 'TEST_TXN_123', '2025-09-22 22:34:33', 0, 0, '2025-09-22 22:31:33'),
(3, '+38344123456', '366075', 'TEST_TXN_123', '2025-09-22 22:35:25', 1, 1, '2025-09-22 22:32:38'),
(4, '+38344123456', '804348', 'TEST_1758580462', '2025-09-22 22:34:22', 1, 0, '2025-09-22 22:34:22'),
(5, '+38344999888', '827766', 'TEST_FINAL_1758580594', '2025-09-22 22:36:34', 1, 1, '2025-09-22 22:36:34'),
(6, '+38349123456', '531729', 'TEST_VALA_1758580801', '2025-09-22 22:40:02', 1, 1, '2025-09-22 22:40:01'),
(7, '+38345434711', '920541', 'TXN_20250922_225911_972321bc', '2025-09-22 22:47:36', 0, 0, '2025-09-22 22:44:36'),
(8, '+38345213675', '843746', 'TXN_20250922_004436_910183ff', '2025-09-22 22:49:48', 1, 0, '2025-09-22 22:49:37'),
(9, '+38344991789', '912701', 'TXN_20250923_100739_1644767e', '2025-09-23 08:10:17', 1, 0, '2025-09-23 08:10:11'),
(10, '+38344991789', '568658', 'TXN_20250923_162611_b5eda433', '2025-09-23 14:40:41', 0, 0, '2025-09-23 14:37:41'),
(11, '+38345123456', '935667', 'TXN_20250925_163106_1d9659de', '2025-09-25 15:18:18', 1, 0, '2025-09-25 15:17:59'),
(12, '+38344994242', '592914', 'TXN_20250926_222309_c42880df', '2025-09-26 20:28:23', 0, 0, '2025-09-26 20:25:23'),
(13, '+38344991789', '255866', 'TXN_20250923_162611_b5eda433', '2025-09-26 21:31:14', 0, 0, '2025-09-26 21:28:14');

-- --------------------------------------------------------

--
-- Table structure for table `phone_verification_logs`
--

CREATE TABLE `phone_verification_logs` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `action_type` enum('send','verify','resend') NOT NULL,
  `provider` varchar(50) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `response_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privacy_policy`
--

CREATE TABLE `privacy_policy` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `punetoret`
--

CREATE TABLE `punetoret` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefoni` varchar(20) DEFAULT NULL,
  `pozita` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `punetoret`
--

INSERT INTO `punetoret` (`id`, `zyra_id`, `emri`, `mbiemri`, `email`, `telefoni`, `pozita`, `password`, `active`, `created_at`, `last_login`) VALUES
(1, 15, 'Avni', 'Ramadani', 'avni.ramadani@gmail.com', '+38345555555', 'Noter', '$2y$10$S3JvlmdRc52GQwzlMCnygugeaWCjVvHFWXywGl/npyEk6iamfT2WO', 1, '2025-09-19 19:49:46', NULL),
(2, 15, 'Dredhëza', 'Sadikaj', 'dredheza.sadikaj@gmail.com', '+38345555555', 'Asistent', '$2y$10$fwiVf2D3iAc/cX.pYgoKhu8YGbv3WSn9k2h.r7V/42JNQqUiMtJLu', 1, '2025-09-19 19:54:17', NULL),
(3, 16, 'Liridona', 'Ajeti', 'liridona.ajeti@gmail.com', '+38345123456', 'Asistent', '$2y$10$cqLLQwPoM7LDtTaDR6eAeOs.LaKPs5mG9PCIQUiggZrxESw9nqpAW', 1, '2025-09-19 20:24:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `raportet`
--

CREATE TABLE `raportet` (
  `id` int(11) NOT NULL,
  `lloji` varchar(50) NOT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `generated_by` varchar(100) DEFAULT NULL,
  `raport_data` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `service` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('në pritje','aprovohet','refuzohet') DEFAULT 'në pritje'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `zyra_id`, `service`, `date`, `time`, `created_at`, `document_path`, `status`) VALUES
(1, 6, 0, 'Legalizim', '2025-08-05', '09:45:00', '2025-08-04 16:00:37', NULL, 'në pritje'),
(2, 6, 0, 'Vertetim Dokumenti', '2025-08-09', '15:05:00', '2025-08-04 16:05:51', NULL, 'në pritje'),
(3, 11, 1, 'Deklaratë', '2025-08-08', '09:58:00', '2025-08-05 16:55:53', NULL, 'në pritje'),
(4, 11, 1, 'Legalizim', '2025-08-08', '10:07:00', '2025-08-05 17:03:39', NULL, 'në pritje'),
(5, 11, 3, 'Vertetim Dokumenti', '2025-08-08', '09:24:00', '2025-08-05 17:22:58', 'uploads/68923df2d23e3_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(6, 14, 1, 'Deklaratë', '2025-08-08', '15:25:00', '2025-08-05 17:46:59', 'uploads/68924393c2837_MICS6_Test_100_Pyetje_Kategorite.pdf', 'në pritje'),
(7, 14, 3, 'Vertetim Dokumenti', '2025-09-29', '09:02:00', '2025-08-07 14:11:01', 'uploads/6894b3f54dd6e_MICS6_Test_100_Pyetje_Kategorite.pdf', 'në pritje'),
(8, 14, 3, 'Vertetim Dokumenti', '2025-08-11', '10:30:00', '2025-08-08 09:05:38', 'uploads/6895bde283bf5_MICS6 Questionnaire for Children Under Five ALB_20191128 (1).pdf', 'në pritje'),
(9, 14, 3, 'Vertetim Dokumenti', '2025-08-11', '11:30:00', '2025-08-08 09:17:16', 'uploads/6895c09c7c7ab_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(10, 14, 3, 'Deklaratë', '2025-08-12', '14:25:00', '2025-08-08 09:22:57', 'uploads/6895c1f13558d_Testi_Ushtrues_MICS_85_Pyetje_Final.pdf', 'në pritje'),
(11, 14, 3, 'Kontratë', '2025-08-13', '14:30:00', '2025-08-08 09:29:52', 'uploads/6895c390513c3_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(12, 14, 3, 'Vertetim Dokumenti', '2025-08-13', '10:37:00', '2025-08-08 09:34:02', 'uploads/6895c48a5858e_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(13, 14, 3, 'Legalizim', '2025-08-14', '11:36:00', '2025-08-08 09:37:03', 'uploads/6895c53f251be_MICS6 Questionnaire for Children Under Five ALB_20191128 (1).pdf', 'në pritje'),
(14, 14, 3, 'Legalizim', '2025-08-15', '09:31:00', '2025-08-10 09:02:03', 'uploads/6898600b6ee8d_1000015350.jpg', 'në pritje'),
(15, 14, 1, 'Deklaratë', '2025-08-12', '11:15:00', '2025-08-10 09:15:50', 'uploads/68986346441e2_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(16, 14, 1, 'Deklaratë', '2025-08-12', '10:15:00', '2025-08-10 09:18:16', 'uploads/689863d818504_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(17, 17, 6, 'Kontratë', '2025-08-12', '10:30:00', '2025-08-10 15:33:56', 'uploads/6898bbe491bab_MICS6 Questionnaire for Children Age 5-17 ALB_20191128 (1) (3).pdf', 'në pritje'),
(18, 19, 6, 'Legalizim', '2025-08-12', '11:30:00', '2025-08-10 22:00:04', 'uploads/689916642721c_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(19, 19, 1, 'Legalizim', '2025-08-12', '06:12:00', '2025-08-10 22:13:54', 'uploads/689919a2f1f1f_MICS6 Questionnaire for Children Under Five ALB_20191128 (1).pdf', 'në pritje'),
(20, 20, 3, 'Kontratë', '2025-08-18', '10:00:00', '2025-08-14 10:00:18', 'uploads/689db3b233966_MICS6 Household Questionnaire ALB_20191128 (1) (1).pdf', 'në pritje'),
(21, 21, 0, 'Vertetim Dokumenti', '2025-08-15', '14:58:00', '2025-08-14 15:59:21', NULL, 'në pritje'),
(22, 23, 0, 'Legalizim', '2025-08-18', '13:03:00', '2025-08-15 22:04:00', NULL, 'në pritje'),
(23, 25, 0, 'Kontratë', '2025-08-22', '13:30:00', '2025-08-19 14:58:52', NULL, 'në pritje'),
(24, 10, 0, 'Vertetim Dokumenti', '2025-08-22', '10:30:00', '2025-08-19 22:42:40', NULL, 'në pritje'),
(25, 10, 0, 'Kontratë', '2025-08-22', '15:08:00', '2025-08-20 09:04:09', NULL, 'në pritje'),
(26, 10, 0, 'Legalizim', '2025-08-22', '10:45:00', '2025-08-20 09:46:44', NULL, 'në pritje'),
(27, 10, 0, 'Autorizim për vozitje të automjetit', '2025-01-09', '10:30:00', '2025-08-29 21:43:39', NULL, 'në pritje'),
(28, 10, 0, 'Kontratë për Shitblerje të Veturës', '2025-09-02', '11:30:00', '2025-08-29 21:54:57', NULL, 'në pritje'),
(29, 10, 0, 'Kontratë për Shitblerje të Veturës', '2025-09-15', '10:30:00', '2025-09-13 20:16:01', NULL, 'në pritje'),
(30, 29, 15, 'Vertetim Dokumenti', '2025-09-22', '10:30:00', '2025-09-20 09:31:14', 'uploads/68ce74625c102_arra logo v1.png', 'në pritje'),
(31, 39, 22, 'Kontratë', '2025-09-22', '10:30:00', '2025-09-20 19:32:46', 'uploads/68cf015eb197b_Lindita Loku-Nikçi.pdf', 'në pritje'),
(32, 39, 22, 'Legalizim', '2025-09-25', '10:30:00', '2025-09-20 19:36:27', 'uploads/68cf023b6ba29_Programet Aplikative në E Qeverisje.pdf', 'në pritje'),
(33, 10, 0, 'Kontratë shitblerjeje të pasurisë së paluajtshme', '2025-10-03', '10:30:00', '2025-10-02 07:51:37', NULL, 'në pritje'),
(34, 24, 0, 'Kontratë furnizimi', '2025-10-09', '09:00:00', '2025-10-08 14:36:18', NULL, 'në pritje');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_alerts`
--

CREATE TABLE `security_alerts` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `alert_time` datetime NOT NULL,
  `alert_type` enum('motion','person','vehicle','animal','custom','offline') NOT NULL,
  `alert_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `image_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_alerts`
--

INSERT INTO `security_alerts` (`id`, `camera_id`, `alert_time`, `alert_type`, `alert_level`, `image_path`, `video_path`, `processed`, `processed_by`, `resolution_notes`, `created_at`) VALUES
(1, 1, '2025-10-04 22:55:31', 'motion', 'medium', NULL, NULL, 0, NULL, NULL, '2025-10-04 20:55:31'),
(2, 1, '2025-10-04 22:56:01', 'motion', 'medium', NULL, NULL, 0, NULL, NULL, '2025-10-04 20:56:01'),
(3, 1, '2025-10-04 22:56:28', 'motion', 'medium', NULL, NULL, 0, NULL, NULL, '2025-10-04 20:56:28'),
(4, 1, '2025-10-04 22:57:01', 'motion', 'medium', NULL, NULL, 0, NULL, NULL, '2025-10-04 20:57:01');

-- --------------------------------------------------------

--
-- Table structure for table `security_cameras`
--

CREATE TABLE `security_cameras` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `zyra_id` int(11) DEFAULT NULL,
  `resolution` varchar(50) DEFAULT NULL,
  `feed_url` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `last_maintenance` datetime DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_cameras`
--

INSERT INTO `security_cameras` (`id`, `name`, `location`, `ip_address`, `model`, `status`, `zyra_id`, `resolution`, `feed_url`, `username`, `password`, `last_maintenance`, `installation_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Kamera hyrëse', 'Dera kryesore', '192.168.1.100', 'Hikvision DS-2CD2385G1-I', 'active', 1, '4K (8MP)', 'rtsp://192.168.1.100:554/Streaming/Channels/101', NULL, NULL, NULL, '2025-10-04', NULL, '2025-10-04 20:40:47', '2025-10-04 20:40:47'),
(2, 'Kamera e dhomës kryesore', 'Dhoma e pritjes', '192.168.1.101', 'Dahua IPC-HDW5831R-ZE', 'active', 2, '1080p', 'rtsp://192.168.1.101:554/Streaming/Channels/101', NULL, NULL, NULL, '2025-10-04', NULL, '2025-10-04 20:40:47', '2025-10-04 20:40:47'),
(3, 'Kamera e parkimit', 'Parkingun i jashtëm', '192.168.1.102', 'Axis P3245-LVE', 'active', 3, '1080p', 'rtsp://192.168.1.102:554/Streaming/Channels/101', NULL, NULL, NULL, '2025-10-04', NULL, '2025-10-04 20:40:47', '2025-10-04 20:40:47'),
(4, 'Kamera e korridorit', 'Korridori kryesor', '192.168.1.103', 'Avigilon 4.0C-H5A-BO1-IR', 'active', 4, '4MP', 'rtsp://192.168.1.103:554/Streaming/Channels/101', NULL, NULL, NULL, '2025-10-04', NULL, '2025-10-04 20:40:47', '2025-10-04 20:40:47');

-- --------------------------------------------------------

--
-- Table structure for table `security_recordings`
--

CREATE TABLE `security_recordings` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `recording_type` enum('scheduled','motion','manual','alarm') DEFAULT 'scheduled',
  `status` enum('available','archived','deleted') DEFAULT 'available',
  `viewed` tinyint(1) DEFAULT 0,
  `flagged` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_settings`
--

CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_settings`
--

INSERT INTO `security_settings` (`id`, `setting_name`, `setting_value`, `description`, `is_encrypted`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'max_daily_transactions_per_email', '5', 'Numri maksimal i transaksioneve për email në ditë', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(2, 'min_payment_amount', '10', 'Shuma minimale e pagesës në Euro', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(3, 'max_payment_amount', '10000', 'Shuma maksimale e pagesës në Euro', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(4, 'payment_verification_timeout', '300', 'Koha e timeout për verifikim në sekonda', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(5, 'max_file_upload_size', '5242880', 'Madhësia maksimale e file në bytes (5MB)', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(6, 'allowed_file_types', 'pdf,jpg,jpeg,png', 'Tipet e lejuara të file-ave', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(7, 'require_payment_proof', 'true', 'A është e detyrueshme dëshmi e pagesës', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(8, 'enable_duplicate_check', 'true', 'A kontrollohen pagesat duplikate', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12'),
(9, 'duplicate_check_hours', '24', 'Orët për kontroll të duplikateve', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `session_logs`
--

CREATE TABLE `session_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` datetime NOT NULL DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `user_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `options` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `category`, `setting_key`, `setting_value`, `type`, `options`, `description`, `updated_at`, `updated_by`) VALUES
(1, 'system', 'app_name', 'Noteria', 'text', NULL, 'Emri i aplikacionit që shfaqet në tituj dhe interfaqe', '2025-09-27 15:25:51', 1),
(2, 'system', 'site_url', 'https://noteria.com', 'text', NULL, 'URL-ja kryesore e faqes', '2025-09-27 15:25:51', 1),
(3, 'system', 'admin_email', 'noreply@noteria.com', 'email', NULL, 'Email-i i administratorit kryesor për njoftimet e sistemit', '2025-10-08 15:05:49', 1),
(4, 'system', 'support_email', 'support@noteria.com', 'email', NULL, 'Email-i i mbështetjes teknike për përdoruesit', '2025-10-02 08:17:46', 1),
(5, 'system', 'default_language', 'sq', 'select', 'sq:Shqip,en:English', 'Gjuha e parazgjedhur e sistemit', '2025-09-27 16:05:48', 1),
(6, 'system', 'timezone', 'Europe/Tirane', 'text', NULL, 'Zona kohore e sistemit (format PHP)', '2025-09-27 15:25:51', 1),
(7, 'interface', 'primary_color', '#2563eb', 'color', NULL, 'Ngjyra primare e temës', '2025-09-27 15:18:26', 1),
(8, 'interface', 'secondary_color', '#64748b', 'color', NULL, 'Ngjyra sekondare e temës', '2025-09-27 15:18:26', 1),
(9, 'interface', 'logo_path', 'images/logo.png', 'file', NULL, 'Shtegu i logos së faqes', '2025-09-27 15:15:49', NULL),
(10, 'interface', 'favicon_path', 'images/favicon.ico', 'file', NULL, 'Shtegu i ikonës favicon', '2025-09-27 15:15:49', NULL),
(11, 'interface', 'show_footer', '1', 'boolean', NULL, 'Shfaq footer-in në fund të faqeve', '2025-09-27 15:18:26', 1),
(12, 'interface', 'items_per_page', '20', 'number', NULL, 'Numri i elementëve për faqe në lista', '2025-09-27 15:18:26', 1),
(13, 'security', 'session_timeout', '30', 'number', NULL, 'Koha e skadimit të sesionit në minuta', '2025-09-27 15:18:01', 1),
(14, 'security', 'password_min_length', '8', 'number', NULL, 'Gjatësia minimale e fjalëkalimeve', '2025-09-27 15:18:01', 1),
(15, 'security', 'password_complexity', 'high', 'select', 'low:E ulët,medium:Mesatare,high:E lartë', 'Niveli i kompleksitetit të kërkuar për fjalëkalimet', '2025-09-27 15:18:01', 1),
(16, 'security', 'max_login_attempts', '5', 'number', NULL, 'Numri maksimal i përpjekjeve të hyrjes para bllokimit', '2025-09-27 15:18:01', 1),
(17, 'security', 'account_lockout_time', '15', 'number', NULL, 'Koha e bllokimit të llogarisë pas shumë përpjekjeve të dështuara (minuta)', '2025-09-27 15:18:01', 1),
(18, 'security', 'force_password_change', '90', 'number', NULL, 'Ditët pas të cilave kërkohet ndryshimi i fjalëkalimit (0 për asnjëherë)', '2025-09-27 15:18:01', 1),
(19, 'notifications', 'enable_email', '1', 'boolean', NULL, 'Aktivizo njoftimet me email', '2025-09-27 15:54:47', 1),
(20, 'notifications', 'email_from_name', 'Noteria System', 'text', NULL, 'Emri i dërguesit për emailet', '2025-09-27 15:54:47', 1),
(21, 'notifications', 'smtp_host', 'smtp.mailtrap.io', 'text', NULL, 'Host-i SMTP për dërgimin e email-eve', '2025-09-27 15:54:47', 1),
(22, 'notifications', 'smtp_port', '2525', 'number', NULL, 'Porti SMTP', '2025-09-27 15:54:47', 1),
(23, 'notifications', 'smtp_username', '', 'text', NULL, 'Username për lidhjen SMTP', '2025-09-27 15:54:47', 1),
(24, 'notifications', 'smtp_password', '', 'password', NULL, 'Fjalëkalimi për lidhjen SMTP', '2025-09-27 15:54:47', 1),
(25, 'notifications', 'smtp_encryption', 'tls', 'select', 'none:None,tls:TLS,ssl:SSL', 'Lloji i enkriptimit për lidhjen SMTP', '2025-09-27 15:54:47', 1),
(26, 'payment', 'currency', 'EUR', 'text', NULL, 'Monedha e parazgjedhur për pagesat', '2025-09-27 15:55:08', 1),
(27, 'payment', 'vat_percentage', '20', 'number', NULL, 'Përqindja e TVSH-së për faturat', '2025-09-27 15:55:08', 1),
(28, 'payment', 'paysera_enabled', '1', 'boolean', NULL, 'Aktivizo pagesat me Paysera', '2025-09-27 15:55:08', 1),
(29, 'payment', 'paysera_project_id', '12345', 'text', NULL, 'ID e projektit në Paysera', '2025-09-27 15:55:08', 1),
(30, 'payment', 'paysera_test_mode', '1', 'boolean', NULL, 'Përdor mjedisin test të Paysera', '2025-09-27 15:55:08', 1),
(31, 'payment', 'bank_transfer_enabled', '1', 'boolean', NULL, 'Aktivizo pagesat me transfertë bankare', '2025-09-27 15:55:08', 1),
(32, 'payment', 'bank_account_details', 'Bank: Example Bank\\nIBAN: AL00 0000 0000 0000 0000 0000 0000\\nSWIFT: EXAMPLEks', 'textarea', NULL, 'Detajet e llogarisë bankare për transferta', '2025-09-27 15:55:08', 1),
(33, 'maintenance', 'maintenance_mode', '0', 'boolean', NULL, 'Aktivizo mënyrën e mirëmbajtjes (faqja nuk do të jetë e disponueshme për përdoruesit)', '2025-09-27 15:15:49', NULL),
(34, 'maintenance', 'maintenance_message', 'Sistemi është aktualisht në mirëmbajtje. Ju lutemi provoni sërish më vonë.', 'textarea', NULL, 'Mesazhi që shfaqet gjatë mënyrës së mirëmbajtjes', '2025-09-27 22:05:22', 1),
(35, 'maintenance', 'debug_mode', '0', 'boolean', NULL, 'Aktivizo mënyrën debug për zhvilluesit', '2025-09-27 15:15:49', NULL),
(36, 'maintenance', 'log_level', 'error', 'select', 'debug:Debug,info:Info,warning:Warning,error:Error', 'Niveli i logimit të gabimeve', '2025-09-27 22:05:22', 1),
(37, 'maintenance', 'auto_backup', '1', 'boolean', NULL, 'Aktivizo backup-et automatike të databazës', '2025-09-27 22:05:22', 1),
(38, 'maintenance', 'backup_frequency', 'daily', 'select', 'daily:Çdo ditë,weekly:Çdo javë,monthly:Çdo muaj', 'Shpeshtësia e backup-eve automatike', '2025-09-27 22:05:22', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sms_provider_config`
--

CREATE TABLE `sms_provider_config` (
  `id` int(11) NOT NULL,
  `provider_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `priority` int(11) DEFAULT 1,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `sender_name` varchar(20) DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL,
  `daily_limit` int(11) DEFAULT 1000,
  `monthly_limit` int(11) DEFAULT 30000,
  `success_rate` decimal(5,2) DEFAULT 95.00,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_provider_config`
--

INSERT INTO `sms_provider_config` (`id`, `provider_name`, `is_active`, `priority`, `api_key`, `api_secret`, `sender_name`, `base_url`, `daily_limit`, `monthly_limit`, `success_rate`, `last_used_at`, `created_at`, `updated_at`) VALUES
(1, 'IPKO', 1, 1, NULL, NULL, 'NOTERIA', NULL, 1000, 30000, 95.00, NULL, '2025-09-22 22:24:08', '2025-09-22 22:24:08'),
(2, 'Infobip', 1, 3, NULL, NULL, 'NOTERIA', NULL, 1000, 30000, 95.00, NULL, '2025-09-22 22:24:08', '2025-09-22 22:39:26'),
(3, 'Twilio', 1, 4, NULL, NULL, 'NOTERIA', NULL, 1000, 30000, 95.00, NULL, '2025-09-22 22:24:08', '2025-09-22 22:39:26'),
(4, 'Vala', 1, 2, NULL, NULL, 'NOTERIA', NULL, 2000, 50000, 96.50, NULL, '2025-09-22 22:39:26', '2025-09-22 22:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `subscription`
--

CREATE TABLE `subscription` (
  `id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','cancelled','suspended') NOT NULL DEFAULT 'active',
  `payment_status` enum('paid','pending','failed') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription`
--

INSERT INTO `subscription` (`id`, `zyra_id`, `start_date`, `expiry_date`, `status`, `payment_status`, `payment_date`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-10-04', '2025-11-03', 'active', 'paid', '2025-10-04 00:04:30', '2025-10-03 22:04:30', NULL),
(2, 2, '2025-10-04', '2025-10-09', 'active', 'paid', '2025-10-04 00:04:30', '2025-10-03 22:04:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `noter_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reference` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_type` enum('automatic','manual') DEFAULT 'manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `noter_id`, `amount`, `payment_date`, `status`, `reference`, `transaction_id`, `payment_method`, `description`, `notes`, `created_at`, `updated_at`, `payment_type`) VALUES
(1, 1, 150.00, '2025-09-27 12:36:59', 'test', 'SUB2025092713472', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 10:36:59', '2025-09-27 13:34:11', 'manual'),
(2, 2, 150.00, '2025-09-27 12:36:59', 'test', 'SUB2025092720768', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 10:36:59', '2025-09-27 13:34:11', 'manual'),
(3, 3, 150.00, '2025-09-27 12:36:59', 'test', 'SUB2025092730345', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 10:36:59', '2025-09-27 13:34:11', 'manual'),
(4, 1, 150.00, '2025-09-27 14:50:17', 'test', 'SUB2025092717639', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:17', '2025-09-27 13:34:11', 'manual'),
(5, 2, 150.00, '2025-09-27 14:50:17', 'test', 'SUB2025092728694', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:17', '2025-09-27 13:34:11', 'manual'),
(6, 3, 150.00, '2025-09-27 14:50:17', 'test', 'SUB2025092738421', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:17', '2025-09-27 13:34:11', 'manual'),
(7, 1, 150.00, '2025-09-27 14:50:26', 'test', 'SUB2025092712718', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:26', '2025-09-27 13:34:11', 'manual'),
(8, 2, 150.00, '2025-09-27 14:50:26', 'test', 'SUB2025092729820', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:26', '2025-09-27 13:34:11', 'manual'),
(9, 3, 150.00, '2025-09-27 14:50:26', 'test', 'SUB2025092730516', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 12:50:26', '2025-09-27 13:34:11', 'manual'),
(10, 1, 150.00, '2025-09-27 15:02:54', 'test', 'SUB2025092711428', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 13:02:54', '2025-09-27 13:34:11', 'manual'),
(11, 2, 150.00, '2025-09-27 15:02:54', 'test', 'SUB2025092722849', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 13:02:54', '2025-09-27 13:34:11', 'manual'),
(12, 3, 150.00, '2025-09-27 15:02:54', 'test', 'SUB2025092734071', NULL, 'automatic', 'Abonim mujor për September 2025', NULL, '2025-09-27 13:02:54', '2025-09-27 13:34:11', 'manual');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `subscription_price` decimal(10,2) NOT NULL DEFAULT 25.00,
  `payment_day` int(11) NOT NULL DEFAULT 1,
  `subscription_frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `email_notification` tinyint(1) NOT NULL DEFAULT 1,
  `grace_period` int(11) NOT NULL DEFAULT 3,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `subscription_price`, `payment_day`, `subscription_frequency`, `email_notification`, `grace_period`, `created_at`, `updated_at`) VALUES
(1, 25.00, 1, 'monthly', 1, 3, '2025-09-27 12:32:43', '2025-09-27 12:32:43');

-- --------------------------------------------------------

--
-- Table structure for table `transcripts`
--

CREATE TABLE `transcripts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `transcript` longtext DEFAULT NULL,
  `lang` varchar(16) DEFAULT NULL,
  `speakers` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `noter_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','rejected') NOT NULL DEFAULT 'pending',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `emri` varchar(50) NOT NULL,
  `mbiemri` varchar(50) NOT NULL,
  `name` varchar(100) GENERATED ALWAYS AS (concat(`emri`,' ',`mbiemri`)) STORED,
  `email` varchar(100) NOT NULL,
  `telefoni` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `roli` varchar(20) NOT NULL DEFAULT 'user',
  `zyra_id` int(11) DEFAULT NULL,
  `personal_number` varchar(10) NOT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `busy` tinyint(1) DEFAULT 0,
  `numri_personal` varchar(30) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `emri`, `mbiemri`, `email`, `telefoni`, `password`, `created_at`, `roli`, `zyra_id`, `personal_number`, `id_document`, `role`, `busy`, `numri_personal`, `photo_path`, `aktiv`) VALUES
(1, 'Valon', 'Sadiku', 'valonsadiku2030@gmail.com', NULL, '$2y$10$VOG/EK1rujRHI2krWhpxvefwp7URAOiBSwUiYMG74ymPIK4OnKq3m', '2025-08-04 09:16:17', 'user', 1, '', NULL, 'user', 1, NULL, NULL, 1),
(3, 'Valon', 'Sadiku', 'shefki.shefkiu@gmail.com', NULL, '$2y$10$d1K1q.UXYQOPZWjL//sbeecobeGLaVyxjoooAq0tyN8cGWQ6RExNO', '2025-08-04 09:38:10', 'user', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(4, 'Valon', 'Sadiku', 'emir.piraj@gmail.com', NULL, '$2y$10$tsvxRg7HNBZ4wFXdmrRXPeUTDsmPNOpV7r.gTuD7tZxZqDvgovXmu', '2025-08-04 09:41:02', 'user', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(5, 'Valon', 'Sadiku', 'valonsadiku2018@gmail.com', NULL, '$2y$10$yHmLETucUMAPcmpuelOVz.kEzwC3bIlLZBqZFTPqcuJEWeV3Z3CoW', '2025-08-04 09:49:13', 'user', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(6, 'Agon', 'Sadiku', 'Agon.sadiku@gmail.com', NULL, '$2y$10$oLscdjfO1/VrLa3JaKegvOc6E2KGrXI.wDM02FnMWYXZ.2fJj5sa.', '2025-08-04 09:57:35', 'user', 1, '', NULL, 'user', 1, NULL, NULL, 1),
(7, 'Drita', 'Sadiku', 'drita.sadiku@gmail.com', NULL, '$2y$10$FjeUFU7nOP.v7QBs1LLehekb.gm1eicjWma/37Bh0JTu6gFPd.RC.', '2025-08-04 15:45:28', 'user', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(8, 'Shaip', 'Sadiku', 'shaip.sadiku@gmail.com', NULL, '$2y$10$OMz.qZ0pVCgus8FdXXpH5eTkgunId3fYFceoaWECOfVpc/asaDH76', '2025-08-04 21:57:52', 'user', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(9, 'Gresa', 'Biçkaj', 'gresa.biqkaj@gmail.com', NULL, '$2y$10$3FiOoCe1fTNxWcRH6/8eG.qZIzAaEekgObZ2KcTjsxOtipAshO5gO', '2025-08-05 09:35:05', 'user', NULL, '1244726298', NULL, 'user', 1, NULL, NULL, 1),
(10, 'Naser', 'Pajaziti', 'naser.pajaziti@gmail.com', NULL, '$2y$10$W7haid56fxVQjD73qGunS.clN5xZ/GxuRJDYMEAMFr1P7mB34gGN2', '2025-08-05 09:58:34', 'admin', 1, '1144789865', NULL, 'user', 0, NULL, NULL, 0),
(11, 'Valon ', 'Biçkaj', 'valon.biqkaj@gmail.com', NULL, '$2y$10$L48WSF653jZrr3lPJC257u0.95LcuxAyPbCBq4HLszsGFC2LDYLcS', '2025-08-05 16:52:19', 'user', NULL, '1344726298', NULL, 'user', 1, NULL, NULL, 1),
(12, 'Dredhëza', 'Sadiku', 'dredhza.sadiku@gmail.com', NULL, '$2y$10$Jfei2EzyeV2Qzhs.QOH.8edsHx1tT6ihTUhKZfe7h4tfCAnHWKYWS', '2025-08-05 17:39:08', 'user', NULL, '111444999', NULL, 'user', 1, NULL, NULL, 1),
(13, 'Dredhëza', 'Sadikaj', 'dredhza.sadikaj@gmail.com', NULL, '$2y$10$uGnInKIJhMduf85JMiiz1.wkrHewibYoJQtusPFlJpGhU4byu6igS', '2025-08-05 17:42:37', 'admin', NULL, '1114449999', NULL, 'user', 1, NULL, NULL, 1),
(14, 'Dredhëza', 'Biçkaj', 'dredhza.biqkaj@gmail.com', NULL, '$2y$10$9LFD7yfERU78MzoFKRKUF.g/YR3sho2w.ZWOjfg8tUnf1ut5LTj8S', '2025-08-05 17:45:09', 'user', NULL, '1114449998', NULL, 'user', 1, NULL, NULL, 1),
(15, 'Valon', 'Pajaziti', 'valonpajaziti2030@gmail.com', NULL, '$2y$10$6AsKa2ZSnheKSXztOs7B..99Thb7YPUL6LM.Zd7ZZRSbRuzvg8FVC', '2025-08-10 15:01:53', 'admin', NULL, '111444888', NULL, 'user', 1, NULL, NULL, 1),
(16, 'Avni', 'Idrizi', 'avni.idrizi@gmail.com', NULL, '$2y$10$62/xtKLT0JHBC/.VZzuIBOCEdzp8OdOKgXiQn3cLfaV72mbxbuhWe', '2025-08-10 15:30:12', 'user', NULL, '111333222', NULL, 'user', 1, NULL, NULL, 1),
(17, 'Avni', 'Idrizi', 'avni.idrizi1@gmail.com', NULL, '$2y$10$g5xXPlzMdvZDzP8MVkRC3OkUO/B61f3Ps/oN/jS5e1d84w8EIQ2AC', '2025-08-10 15:32:13', 'user', NULL, '1113332222', NULL, 'user', 1, NULL, NULL, 1),
(18, 'Valon', 'Dreshaj', 'valondreshaj2049@gmail.com', NULL, '$2y$10$4zzXHrdIygozsJPUNcc8M.koStZK9sJ47ODfI6EM65FF3zTVgty8i', '2025-08-10 21:54:04', 'admin', NULL, '1444555777', NULL, 'user', 1, NULL, NULL, 1),
(19, 'Valon', 'Berisha', 'valonberisha2050@gmail.com', NULL, '$2y$10$srhghgYH5w.xT2j6RTBUgOa1odEg7XCyriXYYD.EyrgLvqNh9HigS', '2025-08-10 21:58:01', 'user', NULL, '1111111111', NULL, 'user', 1, NULL, NULL, 1),
(20, 'Dredhëza', 'Pajaziti', 'dredhza.pajaziti@gmail.com', NULL, '$2y$10$aRuRisBw./bQn9m38OpwV..6VVlJ808Xek53XMMm4s4ut87qZHFdC', '2025-08-14 09:55:46', 'user', NULL, '1111111111', NULL, 'user', 1, NULL, NULL, 1),
(21, 'Shkurte', 'Sadiku', 'shkurte.sadiku@gmail.com', NULL, '$2y$10$iMNt0ugGURTzn.ZomjfXTe8e9U9W29ceeoBkOTTINy70L8KOyGDTy', '2025-08-14 15:25:23', 'admin', NULL, '1212121212', NULL, 'user', 1, NULL, NULL, 1),
(22, 'Advan', 'Selimi', 'advan.selimi@gmail.com', NULL, '$2y$10$CGi6KFdQ.oSQ8tNlhLOlruoixquLrBNQ1qAjGIvKd2HZGZo6ROitG', '2025-08-15 20:35:13', 'user', 1, '4444444444', NULL, 'user', 1, NULL, NULL, 1),
(23, 'Avni', 'Ramadani', 'avni.ramadani@gmail.com', NULL, '$2y$10$Prxs6jsqzqudHGq4iHWlfegnrMsxdPzEy/rj6uYGFlaerdP7y21R.', '2025-08-15 20:47:05', 'user', NULL, '1244791472', NULL, 'user', 1, NULL, NULL, 1),
(24, 'Anjeza', 'Sadiku', 'anjeza.sadiku@gmail.com', NULL, '$2y$10$BJxzQzGsfsIFUJt62QkTmeY3pKAIGvidMzFPREDrdNCJVt2eT5ri6', '2025-08-18 14:18:36', 'user', 1, '1234567891', NULL, 'user', 1, NULL, NULL, 1),
(25, 'Naile', 'Hasani', 'naile.hasani@gmail.com', NULL, '$2y$10$yQpgqIHc7bghRiVzHmNCm.yczbgTFmteT5VRDyDbMo.h9p1e7s.Ri', '2025-08-19 14:48:10', 'user', NULL, '1234567892', NULL, 'user', 1, NULL, NULL, 1),
(26, 'Shalqin', 'Pajaziti', 'shalqin.pajaziti@gmail.com', NULL, '$2y$10$f//uZsrVPPCkTB7haK3Ku.zQEFzPkrLWClZj/59by7bX.RAOQvQWq', '2025-09-03 21:54:00', 'admin', NULL, '1234567891', NULL, 'user', 1, NULL, NULL, 1),
(29, 'Agim', 'Sylejmani', 'agim.saylejmani@gmail.com', NULL, '$2y$10$sYAQ65lpLYLmoWsbWLOvEOzEmiDGbHv0cmCaWJkEMl9Uoys5xgop.', '2025-09-19 20:10:50', 'zyra', NULL, '1234567893', NULL, 'user', 1, NULL, NULL, 1),
(30, 'Avni Bobaj', '', 'avni.bobaj@gmail.com', NULL, '$2y$10$IiaHtPA.fy9t.D4z29KbDOwxFp8UB9.sQ3uITIqXFHeitorxWGjwS', '2025-09-20 13:27:55', 'zyra', NULL, '', NULL, 'user', 1, NULL, NULL, 1),
(32, 'Nser Pajaziti', '', 'naser.pajaziti1@gmail.com', NULL, '$2y$10$dD342/6hOlY1f7s48OoF7usfaRRq/ExkMhbdKOnxuWd.MSY8d9KoW', '2025-09-20 14:12:25', 'zyra', 19, '', NULL, 'user', 1, '1234567894', NULL, 1),
(33, 'Naser Pajaziti', '', 'naser.pajaziti2@gmail.com', NULL, '$2y$10$jdP4pRuQez/DLchR1R.WL.1jTs8MxaUG8BgapNqq4fdpy.tqwb6NG', '2025-09-20 14:34:36', 'zyra', 20, '', NULL, 'user', 1, '1234567894', NULL, 1),
(34, 'Valon Sadiku', '', 'valonsadiku2050@gmail.com', NULL, '$2y$10$a6zdot/vhtDGlSZAQuoaMeIsqGNfxPGeSjTxWMijCzKI/cXTE.zye', '2025-09-20 15:39:29', 'zyra', 21, '', NULL, 'user', 1, '1244726298', NULL, 1),
(35, 'Gresa Biçkaj', '', 'gresabickaj2018@gmail.com', NULL, '$2y$10$JG2P3dFKEHwWgbL0Ztdm6e5UV8lYRp/zZX9d44LVoako4s5zpyFju', '2025-09-20 15:50:23', 'zyra', 22, '', NULL, 'user', 1, '1234567890', NULL, 1),
(36, 'Valon', 'Sadiku', 'valon.sadiku2025@gmail.com', NULL, '$2y$10$FyyCxa0aNQMKXQ66EcVs4u9V8fdHDRLlVoPCBZ30b1.jNNKxrWgkS', '2025-09-20 18:51:24', 'user', 22, '1234567899', 'uploads/id_documents/68cef7acc1971_kyqja.png', 'user', 1, NULL, NULL, 1),
(37, 'Naim', 'Biçkaj', 'naim.bickaj@gmail.com', NULL, '$2y$10$EVBmEgFzJ1bqZQF.jKBEaO5zZgWtT9VAZYqtLIUIrZrBTqo83JVJG', '2025-09-20 19:03:01', 'user', 22, '1234567890', 'uploads/id_documents/68cefa6506961_regjistrimi.jpg', 'user', 1, NULL, NULL, 1),
(38, 'Ibadete', 'Saiti', 'ibadete.saiti@gmail.com', NULL, '$2y$10$qisNlTbjqe.pdD5cXdu7PegLNeimuJMsD6Zw.e729kbVFZiOXtOla', '2025-09-20 19:07:22', 'user', 22, '1233333333', 'uploads/id_documents/68cefb6a90de1_arra logo v2.png', 'user', 1, NULL, NULL, 1),
(39, 'Valdrin', 'Azemi', 'valon.azemi@gmail.com', '+38345779255', '$2y$10$k1Zh3OemAMeL1ZCkIkge9uWjgga1EiGm.rfg1Sk2JWsH8IFHQmX1e', '2025-09-20 19:22:31', 'perdorues', 1, '1234567888', NULL, 'user', 1, NULL, 'D:\\xampp\\htdocs\\noteria/uploads/68cefe1ff1f03_regjistrimi.jpg', 1),
(40, 'Avni', 'Terminatori', 'avni.terminatori@gmail.com', '+38345111111', '$2y$10$QNXfI5iv.8lN7XL4nKGSB.aAIWz9hNw2wWdfZBiVKnIy.tpdkyanm', '2025-09-27 21:50:55', 'perdorues', 1, '1234567899', NULL, 'user', 1, NULL, 'D:\\xampp\\htdocs\\noteria/uploads/68d85c262c372_regjistrimi.jpg', 1),
(41, 'Avni', 'Sadiku', 'avni.sadiku@gmail.com', '+38345123456', '$2y$10$vquQPPDaAZlromBBx10N5e/tx7Dw7jW1S8XreUEYJ2ghBM5SYsAse', '2025-10-04 20:34:07', 'perdorues', NULL, '1234567894', NULL, 'user', 0, NULL, 'D:\\xampp\\htdocs\\noteria/uploads/68e184af06c40_arra logo v2.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_calls`
--

CREATE TABLE `video_calls` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notary_id` int(11) NOT NULL,
  `call_datetime` datetime NOT NULL,
  `room_id` varchar(64) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','in-progress','ended','cancelled') DEFAULT 'scheduled',
  `notification_status` enum('pending','accepted','rejected','notified') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_calls`
--

INSERT INTO `video_calls` (`id`, `user_id`, `notary_id`, `call_datetime`, `room_id`, `subject`, `status`, `notification_status`, `created_at`, `end_time`) VALUES
(1, 40, 35, '2025-09-29 10:30:00', 'room_68d8610e04f00', 'Kontratë Prone', 'scheduled', 'pending', '2025-09-27 22:11:26', NULL),
(2, 40, 35, '2025-10-01 10:30:00', 'room_68d86a1247c0a', 'Kontratë Prone', 'scheduled', 'pending', '2025-09-27 22:49:54', NULL),
(3, 40, 34, '2025-09-29 10:30:00', 'room_68d86db9ab309', 'Kontratë Prone', 'scheduled', 'pending', '2025-09-27 23:05:29', NULL),
(4, 40, 35, '2025-09-28 01:50:24', 'room_qyzciltshj9', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:50:24', NULL),
(5, 40, 35, '2025-09-28 01:53:03', 'room_qyzciltshj9', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:53:03', NULL),
(6, 40, 35, '2025-09-28 01:53:10', 'room_qyzciltshj9', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:53:10', NULL),
(7, 40, 35, '2025-09-28 01:53:21', 'room_qyzciltshj9', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:53:21', NULL),
(8, 40, 35, '2025-09-28 01:56:50', 'room_qyzciltshj9', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:56:50', NULL),
(9, 40, 33, '2025-09-28 01:59:17', 'room_5yjravqbaxs', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-27 23:59:17', NULL),
(10, 40, 35, '2025-09-29 10:05:19', 'room_5qz7m3z33fp', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-29 08:05:19', NULL),
(11, 40, 35, '2025-09-29 10:05:26', 'room_5qz7m3z33fp', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-29 08:05:26', NULL),
(12, 40, 35, '2025-09-29 10:05:29', 'room_5qz7m3z33fp', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-29 08:05:29', NULL),
(13, 40, 35, '2025-09-29 10:05:33', 'room_5qz7m3z33fp', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-29 08:05:33', NULL),
(14, 40, 35, '2025-09-29 10:05:40', 'room_5qz7m3z33fp', 'Video thirrje e menjëhershme', '', 'pending', '2025-09-29 08:05:40', NULL),
(15, 40, 35, '2025-10-01 10:30:00', 'room_68da3e0d7e594', 'Kontratë Prone', 'scheduled', 'pending', '2025-09-29 08:06:37', NULL),
(16, 1, 35, '2025-10-04 23:25:38', 'room_w2p1vecun2l', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-04 21:25:38', NULL),
(17, 1, 35, '2025-10-04 23:25:44', 'room_w2p1vecun2l', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-04 21:25:44', NULL),
(18, 28, 35, '2025-10-07 15:40:04', 'room_y1y9x293kb9', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-07 13:40:04', NULL),
(19, 28, 35, '2025-10-07 15:40:08', 'room_y1y9x293kb9', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-07 13:40:08', NULL),
(20, 24, 35, '2025-10-08 16:28:48', 'room_bhvb49frecq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-08 14:28:48', NULL),
(21, 24, 35, '2025-10-08 16:28:55', 'room_bhvb49frecq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-08 14:28:55', NULL),
(22, 24, 35, '2025-10-08 16:31:25', 'room_bhvb49frecq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-08 14:31:25', NULL),
(23, 22, 35, '2025-10-16 22:19:44', 'room_avr6kctqkoq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 20:19:44', NULL),
(24, 22, 35, '2025-10-16 22:19:48', 'room_avr6kctqkoq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 20:19:48', NULL),
(25, 22, 35, '2025-10-16 22:20:06', 'room_avr6kctqkoq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 20:20:06', NULL),
(26, 22, 35, '2025-10-16 22:20:11', 'room_avr6kctqkoq', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 20:20:11', NULL),
(27, 22, 34, '2025-10-18 10:30:00', 'room_68f153c23d4d9', 'Kontratë Prone', 'scheduled', 'pending', '2025-10-16 20:21:22', NULL),
(28, 22, 35, '2025-10-18 10:30:00', 'room_68f16269c0a59', 'Kontratë Prone', 'scheduled', 'pending', '2025-10-16 21:23:53', NULL),
(29, 22, 35, '2025-10-16 23:24:16', 'room_18bzi12yswn', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 21:24:16', NULL),
(30, 22, 35, '2025-10-16 23:24:20', 'room_18bzi12yswn', 'Video thirrje e menjëhershme', '', 'pending', '2025-10-16 21:24:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `video_call_logs`
--

CREATE TABLE `video_call_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `noter_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `status` varchar(16) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zyra`
--

CREATE TABLE `zyra` (
  `id` int(11) NOT NULL,
  `emri` varchar(255) NOT NULL,
  `qyteti` varchar(100) DEFAULT NULL,
  `adresa` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zyrat`
--

CREATE TABLE `zyrat` (
  `id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `qyteti` varchar(50) DEFAULT NULL,
  `shteti` varchar(50) NOT NULL DEFAULT 'Kosova',
  `email` varchar(100) NOT NULL,
  `telefoni` varchar(20) NOT NULL,
  `operator` varchar(50) DEFAULT NULL,
  `banka` varchar(100) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `llogaria` varchar(30) DEFAULT NULL,
  `numri_fiskal` varchar(20) DEFAULT NULL,
  `numri_biznesit` varchar(20) DEFAULT NULL,
  `numri_licences` varchar(20) DEFAULT NULL,
  `data_licences` date DEFAULT NULL,
  `pagesa` decimal(10,2) DEFAULT NULL,
  `abonim_aktiv` tinyint(1) DEFAULT 0,
  `data_aktivizimit` datetime DEFAULT NULL,
  `lloji_biznesit` varchar(50) NOT NULL,
  `adresa` varchar(255) NOT NULL,
  `nr_fiskal` varchar(20) NOT NULL COMMENT 'Numri fiskal nga ATK',
  `nr_biznesi` varchar(20) NOT NULL COMMENT 'Numri i biznesit nga ARBK',
  `num_punetore` int(11) NOT NULL DEFAULT 1 COMMENT 'Numri i punëtorëve në zyrë',
  `data_regjistrimit` date DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_method` enum('bank_transfer','paypal','card') DEFAULT 'bank_transfer',
  `payment_verified` tinyint(1) DEFAULT 0,
  `payment_proof_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `emri_noterit` varchar(255) DEFAULT NULL,
  `vitet_pervoje` int(11) DEFAULT 0,
  `numri_punetoreve` int(11) DEFAULT 1,
  `gjuhet` varchar(255) DEFAULT NULL,
  `staff_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`staff_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zyrat`
--

INSERT INTO `zyrat` (`id`, `emri`, `qyteti`, `shteti`, `email`, `telefoni`, `operator`, `banka`, `iban`, `llogaria`, `numri_fiskal`, `numri_biznesit`, `numri_licences`, `data_licences`, `pagesa`, `abonim_aktiv`, `data_aktivizimit`, `lloji_biznesit`, `adresa`, `nr_fiskal`, `nr_biznesi`, `num_punetore`, `data_regjistrimit`, `transaction_id`, `payment_method`, `payment_verified`, `payment_proof_path`, `created_at`, `updated_at`, `emri_noterit`, `vitet_pervoje`, `numri_punetoreve`, `gjuhet`, `staff_data`) VALUES
(1, 'Valon Sadiku', NULL, 'Kosova', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(2, 'Valon Sadiku', NULL, 'Kosova', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(3, 'Naser Pajaziti', NULL, 'Kosova', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(4, 'Drita Sadiku', 'Viti', 'Kosova', 'drita.sadiku@gmail.com', '+38344991789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(5, 'Drita Sadiku', 'Viti', 'Kosova', 'drita.sadiku@gmail.com', '+38344991789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(6, 'Naser Pajaziti', 'Viti', 'Kosova', 'naser.pajaziti@gmail.com', '+38344991789', NULL, 'NLB BANK', 'XK1702018603253707', '1702018603253707', NULL, NULL, NULL, NULL, 50.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(7, 'Naser Pajaziti', 'Viti', 'Kosova', 'naser.pajaziti@gmail.com', '+38344991789', NULL, 'NLB BANK', 'XK1702018603253707', '1702018603253707', NULL, NULL, NULL, NULL, 50.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(8, 'Dredhëza', 'Gjilan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344991783', NULL, 'NLB BANK', 'XK170201863253707', '1702018603253707', NULL, NULL, NULL, NULL, 50.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(9, 'Shkurte Sadiku', 'Prishtinë', 'Kosova', 'shkurte.sadiku@gmail.com', '+38344123456', NULL, 'NLB BANK', '2003226526325255', '54323245232556235', NULL, NULL, NULL, NULL, 50.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(10, 'Avni Ramadani', 'Viti', 'Kosova', 'avni.ramadani@gmail.com', '+38345123456', NULL, 'NLB BANK', '172597642642626491', '172597642642626491', NULL, NULL, NULL, NULL, 50.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(11, 'Noteria Hamdi Bicaj', 'Suharekë', 'Kosova', 'hamdi.bicaj2018@gmail.com', '+38345123456', NULL, 'NLB BANK', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(12, 'Shalqin Pajaziti', 'Kllokot', 'Kosova', 'shalqin.pajaziti@gmail.com', '+38344991789', NULL, 'NLB BANK', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(13, 'Valon', 'Viti', 'Kosova', 'valonsadiku2018@gmail.com', '+38344991783', NULL, 'NLB BANK', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(14, 'Naser Pajaziti', 'Viti', 'Kosova', 'valonsadiku2018@gmail.com', '+38344991789', NULL, 'Raiffeisen Bank Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 1, '2025-09-19 21:42:18', 'B.I', 'Rruga Xhemajl Ademi nr 12 61000', '1234567890', '1234567890', 4, '2012-09-12', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(15, 'Agim Sylejmani', 'Viti', 'Kosova', 'agim.saylejmani@gmail.com', '+38345666666', NULL, 'TEB SH.A.', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga e Kabashit nr 32', '1234567890', '1234567890', 4, '2013-11-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(16, 'Naser Pajaziti', 'Viti', 'Kosova', 'naser.pajaziti@gmail.com', '+38344123456', NULL, 'NLB Prishtina', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga Xhemajl Ademi nr 12 61000', '1234567890', '1234567890', 4, '2011-03-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(17, 'Avni Bobaj', 'Prishtinë', 'Kosova', 'avni.bobaj@gmail.com', '+38345666666', NULL, 'NLB Prishtina', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga Zahir Pajaziti Lagjia Ulpianë 10000 Prishtinë', '1234567890', '1234567890', 4, '2010-06-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(18, 'Avni Bobaj', 'Prishtinë', 'Kosova', 'avni.bobaj@gmail.com', '+38345666666', NULL, 'NLB Prishtina', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga Zahir Pajaziti Lagjia Ulpianë 10000 Prishtinë', '1234567890', '1234567890', 4, '2010-06-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(19, 'Nser Pajaziti', 'Viti', 'Kosova', 'naser.pajaziti1@gmail.com', '+38344111111', NULL, 'TEB SH.A.', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga Xhemajl Ademi nr 12 61000', '1234567890', '1234567890', 4, '2011-06-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(20, 'Naser Pajaziti', 'Viti', 'Kosova', 'naser.pajaziti2@gmail.com', '+38345543211', NULL, 'ProCredit Bank', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 1, '2025-09-20 16:34:56', 'B.I', 'Rruga Xhemajl Ademi nr 12 61000', '1234567890', '1234567890', 4, '2011-09-06', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(21, 'Valon Sadiku', 'Viti', 'Kosova', 'valonsadiku2050@gmail.com', '+38344991783', NULL, 'Banka për Biznes', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 1, '2025-09-20 17:44:50', 'B.I', 'Rruga Xhemajl Ademi nr 12 61000', '1234567890', '1234567890', 5, '2011-09-06', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(22, 'Gresa Biçkaj', 'Gjilan', 'Kosova', 'gresabickaj2018@gmail.com', '+38345455233', NULL, 'BKT', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, 'B.I', 'Rruga Abdullah Tahiri numër 26 60000', '1234567890', '1234567890', 4, '2022-06-09', NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(23, 'Valon Sadiku', 'Kllokot', 'Kosova', 'valonsadiku2026@gmail.com', '+38344280296', NULL, 'NLB Banka', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(24, 'Valon Sadiku', 'Kllokot', 'Kosova', 'valonsadiku2026@gmail.com', '+38344280296', NULL, 'NLB Banka', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(25, 'Valon Sadiku', 'Kllokot', 'Kosova', 'valonsadiku2026@gmail.com', '+38344280296', NULL, 'NLB Banka', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(26, 'Valon Sadiku', 'Kllokot', 'Kosova', 'valonsadiku2026@gmail.com', '+38344280296', NULL, 'NLB Banka', 'XK055001000137385385', '1702018603253707', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-22 21:06:12', '2025-09-22 21:06:12', NULL, 0, 1, NULL, NULL),
(27, 'Avni Bobaj', 'Mitrovicë', 'Kosova', 'avni.bobaj1@gmail.com', '+38345434711', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'card', 1, NULL, '2025-09-22 21:16:32', '2025-09-22 21:16:32', NULL, 0, 1, NULL, NULL),
(28, 'Avni Bobaj', 'Mitrovicë', 'Kosova', 'avni.bobaj1@gmail.com', '+38345434711', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'card', 1, NULL, '2025-09-22 21:26:12', '2025-09-22 21:26:12', NULL, 0, 1, NULL, NULL),
(29, 'Avni Bobaj', 'Mitrovicë', 'Kosova', 'avni.bobaj1@gmail.com', '+38345434711', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'card', 1, NULL, '2025-09-22 21:35:08', '2025-09-22 21:35:08', NULL, 0, 1, NULL, NULL),
(31, 'Avni Bobaj', 'Mitrovicë', 'Kosova', 'avni.bobaj1@gmail.com', '+38345434711', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'card', 1, NULL, '2025-09-22 21:49:35', '2025-09-22 21:49:35', NULL, 0, 1, NULL, NULL),
(32, 'Avni Bobaj', 'Mitrovicë', 'Kosova', 'avni.bobaj1@gmail.com', '+38345434711', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'card', 1, NULL, '2025-09-22 21:49:58', '2025-09-22 21:49:58', NULL, 0, 1, NULL, NULL),
(33, 'Avni Bobaj', 'Prishtinë', 'Kosova', 'avni.bobaj@gmail.com', '+38344269896', NULL, 'TEB Bank', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 130.00, 0, NULL, '', '', '', '', 1, NULL, 'TXN_20250922_225911_972321bc', 'bank_transfer', 1, NULL, '2025-09-22 22:00:13', '2025-09-22 22:00:13', NULL, 0, 1, NULL, NULL),
(34, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:16:59', '2025-09-26 22:16:59', NULL, 0, 1, NULL, NULL),
(35, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:19:32', '2025-09-26 22:19:32', NULL, 0, 1, NULL, NULL),
(36, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:22:42', '2025-09-26 22:22:42', NULL, 0, 1, NULL, NULL),
(37, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:25:49', '2025-09-26 22:25:49', NULL, 0, 1, NULL, NULL),
(38, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:27:37', '2025-09-26 22:27:37', NULL, 0, 1, NULL, NULL),
(39, 'Dredhëza', 'Lipjan', 'Kosova', 'valonsadiku2018@gmail.com', '+38344994242', NULL, 'One For Kosovo', 'XK055001000137385385', '54323245232556235', NULL, NULL, NULL, NULL, 150.00, 0, NULL, '', '', '', '', 1, NULL, NULL, 'bank_transfer', 0, NULL, '2025-09-26 22:29:16', '2025-09-26 22:29:16', NULL, 0, 1, NULL, NULL),
(40, 'Noeria Gëzim Vushtrria', 'Vushtrri', 'Kosova', 'gezim.vushtrria@gmail.com', '+38345841111', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', 'ABC1234567', 'NT123456789', '2011-09-21', 150.00, 0, NULL, '', 'Rruga Xhemajl Ademi nr 12 71000', '', '', 1, '2025-09-27', NULL, 'bank_transfer', 0, NULL, '2025-09-27 07:46:28', '2025-09-27 07:46:28', 'Gezim oshlani', 10, 4, 'Shqip.Serbisht,Anglisht', '[{\"emri\":\"Valon Dredhaj\",\"pozita\":\"Asistent\"}]'),
(41, 'Noeria Gëzim Vushtrria', 'Vushtrri', 'Kosova', 'gezim.vushtrria@gmail.com', '+38345841111', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', 'ABC1234567', 'NT123456789', '2011-09-21', 150.00, 0, NULL, '', 'Rruga Xhemajl Ademi nr 12 71000', '', '', 1, '2025-09-27', NULL, 'bank_transfer', 0, NULL, '2025-09-27 09:19:04', '2025-09-27 09:19:04', 'Gezim oshlani', 10, 4, 'Shqip.Serbisht,Anglisht', '[{\"emri\":\"Valon Dredhaj\",\"pozita\":\"Asistent\"}]'),
(42, 'Noeria Gëzim Vushtrria', 'Vushtrri', 'Kosova', 'gezim.vushtrria@gmail.com', '+38345841111', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', 'ABC1234567', 'NT123456789', '2011-09-21', 150.00, 0, NULL, '', 'Rruga Xhemajl Ademi nr 12 71000', '', '', 1, '2025-09-29', NULL, 'bank_transfer', 0, NULL, '2025-09-29 08:44:00', '2025-09-29 08:44:00', 'Gezim oshlani', 10, 4, 'Shqip.Serbisht,Anglisht', '[{\"emri\":\"Valon Dredhaj\",\"pozita\":\"Asistent\"}]'),
(43, 'Noeria Gëzim Vushtrria', 'Vushtrri', 'Kosova', 'gezim.vushtrria@gmail.com', '+38345841111', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', 'ABC1234567', 'NT123456789', '2011-09-21', 150.00, 0, NULL, '', 'Rruga Xhemajl Ademi nr 12 71000', '', '', 1, '2025-09-29', NULL, 'bank_transfer', 0, NULL, '2025-09-29 09:18:33', '2025-09-29 09:18:33', 'Gezim oshlani', 10, 4, 'Shqip.Serbisht,Anglisht', '[{\"emri\":\"Valon Dredhaj\",\"pozita\":\"Asistent\"}]'),
(44, 'Valon Sadiku', 'Gjilan', 'Kosova', 'zyra.valonsadiku@gmail.com', '+38345213675', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', '1234567891', 'NT123456789', '2025-09-18', 150.00, 0, NULL, '', 'Rruga Abdullah Tahiri numër 26 60000', '', '', 1, '2025-10-04', NULL, 'bank_transfer', 0, NULL, '2025-10-04 20:13:32', '2025-10-04 20:13:32', 'Gezim oshlani', 5, 5, 'Shqip.Serbisht,Anglisht', '[]'),
(45, 'Valon Sadiku', 'Gjilan', 'Kosova', 'zyra.valonsadiku@gmail.com', '+38345213675', 'Vala', 'TEB BANKA', 'XK055001000137385385', '54323245232556235', '123456789', '1234567891', 'NT123456789', '2025-09-18', 150.00, 0, NULL, '', 'Rruga Abdullah Tahiri numër 26 60000', '', '', 1, '2025-10-04', NULL, 'bank_transfer', 0, NULL, '2025-10-04 21:05:18', '2025-10-04 21:05:18', 'Gezim oshlani', 5, 5, 'Shqip.Serbisht,Anglisht', '[]'),
(46, 'Valon Sadiku', 'Viti', 'Kosova', 'valonsadiku2018@gmail.com', '+38345213675', 'Vala', 'NLB BANK', 'XK055001000137385385', '1702018603253707', '123456789', 'ABC1234567', 'NT123456789', '2025-09-29', 150.00, 0, NULL, '', 'Rruga Xhemajl Ademi nr 12 61000', '', '', 1, '2025-10-05', NULL, 'bank_transfer', 0, NULL, '2025-10-05 20:42:13', '2025-10-05 20:42:13', 'Gezim oshlani', 2, 4, 'Shqip.Serbisht,Anglisht', '[{\"emri\":\"Valon Dredhaj\",\"pozita\":\"Asistent\"}]');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `abonimet`
--
ALTER TABLE `abonimet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_type` (`log_type`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `aplikimet_konkurs`
--
ALTER TABLE `aplikimet_konkurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `konkurs_id` (`konkurs_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `automatic_payments`
--
ALTER TABLE `automatic_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `billing_config`
--
ALTER TABLE `billing_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD UNIQUE KEY `unique_config_key` (`config_key`);

--
-- Indexes for table `billing_statistics`
--
ALTER TABLE `billing_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_billing_date` (`billing_date`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`ip`);

--
-- Indexes for table `camera_access_logs`
--
ALTER TABLE `camera_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `camera_id` (`camera_id`);

--
-- Indexes for table `camera_configurations`
--
ALTER TABLE `camera_configurations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `camera_id` (`camera_id`);

--
-- Indexes for table `camera_recordings`
--
ALTER TABLE `camera_recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `camera_id` (`camera_id`);

--
-- Indexes for table `chat_history`
--
ALTER TABLE `chat_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fatura`
--
ALTER TABLE `fatura`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `faturat`
--
ALTER TABLE `faturat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `konkurset`
--
ALTER TABLE `konkurset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `lajme`
--
ALTER TABLE `lajme`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `noteret`
--
ALTER TABLE `noteret`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nr_personal` (`nr_personal`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nr_licences` (`nr_licences`);

--
-- Indexes for table `noteri`
--
ALTER TABLE `noteri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_noteri_status` (`status`),
  ADD KEY `idx_noteri_subscription` (`subscription_type`);

--
-- Indexes for table `noteri_abonimet`
--
ALTER TABLE `noteri_abonimet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noter_id` (`noter_id`),
  ADD KEY `abonim_id` (`abonim_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_audit_log`
--
ALTER TABLE `payment_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`office_email`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `phone_verification_codes`
--
ALTER TABLE `phone_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `phone_verification_logs`
--
ALTER TABLE `phone_verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `privacy_policy`
--
ALTER TABLE `privacy_policy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `punetoret`
--
ALTER TABLE `punetoret`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `raportet`
--
ALTER TABLE `raportet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `camera_id` (`camera_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `security_cameras`
--
ALTER TABLE `security_cameras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `security_recordings`
--
ALTER TABLE `security_recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `camera_id` (`camera_id`);

--
-- Indexes for table `security_settings`
--
ALTER TABLE `security_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `session_logs`
--
ALTER TABLE `session_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`category`,`setting_key`);

--
-- Indexes for table `sms_provider_config`
--
ALTER TABLE `sms_provider_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_name` (`provider_name`);

--
-- Indexes for table `subscription`
--
ALTER TABLE `subscription`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noter_id` (`noter_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_payments_noter_date` (`noter_id`,`payment_date`),
  ADD KEY `idx_payments_status_date` (`status`,`payment_date`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transcripts`
--
ALTER TABLE `transcripts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noter_id` (`noter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `zyra_id` (`zyra_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `video_calls`
--
ALTER TABLE `video_calls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `video_call_logs`
--
ALTER TABLE `video_call_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zyra`
--
ALTER TABLE `zyra`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zyrat`
--
ALTER TABLE `zyrat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_verified` (`payment_verified`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `abonimet`
--
ALTER TABLE `abonimet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `aplikimet_konkurs`
--
ALTER TABLE `aplikimet_konkurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `automatic_payments`
--
ALTER TABLE `automatic_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_config`
--
ALTER TABLE `billing_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `billing_statistics`
--
ALTER TABLE `billing_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `camera_access_logs`
--
ALTER TABLE `camera_access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `camera_configurations`
--
ALTER TABLE `camera_configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `camera_recordings`
--
ALTER TABLE `camera_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_history`
--
ALTER TABLE `chat_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `fatura`
--
ALTER TABLE `fatura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `faturat`
--
ALTER TABLE `faturat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `konkurset`
--
ALTER TABLE `konkurset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lajme`
--
ALTER TABLE `lajme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `noteret`
--
ALTER TABLE `noteret`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `noteri`
--
ALTER TABLE `noteri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `noteri_abonimet`
--
ALTER TABLE `noteri_abonimet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payment_audit_log`
--
ALTER TABLE `payment_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `phone_verification_codes`
--
ALTER TABLE `phone_verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `phone_verification_logs`
--
ALTER TABLE `phone_verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privacy_policy`
--
ALTER TABLE `privacy_policy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `punetoret`
--
ALTER TABLE `punetoret`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `raportet`
--
ALTER TABLE `raportet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_alerts`
--
ALTER TABLE `security_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `security_cameras`
--
ALTER TABLE `security_cameras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `security_recordings`
--
ALTER TABLE `security_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_settings`
--
ALTER TABLE `security_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `session_logs`
--
ALTER TABLE `session_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `sms_provider_config`
--
ALTER TABLE `sms_provider_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscription`
--
ALTER TABLE `subscription`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transcripts`
--
ALTER TABLE `transcripts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `video_calls`
--
ALTER TABLE `video_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `video_call_logs`
--
ALTER TABLE `video_call_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zyra`
--
ALTER TABLE `zyra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zyrat`
--
ALTER TABLE `zyrat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aplikimet_konkurs`
--
ALTER TABLE `aplikimet_konkurs`
  ADD CONSTRAINT `aplikimet_konkurs_ibfk_1` FOREIGN KEY (`konkurs_id`) REFERENCES `konkurset` (`id`),
  ADD CONSTRAINT `aplikimet_konkurs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `automatic_payments`
--
ALTER TABLE `automatic_payments`
  ADD CONSTRAINT `automatic_payments_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `camera_access_logs`
--
ALTER TABLE `camera_access_logs`
  ADD CONSTRAINT `camera_access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `camera_access_logs_ibfk_2` FOREIGN KEY (`camera_id`) REFERENCES `security_cameras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `camera_configurations`
--
ALTER TABLE `camera_configurations`
  ADD CONSTRAINT `camera_configurations_ibfk_1` FOREIGN KEY (`camera_id`) REFERENCES `security_cameras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `camera_recordings`
--
ALTER TABLE `camera_recordings`
  ADD CONSTRAINT `camera_recordings_ibfk_1` FOREIGN KEY (`camera_id`) REFERENCES `security_cameras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fatura`
--
ALTER TABLE `fatura`
  ADD CONSTRAINT `fatura_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  ADD CONSTRAINT `fatura_ibfk_2` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `konkurset`
--
ALTER TABLE `konkurset`
  ADD CONSTRAINT `konkurset_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `punetoret`
--
ALTER TABLE `punetoret`
  ADD CONSTRAINT `punetoret_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD CONSTRAINT `security_alerts_ibfk_1` FOREIGN KEY (`camera_id`) REFERENCES `security_cameras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `security_alerts_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `security_cameras`
--
ALTER TABLE `security_cameras`
  ADD CONSTRAINT `security_cameras_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `security_recordings`
--
ALTER TABLE `security_recordings`
  ADD CONSTRAINT `security_recordings_ibfk_1` FOREIGN KEY (`camera_id`) REFERENCES `security_cameras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription`
--
ALTER TABLE `subscription`
  ADD CONSTRAINT `subscription_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`noter_id`) REFERENCES `noteri` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
