-- ============================================
-- Safari Contribution Portal - Database Setup
-- ============================================

CREATE DATABASE IF NOT EXISTS safari_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE safari_portal;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    work_id VARCHAR(50) NOT NULL UNIQUE,
    target_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL
);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_group_link', ''),
('trip_name', 'Safari Adventure Trip'),
('trip_date', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================
-- Example Test Users (optional)
-- ============================================

INSERT INTO users (full_name, work_id, target_amount) VALUES
('Alice Mwangi',   'EMP001', 5000.00),
('Brian Ochieng',  'EMP002', 5000.00),
('Carol Njeri',    'EMP003', 3500.00),
('David Kamau',    'EMP004', 5000.00),
('Eva Wambui',     'EMP005', 4000.00)
ON DUPLICATE KEY UPDATE full_name = full_name;

-- Sample payments for test users
INSERT INTO payments (user_id, amount, payment_date) VALUES
(1, 2000.00, CURDATE()),
(1, 1000.00, CURDATE()),
(2, 5000.00, CURDATE()),
(3, 1500.00, CURDATE()),
(4, 500.00,  CURDATE())
ON DUPLICATE KEY UPDATE user_id = user_id;
