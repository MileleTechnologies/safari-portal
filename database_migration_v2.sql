-- ============================================
-- Safari Portal v2 — Payment Approval Workflow
-- Migration Script (upgrade from v1)
-- ============================================

USE safari_portal;

-- Drop old payments table and recreate with new schema
-- WARNING: Back up your data first if you have real payments!
DROP TABLE IF EXISTS payments;

CREATE TABLE payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    proof_file       VARCHAR(255) DEFAULT NULL,
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    payment_date     DATE NOT NULL,
    submitted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at      TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ensure uploads folder setting exists
INSERT INTO settings (setting_key, setting_value)
VALUES ('uploads_path', 'uploads/')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Seed sample payments for test users (pending + approved mix)
INSERT INTO payments (user_id, amount, reference_number, proof_file, status, payment_date) VALUES
(1, 2000.00, 'REF-001-A', NULL, 'approved',  CURDATE()),
(1, 1000.00, 'REF-001-B', NULL, 'pending',   CURDATE()),
(2, 5000.00, 'REF-002-A', NULL, 'approved',  CURDATE()),
(3, 1500.00, 'REF-003-A', NULL, 'approved',  CURDATE()),
(3,  500.00, 'REF-003-B', NULL, 'pending',   CURDATE()),
(4,  500.00, 'REF-004-A', NULL, 'rejected',  CURDATE()),
(4, 1000.00, 'REF-004-B', NULL, 'pending',   CURDATE()),
(5,  800.00, 'REF-005-A', NULL, 'pending',   CURDATE());
