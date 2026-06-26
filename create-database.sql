-- Create Database for Lumen CRM
CREATE DATABASE IF NOT EXISTS lumen_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user if needed (optional, you can use root)
-- CREATE USER IF NOT EXISTS 'lumen_user'@'localhost' IDENTIFIED BY 'lumen_password';
-- GRANT ALL PRIVILEGES ON lumen_crm.* TO 'lumen_user'@'localhost';
-- FLUSH PRIVILEGES;

-- Use the database
USE lumen_crm;

-- Show success message
SELECT 'Database lumen_crm created successfully!' AS Message;
