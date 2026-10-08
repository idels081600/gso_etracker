-- Add the City Sports office to the shared office master used by IB Monitoring.
-- This migration is idempotent because office_balances does not currently
-- enforce a unique key on office_name.

INSERT INTO office_balances (
    office_name,
    department,
    contact_person,
    contact_email,
    contact_phone
)
SELECT
    'CITY_SPORTS',
    NULL,
    NULL,
    NULL,
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM office_balances
    WHERE UPPER(TRIM(office_name)) = 'CITY_SPORTS'
);
