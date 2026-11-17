-- ==============================================================================
-- DATABASE SCHEMA MIGRATION: LAUNDRYKU
-- Modules: Auth, RBAC, Customer Profile, Address Management, Google OAuth 2.0, Order Laundry & Modul Kurir Pickup
-- Framework: PHP Pure MVC + PDO MySQL
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `marketplace_laundry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `marketplace_laundry`;

-- Disable foreign key checks for clean migration run
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `payment_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `courier_locations`;
DROP TABLE IF EXISTS `order_status_histories`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `stores`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABEL ROLES (6 Role Sistem)
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Key role: owner, admin_owner, owner_toko, admin_toko, kurir, customer',
    `display_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL USERS (Mendukung Google OAuth 2.0 via google_id)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `store_id` INT NULL DEFAULT NULL COMMENT 'ID Toko untuk role admin_toko',
    `google_id` VARCHAR(255) NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `avatar` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `wa_notifications_enabled` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL ADDRESSES (Alamat Lengkap Pelanggan & Koordinat GPS)
CREATE TABLE `addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(50) NOT NULL COMMENT 'Rumah, Kantor, Toko, dll',
    `address_line` TEXT NOT NULL,
    `province` VARCHAR(100) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `district` VARCHAR(100) NOT NULL,
    `village` VARCHAR(100) NOT NULL,
    `rt` VARCHAR(10) NOT NULL,
    `rw` VARCHAR(10) NOT NULL,
    `detail_address` TEXT NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL STORES (Toko Laundry)
CREATE TABLE `stores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'ID Owner Toko',
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `address` TEXT NOT NULL,
    `latitude` DECIMAL(10, 8) NULL DEFAULT -6.22974650,
    `longitude` DECIMAL(11, 8) NULL DEFAULT 106.81644900,
    `phone` VARCHAR(20) NOT NULL,
    `rating` DECIMAL(3, 2) DEFAULT 4.80,
    `working_hours` VARCHAR(100) DEFAULT '08:00 - 21:00',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_stores_owner` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL SERVICES (Layanan & Tarif Laundry)
