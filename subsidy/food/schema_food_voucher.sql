-- --------------------------------------------------------
-- Host:                         157.245.193.124
-- Server version:               8.0.45-0ubuntu0.22.04.1
-- Server OS:                    Linux
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

USE `subsidy`;

-- ========================================================
-- FOOD VOUCHER CLAIMING SYSTEM - TABLES
-- ========================================================

-- 1. FOOD BENEFICIARIES TABLE (Equivalent to tricycle_records)
CREATE TABLE IF NOT EXISTS `food_beneficiaries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `beneficiary_code` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `area` enum('MANGA','COGON','CENTRAL') NOT NULL,
  `total_vouchers` int DEFAULT '12',
  `claimed_vouchers` int DEFAULT '0',
  `status` enum('Active','Not Active') DEFAULT 'Active',
  `last_claim_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `beneficiary_code` (`beneficiary_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. FOOD VOUCHER CLAIMS TABLE (Equivalent to voucher_claims)
CREATE TABLE IF NOT EXISTS `food_voucher_claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `beneficiary_id` int NOT NULL,
  `voucher_number` int NOT NULL,
  `claimant_name` varchar(100) DEFAULT NULL,
  `e_signature` longtext,
  `claim_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `personnel_id` int DEFAULT NULL,
  `area` enum('MANGA','COGON','CENTRAL') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_voucher` (`beneficiary_id`,`voucher_number`),
  KEY `beneficiary_id` (`beneficiary_id`),
  CONSTRAINT `food_voucher_claims_ibfk_1` FOREIGN KEY (`beneficiary_id`) REFERENCES `food_beneficiaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. FOOD PERSONNEL / USERS TABLE
CREATE TABLE IF NOT EXISTS `food_personnel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('ADMIN','PERSONNEL') DEFAULT 'PERSONNEL',
  `assigned_area` enum('MANGA','COGON','CENTRAL','ALL') DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. FOOD VOUCHERS MASTER TABLE
CREATE TABLE IF NOT EXISTS `food_vouchers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `voucher_code` varchar(50) NOT NULL,
  `beneficiary_id` int NOT NULL,
  `status` enum('UNCLAIMED','CLAIMED','REDEEMED') DEFAULT 'UNCLAIMED',
  `claimed_at` timestamp NULL DEFAULT NULL,
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `claimed_by` int DEFAULT NULL,
  `redeemed_by` int DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_code` (`voucher_code`),
  KEY `beneficiary_id` (`beneficiary_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. VENDORS TABLE
CREATE TABLE IF NOT EXISTS `food_vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_serial` varchar(20) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `area` enum('MANGA','COGON','CENTRAL') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_serial` (`vendor_serial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. REDEMPTION BATCHES TABLE
CREATE TABLE IF NOT EXISTS `food_redemption_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `personnel_id` int NOT NULL,
  `total_vouchers` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `personnel_id` (`personnel_id`),
  CONSTRAINT `food_redemption_batches_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `food_vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `food_redemption_batches_ibfk_2` FOREIGN KEY (`personnel_id`) REFERENCES `food_personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 7. REDEMPTION ITEMS TABLE
CREATE TABLE IF NOT EXISTS `food_redemption_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `batch_id` int NOT NULL,
  `voucher_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_id` (`voucher_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `food_redemption_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `food_redemption_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `food_redemption_items_ibfk_2` FOREIGN KEY (`voucher_id`) REFERENCES `food_vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ========================================================
-- SAMPLE TEST DATA
-- ========================================================

-- Insert Sample Beneficiaries
INSERT INTO `food_beneficiaries` (`beneficiary_code`, `full_name`, `address`, `contact_number`, `area`, `total_vouchers`, `claimed_vouchers`) VALUES
('FB0001', 'Juan Dela Cruz', 'Brgy. Manga, City Proper', '09123456789', 'MANGA', 12, 0),
('FB0002', 'Maria Santos', 'Brgy. Cogon, Poblacion', '09129876543', 'COGON', 12, 0),
('FB0003', 'Pedro Reyes', 'Central District', '09171234567', 'CENTRAL', 12, 0),
('FB0004', 'Ana Gonzales', 'Manga Area', '09187654321', 'MANGA', 12, 0),
('FB0005', 'Jose Martinez', 'Cogon Village', '09151112233', 'COGON', 12, 0);

-- Insert Sample Personnel
INSERT INTO `food_personnel` (`employee_id`, `full_name`, `username`, `password`, `role`, `assigned_area`) VALUES
('EMP001', 'Admin User', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', 'ALL'),
('EMP002', 'John Personnel', 'john', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PERSONNEL', 'MANGA'),
('EMP003', 'Jane Personnel', 'jane', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PERSONNEL', 'COGON');

-- Insert Sample Vendors
INSERT INTO `food_vendors` (`vendor_serial`, `vendor_name`, `area`) VALUES
('V001', 'MANGA FOOD CENTER', 'MANGA'),
('V002', 'COGON PUBLIC MARKET', 'COGON'),
('V003', 'CENTRAL SARI-SARI STORE', 'CENTRAL'),
('V004', 'BARANGAY MANGA CANTEEN', 'MANGA'),
('V005', 'COGON EATERY', 'COGON');

-- Insert Sample Vouchers
INSERT INTO `food_vouchers` (`voucher_code`, `beneficiary_id`, `status`) VALUES
('FV000001', 1, 'UNCLAIMED'),
('FV000002', 1, 'UNCLAIMED'),
('FV000003', 1, 'UNCLAIMED'),
('FV000004', 1, 'UNCLAIMED'),
('FV000005', 2, 'UNCLAIMED'),
('FV000006', 2, 'UNCLAIMED'),
('FV000007', 2, 'UNCLAIMED'),
('FV000008', 3, 'UNCLAIMED'),
('FV000009', 3, 'UNCLAIMED'),
('FV000010', 3, 'UNCLAIMED');


/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;