-- ============================================
-- Safari Contribution Portal v3
-- Full Database Setup (Fresh Install)
-- Includes: phone_number, whatsapp_invited
-- ============================================

CREATE DATABASE IF NOT EXISTS safari_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE safari_portal;

CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(100) NOT NULL,
    work_id          VARCHAR(50)  NOT NULL UNIQUE,
    phone_number     VARCHAR(20)  NOT NULL DEFAULT '',
    whatsapp_invited TINYINT(1)   NOT NULL DEFAULT 0,
    target_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100) NOT NULL DEFAULT '',
    proof_file       VARCHAR(255) DEFAULT NULL,
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    payment_date     DATE NOT NULL,
    submitted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at      TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL
);

INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_group_link',        ''),
('trip_name',                  'Safari Adventure Trip'),
('trip_date',                  ''),
('uploads_path',               'uploads/'),
('sms_notifications_enabled',  '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Test Users (with phone numbers)
INSERT INTO users (full_name, work_id, phone_number, target_amount) VALUES
('Alice Mwangi',  'EMP001', '+254712000001', 5000.00),
('Brian Ochieng', 'EMP002', '+254712000002', 5000.00),
('Carol Njeri',   'EMP003', '+254712000003', 3500.00),
('David Kamau',   'EMP004', '+254712000004', 5000.00),
('Eva Wambui',    'EMP005', '+254712000005', 4000.00)
ON DUPLICATE KEY UPDATE full_name = full_name;

-- Sample payments (mixed statuses)
INSERT INTO payments (user_id, amount, reference_number, status, payment_date) VALUES
(1, 2000.00, 'REF-001-A', 'approved', CURDATE()),
(1, 1000.00, 'REF-001-B', 'pending',  CURDATE()),
(2, 5000.00, 'REF-002-A', 'approved', CURDATE()),
(3, 1500.00, 'REF-003-A', 'approved', CURDATE()),
(3,  500.00, 'REF-003-B', 'pending',  CURDATE()),
(4,  500.00, 'REF-004-A', 'rejected', CURDATE()),
(4, 1000.00, 'REF-004-B', 'pending',  CURDATE()),
(5,  800.00, 'REF-005-A', 'pending',  CURDATE());

-- Mark users who have approved payments as wa-invited for demo purposes
UPDATE users SET whatsapp_invited=1 WHERE id IN (1,2,3);
