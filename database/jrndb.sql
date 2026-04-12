-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 12:47 PM
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
-- Database: `jrndb`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','employee','user') NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_type`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(177, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 07:55:39'),
(178, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 07:55:42'),
(179, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:06:33'),
(180, NULL, 'admin', 'login_failed', 'Failed admin login attempt (wrong password): laclan5963ant@student.fatima.edu.ph', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:12:44'),
(181, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:12:47'),
(182, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260326-0001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:14:43'),
(183, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:15:46'),
(184, 2, 'employee', 'service_status_scheduled', 'Service DTI Business Name Registration scheduled to be deactivated on 2026-03-29 09:16:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:16:42'),
(185, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:17:12'),
(186, 2, 'admin', 'invoice_created', 'Invoice INV-20260326-0001 created for inquiry INQ-20260326-0001 (client: Lean Joshua Aclan, amount: 5, status: unpaid)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:17:40'),
(187, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 08:43:52'),
(188, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 08:44:39'),
(189, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:19:57'),
(190, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:48:42'),
(191, NULL, 'user', 'login_failed', 'Failed login attempt (wrong password): kioshiiofficial@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:48:52'),
(192, NULL, 'user', 'login_failed', 'Failed login attempt (wrong password): kioshiiofficial@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:48:54'),
(193, NULL, 'user', 'login_failed', 'Failed login attempt (wrong password): kioshiiofficial@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:48:54'),
(194, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:49:01'),
(195, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 14:54:53'),
(196, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0002 – Business Registration Amendment (Rush Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:11:44'),
(197, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:14:49'),
(198, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:15:20'),
(199, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:16:48'),
(200, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:19:26'),
(201, NULL, 'user', 'login_failed', 'Failed login attempt (wrong password): kioshiiofficial@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:20:11'),
(202, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:20:14'),
(203, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:24:53'),
(204, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:27:01'),
(205, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:29:56'),
(206, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:30:11'),
(207, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:35:01'),
(208, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:35:10'),
(209, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:48:58'),
(210, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:49:08'),
(211, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:58:17'),
(212, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 15:58:29'),
(213, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0003 – BIR Open Cases Resolution (Standard Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:10:46'),
(214, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:18:57'),
(215, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:19:07'),
(216, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:32:57'),
(217, NULL, 'user', 'login_failed', 'Failed login attempt (wrong password): kioshiiofficial@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:33:07'),
(218, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:33:12'),
(219, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:52:12'),
(220, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:52:25'),
(221, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 2 (5/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:53:32'),
(222, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 3 (8/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 16:54:08'),
(223, 2, 'admin', 'service_created', 'Service test1 (ID 37) created.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:10:39'),
(224, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:10:42'),
(225, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:10:57'),
(226, 28, 'user', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:12:17'),
(227, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:12:31'),
(228, 2, 'employee', 'service_updated', 'Service Business Registration Amendment (ID 17) updated: price: 16850.00 → 17000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:29:47'),
(229, 2, 'admin', 'logout', 'Lean Joshua Aclan logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:30:00'),
(230, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:30:10'),
(231, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:31:47'),
(232, 2, 'employee', 'service_schedule_cancelled', 'Scheduled status change for service Business Registration Amendment was cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:34:08'),
(233, 2, 'employee', 'service_schedule_cancelled', 'Scheduled status change for service DTI Business Name Registration was cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:34:09'),
(234, 2, 'admin', 'service_created', 'Service test service (ID 38) created.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:55:24'),
(235, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0004 – test service (Standard Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 17:58:10'),
(236, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0001 – Business Registration Amendment (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 18:08:51'),
(237, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260411-0002 – Business Registration Amendment (Standard Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 18:55:18'),
(238, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 06:27:49'),
(239, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 06:28:00'),
(240, 2, 'admin', 'invoice_status_updated', 'Invoice ID #8 status changed to pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 06:57:08'),
(241, 2, 'admin', 'invoice_status_updated', 'Invoice ID #8 status changed to pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 06:57:13'),
(242, 2, 'admin', 'invoice_status_updated', 'Invoice ID #8 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 07:03:11'),
(243, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 5 (10/2025)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 08:33:03'),
(244, 2, 'employee', 'service_status_scheduled', 'Service Business Registration Amendment scheduled to be deactivated on 2026-04-15 10:58:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 08:58:12'),
(245, 2, 'employee', 'service_schedule_cancelled', 'Scheduled status change for service Business Registration Amendment was cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 08:58:22'),
(246, 2, 'admin', 'login', 'Lean Joshua Aclan (admin) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:10:17'),
(247, 28, 'user', 'login', 'Lean Joshua Aclan logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:12:19'),
(248, 2, 'employee', 'service_updated', 'Service Business Permit Renewal (ID 30) updated: price: 0.00 → 7349; long_description changed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:14:51'),
(249, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0001 – Business Permit Renewal (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:15:17'),
(250, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0002 – Business Registration Amendment (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:16:37'),
(251, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0003 – Professional Bookkeeping Services (Express Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:18:28'),
(252, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0004 – SEC Registration (Express Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:22:42'),
(253, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0005 – Professional Bookkeeping Services (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:35:47'),
(254, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0006 – Professional Bookkeeping Services (Express Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:41:27'),
(255, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0001 – Business Registration Amendment (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:44:49'),
(256, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0002 – Business Registration Amendment (Express Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:48:13'),
(257, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0001 – Business Registration Amendment (Priority Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:50:44'),
(258, 28, 'user', 'inquiry_created', 'Created inquiry INQ-20260412-0002 – Business Consultation Services (Standard Processing)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:52:16'),
(259, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0001 for Lean Joshua Aclan — Status: \'Pending\' → \'In_review\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:53:52'),
(260, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0001 for Lean Joshua Aclan — Status: \'In_review\' → \'In_review\'', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-12 09:54:30'),
(261, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'Pending\' → \'In_review\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:54:36'),
(262, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'In_review\' → \'In_review\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:55:09'),
(263, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'In_review\' → \'Pending\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:55:13'),
(264, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'Pending\' → \'Pending\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 09:55:15'),
(265, 2, 'admin', 'invoice_created', 'Invoice INV-20260412-0001 created for inquiry INQ-20260412-0002 (client: Lean Joshua Aclan, amount: 3000, status: unpaid)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:13:24'),
(266, 2, 'admin', 'invoice_status_updated', 'Invoice ID #9 status changed to pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:14:34'),
(267, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'Pending\' → \'In_review\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:14:46'),
(268, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0002 for Lean Joshua Aclan — Status: \'In_review\' → \'Pending\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:15:00'),
(269, 2, 'employee', 'inquiry_status_updated', 'Updated inquiry #INQ-20260412-0001 for Lean Joshua Aclan — Status: \'In_review\' → \'Pending\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:15:25'),
(270, 2, 'admin', 'invoice_created', 'Invoice INV-20260412-0002 created for inquiry INQ-20260412-0001 (client: Lean Joshua Aclan, amount: 19550, status: unpaid)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:19:25'),
(271, 2, 'admin', 'invoice_status_updated', 'Invoice ID #10 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:19:41'),
(272, 2, 'admin', 'invoice_status_updated', 'Invoice ID #10 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:19:43'),
(273, 2, 'admin', 'invoice_status_updated', 'Invoice ID #10 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:22:08'),
(274, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 2 (4/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:27:18'),
(275, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 3 (4/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:28:17'),
(276, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 4 (5/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:29:25'),
(277, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 5 (5/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:29:58'),
(278, 2, 'admin', 'payroll_added', 'Added payroll for employee ID 5 (5/2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:30:13'),
(279, 2, 'admin', 'payroll_status_updated', 'Payroll ID 8 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:21'),
(280, 2, 'admin', 'payroll_status_updated', 'Payroll ID 8 status changed to pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:24'),
(281, 2, 'admin', 'payroll_status_updated', 'Payroll ID 8 status changed to pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:40'),
(282, 2, 'admin', 'payroll_status_updated', 'Payroll ID 8 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:44'),
(283, 2, 'admin', 'payroll_status_updated', 'Payroll ID 5 status changed to paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:56');

-- --------------------------------------------------------

--
-- Table structure for table `billings`
--

CREATE TABLE `billings` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_name` varchar(150) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','pending','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `service_name` varchar(255) NOT NULL,
  `base_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `processing_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_fees` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billings`
--

INSERT INTO `billings` (`id`, `invoice_number`, `client_name`, `total_amount`, `status`, `created_at`, `updated_at`, `service_name`, `base_fee`, `processing_fee`, `other_fees`, `discount`) VALUES
(9, 'INV-20260412-0001', 'Lean Joshua Aclan', 3000.00, 'pending', '2026-04-12 18:13:24', '2026-04-12 18:14:34', 'Business Consultation Services', 3000.00, 0.00, 0.00, 0.00),
(10, 'INV-20260412-0002', 'Lean Joshua Aclan', 19550.00, 'paid', '2026-04-12 18:19:25', '2026-04-12 18:19:41', 'Business Registration Amendment', 19550.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('admin','employee') DEFAULT 'employee',
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `account_number`, `email`, `phone`, `password`, `first_name`, `last_name`, `position`, `department`, `role`, `status`, `created_at`, `updated_at`) VALUES
(2, 'EMP-20251105-0001', 'laclan5963ant@student.fatima.edu.ph', '', '$2y$10$Ke/.omKIP.UZJDtUVrC0puJ7pvIiU9t3nqAmLPxoGuU5U3UFNC74m', 'Lean Joshua', 'Aclan', 'Developer', '', 'admin', 'active', '2025-11-05 07:29:20', '2025-11-07 11:25:09'),
(3, 'EMP-20251105-0002', 'aaninofranco6975ant@student.fatima.edu.ph', '', '$2y$10$N0FijNZXntm483MEn5oN6.OkdVvmPfdeie9ZYJnlmaHE24po9laXG', 'Ashley Nicole', 'Niñofranco', 'Developer', '', 'admin', 'active', '2025-11-05 07:33:03', '2025-12-18 15:57:35'),
(4, 'EMP-20251105-0003', 'jnmuana6887ant@student.fatima.edu.ph', '', '$2y$10$0MvaIwuErO7HVqL/Hz0d4OAZYCsn1xoLUHQSHiYk793YwrQa9ka0G', 'Jan Manuel', 'Muaña', 'Developer', '', 'admin', 'active', '2025-11-05 07:33:44', '2025-12-18 15:57:31'),
(5, 'EMP-20251105-0004', 'cvpanti6935ant@student.fatima.edu.ph', '', '$2y$10$Bf5svvrAtKVloKUDoyorneqV9bL6hlxPtMa5yyNURVR2JcnC1noXS', 'Chester Lei', 'Panti', 'Developer', '', 'admin', 'active', '2025-11-05 07:34:13', '2025-12-18 15:57:23');

-- --------------------------------------------------------

--
-- Table structure for table `employee_permissions`
--

CREATE TABLE `employee_permissions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_archive` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_permissions`
--

INSERT INTO `employee_permissions` (`id`, `employee_id`, `permission_name`, `can_view`, `can_create`, `can_edit`, `can_archive`, `created_at`) VALUES
(4, 2, 'inquiries', 1, 1, 1, 1, '2025-11-05 07:31:45'),
(5, 2, 'users', 1, 1, 1, 1, '2025-11-05 07:31:46'),
(6, 2, 'employees', 1, 1, 1, 1, '2025-11-05 07:31:46'),
(34, 2, 'billing', 1, 1, 1, 1, '2025-12-18 15:35:11'),
(35, 2, 'logs', 1, 0, 0, 0, '2025-12-18 15:35:11'),
(41, 5, 'inquiries', 1, 0, 1, 0, '2025-12-18 15:57:23'),
(42, 5, 'billing', 1, 1, 1, 0, '2025-12-18 15:57:23'),
(43, 5, 'users', 1, 0, 1, 1, '2025-12-18 15:57:23'),
(44, 5, 'employees', 1, 1, 1, 1, '2025-12-18 15:57:23'),
(45, 5, 'logs', 1, 0, 0, 0, '2025-12-18 15:57:23'),
(46, 4, 'inquiries', 1, 0, 1, 0, '2025-12-18 15:57:31'),
(47, 4, 'billing', 1, 1, 1, 0, '2025-12-18 15:57:31'),
(48, 4, 'users', 1, 0, 1, 1, '2025-12-18 15:57:31'),
(49, 4, 'employees', 1, 1, 1, 1, '2025-12-18 15:57:31'),
(50, 4, 'logs', 1, 0, 0, 0, '2025-12-18 15:57:31'),
(51, 3, 'inquiries', 1, 0, 1, 0, '2025-12-18 15:57:35'),
(52, 3, 'users', 1, 0, 1, 1, '2025-12-18 15:57:35'),
(53, 3, 'employees', 1, 1, 1, 1, '2025-12-18 15:57:35'),
(54, 3, 'logs', 1, 0, 0, 0, '2025-12-18 15:57:35');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `inquiry_number` varchar(20) DEFAULT NULL,
  `qr_code_path` varchar(500) DEFAULT NULL COMMENT 'Path to stored QR code image',
  `user_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `processing_type` enum('standard','priority','express','rush','same_day') NOT NULL DEFAULT 'standard',
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `additional_notes` text DEFAULT NULL,
  `status` enum('pending','in_review','completed','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `inquiry_number`, `qr_code_path`, `user_id`, `service_name`, `processing_type`, `base_price`, `price`, `additional_notes`, `status`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(113, 'INQ-20260412-0001', 'uploads/qrcodes/inquiry_INQ-20260412-0001.png', 28, 'Business Registration Amendment', 'priority', 17000.00, 19550.00, 'Test Submission 1', 'pending', NULL, '2026-04-12 09:50:44', '2026-04-12 10:15:25'),
(114, 'INQ-20260412-0002', 'uploads/qrcodes/inquiry_INQ-20260412-0002.png', 28, 'Business Consultation Services', 'standard', 3000.00, 3000.00, 'Test Submission 2', 'pending', NULL, '2026-04-12 09:52:16', '2026-04-12 10:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_documents`
--

CREATE TABLE `inquiry_documents` (
  `id` int(11) NOT NULL,
  `inquiry_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `iv` varbinary(16) DEFAULT NULL,
  `id_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'File extension (pdf, jpg, png)',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_label` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inquiry_documents`
--

INSERT INTO `inquiry_documents` (`id`, `inquiry_id`, `file_name`, `file_path`, `iv`, `id_type`, `file_size`, `file_type`, `uploaded_at`, `file_label`) VALUES
(324, 113, 'sample file.png', 'uploads/inquiries/28/2026-04-12_115044_Business_Registration_Amendment/sample_file_69db6af4744f1.png', 0x1bb94b6ba3c8ab5fe6824fc72611221b, NULL, 1467, 'png', '2026-04-12 09:50:44', ''),
(325, 113, 'sample file.png', 'uploads/inquiries/28/2026-04-12_115044_Business_Registration_Amendment/sample_file_69db6af474759.png', 0x76be9b265aa2c27765bd77de8b8873e9, NULL, 1467, 'png', '2026-04-12 09:50:44', ''),
(326, 113, 'sample file.png', 'uploads/inquiries/28/2026-04-12_115044_Business_Registration_Amendment/sample_file_69db6af4748be.png', 0x2e3c3514e8decd84c22b500e130118b5, NULL, 1467, 'png', '2026-04-12 09:50:44', ''),
(327, 114, 'sample file.png', 'uploads/inquiries/28/2026-04-12_115216_Business_Consultation_Services/sample_file_69db6b50e79d5.png', 0xf006eb036088205a052cde8e57ba91e2, NULL, 1467, 'png', '2026-04-12 09:52:16', ''),
(328, 114, 'sample file.png', 'uploads/inquiries/28/2026-04-12_115216_Business_Consultation_Services/sample_file_69db6b50e7c01.png', 0xd903abc45f5d05e83e5f4cb93d0e80b5, NULL, 1467, 'png', '2026-04-12 09:52:17', '');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_month` tinyint(2) NOT NULL COMMENT '1-12',
  `period_year` smallint(4) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sss_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `philhealth_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pagibig_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gross_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('paid','pending') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `period_month`, `period_year`, `basic_salary`, `allowances`, `overtime_pay`, `sss_deduction`, `philhealth_deduction`, `pagibig_deduction`, `tax_deduction`, `other_deductions`, `gross_pay`, `net_pay`, `status`, `notes`, `paid_at`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 2, 4, 2026, 20000.00, 1500.00, 500.00, 1200.00, 300.00, 100.00, 1500.00, 200.00, 22000.00, 18700.00, 'pending', 'March payroll', NULL, 2, '2026-04-12 10:27:18', '2026-04-12 10:27:18'),
(5, 3, 4, 2026, 18000.00, 1200.00, 400.00, 1100.00, 250.00, 90.00, 1400.00, 180.00, 19600.00, 16580.00, 'paid', 'March payroll', '2026-04-12 12:36:56', 2, '2026-04-12 10:28:17', '2026-04-12 10:36:56'),
(6, 4, 5, 2026, 25500.00, 2100.00, 750.00, 1450.00, 370.00, 130.00, 1850.00, 270.00, 28350.00, 24280.00, 'pending', 'April payroll', NULL, 2, '2026-04-12 10:29:25', '2026-04-12 10:29:25'),
(7, 5, 5, 2026, 22500.00, 1900.00, 650.00, 1350.00, 300.00, 120.00, 1650.00, 240.00, 25050.00, 21390.00, 'pending', 'April payroll', NULL, 2, '2026-04-12 10:29:58', '2026-04-12 10:29:58'),
(8, 5, 5, 2026, 22500.00, 1900.00, 650.00, 1350.00, 300.00, 120.00, 1650.00, 240.00, 25050.00, 21390.00, 'paid', 'April payroll', '2026-04-12 12:36:44', 2, '2026-04-12 10:30:13', '2026-04-12 10:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `slug` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `short_description` varchar(300) DEFAULT NULL,
  `long_description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `scheduled_action` enum('activate','deactivate') DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `scheduled_effective_at` datetime DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `standard_price` decimal(10,2) DEFAULT 0.00,
  `priority_price` decimal(10,2) DEFAULT 0.00,
  `express_price` decimal(10,2) DEFAULT 0.00,
  `rush_price` decimal(10,2) DEFAULT 0.00,
  `same_day_price` decimal(10,2) DEFAULT 0.00,
  `standard_status` tinyint(1) DEFAULT 1,
  `priority_status` tinyint(1) DEFAULT 1,
  `express_status` tinyint(1) DEFAULT 1,
  `rush_status` tinyint(1) DEFAULT 1,
  `same_day_status` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `price`, `slug`, `category`, `short_description`, `long_description`, `icon`, `image`, `is_active`, `scheduled_action`, `scheduled_at`, `scheduled_effective_at`, `featured`, `display_order`, `created_by`, `created_at`, `updated_at`, `standard_price`, `priority_price`, `express_price`, `rush_price`, `same_day_price`, `standard_status`, `priority_status`, `express_status`, `rush_status`, `same_day_status`) VALUES
(17, 'Business Registration Amendment', 17000.00, 'amendment', 'Business Processing Services', 'Update and amend your business registration documents to reflect current business information and maintain compliance.', '<h2>What is Business Registration Amendment?</h2>\r\n<p>Business Registration Amendment is the formal process of updating or modifying your registered business information with government agencies in the Philippines, including the DTI, SEC, BIR, and local government units (LGUs). Amendments are necessary when your business undergoes significant changes such as business name changes, address relocation, ownership structure modifications, changes in business activities or line of business, increase or decrease in authorized capital stock, or updates to corporate officers and directors. Proper amendment ensures your business records remain accurate, compliant with regulatory requirements, and legally valid for all business transactions.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your business registration amendment, please prepare the following (requirements vary based on type of amendment):</p>\r\n<ul>\r\n<li><strong>BIR Form 1905</strong> &ndash; Application for Registration Information Update for BIR amendments</li>\r\n<li><strong>Original Certificate of Registration (COR/BIR Form 2303)</strong> &ndash; For replacement with updated version reflecting changes</li>\r\n<li><strong>Amended Articles of Incorporation</strong> &ndash; For corporations amending corporate name, purpose, capital structure, term, or other AOI provisions (filed with SEC)</li>\r\n<li><strong>Board Resolution or Stockholders\' Resolution</strong> &ndash; Approving the amendment (requires majority of board and 2/3 of outstanding capital stock for most amendments)</li>\r\n<li><strong>Director\'s Certificate</strong> &ndash; Certifying board and stockholder approval of amendments</li>\r\n<li><strong>Secretary\'s Certificate</strong> &ndash; Certifying no intra-corporate disputes and designating authorized representatives</li>\r\n<li><strong>Updated DTI Certificate</strong> &ndash; For sole proprietorships changing business name, address, line of business, or capitalization</li>\r\n<li><strong>Proof of New Business Address</strong> &ndash; Contract of Lease or Transfer Certificate of Title for address changes</li>\r\n<li><strong>Valid Government-issued IDs</strong> &ndash; Of business owner or authorized representative</li>\r\n<li><strong>Authorization Letter or Special Power of Attorney</strong> &ndash; If using a representative (notarized)</li>\r\n<li><strong>Mayor\'s Permit</strong> &ndash; Updated permit reflecting changes for LGU amendment</li>\r\n<li><strong>Payment Receipts</strong> &ndash; For amendment processing fees at authorized agent banks or RDOs</li>\r\n</ul>\r\n<p><strong>Common Amendment Types:</strong> Business Name Change, Business Address Change, Change of Line of Business, Ownership/Stockholder Changes, Corporate Officers/Directors Update, Capital Stock Increase/Decrease, Business Purpose Amendment.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your amendment progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your amendment.</li>\r\n<li><strong>We Process Your Amendment</strong> &ndash; Our team prepares all required documentation including amended articles, board resolutions, director\'s certificates, and supporting documents. We coordinate the amendment process across multiple agencies based on the type of change required.</li>\r\n<li><strong>Multi-Agency Filing</strong> &ndash; We file amendments with the appropriate agencies: SEC (for corporations), DTI (for sole proprietorships), BIR (tax registration updates), and LGU (permit amendments). We handle the eAMEND portal submission for SEC amendments and RDO filings for BIR updates.</li>\r\n<li><strong>Receive Updated Documents</strong> &ndash; Upon approval by all relevant agencies, we collect your updated certificates including Amended Certificate of Registration from SEC/DTI, Updated BIR Certificate of Registration, and Amended Mayor\'s Permit. All documents will be made available through your client portal and delivered to you, ensuring your business records are fully updated and compliant.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n<li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n<li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with amendment requirements</li>\r\n<li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n<li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n<li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'uploads/services/service_691f28fab9e605.47355135.jpg', 1, NULL, NULL, NULL, 0, 1, NULL, '2025-11-20 11:49:02', '2026-04-12 08:58:22', 17000.00, 19550.00, 22100.00, 25500.00, 28900.00, 1, 1, 1, 1, 0),
(18, 'Annual Income Tax Filing', 3500.00, 'annual-income-tax', 'Accounting & Tax Services', 'Ensure accurate and timely filing of your annual income tax return to comply with BIR regulations and optimize your tax position.', '<h2>What is Annual Income Tax Filing?</h2>\r\n<p>Annual Income Tax Filing is the mandatory yearly submission of your Income Tax Return (ITR) to the Bureau of Internal Revenue, reporting your total income, allowable deductions, tax credits, and final tax liability for the entire taxable year. All individuals and businesses registered with the BIR must file their annual ITR on or before the statutory deadline, typically April 15 of the following year for individuals and on the 15th day of the 4th month after the close of the taxable year for corporations. The appropriate form depends on taxpayer type: BIR Form 1701 for self-employed individuals, estates, and trusts; BIR Form 1702 for corporations, partnerships, and cooperatives; BIR Form 1700 for individuals earning purely compensation income from a single employer; and BIR Form 1701A for individuals with mixed income or multiple employers. Professional annual tax filing ensures accurate computation, maximization of deductions and tax credits, proper reconciliation with quarterly returns, and full compliance to avoid penalties, surcharges, and potential tax audits.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your annual income tax filing, please prepare the following:</p>\r\n<ul>\r\n    <li><strong>BIR Certificate of Registration (Form 2303)</strong> – Showing registered tax types and filing requirements</li>\r\n    <li><strong>Complete Financial Statements</strong> – Income Statement (Profit &amp; Loss), Balance Sheet, Cash Flow Statement, and Notes to Financial Statements for the entire taxable year</li>\r\n    <li><strong>Summary of Gross Income</strong> – All income sources including business/professional income, compensation income, rental income, interest income, dividend income, capital gains, and other taxable income</li>\r\n    <li><strong>Deductible Expense Records</strong> – Itemized expenses with supporting receipts for cost of sales, operating expenses, interest expense, depreciation, salaries and wages, taxes and licenses, professional fees, rent, utilities, and other allowable deductions</li>\r\n    <li><strong>Tax Credit Certificates</strong> – BIR Form 2307 (creditable withholding tax certificates from clients/customers), foreign tax credits, and excess tax credit carryover from previous years</li>\r\n    <li><strong>Quarterly Income Tax Returns</strong> – Previously filed BIR Forms 1701Q or 1702Q for reconciliation and computation of annual adjustment</li>\r\n    <li><strong>Alphalist of Payees</strong> – For withholding agents: complete list of income payments and taxes withheld (suppliers, professionals, employees)</li>\r\n    <li><strong>Audited Financial Statements</strong> – For corporations with required audits: AFS prepared by independent CPAs with audit report</li>\r\n    <li><strong>Inventory Records</strong> – Beginning and ending inventory for cost of goods sold computation (for businesses selling goods)</li>\r\n    <li><strong>Asset Schedules</strong> – Depreciation schedules for fixed assets, amortization schedules for intangible assets</li>\r\n    <li><strong>Prior Year Tax Return</strong> – Previous year\'s ITR for continuity, NOLCO (Net Operating Loss Carryover), and other carryforward items</li>\r\n</ul>\r\n<p><strong>Note:</strong> Complete and organized records throughout the year make annual tax filing more efficient and accurate.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n    <li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n    <li><strong>Receive Your Unique QR Code</strong> – Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your annual tax filing progress.</li>\r\n    <li><strong>Save and Use Your QR Code</strong> – Scan and save the QR code to your device. You can use this code to check your filing status, receive updates, and communicate with our team about your tax return.</li>\r\n    <li><strong>We Process Your Annual Tax Return</strong> – Our tax specialists conduct a comprehensive year-end review of your financial records, reconcile quarterly income tax payments with annual liability, maximize all allowable deductions and exemptions, identify and claim available tax credits, compute accurate taxable income and tax due, and prepare the appropriate BIR Form (1700, 1701, 1701A, or 1702) with complete attachments including financial statements, schedules, and supporting annexes.</li>\r\n    <li><strong>Filing and Payment Processing</strong> – We file your annual income tax return electronically through eBIRForms or manually at your Revenue District Office before the April 15 deadline (or applicable corporate deadline), compute any balance of tax payable or refundable amount/tax credit carryover, process payment of taxes due through authorized agent banks, and secure official BIR filing confirmation and payment receipts.</li>\r\n    <li><strong>Receive Complete Documentation</strong> – After successful filing, we provide you with a copy of your filed annual income tax return with BIR validation, payment confirmation receipts (if tax is due), tax computation summary and reconciliation schedule, recommendations for tax planning and optimization for the coming year, and reminder of post-filing compliance requirements. All documents are organized and stored in your client portal, giving you complete records for future reference, audit defense, and financial planning purposes.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n    <li><strong>Streamlined Process</strong> – Our e-Process system eliminates paperwork and manual tracking</li>\r\n    <li><strong>Accuracy Guaranteed</strong> – We verify all documents and ensure compliance with BIR regulations</li>\r\n    <li><strong>Transparency</strong> – Track your application in real-time using your unique QR code</li>\r\n    <li><strong>Professional Support</strong> – Our team guides you through every step of the registration process</li>\r\n    <li><strong>Fast Turnaround</strong> – We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business—let us handle the registration.</strong></p>', '', 'assets/img/services-img/annual-income-tax-banner.jpg', 1, NULL, NULL, NULL, 0, 2, NULL, '2025-11-20 11:50:06', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(19, 'BIR Open Cases Resolution', 5000.00, 'bir-open-cases', 'Business Processing Services', 'Resolve unfiled tax returns and BIR open cases to clear your tax record and avoid penalties.', '<h2>What are BIR Open Cases?</h2>\r\n<p>BIR Open Cases are automatically generated records in the Bureau of Internal Revenue\'s Integrated Tax System (ITS) when a taxpayer fails to file required tax returns by their statutory deadlines or when system errors prevent filed returns from reaching the BIR\'s data warehouse. These open cases represent unfiled or unprocessed tax returns that create compliance gaps in your tax records. If left unresolved, open cases can lead to accumulating penalties and surcharges, prevent business closure or registration updates, affect loan applications and visa processing, block issuance of tax clearance certificates, and potentially trigger tax audit notices or assessments from the BIR.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your BIR open cases resolution, please prepare the following:</p>\r\n<ul>\r\n    <li><strong>List of Open Cases from BIR</strong> &ndash; Official list from your Revenue District Office (RDO) detailing unfiled returns by period and form type</li>\r\n    <li><strong>BIR Certificate of Registration (Form 2303)</strong> &ndash; Original COR showing registered tax types and filing frequency</li>\r\n    <li><strong>Previously Filed Tax Returns</strong> &ndash; Copies with BIR receiving stamps or email confirmations for returns already filed (if claiming system error)</li>\r\n    <li><strong>Books of Accounts and Financial Records</strong> &ndash; Income statements, sales records, expense records, and supporting documents for preparing missed returns</li>\r\n    <li><strong>Summary List of Sales/Receipts</strong> &ndash; For VAT or Percentage Tax returns covering open case periods</li>\r\n    <li><strong>Alphalist of Employees and Withholding Tax Records</strong> &ndash; For withholding tax returns (BIR Forms 1601C, 1601EQ, 1604CF, 1604E)</li>\r\n    <li><strong>Valid Government-issued ID</strong> &ndash; For taxpayer or authorized representative</li>\r\n    <li><strong>Special Power of Attorney</strong> &ndash; If using an authorized representative (notarized)</li>\r\n    <li><strong>Payment Documentation</strong> &ndash; For compromise penalties, surcharges, and deficiency interest payments</li>\r\n    <li><strong>Reply Letter to BIR</strong> &ndash; For system error cases, explaining that returns were filed with attached proof</li>\r\n</ul>\r\n<p><strong>Note:</strong> Resolution requires filing all missing returns and paying applicable penalties per Revenue Memorandum Order (RMO) 7-2015, as amended.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n    <li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n    <li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your open cases resolution progress.</li>\r\n    <li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your resolution.</li>\r\n    <li><strong>We Process Your Resolution</strong> &ndash; Our team secures the official list of open cases from your RDO, reviews your records to identify whether returns were truly unfiled or affected by system errors, and prepares all missing tax returns with accurate computations based on your financial records.</li>\r\n    <li><strong>Filing and Payment Processing</strong> &ndash; We file all delinquent tax returns electronically through eBIRForms or manually at your RDO, compute and settle compromise penalties, surcharges, and deficiency interest according to BIR regulations, and submit documentation proving previously filed returns if system error is the cause.</li>\r\n    <li><strong>Receive Confirmation and Clearance</strong> &ndash; Upon successful filing and payment of all open cases, we obtain confirmation from the BIR that your tax record has been updated and all cases are closed. We can also secure a Certificate of No Open Case for your records, which serves as proof of compliance and defense against future claims. All documentation will be made available through your client portal, ensuring your tax standing is clean and compliant.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n    <li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n    <li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with BIR requirements</li>\r\n    <li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n    <li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n    <li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/bir-open-cases-banner.jpg', 1, NULL, NULL, NULL, 0, 3, NULL, '2025-11-20 11:58:17', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(20, 'BIR Registration', 9500.00, 'bir-registration', 'Business Registration Services', 'Register your business with the Bureau of Internal Revenue and obtain your Tax Identification Number (TIN).', '<h2>What is BIR Registration?</h2>\r\n<p>BIR Registration is the process of obtaining a Tax Identification Number (TIN) and Certificate of Registration (COR) from the Bureau of Internal Revenue for your business. This mandatory registration enables your business to legally operate, file tax returns, issue official receipts and invoices, and comply with Philippine tax laws. All businesses must register with the BIR before commencing operations or within 30 days from the date of establishment, whichever comes first.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your BIR registration, please prepare the following:</p>\r\n<ul>\r\n    <li><strong>Certificate of Business Registration</strong> &ndash; DTI Certificate for sole proprietorships or SEC Certificate of Incorporation for corporations and partnerships</li>\r\n    <li><strong>Valid Government-issued ID</strong> &ndash; Original and photocopy of owner\'s or authorized representative\'s ID</li>\r\n    <li><strong>Proof of Business Address</strong> &ndash; Contract of Lease (if renting) or Transfer Certificate of Title/Tax Declaration (if owned property)</li>\r\n    <li><strong>BIR Form 1901</strong> &ndash; Application for Registration for sole proprietorships, self-employed individuals, estates, and trusts</li>\r\n    <li><strong>BIR Form 1903</strong> &ndash; Application for Registration for corporations, partnerships, and cooperatives</li>\r\n    <li><strong>Articles of Incorporation/Partnership</strong> &ndash; For corporations and partnerships (from SEC)</li>\r\n    <li><strong>Secretary\'s Certificate</strong> &ndash; For corporations, designating authorized signatories</li>\r\n    <li><strong>Special Power of Attorney (SPA)</strong> &ndash; If filing through an authorized representative (notarized)</li>\r\n    <li><strong>Books of Account</strong> &ndash; BIR Form 1905 (Application for Authority to Use Computerized Accounting System or Manual Books of Account)</li>\r\n    <li><strong>Documentary Stamp Tax (DST) Payment</strong> &ndash; Required for issuance of Certificate of Registration</li>\r\n</ul>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n    <li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n    <li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your registration progress.</li>\r\n    <li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your registration.</li>\r\n    <li><strong>We Process Your Application</strong> &ndash; Our team prepares and files BIR Form 1901 (for sole proprietorships) or Form 1903 (for corporations/partnerships) with the appropriate Revenue District Office (RDO), along with all supporting documents and requirements.</li>\r\n    <li><strong>Payment Processing</strong> &ndash; We process the required Documentary Stamp Tax payment and handle BIR Form 1906 (Application for Authority to Print receipts/invoices) on your behalf.</li>\r\n    <li><strong>Receive Your Certificate</strong> &ndash; Upon BIR approval, we collect your Certificate of Registration (COR/BIR Form 2303), TIN, and Authority to Print. These documents will be made available through your client portal and delivered to you.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n    <li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n    <li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with BIR requirements</li>\r\n    <li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n    <li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n    <li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/bir-banner.jpg', 1, NULL, NULL, NULL, 0, 4, NULL, '2025-11-20 12:44:11', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(21, 'Professional Bookkeeping Services', 5000.00, 'bookkeeping', 'Accounting & Tax Services', 'Maintain accurate financial records and streamline your business operations with expert bookkeeping support.', '<h2>What are Professional Bookkeeping Services?</h2>\r\n<p>Professional bookkeeping services involve the systematic recording, organizing, and management of your company\'s financial transactions and records on a daily basis. As the foundation of sound financial management, bookkeeping ensures that every income, expense, asset, and liability is accurately tracked and categorized. These services provide businesses with up-to-date financial information essential for informed decision-making, tax compliance, regulatory reporting, and strategic business planning. Unlike accounting, which focuses on analysis and interpretation, bookkeeping handles the routine administrative task of maintaining clean, organized financial records that serve as the backbone of your business\'s financial health.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your bookkeeping services, please prepare the following:</p>\r\n<ul>\r\n<li><strong>Bank Statements</strong> &ndash; Monthly statements from all business bank accounts for reconciliation and transaction recording</li>\r\n<li><strong>Sales Invoices and Receipts</strong> &ndash; All customer invoices, official receipts, sales records, and proof of income</li>\r\n<li><strong>Purchase Invoices and Bills</strong> &ndash; Supplier invoices, vendor bills, expense receipts, and proof of business expenditures</li>\r\n<li><strong>Payroll Records</strong> &ndash; Employee timesheets, salary computations, payroll reports, withholding tax records, and benefit contributions</li>\r\n<li><strong>Loan and Credit Documents</strong> &ndash; Loan agreements, credit card statements, payment schedules, and financing records</li>\r\n<li><strong>Fixed Asset Information</strong> &ndash; Purchase receipts, depreciation schedules, and details of property, equipment, and vehicles</li>\r\n<li><strong>Previous Financial Records</strong> &ndash; Prior year\'s books, ledgers, financial statements, and tax returns for continuity</li>\r\n<li><strong>Chart of Accounts</strong> &ndash; List of account categories used for recording transactions (we can create this if unavailable)</li>\r\n<li><strong>Business Permits and Registrations</strong> &ndash; BIR registration, DTI/SEC certificates, Mayor\'s Permit for compliance verification</li>\r\n<li><strong>Access to Accounting Software</strong> &ndash; Login credentials if you already use cloud-based systems like QuickBooks, Xero, or other platforms</li>\r\n</ul>\r\n<p><strong>Note:</strong> Documents can be submitted digitally through our secure portal. We work with both manual and computerized accounting systems.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your bookkeeping setup progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your service status, receive updates, and communicate with our team about your bookkeeping needs.</li>\r\n<li><strong>We Process Your Setup</strong> &ndash; Our professional bookkeepers review your business structure, set up or update your chart of accounts, configure your accounting software (cloud-based or manual), and establish proper categorization systems for accurate recording of all financial transactions.</li>\r\n<li><strong>Ongoing Transaction Recording</strong> &ndash; We systematically record all your business transactions including sales and income, purchases and expenses, accounts receivable and payable, bank deposits and withdrawals, cash transactions, payroll entries, and inventory movements. We perform monthly bank reconciliations to ensure accuracy between your books and actual bank balances.</li>\r\n<li><strong>Receive Regular Reports</strong> &ndash; Monthly or as agreed, we deliver comprehensive financial reports including General Ledger, Trial Balance, Income Statement (Profit &amp; Loss), Balance Sheet, Cash Flow Summary, and Accounts Receivable/Payable Aging Reports. All reports and organized records are made available through your client portal, giving you real-time visibility into your business finances and ensuring you\'re always prepared for tax filings and business decisions.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n<li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n<li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with accounting standards</li>\r\n<li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n<li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n<li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/bookkeeping-banner.jpg', 1, NULL, NULL, NULL, 0, 6, NULL, '2025-11-20 12:50:42', '2026-04-12 09:18:08', 5000.00, 5750.00, 6500.00, 7500.00, 8500.00, 1, 1, 1, 1, 0),
(23, 'Business Consultation Services', 3000.00, 'business-consultation', 'Advisory & Management Services', 'Get expert guidance and strategic advice to navigate business challenges, optimize operations, and achieve sustainable growth.', '<h2>What are Business Consultation Services?</h2>\r\n<p>Business Consultation Services provide expert professional advice and strategic guidance to help business owners and entrepreneurs navigate complex challenges, make informed decisions, and achieve their business objectives. Our consultation services cover a wide range of business needs including business structure selection and registration strategy, tax planning and optimization, financial management and cash flow improvement, compliance risk assessment and mitigation, business expansion and scaling strategies, operational efficiency and process improvement, and regulatory compliance guidance. Whether you\'re starting a new business, facing operational challenges, planning for growth, or seeking to optimize your current operations, our experienced consultants offer personalized insights, practical recommendations, and actionable solutions tailored to your specific industry, business size, and goals.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your business consultation services, please prepare the following:</p>\r\n<ul>\r\n<li><strong>Business Overview</strong> &ndash; Company background, history, ownership structure, industry sector, products/services offered, and target market description</li>\r\n<li><strong>Current Business Registrations</strong> &ndash; Existing DTI/SEC registration, BIR registration, Mayor\'s Permit, and other licenses/permits</li>\r\n<li><strong>Financial Information</strong> &ndash; Recent financial statements, tax returns, cash flow projections, profit and loss statements, and balance sheets for financial health assessment</li>\r\n<li><strong>Organizational Structure</strong> &ndash; Organizational chart, list of key personnel, roles and responsibilities, and management team information</li>\r\n<li><strong>Current Challenges or Objectives</strong> &ndash; Detailed description of specific issues, concerns, goals, growth plans, or areas requiring expert guidance</li>\r\n<li><strong>Operational Documents</strong> &ndash; Business processes, workflows, contracts, agreements, policies, and standard operating procedures</li>\r\n<li><strong>Market and Competitive Analysis</strong> &ndash; Information about competitors, market position, customer base, and industry trends affecting your business</li>\r\n<li><strong>Future Plans and Vision</strong> &ndash; Short-term and long-term business goals, expansion plans, new product/service launches, or strategic initiatives</li>\r\n<li><strong>Compliance Records</strong> &ndash; Recent tax filings, audit reports, compliance assessments, and any pending regulatory issues or concerns</li>\r\n<li><strong>Questions and Priorities</strong> &ndash; Specific questions you want answered and priority areas requiring immediate attention or expert input</li>\r\n</ul>\r\n<p><strong>Note:</strong> Consultation sessions can be scheduled based on your availability and urgency of needs. Initial consultations assess your situation and recommend tailored solutions.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your consultation setup progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your consultation status, receive updates, and communicate with our team about your business needs.</li>\r\n<li><strong>We Process Your Initial Assessment</strong> &ndash; Our experienced business consultants conduct a thorough review of your submitted information, analyze your business situation and current challenges, identify opportunities for improvement and growth, research industry-specific best practices and regulatory requirements, and prepare a preliminary assessment with key findings and recommendations for discussion.</li>\r\n<li><strong>Consultation Session and Strategy Development</strong> &ndash; We schedule a dedicated consultation session (in-person, video call, or phone) to discuss our assessment findings in detail, explore your specific concerns and objectives, answer your questions and provide expert insights, collaborate on developing customized solutions and action plans, and provide guidance on implementation steps, timelines, and resource requirements.</li>\r\n<li><strong>Receive Comprehensive Consultation Report</strong> &ndash; Following the consultation session, we deliver a detailed consultation report including executive summary of key findings, analysis of current business situation and challenges, recommended strategies and actionable solutions, implementation roadmap with priorities and timelines, compliance checklist and regulatory guidance, and follow-up support options for ongoing assistance. All documents and consultation materials are made available through your client portal, ensuring you have a clear action plan and expert guidance to move your business forward with confidence.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n<li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n<li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with all requirements</li>\r\n<li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n<li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n<li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/business-consultation-banner.jpg', 1, NULL, NULL, NULL, 0, 7, NULL, '2025-11-20 12:52:07', '2026-04-12 09:51:56', 3000.00, 3450.00, 3900.00, 4500.00, 5100.00, 1, 1, 1, 1, 0),
(24, 'Business Closure & Deregistration', 28500.00, 'closure', 'Business Processing Services', 'Properly close your business and complete all deregistration requirements to avoid future liabilities and penalties.', '<h2>What is Business Closure?</h2>\r\n<p>Business closure and deregistration is the legal process of formally terminating business operations and canceling all government registrations in the Philippines. This comprehensive process involves settling all tax obligations, canceling permits and licenses with BIR, SEC/DTI, and local government units (LGUs), and ensuring complete compliance to avoid future liabilities, penalties, or \"open cases\" with government agencies. Proper closure protects business owners from ongoing tax assessments and legal complications.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your business closure and deregistration, please prepare the following:</p>\r\n<ul>\r\n<li><strong>Board Resolution or Owner\'s Decision</strong> &ndash; For corporations/partnerships: Board Resolution approving closure; For sole proprietorships: Written notice of business closure decision</li>\r\n<li><strong>BIR Form 1905</strong> &ndash; Application for Registration Update/Cancellation/Amendment (2 originals)</li>\r\n<li><strong>List of Ending Inventory</strong> &ndash; Complete inventory of goods, supplies, and capital goods at closure date</li>\r\n<li><strong>Inventory of Unused Receipts/Invoices</strong> &ndash; Detailed list of all unused official receipts, sales invoices, and accounting forms</li>\r\n<li><strong>Unused Business Forms</strong> &ndash; All unused sales invoices, official receipts, vouchers, debit/credit memos, delivery receipts, purchase orders, and other accounting forms (for destruction witnessed by BIR)</li>\r\n<li><strong>BIR Certificate of Registration (Form 2303)</strong> &ndash; Original COR for cancellation and destruction</li>\r\n<li><strong>Final Tax Returns</strong> &ndash; All pending tax returns filed up to date of closure</li>\r\n<li><strong>Proof of Tax Payments</strong> &ndash; Evidence of settlement of all outstanding tax liabilities</li>\r\n<li><strong>Mayor\'s Permit</strong> &ndash; Current business permit for cancellation</li>\r\n<li><strong>Barangay Clearance</strong> &ndash; For local government closure requirements</li>\r\n</ul>\r\n<p><strong>For Corporations:</strong> SEC Certificate of Filing of Articles of Dissolution and additional SEC requirements for formal dissolution.</p>\r\n<p><strong>If Using a Representative:</strong> Special Power of Attorney (notarized) and valid government-issued IDs of both taxpayer and authorized representative; For corporations: Board Resolution or Secretary\'s Certificate designating representative.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your closure process progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your closure.</li>\r\n<li><strong>We Process Your Closure</strong> &ndash; Our team conducts a comprehensive review of your business obligations, files final tax returns, settles outstanding liabilities, and coordinates with BIR for tax audit and clearance. We handle document destruction procedures and secure your Tax Clearance Certificate.</li>\r\n<li><strong>Multi-Agency Coordination</strong> &ndash; After BIR clearance, we process cancellations with DTI (for sole proprietorships) or SEC (for corporations), cancel your Mayor\'s Permit and Barangay Clearance with the LGU, and ensure all government registrations are properly closed.</li>\r\n<li><strong>Receive Closure Documentation</strong> &ndash; Upon completion of all deregistration processes, we provide you with your Tax Clearance Certificate, SEC Certificate of Dissolution (if applicable), and all closure confirmation documents through your client portal, ensuring your business is fully and legally closed.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n<li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n<li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with closure requirements</li>\r\n<li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n<li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n<li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/closure-banner.jpg', 1, NULL, NULL, NULL, 0, 8, NULL, '2025-11-20 12:53:34', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(25, 'Tax Advisory Services', 0.00, 'tax-advisory-services', 'Accounting & Tax Services', 'Receive expert tax guidance to minimize liabilities, maximize savings, and ensure full compliance with Philippine tax laws.', '<h2>What are Tax Advisory Services?</h2>\r\n<p>Tax Advisory Services provide professional expert guidance on tax planning, compliance, and optimization strategies to help individuals and businesses minimize tax liabilities while maintaining full compliance with Philippine tax laws and BIR regulations. Our tax advisors offer strategic counsel on complex tax matters including tax-efficient business structure selection, timing of income recognition and expense deductions, maximization of available tax incentives and exemptions, estate and succession planning for tax efficiency, mergers, acquisitions, and corporate restructuring tax implications, international taxation and transfer pricing issues, and BIR audit defense and dispute resolution. Through proactive tax planning and strategic advice, we help clients make informed financial decisions that optimize their tax position, reduce unnecessary tax burdens, and avoid costly compliance errors or penalties.</p>\r\n\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>BIR Registration Documents</strong> – Certificate of Registration (Form 2303), registered tax types, and current compliance status</li>\r\n<li><strong>Recent Tax Returns</strong> – Previous year\'s income tax returns, quarterly tax filings, VAT/percentage tax returns, and withholding tax returns for historical analysis</li>\r\n<li><strong>Financial Statements</strong> – Current and prior years\' income statements, balance sheets, cash flow statements, and supporting schedules</li>\r\n<li><strong>Business Structure Information</strong> – Organizational chart, ownership structure, related party relationships, and corporate governance documents</li>\r\n<li><strong>Specific Tax Concerns or Opportunities</strong> – Detailed description of tax issues requiring advice, planned transactions, expansion plans, or tax-saving opportunities you want to explore</li>\r\n<li><strong>Contracts and Agreements</strong> – Material contracts, supplier/customer agreements, loan documents, lease agreements affecting tax treatment</li>\r\n<li><strong>Asset and Investment Records</strong> – Real property holdings, investment portfolios, depreciation schedules, and capital asset details</li>\r\n<li><strong>BIR Correspondence</strong> – Any letters, notices, assessments, or communications from the Bureau of Internal Revenue requiring response or clarification</li>\r\n<li><strong>Industry and Transaction Context</strong> – Information about your industry sector, planned business transactions, expansion into new markets, or significant operational changes</li>\r\n<li><strong>Tax Objectives and Constraints</strong> – Short-term and long-term tax planning goals, risk tolerance, liquidity needs, and business priorities</li>\r\n</ul>\r\n\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> – Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your tax advisory engagement progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> – Scan and save the QR code to your device. You can use this code to check your advisory status, receive updates, and communicate with our team about your tax matters.</li>\r\n<li><strong>We Process Your Tax Analysis</strong> – Our tax experts conduct a comprehensive review of your current tax situation, analyze your financial statements and tax returns for optimization opportunities, research applicable tax laws, regulations, and recent BIR issuances affecting your situation, identify potential tax risks and compliance gaps, evaluate tax-saving strategies aligned with your business objectives, and prepare detailed findings and preliminary recommendations for discussion.</li>\r\n<li><strong>Advisory Session and Strategy Development</strong> – We schedule a dedicated tax advisory consultation to present our analysis and findings, discuss tax planning opportunities and risk mitigation strategies, address your specific questions and concerns, collaborate on developing customized tax strategies, and provide guidance on implementation steps, documentation requirements, and compliance considerations to ensure successful execution.</li>\r\n<li><strong>Receive Comprehensive Tax Advisory Report</strong> – Following the advisory session, we deliver a detailed tax advisory memorandum including executive summary of key findings and recommendations, analysis of current tax position and potential exposures, proposed tax planning strategies with projected tax impact, implementation action plan with timelines and responsible parties, compliance calendar and regulatory monitoring plan, and ongoing support options for continued tax advisory assistance. All advisory documents and communications are securely stored in your client portal, providing you with a complete reference for tax planning decisions and compliance documentation.</li>\r\n</ol>', 'assets/img/icons/tax-advisory-icon.png', 'assets/img/services-img/tax-advisory-banner.jpg', 1, NULL, NULL, NULL, 0, 5, NULL, '2025-11-20 12:58:14', '2025-11-20 12:58:14', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0);
INSERT INTO `services` (`id`, `name`, `price`, `slug`, `category`, `short_description`, `long_description`, `icon`, `image`, `is_active`, `scheduled_action`, `scheduled_at`, `scheduled_effective_at`, `featured`, `display_order`, `created_by`, `created_at`, `updated_at`, `standard_price`, `priority_price`, `express_price`, `rush_price`, `same_day_price`, `standard_status`, `priority_status`, `express_status`, `rush_status`, `same_day_status`) VALUES
(26, 'Mayor\'s Permit', 10000.00, 'mayors-permit', 'Business Registration Services', 'Secure your Mayor\'s Permit or Business Permit to legally operate your business within your city or municipality.', '<h2>What is a Mayor\'s Permit?</h2>\r\n<p>A Mayor\'s Permit, also known as a Business Permit or BPLO Permit, is a local government-issued license that authorizes your business to operate within a specific city or municipal jurisdiction in the Philippines. Issued by the Business Permits and Licensing Office (BPLO), this permit confirms payment of local business taxes and ensures compliance with health, safety, sanitation, environmental regulations, and zoning laws. It is mandatory for all businesses and must be renewed annually, typically by January 20 each year.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your Mayor\'s Permit application, please prepare the following:</p>\r\n<ul>\r\n    <li><strong>Certificate of Business Registration</strong> &ndash; DTI Certificate for sole proprietorships, SEC Certificate for corporations/partnerships, or CDA Certificate for cooperatives</li>\r\n    <li><strong>Barangay Business Clearance</strong> &ndash; Clearance from the barangay where your business is located</li>\r\n    <li><strong>Community Tax Certificate (CTC or Cedula)</strong> &ndash; Issued by the local government unit</li>\r\n    <li><strong>Valid Government-issued ID</strong> &ndash; For business owner and authorized representatives</li>\r\n    <li><strong>Proof of Business Location</strong> &ndash; Contract of Lease (if renting) or Transfer Certificate of Title/Tax Declaration (if owned property)</li>\r\n    <li><strong>Location Sketch and Photos</strong> &ndash; Sketch of business premises and photos of establishment (inside and outside), typically 3 copies</li>\r\n    <li><strong>Zoning/Locational Clearance</strong> &ndash; Confirms business complies with local zoning ordinances</li>\r\n    <li><strong>Fire Safety Inspection Certificate (FSIC)</strong> &ndash; Issued by the Bureau of Fire Protection</li>\r\n    <li><strong>Sanitary Permit</strong> &ndash; Required for food-related businesses, issued by the City Health Office</li>\r\n    <li><strong>Certificate of Occupancy</strong> &ndash; For the building and unit where business operates</li>\r\n</ul>\r\n<p><strong>For Specific Business Types:</strong> Additional permits may be required (e.g., Public Liability Insurance for restaurants, cinemas, malls; Environmental Compliance Certificate for certain industries; DOH permits for health-related businesses).</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n    <li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n    <li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your registration progress.</li>\r\n    <li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your registration.</li>\r\n    <li><strong>We Process Your Application</strong> &ndash; Our team secures all prerequisite clearances (Barangay Clearance, Fire Safety, Sanitary Permit, Zoning Clearance) and prepares your complete application package for submission to the BPLO.</li>\r\n    <li><strong>Payment Processing</strong> &ndash; Once the BPLO approves your documents and assesses the fees, we process the required local taxes and permit fees on your behalf to ensure timely completion.</li>\r\n    <li><strong>Receive Your Permit</strong> &ndash; Upon approval and payment, we collect your Mayor\'s Permit from the BPLO and deliver it to you through your client portal. The permit is valid for one year and must be displayed prominently at your business location.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n    <li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n    <li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with local government requirements</li>\r\n    <li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n    <li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n    <li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business permitted quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/mayor-banner.jpg', 1, NULL, NULL, NULL, 0, 10, NULL, '2025-11-20 12:58:46', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(27, 'SEC Registration', 19750.00, 'sec-registration', 'Business Registration Services', 'Register your corporation, partnership, or one-person company and make it legally recognized by the SEC.', '<h2>What is SEC Registration?</h2>\r\n<p>The Securities and Exchange Commission (SEC) registration is the legal process of incorporating your business as a corporation, partnership, or One Person Corporation (OPC) in the Philippines. Through the SEC\'s Electronic Simplified Processing Application for Registration of Company (eSPARC) system, your business entity becomes legally recognized, allowing you to operate, enter contracts, own property, and enjoy corporate rights and protections under Philippine law.</p>\r\n\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>Articles of Incorporation (AOI)</strong> – Includes corporate name, principal office address, term of existence, names and addresses of incorporators, capital stock details, number of shares, par value, and subscription information</li>\r\n<li><strong>By-laws</strong> – Outlines internal rules, structure, and management of the corporation (not required for One Person Corporations)</li>\r\n<li><strong>Company Name Verification Slip</strong> – Proof of name reservation from SEC eSPARC</li>\r\n<li><strong>Treasurer\'s Affidavit</strong> – Affirms that required capital stock has been subscribed and paid</li>\r\n<li><strong>Bank Certificate of Deposit</strong> – Shows subscribed capital stock deposited in bank under corporation\'s name (if applicable)</li>\r\n<li><strong>Valid Government-issued IDs</strong> – For all incorporators, directors, and officers</li>\r\n<li><strong>SEC Cover Sheet</strong> – Standard form with basic company information</li>\r\n</ul>\r\n<p><strong>For One Person Corporations (OPC):</strong> Nominee and Alternate Nominee Statement is required to designate who will manage the corporation in case of the single stockholder\'s death or incapacity.</p>\r\n<p><strong>For Regulated Industries:</strong> Additional permits or endorsements may be required (e.g., from Bangko Sentral ng Pilipinas for financial institutions, Insurance Commission for insurance companies).</p>\r\n\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> – Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your registration progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> – Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your registration.</li>\r\n<li><strong>We Process Your Application</strong> – Our team prepares your Articles of Incorporation, By-laws, and all required documents. We verify your company name through SEC eSPARC and handle the complete registration process on your behalf.</li>\r\n<li><strong>Payment Processing</strong> – Once SEC issues the Payment Assessment Form (PAF), we process the registration fees within the required timeframe to ensure your application proceeds without delay.</li>\r\n<li><strong>Receive Your Certificate</strong> – Upon SEC approval and payment completion, we download your Certificate of Incorporation and notify you via email. The certificate will be made available through your client portal.</li>\r\n</ol>', 'assets/img/icons/sec-icon.png', 'assets/img/services-img/sec-banner.jpg', 1, NULL, NULL, NULL, 0, 8, NULL, '2025-11-20 12:59:17', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(28, 'Payroll Management Services', 4500.00, 'payroll-management', 'Advisory & Management Services', 'Simplify employee compensation with accurate payroll processing, statutory compliance, and timely remittances.', '<h2>What are Payroll Management Services?</h2>\r\n<p>Payroll Management Services provide comprehensive professional handling of all employee compensation processes, from salary computation to government remittances, ensuring accurate and timely payment of wages while maintaining full compliance with Philippine labor laws and tax regulations. Our payroll services cover salary and wage calculations including overtime, night differential, holiday pay, and other benefits, mandatory deductions for SSS, PhilHealth, Pag-IBIG contributions, and withholding taxes, computation and filing of monthly and quarterly withholding tax returns to BIR, timely remittance of statutory contributions to SSS, PhilHealth, and Pag-IBIG, preparation of payslips and payroll registers, year-end processing including 13th month pay computation and annual alphalist submission, and employee recordkeeping and payroll reporting. Professional payroll management eliminates calculation errors, ensures compliance with changing regulations, reduces administrative burden, protects against penalties and audits, and allows business owners to focus on core operations while employees receive accurate and timely compensation.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<p>To process your payroll management services, please prepare the following:</p>\r\n<ul>\r\n<li><strong>Employee Master List</strong> &ndash; Complete roster with names, positions, employment status (regular, probationary, contractual), hire dates, and contact information</li>\r\n<li><strong>Compensation Details</strong> &ndash; Basic salary/wage rates, allowances, commissions, bonuses, and other regular compensation components for each employee</li>\r\n<li><strong>Government Registration Numbers</strong> &ndash; SSS, PhilHealth, Pag-IBIG, and TIN (Tax Identification Numbers) for all employees</li>\r\n<li><strong>Attendance and Time Records</strong> &ndash; Daily time records (DTR), timesheets, overtime hours, absences, leaves, and tardiness for payroll period</li>\r\n<li><strong>Company Tax and Statutory Registrations</strong> &ndash; BIR registration for withholding tax, SSS employer number, PhilHealth employer number, Pag-IBIG employer number</li>\r\n<li><strong>Payroll Policies</strong> &ndash; Company policies on overtime computation, night differential, holiday pay, leave conversions, deductions, and other payroll-related rules</li>\r\n<li><strong>Previous Payroll Records</strong> &ndash; Prior payroll registers, government remittances, and withholding tax returns for continuity and reconciliation</li>\r\n<li><strong>Employee Changes and Updates</strong> &ndash; New hires, resignations, promotions, salary adjustments, status changes, and tax exemption updates</li>\r\n<li><strong>Loan and Deduction Schedules</strong> &ndash; SSS/Pag-IBIG loan deductions, cash advances, uniform deductions, or other authorized payroll deductions</li>\r\n<li><strong>Bank Account Information</strong> &ndash; For direct deposit/bank transfer: employee bank account details and company disbursement account</li>\r\n</ul>\r\n<p><strong>Note:</strong> Accurate and timely submission of attendance and employee information ensures error-free payroll processing.</p>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your payroll setup progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your payroll status, receive updates, and communicate with our team about your payroll needs.</li>\r\n<li><strong>We Process Your Payroll Setup</strong> &ndash; Our payroll specialists create and configure your employee master file with all compensation details and government registrations, set up payroll computation templates aligned with your company policies and labor law requirements, establish deduction schedules for statutory contributions and other authorized deductions, and configure your payroll calendar based on your pay frequency (weekly, bi-monthly, or monthly).</li>\r\n<li><strong>Ongoing Payroll Processing</strong> &ndash; Each payroll period, we collect and verify employee attendance and time records, compute gross pay including basic salary, overtime, night differential, holiday pay, and allowances, calculate mandatory deductions for SSS, PhilHealth, Pag-IBIG, and withholding tax, process other authorized deductions (loans, advances, etc.), generate net pay and prepare payslips for each employee, create payroll registers and management reports, and prepare payment instructions for bank transfers or cash disbursement.</li>\r\n<li><strong>Receive Payroll and Compliance Deliverables</strong> &ndash; After each payroll run, we provide you with individual employee payslips (digital or printed), comprehensive payroll register showing all compensation and deductions, government remittance forms and schedules (SSS, PhilHealth, Pag-IBIG contribution lists), monthly withholding tax returns (BIR Form 1601C) filed on your behalf, bank payment files or disbursement vouchers for salary release, and quarterly and year-end compliance reports including alphalist and annual information returns. All payroll records, reports, and compliance documents are organized and stored in your client portal, ensuring complete audit trail, employee access to payslips, and full regulatory compliance.</li>\r\n</ol>\r\n<h2>Why Choose JRN Business Solutions?</h2>\r\n<p>With <strong>JRN Business Solutions Co.</strong>, you benefit from our comprehensive digital approach to business registration:</p>\r\n<ul>\r\n<li><strong>Streamlined Process</strong> &ndash; Our e-Process system eliminates paperwork and manual tracking</li>\r\n<li><strong>Accuracy Guaranteed</strong> &ndash; We verify all documents and ensure compliance with payroll regulations</li>\r\n<li><strong>Transparency</strong> &ndash; Track your application in real-time using your unique QR code</li>\r\n<li><strong>Professional Support</strong> &ndash; Our team guides you through every step of the registration process</li>\r\n<li><strong>Fast Turnaround</strong> &ndash; We prioritize efficient processing to get your business registered quickly</li>\r\n</ul>\r\n<p><strong>Focus on growing your business&mdash;let us handle the registration.</strong></p>', '', 'assets/img/services-img/payroll-banner.jpg', 1, NULL, NULL, NULL, 0, 11, NULL, '2025-11-20 13:00:18', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(29, 'Retainership Services', 12000.00, 'retainership', 'Advisory & Management Services', 'Enjoy comprehensive, ongoing business support with dedicated accounting, compliance, and advisory services on a fixed monthly basis.', '<h2>What are Retainership Services?</h2>\r\n<p>Retainership Services provide businesses with continuous, comprehensive accounting, tax compliance, and business advisory support through a fixed monthly arrangement. Unlike project-based or one-time services, a retainer agreement ensures you have dedicated professionals managing your financial operations, regulatory compliance, and strategic business needs consistently throughout the year. This ongoing relationship allows for deeper understanding of your business, proactive issue prevention, consistent quality, priority support, and cost predictability. With a retainer, you gain a trusted financial partner invested in your long-term success, ensuring your business remains compliant, financially healthy, and positioned for sustainable growth.</p>\r\n\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>Business Registration Documents</strong> – DTI Certificate, SEC Certificate of Incorporation, BIR Certificate of Registration, Mayor\'s Permit, and all active business permits</li>\r\n<li><strong>Financial Records Access</strong> – Bank account details, accounting software credentials, previous financial statements, and tax returns for baseline assessment</li>\r\n<li><strong>Organizational Structure</strong> – List of officers, directors, stockholders, key personnel, and their roles for comprehensive service delivery</li>\r\n<li><strong>Service Scope Agreement</strong> – Detailed discussion of required services including bookkeeping frequency, tax filing schedules, compliance calendar, and reporting preferences</li>\r\n<li><strong>Payroll Information</strong> – Employee list, compensation details, SSS/PhilHealth/Pag-IBIG numbers, and existing payroll records if applicable</li>\r\n<li><strong>Current Challenges and Goals</strong> – Documented business objectives, growth plans, compliance concerns, and specific areas requiring focus</li>\r\n<li><strong>Communication Preferences</strong> – Designated point persons, preferred communication channels, meeting schedules, and escalation protocols</li>\r\n<li><strong>Special Requirements</strong> – Industry-specific compliance needs, investor reporting obligations, audit preparation requirements, or multi-entity management</li>\r\n</ul>\r\n\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> – Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your retainership setup progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> – Scan and save the QR code to your device. You can use this code to check your service status, receive updates, and communicate with our team about your retainership needs.</li>\r\n<li><strong>We Process Your Onboarding</strong> – Our team conducts a comprehensive business assessment, reviews your current financial processes and compliance status, identifies gaps and improvement opportunities, and designs a customized service package tailored to your specific business needs, industry requirements, and growth objectives.</li>\r\n<li><strong>Ongoing Service Delivery</strong> – We provide continuous monthly services including bookkeeping and financial record maintenance, preparation and filing of all required tax returns (monthly, quarterly, annual), payroll processing and remittance of statutory contributions, business permit renewals and compliance monitoring, financial reporting and management advisory, and responsive support for ad-hoc business needs and inquiries.</li>\r\n<li><strong>Receive Regular Updates and Reports</strong> – Throughout our retainer relationship, you receive scheduled monthly financial statements and management reports, compliance calendars and deadline reminders, tax planning advice and strategic recommendations, quarterly business review meetings, and priority access to our team for consultations. All documents, reports, and communications are accessible through your dedicated client portal, ensuring seamless collaboration and complete transparency in managing your business\'s financial health.</li>\r\n</ol>', 'assets/img/icons/retainership-icon.png', 'assets/img/services-img/retainership-banner.jpg', 1, NULL, NULL, NULL, 0, 9, NULL, '2025-11-20 13:00:38', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(30, 'Business Permit Renewal', 7349.00, 'renewal', 'Business Processing Services', 'Renew your business permits annually to maintain compliance and keep your business operating legally.', '<h2>What is Business Permit Renewal?</h2>\r\n<p>Business Permit Renewal is the mandatory annual process of updating and renewing your business permits and registrations with local and national government agencies in the Philippines. All businesses, regardless of size or structure, must renew their Barangay Clearance, Mayor\'s Permit (Business Permit), and BIR Annual Registration Fee each year to maintain legal operating status, ensure tax compliance, and avoid penalties, fines, or business closure. The standard renewal deadline is January 20 for local permits and January 31 for BIR registration.</p>\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>Previous Year\'s Business Permit</strong> &ndash; Original and photocopy of last year\'s Mayor\'s Permit and official receipt</li>\r\n<li><strong>Barangay Clearance</strong> &ndash; Updated clearance from the barangay where your business is located (must be renewed first)</li>\r\n<li><strong>Community Tax Certificate (Cedula)</strong> &ndash; Current year\'s CTC for the business owner or authorized representative</li>\r\n<li><strong>Income Tax Return (ITR) or Audited Financial Statements</strong> &ndash; Latest annual, quarterly, or monthly ITR; corporations require audited financial statements</li>\r\n<li><strong>Certificate of Gross Sales or Receipts</strong> &ndash; Declaration of gross sales/receipts for the previous year</li>\r\n<li><strong>DTI or SEC Registration</strong> &ndash; Valid DTI Certificate for sole proprietorships or SEC Certificate for corporations/partnerships (renew if expired)</li>\r\n<li><strong>BIR Certificate of Registration (Form 2303)</strong> &ndash; Proof of BIR registration and tax compliance</li>\r\n<li><strong>Proof of Business Location</strong> &ndash; Contract of Lease (if renting) or Transfer Certificate of Title (if owned property)</li>\r\n<li><strong>Fire Safety Inspection Certificate (FSIC)</strong> &ndash; Updated certificate from Bureau of Fire Protection</li>\r\n<li><strong>Sanitary Permit</strong> &ndash; For food-related businesses, renewed annually from City Health Office</li>\r\n<li><strong>SSS, PhilHealth, Pag-IBIG Contributions</strong> &ndash; Proof of remittances for employees for the previous year</li>\r\n<li><strong>Employee Information</strong> &ndash; Number of employees at the time of renewal and their registration documents</li>\r\n</ul>\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> &ndash; Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> &ndash; Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your renewal progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> &ndash; Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your renewal.</li>\r\n<li><strong>We Process Your Renewal</strong> &ndash; Our team secures your updated Barangay Clearance, prepares all required documents, verifies compliance with all regulatory requirements, and handles submissions to the Business Permits and Licensing Office (BPLO) on your behalf.</li>\r\n<li><strong>Payment Processing</strong> &ndash; We process all renewal fees including local business taxes, Mayor\'s Permit fees, regulatory charges, and BIR Annual Registration Fee within the required deadlines to avoid penalties.</li>\r\n<li><strong>Receive Your Renewed Permits</strong> &ndash; Upon approval by the local government and BIR, we collect your renewed Mayor\'s Permit, Barangay Clearance, and BIR Certificate of Registration. All documents will be made available through your client portal and delivered to you, ensuring your business remains compliant for the coming year.</li>\r\n</ol>', 'assets/img/icons/renewal-icon.png', 'assets/img/services-img/renewal-banner.jpg', 1, NULL, NULL, NULL, 0, 10, NULL, '2025-11-20 13:01:32', '2026-04-12 09:14:51', 7349.00, 8451.35, 9553.70, 11023.50, 12493.30, 1, 1, 1, 1, 0),
(31, 'DTI Business Name Registration', 4000.00, 'dti-registration', 'Business Processing Services', 'Register your sole proprietorship business name to legally operate in the Philippines.', '<h2>What is DTI Business Name Registration?</h2>\r\n<p>The Department of Trade and Industry (DTI) Business Name Registration is a mandatory requirement for all sole proprietorships in the Philippines. Through the Business Name Registration System (BNRS), you can legally register your business name, ensuring it is unique, protected, and compliant with government regulations. This certificate is essential for obtaining other permits such as Mayor\'s Permit and BIR registration.</p>\r\n\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>Valid Government-issued ID</strong> (Passport, Driver\'s License, UMID, National ID, PRC ID, Postal ID, Voter\'s ID, etc.)</li>\r\n<li><strong>Personal Information</strong> – Full name, birthdate, citizenship, civil status, complete address, contact number, email</li>\r\n<li><strong>Tax Identification Number (TIN)</strong></li>\r\n<li><strong>Complete Business Address</strong> (location where the business will operate)</li>\r\n<li><strong>Business Name Options</strong> – At least 3-5 preferred names with:\r\n    <ul>\r\n        <li><em>Dominant Name</em> (main/unique identifier)</li>\r\n        <li><em>Descriptor</em> (aligned with Philippine Standard Industrial Classification)</li>\r\n    </ul>\r\n</li>\r\n</ul>\r\n<p><strong>For Foreign Nationals:</strong> Alien Certificate of Registration (ACR) and authority/eligibility documents must be presented at a DTI office before proceeding with online registration.</p>\r\n<p><strong>For Recognized Refugees/Stateless Persons:</strong> Department of Justice (DOJ) recognition certificate must be presented at DTI before online processing.</p>\r\n\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents and information through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> – Once your submission is complete, the system will automatically generate a unique QR code for your application. This QR code serves as your reference for tracking and monitoring your registration progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> – Scan and save the QR code to your device. You can use this code to check your application status, receive updates, and communicate with our team about your registration.</li>\r\n<li><strong>We Process Your Application</strong> – Our team reviews your documents, verifies business name availability through DTI BNRS, and handles the complete registration process on your behalf.</li>\r\n<li><strong>Payment Processing</strong> – We process the registration fee (based on territorial scope) plus ₱30 DST within the required timeframe to ensure your application proceeds without delay.</li>\r\n<li><strong>Receive Your Certificate</strong> – Upon DTI approval, we download your Certificate of Business Name Registration (CBNR) and notify you via email. The certificate is valid for 5 years and will be made available through your client portal.</li>\r\n</ol>', 'assets/img/icons/dti-icon.png', 'assets/img/services-img/dti-banner.jpg', 1, NULL, NULL, NULL, 0, 1, NULL, '2025-11-20 13:05:50', '2026-04-11 17:34:09', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0),
(32, 'BIR Tax Filing Services', 3500.00, 'bir-tax-filing', 'Accounting & Tax Services', 'Stay compliant with accurate and timely filing of all required tax returns to the Bureau of Internal Revenue.', '<h2>What are BIR Tax Filing Services?</h2>\r\n<p>BIR Tax Filing Services involve the professional preparation, review, and submission of all required tax returns and compliance reports to the Bureau of Internal Revenue on behalf of your business. These services ensure accurate computation of tax liabilities, timely filing within statutory deadlines, proper documentation and record-keeping, and full compliance with Philippine tax laws and regulations. Tax filing requirements vary based on business structure, registration type, and tax obligations, covering monthly, quarterly, and annual returns including income tax, value-added tax (VAT) or percentage tax, withholding taxes, and documentary stamp tax. Professional tax filing services protect your business from penalties, surcharges, interest, and potential audits while ensuring you claim all eligible deductions and comply with evolving tax regulations.</p>\r\n\r\n<h2>Required Information &amp; Documents</h2>\r\n<ul>\r\n<li><strong>BIR Certificate of Registration (Form 2303)</strong> – Shows registered tax types, filing frequency, and Revenue District Office (RDO) assignment</li>\r\n<li><strong>Financial Records</strong> – Sales invoices, official receipts, purchase invoices, expense receipts, bank statements, and general ledger for the filing period</li>\r\n<li><strong>Income Documentation</strong> – Summary of gross sales/receipts, service income, interest income, rental income, and other revenue sources</li>\r\n<li><strong>Expense Records</strong> – Detailed expense reports with supporting receipts for cost of goods sold, operating expenses, salaries, rent, utilities, and business-related costs</li>\r\n<li><strong>Withholding Tax Documents</strong> – BIR Form 2307, expanded withholding tax certificates, and summary lists</li>\r\n<li><strong>Payroll Records</strong> – Employee compensation details, alphalists, government remittances (SSS, PhilHealth, Pag-IBIG), and previous withholding tax returns</li>\r\n<li><strong>VAT Input and Output Records</strong> – For VAT-registered businesses: purchase journals, sales journals, VAT input tax on purchases, VAT output tax on sales</li>\r\n<li><strong>Previous Tax Returns</strong> – Prior period returns for continuity and carryover computations</li>\r\n<li><strong>Asset and Liability Schedules</strong> – Depreciation schedules, loan amortization tables, inventory records</li>\r\n<li><strong>BIR Forms Previously Filed</strong> – Copies with BIR receiving stamps or electronic filing confirmation</li>\r\n</ul>\r\n<p><strong>Common Tax Returns We File:</strong> Income Tax (1701/1702), VAT (2550M/Q), Percentage Tax (2551M/Q), Withholding Tax (0619E/F, 1601C/EQ, 1604CF/E), Annual Information Returns (1604E, 1604F), Documentary Stamp Tax (2000).</p>\r\n\r\n<h2>How We Process Your Request</h2>\r\n<ol>\r\n<li><strong>Submit Your Requirements Online</strong> – Log in to your account and upload all required documents through our secure e-Process portal.</li>\r\n<li><strong>Receive Your Unique QR Code</strong> – System generates a unique QR code for tracking and monitoring your tax filing progress.</li>\r\n<li><strong>Save and Use Your QR Code</strong> – Check filing status, receive updates, and communicate with our team about compliance.</li>\r\n<li><strong>We Process Your Tax Returns</strong> – Review financial records, compute taxes, prepare forms with schedules and annexes.</li>\r\n<li><strong>Filing and Payment Coordination</strong> – File returns electronically or manually, generate payment forms, coordinate with banks, secure official receipts.</li>\r\n<li><strong>Receive Compliance Documentation</strong> – Copies of filed returns, payment confirmations, compliance calendar, summary reports, advisory notes, all accessible in your client portal.</li>\r\n</ol>', 'assets/img/icons/bir-icon.png', 'assets/img/services-img/bir-banner.jpg', 1, NULL, NULL, NULL, 0, 4, NULL, '2025-11-20 13:06:28', '2026-04-11 15:03:48', 0.00, 0.00, 0.00, 0.00, 0.00, 1, 1, 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `service_requirements`
--

CREATE TABLE `service_requirements` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `requirement_text` varchar(255) NOT NULL,
  `requires_id_type` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_requirements`
--

INSERT INTO `service_requirements` (`id`, `service_id`, `requirement_text`, `requires_id_type`, `sort_order`, `is_required`, `created_at`) VALUES
(1, 31, 'Valid government-issued ID', 1, 1, 1, '2026-04-11 17:37:19'),
(2, 31, 'Proposed business name (3 options)', 0, 2, 1, '2026-04-11 17:37:19'),
(3, 31, 'Barangay clearance (if applicable)', 0, 3, 1, '2026-04-11 17:37:19'),
(4, 31, 'Payment for registration fee', 0, 4, 1, '2026-04-11 17:37:19'),
(5, 27, 'Articles of Incorporation/Partnership', 0, 1, 1, '2026-04-11 17:37:19'),
(6, 27, 'Bylaws (for corporations)', 0, 2, 1, '2026-04-11 17:37:19'),
(7, 27, 'Treasurer\'s Affidavit', 1, 3, 1, '2026-04-11 17:37:19'),
(8, 27, 'Proof of Address', 0, 4, 1, '2026-04-11 17:37:19'),
(9, 26, 'DTI/SEC Registration', 0, 1, 1, '2026-04-11 17:37:19'),
(10, 26, 'Barangay Clearance', 0, 2, 1, '2026-04-11 17:37:19'),
(11, 26, 'Lease Contract / Proof of Business Address', 0, 3, 1, '2026-04-11 17:37:19'),
(12, 26, 'Sanitary Permit (if applicable)', 0, 4, 1, '2026-04-11 17:37:19'),
(13, 26, 'Fire Safety Inspection Certificate', 0, 5, 1, '2026-04-11 17:37:19'),
(14, 20, 'DTI/SEC Certificate', 0, 1, 1, '2026-04-11 17:37:19'),
(15, 20, 'Mayor\'s Permit / Application', 0, 2, 1, '2026-04-11 17:37:19'),
(16, 20, 'Valid ID of Owner', 1, 3, 1, '2026-04-11 17:37:19'),
(17, 20, 'Lease Contract / Proof of Address', 0, 4, 1, '2026-04-11 17:37:19'),
(18, 24, 'BIR Certificate of Registration', 0, 1, 1, '2026-04-11 17:37:19'),
(19, 24, 'Mayor\'s Permit', 0, 2, 1, '2026-04-11 17:37:19'),
(20, 24, 'Board Resolution / Affidavit of Closure', 1, 3, 1, '2026-04-11 17:37:19'),
(27, 19, 'Notice from BIR', 0, 1, 1, '2026-04-11 17:37:19'),
(28, 19, 'Previous Tax Filings', 0, 2, 1, '2026-04-11 17:37:19'),
(29, 19, 'Proof of Payment / Receipts', 0, 3, 1, '2026-04-11 17:37:19'),
(33, 29, 'List of Business Transactions', 0, 1, 1, '2026-04-11 17:37:19'),
(34, 29, 'Company Profile / Scope of Work', 0, 2, 1, '2026-04-11 17:37:19'),
(35, 32, 'Books of Accounts', 0, 1, 1, '2026-04-11 17:37:19'),
(36, 32, 'Official Receipts / Invoices', 0, 2, 1, '2026-04-11 17:37:19'),
(37, 32, 'Payroll (if applicable)', 0, 3, 1, '2026-04-11 17:37:19'),
(38, 18, 'Financial Statements', 0, 1, 1, '2026-04-11 17:37:19'),
(39, 18, 'Certificate of Income Tax Withheld (if any)', 0, 2, 1, '2026-04-11 17:37:19'),
(42, 28, 'Employee List with Details', 0, 1, 1, '2026-04-11 17:37:19'),
(43, 28, 'Payroll Summary (if existing)', 0, 2, 1, '2026-04-11 17:37:19'),
(44, 28, 'Government ID numbers (SSS, PhilHealth, Pag-IBIG)', 1, 3, 1, '2026-04-11 17:37:19'),
(77, 17, 'Existing Business Registration Documents', 0, 1, 1, '2026-04-11 17:47:57'),
(78, 17, 'Amended Articles of Incorporation / DTI Certificate', 0, 2, 1, '2026-04-11 17:47:57'),
(79, 17, 'Board Resolution for Amendment', 0, 3, 1, '2026-04-11 17:47:57'),
(85, 30, 'Previous Year\'s Mayor\'s Permit', 0, 1, 1, '2026-04-12 09:14:51'),
(86, 30, 'Barangay Clearance', 0, 2, 1, '2026-04-12 09:14:51'),
(87, 30, 'Updated Financial Statements', 0, 3, 1, '2026-04-12 09:14:51'),
(88, 21, 'Previous Books of Accounts (if any)', 0, 1, 1, '2026-04-12 09:18:08'),
(89, 21, 'Receipts and Vouchers', 0, 2, 1, '2026-04-12 09:18:08'),
(90, 21, 'Sales Invoices', 0, 3, 1, '2026-04-12 09:18:08'),
(91, 23, 'Basic Business Info / Idea', 1, 1, 1, '2026-04-12 09:51:56'),
(92, 23, 'List of Questions / Concerns', 0, 2, 1, '2026-04-12 09:51:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `verification_token` varchar(64) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `account_number`, `fullname`, `first_name`, `last_name`, `username`, `phone`, `address`, `city`, `state`, `postal_code`, `email`, `password`, `verification_token`, `is_verified`, `role`, `status`, `created_at`, `reset_token`, `reset_expires`) VALUES
(28, 'JRN-E1579B82', 'Lean Joshua Aclan', 'Lean Joshua', 'Aclan', 'leannnnn', '09876543211', 'basta dito sa bahay', 'Antipolo City', 'Rizal', '1870', 'kioshiiofficial@gmail.com', '$2y$10$543iFIL6JuMcKrC15FeUXeguE/wO78X5OQM1Ep1BRLEJST/phMCOO', '', 1, 'user', 'active', '2025-11-14 03:38:26', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `billings`
--
ALTER TABLE `billings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_invoice_number` (`invoice_number`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `role` (`role`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `employee_permissions`
--
ALTER TABLE `employee_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_permission` (`employee_id`,`permission_name`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inquiry_number` (`inquiry_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `inquiry_documents`
--
ALTER TABLE `inquiry_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiry_id` (`inquiry_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_period` (`period_year`,`period_month`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `billings`
--
ALTER TABLE `billings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_permissions`
--
ALTER TABLE `employee_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `inquiry_documents`
--
ALTER TABLE `inquiry_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=329;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `service_requirements`
--
ALTER TABLE `service_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employee_permissions`
--
ALTER TABLE `employee_permissions`
  ADD CONSTRAINT `employee_permissions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiry_documents`
--
ALTER TABLE `inquiry_documents`
  ADD CONSTRAINT `inquiry_documents_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD CONSTRAINT `fk_service_requirements_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