CREATE TABLE `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(12, 2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL COMMENT 'kg, pcs, meter, pasang',
    `duration_hours` INT DEFAULT 24,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_services_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL ORDERS (Transaksi Pesanan & 13 Status Laundry)
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `address_id` INT NOT NULL,
    `courier_id` INT NULL COMMENT 'Kurir penjemput/pengantar',
    `estimated_weight` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `estimated_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `actual_weight` DECIMAL(8, 2) NULL COMMENT 'Diisi toko setelah penimbangan',
    `unit_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(12, 2) NULL COMMENT 'actual_weight * unit_price',
    `additional_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal + additional_fee - discount',
    `notes` TEXT NULL,
    `payment_method` ENUM('cash', 'online') NOT NULL DEFAULT 'cash',
    `delivery_type` ENUM('pickup', 'delivery') NOT NULL DEFAULT 'delivery' COMMENT 'pickup: Ambil di Toko, delivery: Diantar Kurir',
    `payment_status` ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    `status` ENUM(
        'pending',
        'assigned_to_courier',
        'courier_on_the_way',
        'picked_up',
        'arrived_at_store',
        'weighed',
        'waiting_payment',
        'paid',
        'processing',
        'ready',
        'out_for_delivery',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
    CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
    CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
    CONSTRAINT `fk_orders_courier` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL ORDER_ITEMS
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `service_name` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(12, 2) NOT NULL,
    `quantity` DECIMAL(8, 2) NOT NULL,
    `subtotal` DECIMAL(12, 2) NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABEL PAYMENTS (Mendukung Pembayaran QRIS Online & Gateway)
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'qris',
    `gateway` VARCHAR(50) NOT NULL DEFAULT 'mock_gateway',
    `gateway_reference` VARCHAR(100) NULL UNIQUE,
    `qr_string` TEXT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `status` ENUM('pending', 'paid', 'expired', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `expired_at` DATETIME NULL,
    `paid_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8b. TABEL PAYMENT_LOGS (Audit Trail & Idempotency Webhook)
CREATE TABLE `payment_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NULL,
    `order_id` INT NULL,
    `gateway` VARCHAR(50) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `payload` TEXT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_logs_order` (`order_id`),
    INDEX `idx_payment_logs_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABEL ORDER_STATUS_HISTORIES (Histori Timeline Status)
CREATE TABLE `order_status_histories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_histories_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TABEL COURIER_LOCATIONS (Tracking Koordinat GPS & Status Tugas Kurir)
CREATE TABLE `courier_locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `courier_id` INT NOT NULL,
    `order_id` INT NULL,
    `duty_status` ENUM('available', 'busy', 'offline') NOT NULL DEFAULT 'available',
    `latitude` DECIMAL(10, 8) NOT NULL DEFAULT -6.20880000,
    `longitude` DECIMAL(11, 8) NOT NULL DEFAULT 106.84560000,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_courier_loc_user` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TABEL NOTIFICATIONS
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `type` VARCHAR(50) DEFAULT 'info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TABEL REFUNDS (Pengembalian Dana / Refund Transaksi)
CREATE TABLE `refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `gateway_reference` VARCHAR(100) NULL UNIQUE,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    INDEX `idx_refunds_payment` (`payment_id`),
    INDEX `idx_refunds_order` (`order_id`),
    INDEX `idx_refunds_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TABEL PAYMENT_EVENTS (Audit Trail Event Pembayaran & Refund)
CREATE TABLE `payment_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NULL,
    `refund_id` INT NULL,
    `order_id` INT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pe_payment` (`payment_id`),
    INDEX `idx_pe_refund` (`refund_id`),
    INDEX `idx_pe_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SEED DATA AWAL
-- Password Default: password123 ($2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4)
-- ==============================================================================

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'owner', 'Owner Platform', 'Pemilik utama seluruh platform marketplace laundry'),
(2, 'admin_owner', 'Admin Owner', 'Administrator pengelola platform dari sisi owner pusat'),
(3, 'owner_toko', 'Owner Toko', 'Pemilik cabang toko laundry terdaftar'),
(4, 'admin_toko', 'Admin Toko', 'Admin/kasir operasional harian cabang toko laundry'),
(5, 'kurir', 'Kurir / Jasa Antar', 'Petugas kurir penjemputan dan pengantaran cucian'),
(6, 'customer', 'Customer', 'Pelanggan pengguna layanan laundry');

INSERT INTO `users` (`id`, `role_id`, `store_id`, `name`, `email`, `password`, `phone`, `is_active`) VALUES
(1, 1, NULL, 'Budi Santoso', 'owner@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567890', 1),
(2, 2, NULL, 'Siti Rahma', 'admin.owner@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567891', 1),
(3, 3, NULL, 'Hendra Wijaya', 'owner.toko@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567892', 1),
(4, 4, 1, 'Rina Melati', 'admin.toko@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567893', 1),
(5, 5, NULL, 'Agus Express', 'kurir@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567894', 1),
(6, 6, NULL, 'Dewi Lestari', 'customer@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567895', 1),
(7, 3, NULL, 'Surya Pratama', 'owner2@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567897', 1),
(8, 4, 2, 'Maya Sari', 'admin2@laundry.test', '$2y$10$04m/a7kStpWlA3lM4nO5.e9.v4l/v7Rk0z3d2a1b0c9d8e7f6g5h4', '081234567898', 1);

INSERT INTO `courier_locations` (`id`, `courier_id`, `duty_status`, `latitude`, `longitude`) VALUES
(1, 5, 'available', -6.24430000, 106.79300000);

INSERT INTO `addresses` (`id`, `user_id`, `title`, `address_line`, `province`, `city`, `district`, `village`, `rt`, `rw`, `detail_address`, `latitude`, `longitude`, `is_primary`) VALUES
(1, 6, 'Rumah Utama', 'Jl. Mawar Indah No. 12', 'DKI Jakarta', 'Jakarta Selatan', 'Kebayoran Baru', 'Gandaria Utara', '003', '005', 'Rumah pagar hitam sebelah warung', -6.24430000, 106.79300000, 1);

INSERT INTO `stores` (`id`, `user_id`, `name`, `description`, `address`, `phone`, `rating`, `working_hours`, `is_active`) VALUES
(1, 3, 'CleanMaster Laundry Express', 'Layanan cuci komplit cepat dan harum bergaransi', 'Jl. Merdeka No. 45, Jakarta Selatan', '081299887766', 4.85, '07:00 - 21:00', 1),
(2, 7, 'Fresh & Clean Laundry', 'Spesialis cuci kiloan wangi dan perlengkapan rumah tangga', 'Jl. Senopati No. 88, Jakarta Selatan', '081388776655', 4.70, '08:00 - 20:00', 1);

INSERT INTO `services` (`id`, `store_id`, `name`, `description`, `price`, `unit`, `duration_hours`, `is_active`) VALUES
(1, 1, 'Cuci Komplit Reguler', 'Cuci, keringkan, dan setrika rapi', 6000.00, 'kg', 24, 1),
(2, 1, 'Cuci Kilat / Express 3 Jam', 'Selesai cepat dalam 3 jam', 12000.00, 'kg', 3, 1),
(3, 1, 'Dry Cleaning Jas / Tuxedo', 'Perawatan khusus pakaian berbahan halus', 35000.00, 'pcs', 48, 1),
(4, 2, 'Cuci Kering Lipat Hemat', 'Cuci bersih harum tanpa setrika, ekonomis', 5000.00, 'kg', 24, 1),
(5, 2, 'Bed Cover & Selimut Express', 'Pembersihan higienis bebas tungau', 25000.00, 'pcs', 12, 1);

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `store_id`, `service_id`, `address_id`, `courier_id`, `estimated_weight`, `estimated_amount`, `actual_weight`, `unit_price`, `subtotal`, `additional_fee`, `discount`, `total_amount`, `notes`, `payment_method`, `payment_status`, `status`) VALUES
(1, 'ORD-20260903-001', 6, 1, 1, 1, 5, 5.00, 30000.00, 4.80, 6000.00, 28800.00, 2000.00, 1000.00, 29800.00, 'Tolong lipat rapi dan beri wewangi lavender', 'cash', 'unpaid', 'assigned_to_courier');

