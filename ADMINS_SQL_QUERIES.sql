-- ===========================================
-- ADMINS TABLE - SAMPLE SQL QUERIES
-- Database: Noteria
-- Created: 2025-11-22
-- ===========================================

-- 1. VIEW ALL ADMINISTRATORS
SELECT id, email, emri, role, status, created_at, last_login 
FROM admins 
ORDER BY created_at DESC;

-- 2. VIEW ACTIVE ADMINISTRATORS ONLY
SELECT id, email, emri, role, created_at 
FROM admins 
WHERE status = 'active' 
ORDER BY emri;

-- 3. VIEW ADMINISTRATORS BY ROLE
SELECT id, email, emri, role, status 
FROM admins 
WHERE role = 'super_admin'
ORDER BY created_at DESC;

-- 4. COUNT ADMINISTRATORS BY ROLE
SELECT role, COUNT(*) as total 
FROM admins 
GROUP BY role 
ORDER BY total DESC;

-- 5. COUNT ADMINISTRATORS BY STATUS
SELECT status, COUNT(*) as total 
FROM admins 
GROUP BY status;

-- 6. FIND ADMINISTRATOR BY EMAIL
SELECT * FROM admins 
WHERE email = 'admin@noteria.al' 
LIMIT 1;

-- 7. UPDATE ADMINISTRATOR STATUS
UPDATE admins 
SET status = 'inactive' 
WHERE email = 'admin@noteria.al';

-- 8. UPDATE ADMINISTRATOR ROLE
UPDATE admins 
SET role = 'moderator' 
WHERE id = 2;

-- 9. UPDATE LAST LOGIN
UPDATE admins 
SET last_login = NOW() 
WHERE id = 1;

-- 10. CHANGE ADMINISTRATOR PASSWORD
UPDATE admins 
SET password = '$2y$12$HASH_OF_NEW_PASSWORD_HERE' 
WHERE email = 'admin@noteria.al';

-- 11. ENABLE 2FA FOR ADMINISTRATOR
UPDATE admins 
SET is_2fa_enabled = TRUE 
WHERE email = 'admin@noteria.al';

-- 12. DISABLE 2FA FOR ADMINISTRATOR
UPDATE admins 
SET is_2fa_enabled = FALSE 
WHERE email = 'admin@noteria.al';

-- 13. VIEW ADMINISTRATORS WITH 2FA ENABLED
SELECT id, email, emri, is_2fa_enabled 
FROM admins 
WHERE is_2fa_enabled = TRUE;

-- 14. VIEW RECENT LOGIN ACTIVITY
SELECT id, email, emri, last_login 
FROM admins 
WHERE last_login IS NOT NULL 
ORDER BY last_login DESC 
LIMIT 10;

-- 15. DELETE ADMINISTRATOR
DELETE FROM admins 
WHERE id = 2;

-- 16. ADD ADMINISTRATOR (with hashed password)
INSERT INTO admins (email, password, emri, status, role, phone) 
VALUES (
    'newadmin@noteria.al',
    '$2y$12$HASH_OF_PASSWORD_HERE',
    'New Administrator',
    'active',
    'admin',
    '+355692123456'
);

-- 17. VIEW ADMINISTRATORS CREATED IN LAST 7 DAYS
SELECT id, email, emri, role, status, created_at 
FROM admins 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;

-- 18. VIEW SUSPENDED ADMINISTRATORS
SELECT id, email, emri, role, updated_at 
FROM admins 
WHERE status = 'suspended'
ORDER BY updated_at DESC;

-- 19. RESTORE ADMINISTRATOR (change from suspended to active)
UPDATE admins 
SET status = 'active' 
WHERE status = 'suspended' 
AND email = 'admin@noteria.al';

-- 20. VIEW TOTAL ADMINISTRATOR STATISTICS
SELECT 
    COUNT(*) as total_admins,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_admins,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_admins,
    SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins,
    SUM(CASE WHEN is_2fa_enabled = TRUE THEN 1 ELSE 0 END) as admins_with_2fa
FROM admins;

-- 21. CHECK ADMINISTRATORS WITHOUT PHONE NUMBER
SELECT id, email, emri, role 
FROM admins 
WHERE phone IS NULL;

-- 22. ADD PHONE NUMBER TO ADMINISTRATOR
UPDATE admins 
SET phone = '+355692123456' 
WHERE email = 'admin@noteria.al';

-- 23. VIEW ALL ADMINISTRATORS WITH CONTACT INFO
SELECT 
    id, 
    email, 
    emri, 
    phone, 
    role, 
    status 
FROM admins 
ORDER BY emri;

-- 24. SEARCH ADMINISTRATORS (by name or email)
SELECT id, email, emri, role, status 
FROM admins 
WHERE emri LIKE '%admin%' 
   OR email LIKE '%admin%'
ORDER BY emri;

-- 25. EXPORT ADMINISTRATOR EMAILS
SELECT email FROM admins 
WHERE status = 'active'
ORDER BY email;
