-- ============================================
-- Safari Portal — Migration to v3
-- Run this if upgrading from v1 or v2
-- ============================================
USE safari_portal;

-- Add phone_number column if missing
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone_number     VARCHAR(20)  NOT NULL DEFAULT '' AFTER work_id,
  ADD COLUMN IF NOT EXISTS whatsapp_invited TINYINT(1)   NOT NULL DEFAULT 0  AFTER phone_number;

-- Add v2 payment columns if missing
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) NOT NULL DEFAULT '' AFTER amount,
  ADD COLUMN IF NOT EXISTS proof_file       VARCHAR(255) DEFAULT NULL AFTER reference_number,
  ADD COLUMN IF NOT EXISTS status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER proof_file,
  ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) DEFAULT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS submitted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_date,
  ADD COLUMN IF NOT EXISTS reviewed_at      TIMESTAMP NULL DEFAULT NULL AFTER submitted_at;

-- Mark legacy payments as approved
UPDATE payments SET status='approved', reference_number=CONCAT('LEGACY-',id) WHERE reference_number='';

-- Add SMS setting
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('sms_notifications_enabled','1');
