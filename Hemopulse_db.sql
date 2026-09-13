-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 13, 2026 at 09:07 AM
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
-- Database: `Hemopulse_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `scheduled_time_slot` time NOT NULL,
  `appointment_status` enum('Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show') NOT NULL DEFAULT 'Confirmed',
  `qr_pass_token` varchar(64) NOT NULL,
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active_flag` tinyint(1) GENERATED ALWAYS AS (case when `appointment_status` in ('Pending','Confirmed','Checked-In') then 1 else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `donor_id`, `campaign_id`, `scheduled_time_slot`, `appointment_status`, `qr_pass_token`, `booked_at`) VALUES
(3, 3, 1, '09:00:00', 'Completed', '2a88ee8acf813197660412aca1ab5893', '2026-09-13 06:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_performed` varchar(100) NOT NULL,
  `affected_table` varchar(50) NOT NULL,
  `target_record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_ip_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

CREATE TABLE `blood_inventory` (
  `inventory_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_available` int(11) NOT NULL DEFAULT 0,
  `volume_ml_per_unit` int(11) NOT NULL DEFAULT 450,
  `collection_date` date NOT NULL,
  `expiration_date` date NOT NULL,
  `inventory_status` enum('Available','Reserved','Expired','Disposed') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_inventory`
--

INSERT INTO `blood_inventory` (`inventory_id`, `blood_type`, `units_available`, `volume_ml_per_unit`, `collection_date`, `expiration_date`, `inventory_status`) VALUES
(1, 'O+', 5, 450, '2026-09-08', '2026-10-13', 'Available'),
(2, 'A+', 3, 450, '2026-09-10', '2026-10-15', 'Available'),
(3, 'B+', 2, 450, '2026-09-11', '2026-10-16', 'Available'),
(4, 'AB+', 1, 450, '2026-09-12', '2026-10-17', 'Available'),
(5, 'O+', 1, 450, '2026-09-13', '2026-10-18', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `blood_requests`
--

CREATE TABLE `blood_requests` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `blood_type_requested` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_requested` int(11) NOT NULL DEFAULT 1,
  `urgency_level` enum('Routine','Urgent','Emergency') NOT NULL DEFAULT 'Routine',
  `request_status` enum('Pending','Under Review','Approved','Rejected','Fulfilled') NOT NULL DEFAULT 'Pending',
  `clinical_justification` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `campaign_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location_venue` varchar(150) NOT NULL,
  `campaign_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `total_slots` int(11) NOT NULL DEFAULT 30,
  `available_slots` int(11) NOT NULL DEFAULT 30,
  `walk_in_capacity` int(11) NOT NULL DEFAULT 20,
  `target_units` int(11) NOT NULL DEFAULT 50,
  `campaign_status` enum('Draft','Published','Active','Closed') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`campaign_id`, `created_by`, `title`, `description`, `location_venue`, `campaign_date`, `start_time`, `end_time`, `total_slots`, `available_slots`, `walk_in_capacity`, `target_units`, `campaign_status`, `created_at`) VALUES
(1, 1, 'Community Blood Drive 2026', 'Annual voluntary mobile drive with walk-in support.', 'Taguig City Hall Auditorium', '2026-09-13', '08:00:00', '16:00:00', 30, 29, 20, 50, 'Active', '2026-09-13 06:29:01');

-- --------------------------------------------------------

--
-- Table structure for table `donation_records`
--

CREATE TABLE `donation_records` (
  `donation_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `verified_by_staff_id` int(11) DEFAULT NULL,
  `donation_date` date NOT NULL,
  `blood_type_collected` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `volume_ml` int(11) NOT NULL DEFAULT 450,
  `clinical_outcome` enum('Completed','Deferred') NOT NULL,
  `deferral_reason` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donation_records`
--

INSERT INTO `donation_records` (`donation_id`, `appointment_id`, `verified_by_staff_id`, `donation_date`, `blood_type_collected`, `volume_ml`, `clinical_outcome`, `deferral_reason`, `medical_notes`) VALUES
(1, 3, 2, '2026-09-13', 'O+', 450, 'Completed', NULL, 'Donor passed physical screening and vitals.');

-- --------------------------------------------------------

--
-- Table structure for table `donor_profiles`
--

CREATE TABLE `donor_profiles` (
  `donor_profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `date_of_birth` date NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `last_donation_date` date DEFAULT NULL,
  `estimated_eligible_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donor_profiles`
--

INSERT INTO `donor_profiles` (`donor_profile_id`, `user_id`, `blood_type`, `date_of_birth`, `weight_kg`, `last_donation_date`, `estimated_eligible_date`) VALUES
(1, 3, 'O+', '2001-05-20', 68.00, '2026-09-13', '2026-12-12');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_thresholds`
--

CREATE TABLE `inventory_thresholds` (
  `threshold_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `minimum_units` int(11) NOT NULL DEFAULT 5,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_thresholds`
--

INSERT INTO `inventory_thresholds` (`threshold_id`, `blood_type`, `minimum_units`, `updated_by`, `updated_at`) VALUES
(1, 'A+', 5, 1, '2026-09-13 06:29:01'),
(2, 'A-', 3, 1, '2026-09-13 06:29:01'),
(3, 'B+', 5, 1, '2026-09-13 06:29:01'),
(4, 'B-', 3, 1, '2026-09-13 06:29:01'),
(5, 'AB+', 3, 1, '2026-09-13 06:29:01'),
(6, 'AB-', 2, 1, '2026-09-13 06:29:01'),
(7, 'O+', 8, 1, '2026-09-13 06:29:01'),
(8, 'O-', 5, 1, '2026-09-13 06:29:01');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `executed_by_staff_id` int(11) DEFAULT NULL,
  `transaction_type` enum('Addition','Deduction','Expiration','Disposal') NOT NULL,
  `units_transacted` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `transaction_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`transaction_id`, `inventory_id`, `executed_by_staff_id`, `transaction_type`, `units_transacted`, `donation_id`, `request_id`, `transaction_timestamp`, `transaction_notes`) VALUES
(1, 5, 2, 'Addition', 1, 1, NULL, '2026-09-13 06:36:50', 'Donation intake recorded');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('Appointment','Eligibility','Inventory_Alert','Request_Update') NOT NULL DEFAULT 'Appointment',
  `subject` varchar(100) NOT NULL,
  `message_body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_fulfillments`
--

CREATE TABLE `request_fulfillments` (
  `fulfillment_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `fulfilled_by_staff_id` int(11) DEFAULT NULL,
  `units_allocated` int(11) NOT NULL DEFAULT 1,
  `fulfillment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` enum('Admin','Staff','Donor','Recipient') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Staff'),
(3, 'Donor'),
(4, 'Recipient');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `account_status` enum('Active','Suspended','Deactivated','Pending') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `email`, `password_hash`, `first_name`, `last_name`, `contact_number`, `account_status`, `created_at`) VALUES
(1, 1, 'admin@hemopulse.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'System', 'Admin', '09170000001', 'Active', '2026-09-13 06:29:01'),
(2, 2, 'staff@hemopulse.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Maria', 'Santos', '09170000002', 'Active', '2026-09-13 06:29:01'),
(3, 3, 'donor@hemopulse.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Juan', 'Dela Cruz', '09170000003', 'Active', '2026-09-13 06:29:01'),
(4, 4, 'recipient@hemopulse.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Ana', 'Reyes', '09170000004', 'Active', '2026-09-13 06:29:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `uk_qr_pass_token` (`qr_pass_token`),
  ADD UNIQUE KEY `uk_donor_campaign_active` (`donor_id`,`campaign_id`,`active_flag`),
  ADD KEY `idx_appointments_donor` (`donor_id`),
  ADD KEY `idx_appointments_campaign` (`campaign_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_logs_user` (`user_id`),
  ADD KEY `idx_audit_logs_timestamp` (`log_timestamp`);

--
-- Indexes for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `idx_inventory_blood_type` (`blood_type`),
  ADD KEY `idx_inventory_expiry` (`expiration_date`,`inventory_status`);

--
-- Indexes for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_requests_requester` (`requester_id`),
  ADD KEY `idx_requests_status` (`request_status`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`campaign_id`),
  ADD KEY `idx_campaigns_status_date` (`campaign_status`,`campaign_date`),
  ADD KEY `idx_campaigns_created_by` (`created_by`);

--
-- Indexes for table `donation_records`
--
ALTER TABLE `donation_records`
  ADD PRIMARY KEY (`donation_id`),
  ADD UNIQUE KEY `uk_donation_appointment` (`appointment_id`),
  ADD KEY `idx_donations_staff` (`verified_by_staff_id`),
  ADD KEY `idx_donations_date` (`donation_date`);

--
-- Indexes for table `donor_profiles`
--
ALTER TABLE `donor_profiles`
  ADD PRIMARY KEY (`donor_profile_id`),
  ADD UNIQUE KEY `uk_donor_profile_user` (`user_id`);

--
-- Indexes for table `inventory_thresholds`
--
ALTER TABLE `inventory_thresholds`
  ADD PRIMARY KEY (`threshold_id`),
  ADD UNIQUE KEY `uk_threshold_blood_type` (`blood_type`),
  ADD KEY `fk_threshold_admin` (`updated_by`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_transactions_inventory` (`inventory_id`),
  ADD KEY `idx_transactions_staff` (`executed_by_staff_id`),
  ADD KEY `idx_transactions_donation` (`donation_id`),
  ADD KEY `idx_transactions_request` (`request_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`);

--
-- Indexes for table `request_fulfillments`
--
ALTER TABLE `request_fulfillments`
  ADD PRIMARY KEY (`fulfillment_id`),
  ADD KEY `idx_fulfillments_request` (`request_id`),
  ADD KEY `idx_fulfillments_inventory` (`inventory_id`),
  ADD KEY `idx_fulfillments_staff` (`fulfilled_by_staff_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uk_role_name` (`role_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blood_requests`
--
ALTER TABLE `blood_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `campaign_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `donation_records`
--
ALTER TABLE `donation_records`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `donor_profiles`
--
ALTER TABLE `donor_profiles`
  MODIFY `donor_profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_thresholds`
--
ALTER TABLE `inventory_thresholds`
  MODIFY `threshold_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_fulfillments`
--
ALTER TABLE `request_fulfillments`
  MODIFY `fulfillment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`campaign_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_donor` FOREIGN KEY (`donor_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD CONSTRAINT `fk_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD CONSTRAINT `fk_campaigns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `donation_records`
--
ALTER TABLE `donation_records`
  ADD CONSTRAINT `fk_donations_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_donations_staff` FOREIGN KEY (`verified_by_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `donor_profiles`
--
ALTER TABLE `donor_profiles`
  ADD CONSTRAINT `fk_donor_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_thresholds`
--
ALTER TABLE `inventory_thresholds`
  ADD CONSTRAINT `fk_threshold_admin` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_transactions_donation` FOREIGN KEY (`donation_id`) REFERENCES `donation_records` (`donation_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `blood_inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_staff` FOREIGN KEY (`executed_by_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `request_fulfillments`
--
ALTER TABLE `request_fulfillments`
  ADD CONSTRAINT `fk_fulfillments_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `blood_inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fulfillments_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fulfillments_staff` FOREIGN KEY (`fulfilled_by_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
