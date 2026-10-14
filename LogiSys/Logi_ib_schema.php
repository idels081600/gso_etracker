<?php

function logi_ensure_non_ib_delivery_schema(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS ib_non_ib_delivery_lines (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        delivery_id BIGINT UNSIGNED NOT NULL,
        ib_id BIGINT UNSIGNED NOT NULL,
        office_group_id BIGINT UNSIGNED NOT NULL,
        item_id INT NULL,
        item_no VARCHAR(500) NOT NULL,
        item_name VARCHAR(500) NOT NULL,
        unit VARCHAR(50) NULL,
        quantity INT UNSIGNED NOT NULL,
        unit_price DECIMAL(15,2) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        inventory_transaction_id INT NULL,
        reversal_transaction_id INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ib_non_ib_delivery_item (delivery_id, item_id),
        KEY idx_ib_non_ib_header (ib_id, office_group_id),
        KEY idx_ib_non_ib_delivery (delivery_id),
        KEY idx_ib_non_ib_item (item_id),
        CONSTRAINT fk_ib_non_ib_delivery FOREIGN KEY (delivery_id) REFERENCES ib_deliveries(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ib_non_ib_header FOREIGN KEY (ib_id) REFERENCES ib_headers(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ib_non_ib_group FOREIGN KEY (office_group_id) REFERENCES ib_office_groups(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ib_non_ib_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ib_non_ib_inventory_tx FOREIGN KEY (inventory_transaction_id) REFERENCES inventory_transactions(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ib_non_ib_reversal_tx FOREIGN KEY (reversal_transaction_id) REFERENCES inventory_transactions(id) ON DELETE RESTRICT,
        CONSTRAINT chk_ib_non_ib_qty CHECK (quantity > 0),
        CONSTRAINT chk_ib_non_ib_price CHECK (unit_price >= 0)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize non-IB delivery records: ' . $conn->error);
    }

    $columnResult = $conn->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ib_non_ib_delivery_lines' AND COLUMN_NAME='item_id' LIMIT 1");
    $column = $columnResult ? $columnResult->fetch_assoc() : null;
    if ($column && strtoupper((string)$column['IS_NULLABLE']) !== 'YES') {
        if (!$conn->query('ALTER TABLE ib_non_ib_delivery_lines MODIFY item_id INT NULL')) {
            throw new RuntimeException('Unable to allow monitoring-only non-inventory IB items: ' . $conn->error);
        }
    }
}
