-- SQL Setup for Fuel Subsidy System

-- 1. Main Table - Tricycle Records
CREATE TABLE IF NOT EXISTS tricycle_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tricycle_no VARCHAR(10) NOT NULL UNIQUE,
    driver_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    total_vouchers INT DEFAULT 10,
    claimed_vouchers INT DEFAULT 0,
    status ENUM('Active', 'Not Active') DEFAULT 'Active',
    last_claim_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Voucher Claims Table
CREATE TABLE IF NOT EXISTS voucher_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tricycle_id INT NOT NULL,
    voucher_number INT NOT NULL,
    claimant_name VARCHAR(100) NULL,
    e_signature LONGTEXT NULL,
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tricycle_id) REFERENCES tricycle_records(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voucher (tricycle_id, voucher_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Sample Data
INSERT INTO tricycle_records (tricycle_no, driver_name, address, contact_number, total_vouchers, claimed_vouchers, status, last_claim_date) VALUES
('0001', 'John Doe', '123 Main Street, Poblacion', '09123456789', 10, 8, 'Active', '2026-04-04'),
('0002', 'Maria Cruz', '456 Rizal St, San Isidro', '09234567890', 10, 10, 'Active', '2026-04-05'),
('0003', 'Pedro Santos', '789 Mabini Ave, Batong Malake', '09345678901', 10, 5, 'Not Active', '2026-04-06');