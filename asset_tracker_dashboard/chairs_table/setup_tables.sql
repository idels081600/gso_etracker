-- ======================================================
-- Chairs & Table Tracking - Database Setup
-- Run this SQL to create the required tables
-- ======================================================

-- 1. Equipment Types (lookup table for chair colors & table shapes)
CREATE TABLE IF NOT EXISTS `equipment_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `category` ENUM('Chair','Table') NOT NULL,
    `subtype_name` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `total_qty` INT NOT NULL DEFAULT 0,
    `available_qty` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data: Chair colors
INSERT INTO `equipment_types` (`category`, `subtype_name`, `display_name`, `total_qty`, `available_qty`) VALUES
('Chair', 'White', 'White Monoblock Chair', 100, 100),
('Chair', 'Blue', 'Blue Monoblock Chair', 60, 60),
('Chair', 'Red', 'Red Monoblock Chair', 40, 40),
('Chair', 'Black', 'Black Monoblock Chair', 50, 50);

-- Seed data: Table shapes
INSERT INTO `equipment_types` (`category`, `subtype_name`, `display_name`, `total_qty`, `available_qty`) VALUES
('Table', 'Round', 'Round Table', 20, 20),
('Table', 'Rectangle', 'Rectangle Table', 25, 25),
('Table', 'Square', 'Square Table', 15, 15);

-- 2. Deployments (main request table)
CREATE TABLE IF NOT EXISTS `deployments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `contact_no` VARCHAR(30) NOT NULL,
    `purpose` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `address` VARCHAR(500) NOT NULL,
    `status` ENUM('Pending','Deployed','For Retrieval','Retrieved','Long Term') NOT NULL DEFAULT 'Pending',
    `date` DATE NOT NULL,
    `retrieval_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Deployment Items (items within a deployment)
CREATE TABLE IF NOT EXISTS `deployment_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `deployment_id` INT NOT NULL,
    `equipment_type_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`deployment_id`) REFERENCES `deployments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;