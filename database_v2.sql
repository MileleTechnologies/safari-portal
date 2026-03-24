-- ============================================
-- Safari Contribution Portal v2
-- Full Database Setup (Fresh Install)
-- ============================================

CREATE DATABASE IF NOT EXISTS safari_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE safari_portal;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    work_id       VARCHAR(50)  NOT NULL UNIQUE,
    target_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    proof_file       VARCHAR(255)  DEFAULT NULL,
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255)  DEFAULT NULL,
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
('whatsapp_group_link', ''),
('trip_name',           'Safari Adventure Trip'),
('trip_date',           ''),
('uploads_path',        'uploads/')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO users (full_name, work_id, target_amount) VALUES
('Alice Mwangi',  'EMP001', 5000.00),
('Brian Ochieng', 'EMP002', 5000.00),
('Carol Njeri',   'EMP003', 3500.00),
('David Kamau',   'EMP004', 5000.00),
('Eva Wambui',    'EMP005', 4000.00)
ON DUPLICATE KEY UPDATE full_name = full_name;

INSERT INTO payments (user_id, amount, reference_number, proof_file, status, payment_date) VALUES
(1, 2000.00, 'REF-001-A', NULL, 'approved', CURDATE()),
(1, 1000.00, 'REF-001-B', NULL, 'pending',  CURDATE()),
(2, 5000.00, 'REF-002-A', NULL, 'approved', CURDATE()),
(3, 1500.00, 'REF-003-A', NULL, 'approved', CURDATE()),
(3,  500.00, 'REF-003-B', NULL, 'pending',  CURDATE()),
(4,  500.00, 'REF-004-A', NULL, 'rejected', CURDATE()),
(4, 1000.00, 'REF-004-B', NULL, 'pending',  CURDATE()),
(5,  800.00, 'REF-005-A', NULL, 'pending',  CURDATE());
