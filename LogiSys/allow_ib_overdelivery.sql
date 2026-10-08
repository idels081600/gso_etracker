-- Allow actual delivery quantities to exceed the quantity planned in the IB.
-- Safe to rerun: the dynamic statement only drops the constraint when present.

SET @has_ib_delivery_cap = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ib_item_lines'
      AND CONSTRAINT_NAME = 'chk_ib_line_delivered'
      AND CONSTRAINT_TYPE = 'CHECK'
);

SET @drop_ib_delivery_cap = IF(
    @has_ib_delivery_cap > 0,
    'ALTER TABLE ib_item_lines DROP CHECK chk_ib_line_delivered',
    'SELECT 1'
);

PREPARE ib_overdelivery_stmt FROM @drop_ib_delivery_cap;
EXECUTE ib_overdelivery_stmt;
DEALLOCATE PREPARE ib_overdelivery_stmt;
