-- Update to Billings table for version X.Y (add description, remove payment_method and transaction_id)

-- Add the description column
ALTER TABLE Billings
ADD COLUMN description TEXT NULL AFTER status;

-- Remove the payment_method column if it exists
-- Note: Some DBs might require checking if column exists first, or use IF EXISTS if supported
-- For MySQL:
-- ALTER TABLE Billings DROP COLUMN IF EXISTS payment_method;
-- For PostgreSQL:
-- ALTER TABLE Billings DROP COLUMN IF EXISTS payment_method;
-- For SQLite: SQLite does not directly support DROP COLUMN in older versions.
-- A common workaround is to rename table, create new table, copy data, drop old table.
-- However, for this project context, we'll assume a direct DROP is possible or will be handled.
ALTER TABLE Billings DROP COLUMN payment_method;

-- Remove the transaction_id column if it exists
-- Similar DB specific notes apply as for payment_method
ALTER TABLE Billings DROP COLUMN transaction_id;

-- Example of how to check if a column exists in MySQL before dropping (optional, for robust scripts)
-- SET @s = (SELECT IF(
--     (SELECT COUNT(*)
--     FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE table_name = 'Billings'
--     AND table_schema = DATABASE()
--     AND column_name = 'payment_method'
--     ) > 0,
--     "ALTER TABLE Billings DROP COLUMN payment_method;",
--     "SELECT 1;"
-- ));
-- PREPARE stmt FROM @s;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- SET @s = (SELECT IF(
--     (SELECT COUNT(*)
--     FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE table_name = 'Billings'
--     AND table_schema = DATABASE()
--     AND column_name = 'transaction_id'
--     ) > 0,
--     "ALTER TABLE Billings DROP COLUMN transaction_id;",
--     "SELECT 1;"
-- ));
-- PREPARE stmt FROM @s;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- Consider adding 'partially_paid' to allowed enum/check constraint for Billings.status if using one.
-- For example, in PostgreSQL:
-- ALTER TABLE Billings DROP CONSTRAINT billings_status_check; -- Assuming one exists
-- ALTER TABLE Billings ADD CONSTRAINT billings_status_check CHECK (status IN ('Pending', 'Paid', 'Overdue', 'Cancelled', 'Partially Paid'));

-- No changes needed for Payments table based on current requirements.
-- The transaction_id UNIQUE constraint on Payments table is kept as is.
-- If it needs to be non-unique or composite unique, that would be a separate alter statement.
-- Example: ALTER TABLE Payments DROP CONSTRAINT transaction_id; (if it was named transaction_id)
-- Example: ALTER TABLE Payments ADD CONSTRAINT uq_billing_transaction UNIQUE (billing_id, transaction_id);
-- For now, Payments.transaction_id UNIQUE is fine.
SELECT 'Billing and Payments table schema review complete. Necessary ALTER statements for Billings table included above.' AS script_status;
