-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 26, 2025 at 05:09 PM
-- Server version: 10.4.18-MariaDB
-- PHP Version: 8.0.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rp_attendance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','absent') NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `course_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `credits` int(11) NOT NULL DEFAULT 0,
  `duration_hours` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `name`, `course_name`, `description`, `department_id`, `option_id`, `lecturer_id`, `credits`, `duration_hours`, `status`) VALUES
(11, 'ICT101', 'Introduction to Information Technology', 'Introduction to Information Technology', 'Basic concepts of IT and computer systems', 7, NULL, NULL, 3, 45, 'active'),
(12, 'ICT201', 'Programming Fundamentals', 'Programming Fundamentals', 'Introduction to programming concepts and logic', 7, NULL, NULL, 4, 60, 'active'),
(13, 'ICT301', 'Database Management Systems', 'Database Management Systems', 'Database design and SQL programming', 7, NULL, NULL, 3, 45, 'active'),
(14, 'ICT401', 'Web Development', 'Web Development', 'HTML, CSS, JavaScript and web technologies', 7, NULL, NULL, 4, 60, 'active'),
(15, 'ICT501', 'Network Administration', 'Network Administration', 'Computer networks and system administration', 7, NULL, NULL, 3, 45, 'active'),
(16, 'CIV101', 'Introduction to Civil Engineering', 'Introduction to Civil Engineering', 'Basic principles of civil engineering', 3, NULL, 2, 3, 45, 'active'),
(17, 'CIV201', 'Structural Analysis', 'Structural Analysis', 'Analysis of structures and materials', 3, NULL, 2, 4, 60, 'active'),
(18, 'CIV301', 'Construction Materials', 'Construction Materials', 'Properties and testing of construction materials', 3, NULL, 2, 3, 45, 'active'),
(19, 'CIV401', 'Project Management', 'Project Management', 'Construction project planning and management', 3, NULL, 2, 3, 45, 'active'),
(20, 'CIV501', 'Environmental Engineering', 'Environmental Engineering', 'Environmental impact assessment and management', 3, NULL, 2, 4, 60, 'active'),
(21, 'CA101', 'Introduction to Creative Arts', 'Introduction to Creative Arts', NULL, 4, NULL, NULL, 3, 45, 'active'),
(22, 'CA102', 'Digital Art Fundamentals', 'Digital Art Fundamentals', NULL, 4, NULL, NULL, 3, 45, 'active'),
(23, 'CA201', 'Advanced Drawing Techniques', 'Advanced Drawing Techniques', NULL, 4, NULL, NULL, 4, 60, 'active'),
(24, 'CA202', 'Color Theory and Application', 'Color Theory and Application', NULL, 4, NULL, NULL, 3, 45, 'active'),
(25, 'CA301', 'Portfolio Development', 'Portfolio Development', NULL, 4, NULL, NULL, 4, 60, 'active'),
(26, 'CA302', 'Art History and Criticism', 'Art History and Criticism', NULL, 4, NULL, NULL, 3, 45, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hod_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `hod_id`) VALUES
(3, 'Civil Engineering', 4),
(4, 'Creative Arts', NULL),
(5, 'Mechanical Engineering', NULL),
(6, 'Electrical & Electronics Engineering', NULL),
(7, 'Information & Communication Technology', NULL),
(8, 'Mining Engineering', NULL),
(9, 'Transport & Logistics', NULL),
(10, 'General Courses', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `supporting_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `student_id`, `reason`, `supporting_file`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`) VALUES
(1, 5, 'familly\n-- Details --\nFrom: 2025-09-25\nTo: 2025-10-02\nRequested To: HoD\n', '1758708581_44071231.png', 'pending', '2025-09-24 10:09:41', NULL, NULL),
(2, 7, 'medical issues.\n-- Details --\nFrom: 2025-09-27\nTo: 2025-09-30\nRequested To: HoD\n', NULL, 'pending', '2025-09-25 07:38:17', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `dob` date NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `role` enum('lecturer','hod') NOT NULL DEFAULT 'lecturer',
  `photo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL DEFAULT '12345',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`id`, `first_name`, `last_name`, `gender`, `dob`, `id_number`, `email`, `phone`, `department_id`, `education_level`, `role`, `photo`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Frank', 'Mugabe', 'Male', '2004-09-02', '120018001269039', 'frankm@gmail.com', '0784615059', 4, 'phd', 'lecturer', NULL, '12345', '2025-09-23 09:43:25', '2025-09-23 09:43:25'),
(2, 'scott', 'adkin', 'Male', '2008-06-25', '12345678900987654', 'scott@gmail.com', '078789234', 3, 'PhD', 'lecturer', 'lec_68d59ce7579f3.jpg', '$2y$10$/AznslTTSRmADosusuUnX.VdoAeZdVRV4uq649pxAnUCWdCP8tqVa', '2025-09-25 19:49:59', '2025-09-25 20:46:24');

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`id`, `name`, `department_id`) VALUES
(1, 'Quantity Surveying', 3),
(2, 'Water & Sanitation Technology', 3),
(3, 'Highway Technology', 3),
(4, 'Construction Technology', 3),
(5, 'Land Surveying', 3),
(6, 'Geomatics', 3),
(7, 'Fashion Design', 4),
(8, 'Film Making & TV Production', 4),
(9, 'Graphic Design & Animation', 4),
(10, 'Automobile Technology', 5),
(11, 'Manufacturing Technology', 5),
(12, 'Mechatronics Technology', 5),
(13, 'Air Conditioning & Refrigeration Technology', 5),
(14, 'Electrical Technology', 6),
(15, 'Electronics & Telecommunication Technology', 6),
(16, 'Biomedical Equipment Technology', 6),
(17, 'Information Technology', 7),
(18, 'Mining Technology', 8),
(19, 'Automobile Technology', 9),
(20, 'Visual Arts', 4),
(21, 'Digital Media', 4),
(22, 'Performing Arts', 4),
(23, 'Visual Arts', 4),
(24, 'Digital Media', 4),
(25, 'Performing Arts', 4);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `cell` varchar(100) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `parent_first_name` varchar(100) DEFAULT NULL,
  `parent_last_name` varchar(100) DEFAULT NULL,
  `parent_contact` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `reg_no` varchar(50) NOT NULL,
  `student_id_number` varchar(25) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `fingerprint` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `fingerprint_path` varchar(255) DEFAULT NULL,
  `fingerprint_quality` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `option_id`, `year_level`, `first_name`, `last_name`, `dob`, `cell`, `sector`, `district`, `province`, `parent_first_name`, `parent_last_name`, `parent_contact`, `email`, `reg_no`, `student_id_number`, `department_id`, `telephone`, `sex`, `photo`, `fingerprint`, `password`, `fingerprint_path`, `fingerprint_quality`) VALUES
(4, 6, 1, '1', 'fils', 'iradukunda', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'iradukundabonfils2000@gmail.com', '22rp08765', NULL, 1, '0790759103', 'Male', 'uploads/WIN_20250821_11_20_24_Pro.jpg', '', '$2y$10$zfFOORZyxq7M7KKG2vwqMesNH5W9aatZ84S7urKS5YVQxohdEBcuq', NULL, 0),
(5, 8, 2, '1', 'fils', 'iradukunda', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sepacifiq@gmail.com', '22rp09877', NULL, 1, '0790759103', 'Male', 'uploads/68a6f5584d0f1.png', '', '$2y$10$llJls0sZ6wg2Um6kJIHoQ.dakn9lZeLeO9dfcoAcfXhHoZT.nRqXK', NULL, 0),
(6, 11, 2, '1', 'Muganga', 'Antu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'antu@gmail.com', '22rp08765', NULL, 1, '0790759103', 'Male', 'uploads/1755772530.png', '', '$2y$10$zMfAJOUUsuqRSg0JQv372uQTrZqfFdpyqvicFAZeeL/yMX4US1d7O', NULL, 0),
(7, 12, 1, '1', 'ange', 'muni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ange@gmail.com', '22rp04657', NULL, 1, '0789262098', 'Female', 'uploads/68a6f7cc789cd.png', '', '$2y$10$jnXcv8du6.NWMFyfWD1H9uNW.s./VO619KlHBxiLC5hFpHo1HNRS6', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tech_logs`
--

CREATE TABLE `tech_logs` (
  `id` int(11) NOT NULL,
  `tech_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','lecturer','student','hod','tech') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `status`, `updated_at`, `last_login`) VALUES
(1, 'adminuser', 'admin@example.com', '$2y$10$tAwjhVXmZuHtHyZ1HDITreA/D8kW7SkYI0LwwGbo24zX14uonrAFa', 'admin', '2025-08-20 11:17:29', 'active', '2025-09-25 15:38:03', NULL),
(2, 'lecturer1', 'lecturer1@example.com', '$2y$10$w8h1M1bKwjKv2J0DyNPRgOWdCiyMeiPnmrFcLO.JKPE3Z39cd6Ri6', 'lecturer', '2025-08-20 11:17:29', 'active', '2025-09-25 15:38:03', NULL),
(3, 'student1', 'student1@example.com', '$2y$10$z1KXFa6ufPAiTZjSg/fjOem8S5xGIiPGEt2f1liTRmSH/cL2fDPYW', 'student', '2025-08-20 11:17:29', 'active', '2025-09-25 15:38:03', NULL),
(4, 'hod1', 'hod1@example.com', '$2y$10$J3wOKED.9jMZkdlnfjIGtO3b0Pa8x4QaK31qWhUnttE5l4167r542', 'hod', '2025-08-20 11:17:29', 'active', '2025-09-25 15:38:03', NULL),
(5, 'tech1', 'tech1@example.com', 'Password123!', 'tech', '2025-08-20 11:17:29', 'active', '2025-09-25 15:38:03', NULL),
(6, 'fils_iradukunda', 'iradukundabonfils2000@gmail.com', '$2y$10$zfFOORZyxq7M7KKG2vwqMesNH5W9aatZ84S7urKS5YVQxohdEBcuq', 'student', '2025-08-21 10:14:52', 'active', '2025-09-25 15:38:03', NULL),
(8, 'fils iradukunda', 'sepacifiq@gmail.com', '$2y$10$llJls0sZ6wg2Um6kJIHoQ.dakn9lZeLeO9dfcoAcfXhHoZT.nRqXK', 'student', '2025-08-21 10:30:48', 'active', '2025-09-25 15:38:03', NULL),
(11, '22rp08765', 'antu@gmail.com', '$2y$10$zMfAJOUUsuqRSg0JQv372uQTrZqfFdpyqvicFAZeeL/yMX4US1d7O', 'student', '2025-08-21 10:35:30', 'active', '2025-09-25 15:38:03', NULL),
(12, 'ange muni', 'ange@gmail.com', '$2y$10$jnXcv8du6.NWMFyfWD1H9uNW.s./VO619KlHBxiLC5hFpHo1HNRS6', 'student', '2025-08-21 10:41:16', 'active', '2025-09-25 15:38:03', NULL),
(14, 'frank', 'frankm@gmail.com', '$2y$10$rCVEViMkddVSDdLIuQ.D4eUojG9exSJiSCoia8RfoxVt3wJDZJW76', 'lecturer', '2025-09-23 10:23:19', 'active', '2025-09-25 15:38:03', NULL),
(17, 'scott.adkin', 'scott@gmail.com', '$2y$10$/AznslTTSRmADosusuUnX.VdoAeZdVRV4uq649pxAnUCWdCP8tqVa', 'lecturer', '2025-09-25 19:49:59', 'active', '2025-09-25 19:49:59', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_lecturer_id` (`lecturer_id`),
  ADD KEY `fk_courses_option` (`option_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `hod_id` (`hod_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indexes for table `tech_logs`
--
ALTER TABLE `tech_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tech_id` (`tech_id`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tech_logs`
--
ALTER TABLE `tech_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD CONSTRAINT `attendance_sessions_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_sessions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_sessions_ibfk_3` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_courses_option` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `options`
--
ALTER TABLE `options`
  ADD CONSTRAINT `options_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tech_logs`
--
ALTER TABLE `tech_logs`
  ADD CONSTRAINT `tech_logs_ibfk_1` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
