-- Verify Customer Management Setup
USE billing_system;

-- Check if all tables exist
SELECT 
    'customers' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = 'billing_system' AND table_name = 'customers'

UNION ALL

SELECT 
    'purchases' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = 'billing_system' AND table_name = 'purchases'

UNION ALL

SELECT 
    'payments' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = 'billing_system' AND table_name = 'payments';

-- Check table structures
DESCRIBE customers;
DESCRIBE purchases;
DESCRIBE payments;

-- Check sample data
SELECT 'customers' as table_name, COUNT(*) as record_count FROM customers
UNION ALL
SELECT 'purchases' as table_name, COUNT(*) as record_count FROM purchases
UNION ALL
SELECT 'payments' as table_name, COUNT(*) as record_count FROM payments;

-- Check for any data inconsistencies
SELECT 
    customer_id,
    name,
    total_amount,
    paid_amount,
    pending_amount,
    payment_status,
    CASE 
        WHEN total_amount != (paid_amount + pending_amount) THEN 'INCONSISTENT'
        ELSE 'OK'
    END as amount_check
FROM customers
WHERE total_amount != (paid_amount + pending_amount);
