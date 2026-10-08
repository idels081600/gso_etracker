-- Add TCC to the shared office master used by IB Monitoring.
-- Safe to rerun because the insert only occurs when TCC is absent.

INSERT INTO office_balances (
    office_name,
    department,
    contact_person,
    contact_email,
    contact_phone
)
SELECT
    'TCC',
    NULL,
    NULL,
    NULL,
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM office_balances
    WHERE UPPER(TRIM(office_name)) = 'TCC'
);
