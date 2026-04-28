-- =====================================================
-- BATCH VOUCHER REDEMPTION SYSTEM - DATABASE CHANGES
-- =====================================================

-- 1. Add batch_id to food_voucher_claims to track which batch a voucher belongs to
ALTER TABLE food_voucher_claims 
    ADD COLUMN batch_id INT NULL AFTER area,
    ADD INDEX idx_batch_id (batch_id);

-- 2. Add new columns to food_redemption_batches for batch tracking
ALTER TABLE food_redemption_batches 
    ADD COLUMN batch_number VARCHAR(50) UNIQUE AFTER id,
    ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_vouchers,
    ADD COLUMN status ENUM('pending','completed','cancelled') DEFAULT 'pending' AFTER total_amount,
    ADD COLUMN created_by VARCHAR(100) AFTER status,
    ADD COLUMN redeemed_at DATETIME NULL AFTER created_at,
    ADD COLUMN remarks TEXT NULL AFTER redeemed_at;

-- 3. Add new columns to food_redemption_items for detailed voucher tracking
ALTER TABLE food_redemption_items 
    ADD COLUMN amount DECIMAL(10,2) DEFAULT 200.00 AFTER voucher_id,
    ADD COLUMN beneficiary_name VARCHAR(255) NULL AFTER amount,
    ADD COLUMN beneficiary_code VARCHAR(50) NULL AFTER beneficiary_name,
    ADD COLUMN voucher_number INT NULL AFTER beneficiary_code;

-- =====================================================
-- OPTIONAL: Verify existing tables structure
-- =====================================================

-- DESCRIBE food_voucher_claims;
-- DESCRIBE food_redemption_batches;
-- DESCRIBE food_redemption_items;
-- DESCRIBE food_vendors;

-- =====================================================
-- OPTIONAL: Check existing data
-- =====================================================

-- SELECT COUNT(*) as total_claims FROM food_voucher_claims;
-- SELECT COUNT(*) as total_batches FROM food_redemption_batches;
-- SELECT COUNT(*) as total_items FROM food_redemption_items;
-- SELECT COUNT(*) as total_vendors FROM food_vendors;