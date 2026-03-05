<?php
// Document Tracking System - Database Setup
// Run this file once to create the doc_tracker table

error_reporting(0);
ini_set('display_errors', 0);

$servername = "157.245.193.124";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "docu_tracker";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// SQL to create doc_tracker table
$sql = "CREATE TABLE IF NOT EXISTS `doc_tracker` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_no` (`tracking_no`),
  KEY `barcode` (`barcode`),
  KEY `doc_direction` (`doc_direction`),
  KEY `status` (`status`),
  KEY `date_received` (`date_received`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (mysqli_query($conn, $sql)) {
    echo "<h2 style='color: green;'>✓ Table 'doc_tracker' created successfully or already exists!</h2>";
    echo "<p>You can now use the Document Tracking System.</p>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Error creating table: " . mysqli_error($conn) . "</h2>";
}

mysqli_close($conn);
?>