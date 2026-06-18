USE `subsidy`;

CREATE TABLE IF NOT EXISTS `rice_households` (
  `id` int NOT NULL AUTO_INCREMENT,
  `household_code` varchar(50) NOT NULL,
  `household_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Not Active') NOT NULL DEFAULT 'Active',
  `is_claimed` tinyint(1) NOT NULL DEFAULT '0',
  `claimed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rice_household_code` (`household_code`),
  KEY `idx_rice_household_name` (`household_name`),
  KEY `idx_rice_claimed_status` (`is_claimed`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `rice_voucher_claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `household_id` int NOT NULL,
  `claimant_name` varchar(150) DEFAULT NULL,
  `e_signature` longtext,
  `claim_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `verifier_name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rice_household_claim` (`household_id`),
  KEY `idx_rice_claim_date` (`claim_date`),
  CONSTRAINT `rice_voucher_claims_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `rice_households` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
