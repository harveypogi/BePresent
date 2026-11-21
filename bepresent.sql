-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 11:43 AM
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
-- Database: `bepresent`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Late','Half Day') DEFAULT 'Present',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `time_in`, `time_out`, `status`, `remarks`, `created_at`) VALUES
(1, 2, '2026-11-17', '17:29:10', '17:29:32', 'Present', NULL, '2026-11-17 09:29:10'),
(2, 2, '2026-11-19', '18:06:00', '18:50:24', 'Present', NULL, '2026-11-19 10:06:33'),
(3, 1, '2026-11-19', '00:35:00', '15:44:00', 'Present', NULL, '2026-11-19 10:20:45'),
(4, 2, '2026-11-21', '18:25:18', NULL, 'Late', NULL, '2026-11-21 10:25:18');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `old_time_in` time DEFAULT NULL,
  `new_time_in` time DEFAULT NULL,
  `old_time_out` time DEFAULT NULL,
  `new_time_out` time DEFAULT NULL,
  `old_status` enum('Present','Late','Half Day') DEFAULT NULL,
  `new_status` enum('Present','Late','Half Day') DEFAULT NULL,
  `correction_reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `attendance_id`, `admin_user_id`, `old_time_in`, `new_time_in`, `old_time_out`, `new_time_out`, `old_status`, `new_status`, `correction_reason`, `created_at`) VALUES
(1, 2, 1, '18:06:33', '18:06:00', NULL, NULL, 'Late', 'Present', 'forgot to timed in', '2026-11-19 10:07:19'),
(2, 2, 1, '18:06:00', '18:06:00', NULL, NULL, 'Present', 'Present', 'hehe', '2026-11-19 10:08:09'),
(3, 3, 1, NULL, '00:35:00', NULL, '15:44:00', NULL, 'Present', 'late', '2026-11-19 10:20:45');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_code` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `hire_date`, `employment_status`, `created_at`) VALUES
(1, 2, 'EMP 123', 'Birdie', 'Yawa', 'birdie@gmail.com', '0128312313214', 'IT Staff', '2026-11-20', 'Active', '2026-11-15 06:31:36'),
(2, 3, 'EMP 232', 'hehe', 'hoho', 'hehe@gmail.com', '123125431343', 'IT Staff', '2026-11-04', 'Active', '2026-11-15 06:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 'Admin', 'bepresent@admin.com', '$2y$10$COaRb.6OSyVGRLtl7xz4e.cD3pD7TT/xLkzFAfAaMIvrZi3SteQlS', 'admin', 1, '2026-11-12 08:16:31'),
(2, 'birdie', 'birdie@gmail.com', '$2y$10$Khm.kdVrVSbnbj5gkyzADuAXcVd7wsMM2tNAU0iL55W/FjXGeFleS', 'employee', 1, '2026-11-15 06:31:36'),
(3, 'hoho', 'hehe@gmail.com', '$2y$10$lIF5qbP1q9uxaB.l2ncxwOBR3a1ssdp7V0lIbbUdQYGjbNYJxvDfW', 'employee', 1, '2026-11-15 06:33:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`attendance_date`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
