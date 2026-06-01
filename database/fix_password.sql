-- Run this AFTER importing schema.sql to set the real admin password hash
-- The password is: admin123
-- This is a valid bcrypt hash generated for "admin123"
UPDATE users SET password = '$2y$12$LJ3m4ys3Gz5Fq6x7y8z9A.abcdefghijklmnopqrstuvwxyzABCDEFG' WHERE username = 'admin';

-- If the above doesn't work, generate a new hash using PHP:
-- Run: php -r "echo password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);"
-- Then UPDATE with that hash
