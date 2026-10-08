CREATE TABLE IF NOT EXISTS ib_headers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ib_no VARCHAR(100) NOT NULL,
    status ENUM('DRAFT','ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by VARCHAR(100) NOT NULL,
    activated_by VARCHAR(100) NULL,
    cancelled_by VARCHAR(100) NULL,
    cancel_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activated_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ib_headers_no (ib_no),
    KEY idx_ib_headers_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ib_office_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ib_id BIGINT UNSIGNED NOT NULL,
    office_id INT NOT NULL,
    office_name VARCHAR(255) NOT NULL,
    description VARCHAR(500) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ib_groups_ib (ib_id, sort_order),
    CONSTRAINT fk_ib_groups_header FOREIGN KEY (ib_id) REFERENCES ib_headers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ib_groups_office FOREIGN KEY (office_id) REFERENCES office_balances(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ib_item_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    item_id INT NULL,
    item_no VARCHAR(500) NOT NULL,
    item_name VARCHAR(500) NOT NULL,
    unit VARCHAR(50) NULL,
    add_to_inventory TINYINT(1) NOT NULL DEFAULT 1,
    planned_quantity INT UNSIGNED NOT NULL,
    delivered_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit_price DECIMAL(15,2) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ib_group_item (group_id, item_id),
    KEY idx_ib_lines_group (group_id, sort_order),
    KEY idx_ib_lines_item (item_id),
    CONSTRAINT fk_ib_lines_group FOREIGN KEY (group_id) REFERENCES ib_office_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_ib_lines_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    CONSTRAINT chk_ib_line_qty CHECK (planned_quantity > 0),
    CONSTRAINT chk_ib_line_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ib_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ib_id BIGINT UNSIGNED NOT NULL,
    delivery_date DATE NOT NULL,
    notes VARCHAR(500) NULL,
    status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
    idempotency_token CHAR(36) NOT NULL,
    posted_by VARCHAR(100) NOT NULL,
    posted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_by VARCHAR(100) NULL,
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ib_delivery_token (idempotency_token),
    KEY idx_ib_deliveries_header (ib_id, delivery_date, id),
    CONSTRAINT fk_ib_delivery_header FOREIGN KEY (ib_id) REFERENCES ib_headers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ib_delivery_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id BIGINT UNSIGNED NOT NULL,
    ib_item_line_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    inventory_transaction_id INT NULL,
    reversal_transaction_id INT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ib_delivery_line (delivery_id, ib_item_line_id),
    KEY idx_ib_delivery_lines_line (ib_item_line_id),
    CONSTRAINT fk_ib_delivery_lines_delivery FOREIGN KEY (delivery_id) REFERENCES ib_deliveries(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ib_delivery_lines_item_line FOREIGN KEY (ib_item_line_id) REFERENCES ib_item_lines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ib_delivery_inventory_tx FOREIGN KEY (inventory_transaction_id) REFERENCES inventory_transactions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ib_reversal_inventory_tx FOREIGN KEY (reversal_transaction_id) REFERENCES inventory_transactions(id) ON DELETE RESTRICT,
    CONSTRAINT chk_ib_delivery_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ib_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ib_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor VARCHAR(100) NOT NULL,
    details_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ib_activity_header (ib_id, created_at, id),
    CONSTRAINT fk_ib_activity_header FOREIGN KEY (ib_id) REFERENCES ib_headers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
