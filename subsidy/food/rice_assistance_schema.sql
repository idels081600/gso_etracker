USE `subsidy`;

CREATE TABLE IF NOT EXISTS `rice_households` (
  `id` int NOT NULL AUTO_INCREMENT,
  `household_code` varchar(50) NOT NULL,
  `household_code_prefix` varchar(50) NOT NULL,
  `household_code_number` int NOT NULL DEFAULT '0',
  `household_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Not Active') NOT NULL DEFAULT 'Active',
  `is_claimed` tinyint(1) NOT NULL DEFAULT '0',
  `is_checked` tinyint(1) NOT NULL DEFAULT '0',
  `claimed_at` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rice_household_code` (`household_code`),
  KEY `idx_rice_household_name` (`household_name`),
  KEY `idx_rice_claimed_status` (`is_claimed`,`status`),
  KEY `idx_rice_household_code_sort` (`household_code_prefix`,`household_code_number`,`household_code`),
  KEY `idx_rice_household_address_sort` (`address`,`household_code_prefix`,`household_code_number`,`household_code`),
  KEY `idx_rice_checked_modified` (`is_checked`,`modified`)
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
CREATE TABLE IF NOT EXISTS `rice_claimed_households` LIKE `rice_households`;

ALTER TABLE `rice_claimed_households`
  ADD COLUMN IF NOT EXISTS `last_name` varchar(100) DEFAULT NULL AFTER `household_name`,
  ADD COLUMN IF NOT EXISTS `first_name` varchar(150) DEFAULT NULL AFTER `last_name`,
  ADD COLUMN IF NOT EXISTS `middle_name` varchar(150) DEFAULT NULL AFTER `first_name`,
  ADD COLUMN IF NOT EXISTS `sex` enum('M','F') DEFAULT NULL AFTER `middle_name`,
  ADD COLUMN IF NOT EXISTS `pwd` tinyint(1) DEFAULT NULL AFTER `sex`,
  ADD COLUMN IF NOT EXISTS `age` smallint unsigned DEFAULT NULL AFTER `pwd`,
  ADD COLUMN IF NOT EXISTS `office` varchar(150) DEFAULT NULL AFTER `age`,
  ADD COLUMN IF NOT EXISTS `designation` varchar(150) DEFAULT NULL AFTER `office`,
  ADD COLUMN IF NOT EXISTS `sectoral_representation` varchar(50) DEFAULT NULL AFTER `designation`,
  ADD COLUMN IF NOT EXISTS `contact_number` varchar(30) DEFAULT NULL AFTER `sectoral_representation`;

INSERT INTO `rice_claimed_households` (
  `id`, `household_code`, `household_code_prefix`, `household_code_number`,
  `household_name`, `address`, `status`, `is_claimed`, `is_checked`,
  `claimed_at`, `modified`, `created_at`, `updated_at`
)
SELECT
  `id`, `household_code`, `household_code_prefix`, `household_code_number`,
  `household_name`, `address`, `status`, 0, `is_checked`,
  NULL, `modified`, `created_at`, `updated_at`
FROM `rice_households`
WHERE `is_claimed` = 1
ON DUPLICATE KEY UPDATE
  `household_code` = VALUES(`household_code`),
  `household_code_prefix` = VALUES(`household_code_prefix`),
  `household_code_number` = VALUES(`household_code_number`),
  `household_name` = VALUES(`household_name`),
  `address` = VALUES(`address`),
  `status` = VALUES(`status`),
  `is_checked` = VALUES(`is_checked`),
  `modified` = VALUES(`modified`),
  `created_at` = VALUES(`created_at`),
  `updated_at` = VALUES(`updated_at`);
UPDATE `rice_claimed_households`
SET
  `last_name` = NULLIF(TRIM(SUBSTRING_INDEX(`household_name`, ',', 1)), ''),
  `first_name` = CASE
    WHEN `household_name` LIKE '%,%'
    THEN NULLIF(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(`household_name`, ',', 2), ',', -1)), '')
    ELSE NULL
  END,
  `middle_name` = CASE
    WHEN LENGTH(`household_name`) - LENGTH(REPLACE(`household_name`, ',', '')) >= 2
    THEN NULLIF(
      TRIM(SUBSTRING(
        `household_name`,
        LOCATE(',', `household_name`, LOCATE(',', `household_name`) + 1) + 1
      )),
      ''
    )
    ELSE NULL
  END;
CREATE TABLE IF NOT EXISTS `rice_next_wave_claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `household_id` int NOT NULL,
  `claimant_name` varchar(150) DEFAULT NULL,
  `e_signature` longtext,
  `claim_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `verifier_name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rice_next_wave_household_claim` (`household_id`),
  KEY `idx_rice_next_wave_claim_date` (`claim_date`),
  CONSTRAINT `rice_next_wave_claims_household_fk` FOREIGN KEY (`household_id`) REFERENCES `rice_claimed_households` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;