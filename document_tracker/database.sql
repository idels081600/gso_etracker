-- Database table for Document Tracking System
-- Run this SQL to create the doc_tracker table

CREATE TABLE IF NOT EXISTS `doc_tracker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_no` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `date_received` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `date_released` date DEFAULT NULL,
  `date_deadline` date DEFAULT NULL COMMENT 'Return deadline for outgoing documents',
  `destination` varchar(255) DEFAULT NULL COMMENT 'Destination for outgoing documents',
  `doc_direction` enum('incoming','outgoing') NOT NULL DEFAULT 'incoming' COMMENT 'Document direction: incoming or outgoing',
  `office` varchar(32) NOT NULL DEFAULT 'ASSET' COMMENT 'Owning office',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_no` (`tracking_no`),
  KEY `barcode` (`barcode`),
  KEY `doc_direction` (`doc_direction`),
  KEY `status` (`status`),
  KEY `date_received` (`date_received`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample data for testing (optional)
-- INSERT INTO `doc_tracker` (`tracking_no`, `barcode`, `description`, `doc_type`, `date_received`, `status`, `date_released`, `date_deadline`, `destination`, `doc_direction`) VALUES
-- ('INC-2026-0001', 'BC001', 'Sample incoming document', 'Letter', '2026-03-05', 'Pending', NULL, NULL, NULL, 'incoming'),
-- ('OUT-2026-0001', 'BC002', 'Sample outgoing document', 'Memo', '2026-03-05', 'In Transit', NULL, '2026-03-12', 'City Hall', 'outgoing');