CREATE TABLE IF NOT EXISTS passlip_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_username VARCHAR(191) NULL,
    actor_role VARCHAR(100) NULL,
    action VARCHAR(100) NOT NULL,
    request_id INT NULL,
    summary TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_created (action, created_at),
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS passlip_export_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_username VARCHAR(191) NULL,
    export_type VARCHAR(100) NOT NULL,
    filters JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_export_created (export_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS passlip_system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO passlip_system_settings (setting_key, setting_value)
VALUES
    ('timezone', 'Asia/Manila'),
    ('personal_request_max_minutes', '30'),
    ('official_request_max_minutes', '240'),
    ('overdue_threshold_minutes', '60')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
