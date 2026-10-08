-- Preserve legacy inventory history while allowing an explicitly authorized
-- IB number to be used once in IB Monitoring.

CREATE TABLE IF NOT EXISTS ib_reference_reuse_authorizations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ib_no VARCHAR(100) NOT NULL,
    legacy_transaction_count INT UNSIGNED NOT NULL DEFAULT 0,
    reason VARCHAR(500) NOT NULL,
    authorized_by VARCHAR(100) NOT NULL,
    authorized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_by_ib_id BIGINT UNSIGNED NULL,
    used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ib_reference_reuse_no (ib_no),
    UNIQUE KEY uq_ib_reference_reuse_used_ib (used_by_ib_id),
    CONSTRAINT fk_ib_reference_reuse_header
        FOREIGN KEY (used_by_ib_id) REFERENCES ib_headers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ib_reference_reuse_authorizations (
    ib_no,
    legacy_transaction_count,
    reason,
    authorized_by
)
SELECT
    '2510-545',
    50,
    'Authorized for one-time reuse. Matching December 1, 2025 legacy import rows were duplicated, had no user identity, and posted no balance change (0 to 0). Original rows remain unchanged.',
    'ADMIN_SAP'
WHERE NOT EXISTS (
    SELECT 1
    FROM ib_reference_reuse_authorizations
    WHERE ib_no = '2510-545'
);
