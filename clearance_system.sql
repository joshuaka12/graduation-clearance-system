-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 12:26 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clearance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `semester` enum('Fall','Spring','Summer') NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `graduation_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(4, 1, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 12:30:27'),
(5, 1, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 13:01:23'),
(10, 1, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 13:23:08'),
(11, 1, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 13:23:16'),
(15, 1, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 13:33:56'),
(16, 1, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 13:34:03'),
(17, 2, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 13:34:31'),
(18, 2, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 13:51:37'),
(19, 3, 'Registration', 'Student registered successfully', '::1', NULL, '2026-05-08 13:54:41'),
(23, 1, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 14:00:17'),
(24, 1, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 14:00:50'),
(25, 4, 'Registration', 'Student registered successfully', '::1', NULL, '2026-05-08 14:01:43'),
(26, 4, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 14:02:14'),
(27, 4, 'Password Changed', 'User changed their password', '::1', NULL, '2026-05-08 14:02:53'),
(28, 4, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 14:02:59'),
(29, 4, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 14:03:05'),
(30, 4, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 14:03:10'),
(31, 4, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 17:49:12'),
(32, 4, 'Logout', 'User logged out', '::1', NULL, '2026-05-08 17:49:29'),
(33, 4, 'Login', 'User logged in successfully', '::1', NULL, '2026-05-08 17:50:44'),
(34, 2, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 12:56:32'),
(35, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 12:58:25'),
(36, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 09:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `clearance_certificates`
--

CREATE TABLE `clearance_certificates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_number` varchar(50) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `verification_code` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clearance_items`
--

CREATE TABLE `clearance_items` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `requires_document` tinyint(1) DEFAULT 0,
  `document_types` varchar(255) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'Karamuzi Jo', 'jokaramuzi@gmail.com', 'my work', 'am done dihigkvknm vn v oi oii ifjv  43iokmca  doiijvewkmvw  ewjvwmkwm  cinv kdi dkiweln knijfewk dm  moikfnvk lsdwev nmewqknfm mv omv mkewv nin cvn in vlk vwe n woinmkv nm io niv  wmom m c', 'unread', '2026-05-08 12:22:12'),
(2, 'Karamuzi Jo', 'jokaramuzi@gmail.com', 'my work', 'am done dihigkvknm vn v oi oii ifjv  43iokmca  doiijvewkmvw  ewjvwmkwm  cinv kdi dkiweln knijfewk dm  moikfnvk lsdwev nmewqknfm mv omv mkewv nin cvn in vlk vwe n woinmkv nm io niv  wmom m c', 'unread', '2026-05-08 12:27:28');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `hod_email` varchar(100) DEFAULT NULL,
  `hod_user_id` int(11) DEFAULT NULL,
  `clearance_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `clearance_item_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `permission_value` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_clearance`
--

CREATE TABLE `student_clearance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `clearance_item_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','waived') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'site_name', 'Graduation Clearance System', 'text', 'Name of the system', NULL, '2026-05-08 11:57:19'),
(2, 'site_email', 'admin@clearance.edu', 'text', 'System email address', NULL, '2026-05-08 11:57:19'),
(3, 'current_semester', 'Spring 2024', 'text', 'Current academic semester', NULL, '2026-05-08 11:57:19'),
(4, 'graduation_date', '2024-06-15', 'text', 'Upcoming graduation date', NULL, '2026-05-08 11:57:19'),
(5, 'clearance_deadline', '2024-06-01', 'text', 'Clearance completion deadline', NULL, '2026-05-08 11:57:19'),
(6, 'enable_registration', 'true', 'boolean', 'Allow student registration', NULL, '2026-05-08 11:57:19'),
(7, 'maintenance_mode', 'false', 'boolean', 'System maintenance mode', NULL, '2026-05-08 11:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT 'default-avatar.png',
  `role` enum('student','admin','department_head','registrar') DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `student_id`, `email`, `phone`, `password`, `profile_pic`, `role`, `is_active`, `verification_token`, `reset_token`, `reset_token_expiry`, `remember_token`, `last_login`, `created_at`, `updated_at`, `department_id`) VALUES
(1, 'System Administrator', NULL, 'admin@clearance.edu', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'default-avatar.png', 'admin', 1, NULL, NULL, NULL, NULL, '2026-05-13 09:37:49', '2026-05-08 11:57:19', '2026-05-13 09:37:49', NULL),
(2, 'John Doe', 'STU2024001', 'student@clearance.edu', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user_2_1778590651.jpg', 'student', 1, NULL, NULL, NULL, NULL, '2026-05-12 12:56:32', '2026-05-08 11:57:19', '2026-05-12 12:57:31', NULL),
(3, 'Karamuzi Jo', '23/701BIT/U', 'jokaramuzi@gmail.com', '0754135798', '$2y$10$vJiyr.BZBzFRIx22rCSr..NLDk6HBRe2Cy8TZG0lbsPLgYieb8q8O', 'default-avatar.png', 'student', 1, '551837eb3fc91e8a7719b4d842a11e49bf541030f06d461ddc091a514cc3ad86', NULL, NULL, NULL, NULL, '2026-05-08 13:54:41', '2026-05-08 13:54:41', NULL),
(4, 'Karamuzi Jo', '23/702BIT/U', 'joshuakaramuzi@gmail.com', '0754135798', '$2y$10$EFA49wqgaACJb4j0eC82m.vZ3uXJNrVpNpEhWLZ8J6O0Dt7K6nau2', 'user_4_1778262688.jpg', 'student', 1, '5fce984f89c83620cd7849f262157278edccc01a3621399fa4fe9d22f207b8e3', '45825f06882ad5d694b01fb8ab3c5fa8c8984e618622fedcd66c50013315b42c', '2026-05-08 17:03:45', '8de228982b373af9105cf2c0b149eef61c3f3c22d092d79fd286c08e39bef0c3', '2026-05-08 17:50:44', '2026-05-08 14:01:43', '2026-05-08 17:51:28', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `clearance_certificates`
--
ALTER TABLE `clearance_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `clearance_items`
--
ALTER TABLE `clearance_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `hod_user_id` (`hod_user_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `clearance_item_id` (`clearance_item_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `student_clearance`
--
ALTER TABLE `student_clearance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_clearance` (`student_id`,`clearance_item_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `clearance_item_id` (`clearance_item_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `clearance_certificates`
--
ALTER TABLE `clearance_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clearance_items`
--
ALTER TABLE `clearance_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_clearance`
--
ALTER TABLE `student_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clearance_certificates`
--
ALTER TABLE `clearance_certificates`
  ADD CONSTRAINT `clearance_certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clearance_certificates_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clearance_items`
--
ALTER TABLE `clearance_items`
  ADD CONSTRAINT `clearance_items_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`hod_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`clearance_item_id`) REFERENCES `clearance_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `student_clearance`
--
ALTER TABLE `student_clearance`
  ADD CONSTRAINT `student_clearance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_clearance_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_clearance_ibfk_3` FOREIGN KEY (`clearance_item_id`) REFERENCES `clearance_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_clearance_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