INSERT INTO `order_status_histories` (`id`, `order_id`, `status`, `notes`, `created_by`) VALUES
(1, 1, 'pending', 'Pesanan dibuat oleh Customer (Estimasi 5 kg)', 6),
(2, 1, 'assigned_to_courier', 'Kurir Agus Express ditugaskan menjemput cucian', 1);

-- =============================================
-- Login Attempts (Rate Limiting)
-- =============================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_ip` (`email`, `ip_address`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- WhatsApp Notification Logs & Queue
-- =============================================
CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `notification_id` INT NULL,
    `user_id` INT NOT NULL,
    `recipient_phone` VARCHAR(30) NOT NULL,
    `recipient_role` VARCHAR(30) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `order_code` VARCHAR(50) NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    `http_code` INT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `provider_message_id` VARCHAR(100) NULL,
    `provider_response` TEXT NULL,
    `attempts` INT NOT NULL DEFAULT 1,
    `idempotency_key` VARCHAR(120) NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_wa_user` (`user_id`),
    INDEX `idx_wa_event` (`event_type`),
    INDEX `idx_wa_order` (`order_code`),
    INDEX `idx_wa_status` (`status`),
    INDEX `idx_wa_idempotency` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- AI Settings (Platform Level Key-Value)
-- =============================================
CREATE TABLE IF NOT EXISTS `ai_settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ai_settings_updated_by` (`updated_by`),
    CONSTRAINT `fk_ai_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Store AI Settings (Store-Specific Information)
-- =============================================
CREATE TABLE IF NOT EXISTS `store_ai_settings` (
    `store_id` INT NOT NULL PRIMARY KEY,
    `ai_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `info_jam_operasional` TEXT NULL,
    `info_antar_jemput` TEXT NULL,
    `info_kebijakan` TEXT NULL,
    `info_faq` TEXT NULL,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_store_ai_updated_by` (`updated_by`),
    CONSTRAINT `fk_store_ai_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_store_ai_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- AI Settings Audit (Audit Trail & Rate Limiting)
-- =============================================
CREATE TABLE IF NOT EXISTS `ai_settings_audit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `role` VARCHAR(50) NOT NULL,
    `store_id` INT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ai_audit_user` (`user_id`),
    INDEX `idx_ai_audit_store` (`store_id`),
    INDEX `idx_ai_audit_key_created` (`setting_key`, `created_at`),
    CONSTRAINT `fk_ai_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_audit_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Store WhatsApp Settings (Store-Specific Fonnte Gateway)
-- =============================================
CREATE TABLE IF NOT EXISTS `store_whatsapp_settings` (
    `store_id` INT NOT NULL PRIMARY KEY,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'fonnte',
    `mode` ENUM('TEST','PRODUCTION') NOT NULL DEFAULT 'TEST',
    `api_url` VARCHAR(255) NULL DEFAULT 'https://api.fonnte.com/send',
    `access_token` VARCHAR(255) NULL,
    `phone_number` VARCHAR(30) NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    INDEX `idx_store_wa_updated_by` (`updated_by`),
    CONSTRAINT `fk_store_whatsapp_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_store_whatsapp_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Store WhatsApp Bot Settings (Chatbot Mode & Fallback)
-- =============================================
CREATE TABLE IF NOT EXISTS `store_whatsapp_bot_settings` (
    `store_id` INT NOT NULL PRIMARY KEY,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `fallback_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    INDEX `idx_store_bot_updated_by` (`updated_by`),
    CONSTRAINT `fk_bot_settings_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bot_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Store WhatsApp Bot Responses (Keyword Auto-Replies)
-- =============================================
CREATE TABLE IF NOT EXISTS `store_whatsapp_bot_responses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `keyword` VARCHAR(100) NOT NULL,
    `response` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    UNIQUE KEY `uq_store_keyword` (`store_id`, `keyword`),
    INDEX `idx_bot_resp_store` (`store_id`),
    INDEX `idx_bot_resp_active` (`store_id`, `is_active`),
    CONSTRAINT `fk_bot_resp_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bot_resp_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
