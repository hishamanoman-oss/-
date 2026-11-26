CREATE DATABASE IF NOT EXISTS `rbd_platform` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `rbd_platform_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `mock_pos_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mock_pos_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tbl_invoice_lines`;
DROP TABLE IF EXISTS `tbl_invoices`;
DROP TABLE IF EXISTS `tbl_stock_qty`;
DROP TABLE IF EXISTS `tbl_items`;
DROP TABLE IF EXISTS `tbl_clients`;
DROP TABLE IF EXISTS `tbl_warehouses`;
DROP TABLE IF EXISTS `tbl_branches`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `tbl_branches` (
    `br_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `br_name` VARCHAR(120) NOT NULL,
    `br_city` VARCHAR(80) NOT NULL,
    PRIMARY KEY (`br_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_warehouses` (
    `wh_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `br_id` INT UNSIGNED NOT NULL,
    `wh_name` VARCHAR(120) NOT NULL,
    PRIMARY KEY (`wh_id`),
    KEY `idx_wh_branch` (`br_id`),
    CONSTRAINT `fk_wh_branch` FOREIGN KEY (`br_id`) REFERENCES `tbl_branches` (`br_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_clients` (
    `client_no` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(160) NOT NULL,
    `mobile_no` VARCHAR(32) NOT NULL,
    `email_addr` VARCHAR(160) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`client_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_items` (
    `item_code` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_sku` VARCHAR(64) NOT NULL,
    `item_title` VARCHAR(180) NOT NULL,
    `cat_name` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(14,2) NOT NULL,
    `cost_price` DECIMAL(14,4) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`item_code`),
    UNIQUE KEY `uq_item_sku` (`item_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_stock_qty` (
    `wh_id` INT UNSIGNED NOT NULL,
    `item_code` INT UNSIGNED NOT NULL,
    `qty_on_hand` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `qty_reserved` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `last_changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`wh_id`, `item_code`),
    CONSTRAINT `fk_sq_wh` FOREIGN KEY (`wh_id`) REFERENCES `tbl_warehouses` (`wh_id`),
    CONSTRAINT `fk_sq_item` FOREIGN KEY (`item_code`) REFERENCES `tbl_items` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_invoices` (
    `inv_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inv_number` VARCHAR(64) NOT NULL,
    `br_id` INT UNSIGNED NOT NULL,
    `client_no` INT UNSIGNED NOT NULL,
    `inv_status` VARCHAR(32) NOT NULL,
    `gross_amt` DECIMAL(14,2) NOT NULL,
    `disc_amt` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tax_amt` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `net_amt` DECIMAL(14,2) NOT NULL,
    `paid_amt` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `currency_code` CHAR(3) NOT NULL DEFAULT 'SAR',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`inv_id`),
    UNIQUE KEY `uq_inv_number` (`inv_number`),
    KEY `idx_inv_br` (`br_id`),
    KEY `idx_inv_client` (`client_no`),
    CONSTRAINT `fk_inv_br` FOREIGN KEY (`br_id`) REFERENCES `tbl_branches` (`br_id`),
    CONSTRAINT `fk_inv_client` FOREIGN KEY (`client_no`) REFERENCES `tbl_clients` (`client_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_invoice_lines` (
    `line_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inv_id` BIGINT UNSIGNED NOT NULL,
    `item_code` INT UNSIGNED NOT NULL,
    `qty` DECIMAL(18,4) NOT NULL,
    `unit_price` DECIMAL(14,2) NOT NULL,
    `line_total` DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (`line_id`),
    KEY `idx_line_inv` (`inv_id`),
    CONSTRAINT `fk_line_inv` FOREIGN KEY (`inv_id`) REFERENCES `tbl_invoices` (`inv_id`),
    CONSTRAINT `fk_line_item` FOREIGN KEY (`item_code`) REFERENCES `tbl_items` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 3 Branches
INSERT INTO `tbl_branches` (`br_id`, `br_name`, `br_city`) VALUES
(1, 'الفرع الرئيسي - العليا (Riyadh Main)', 'الرياض'),
(2, 'فرع جدة الساحلي (Jeddah Coastal)', 'جدة'),
(3, 'فرع الدمام اللوجستي (Dammam Hub)', 'الدمام');

-- Seed 3 Warehouses
INSERT INTO `tbl_warehouses` (`wh_id`, `br_id`, `wh_name`) VALUES
(1, 1, 'مستودع الرياض المركزي'),
(2, 2, 'مستودع جدة الغربي'),
(3, 3, 'مستودع الشرقية المبرد');

-- Seed 16 Clients (Arabic & English)
INSERT INTO `tbl_clients` (`client_no`, `full_name`, `mobile_no`, `email_addr`, `updated_at`) VALUES
(1, 'شركة آفاق التجارة الحديثة', '+966501112201', 'orders@afaq-trade.sa', '2026-11-20 09:15:00'),
(2, 'محمد عبدالله السبيعي', '+966501112202', 'm.subaie@example.sa', '2026-11-20 10:30:00'),
(3, 'سارة خالد القحطاني', '+966501112203', 'sara.q@example.sa', '2026-11-21 11:00:00'),
(4, 'Al-Noor Retail Group', '+966501112204', 'procurement@alnoor-group.com', '2026-11-21 14:20:00'),
(5, 'فهد ناصر الدوسري', '+966501112205', 'fahad.d@example.sa', '2026-11-22 08:45:00'),
(6, 'مؤسسة الرؤية المتقدمة', '+966501112206', 'finance@roya-est.sa', '2026-11-22 13:10:00'),
(7, 'Layla Hassan Al-Harbi', '+966501112207', 'layla.h@example.com', '2026-11-22 16:05:00'),
(8, 'عمر يحيى الغامدي', '+966501112208', 'omar.g@example.sa', '2026-11-23 09:30:00'),
(9, 'متجر الواحة الذهبية', '+966501112209', 'oasis.gold@example.sa', '2026-11-23 12:15:00'),
(10, 'Tariq Ibrahim Mansour', '+966501112210', 'tariq.m@example.com', '2026-11-23 17:40:00'),
(11, 'نورة سعد العتيبي', '+966501112211', 'noura.o@example.sa', '2026-11-24 10:00:00'),
(12, 'شركة المدار للتوريدات', '+966501112212', 'supply@madar-co.sa', '2026-11-24 15:25:00'),
(13, 'خالد سعيد الشهري', '+966501112213', 'khaled.sh@example.sa', '2026-11-24 19:50:00'),
(14, 'Horizon Tech Supplies', '+966501112214', 'ap@horizontech.io', '2026-11-25 11:10:00'),
(15, 'ريم فوزي الزهراني', '+966501112215', 'reem.z@example.sa', '2026-11-25 14:45:00'),
(16, 'مجموعة البدر التجارية', '+966501112216', NULL, '2026-11-25 20:00:00');

-- Seed 22 Items (2 items intentionally have NULL cost_price for future completeness guardrail tests)
INSERT INTO `tbl_items` (`item_code`, `item_sku`, `item_title`, `cat_name`, `unit_price`, `cost_price`, `is_active`, `updated_at`) VALUES
(1, 'POS-PRN-001', 'طابعة فواتير حرارية 80مم', 'أجهزة نقاط البيع', 480.00, 310.0000, 1, '2026-11-20 08:00:00'),
(2, 'POS-SCN-002', 'قارئ باركود لاسلكي ثنائي الأبعاد', 'أجهزة نقاط البيع', 320.00, 195.5000, 1, '2026-11-20 08:05:00'),
(3, 'POS-DRW-003', 'درج نقدية معدني 5 خانات', 'أجهزة نقاط البيع', 260.00, 160.0000, 1, '2026-11-20 08:10:00'),
(4, 'NET-RTR-004', 'Enterprise Wi-Fi 6 Router', 'الشبكات', 890.00, 620.0000, 1, '2026-11-20 08:15:00'),
(5, 'NET-SW8-005', 'محول شبكة 8 منافذ جيجابت', 'الشبكات', 340.00, 215.0000, 1, '2026-11-20 08:20:00'),
(6, 'CON-PAP-006', 'ورق حراري 80x80 (كرتون 50 لفة)', 'المستهلكات', 145.00, 92.0000, 1, '2026-11-20 08:25:00'),
(7, 'CON-LBL-007', 'ملصقات باركود حرارية 50x30', 'المستهلكات', 75.00, 41.2500, 1, '2026-11-20 08:30:00'),
(8, 'CMP-MON-008', 'شاشة لمس كاشير 15.6 بوصة', 'أجهزة نقاط البيع', 1650.00, 1180.0000, 1, '2026-11-21 09:00:00'),
(9, 'CMP-SSD-009', 'NVMe SSD 1TB Industrial', 'التخزين', 420.00, 290.0000, 1, '2026-11-21 09:10:00'),
(10, 'CMP-UPS-010', 'مزود طاقة احتياطي 1500VA', 'الطاقة', 680.00, 465.0000, 1, '2026-11-21 09:20:00'),
(11, 'SEC-CAM-011', 'كاميرا مراقبة IP بدقة 4K', 'الأمن والسلامة', 540.00, 355.0000, 1, '2026-11-22 10:00:00'),
(12, 'SEC-NVR-012', 'جهاز تسجيل شبكي 8 قنوات', 'الأمن والسلامة', 1120.00, 790.0000, 1, '2026-11-22 10:15:00'),
(13, 'ACC-KBD-013', 'لوحة مفاتيح ميكانيكية لاسلكية', 'ملحقات', 290.00, 175.0000, 1, '2026-11-22 10:30:00'),
(14, 'ACC-MSE-014', 'ماوس مكتبي احترافي', 'ملحقات', 115.00, 62.0000, 1, '2026-11-22 10:45:00'),
(15, 'ACC-HUB-015', 'موزع USB-C متعدد المنافذ', 'ملحقات', 185.00, 108.0000, 1, '2026-11-23 11:00:00'),
(16, 'SRV-UPS-016', 'Smart Rack PDU 8-Outlet', 'الطاقة', 790.00, 540.0000, 1, '2026-11-23 11:15:00'),
(17, 'POS-TAB-017', 'جهاز لوحي لإدارة المستودعات 10 بوصة', 'أجهزة نقاط البيع', 1350.00, 960.0000, 1, '2026-11-23 11:30:00'),
(18, 'POS-SCL-018', 'ميزان إلكتروني متصل بالشبكة', 'أجهزة نقاط البيع', 1890.00, 1340.0000, 1, '2026-11-24 12:00:00'),
(19, 'CON-RBN-019', 'شريط حبر حراري شمعي 110x300', 'المستهلكات', 95.00, 52.0000, 1, '2026-11-24 12:15:00'),
(20, 'NET-CBL-020', 'كابل شبكة Cat6 (لفة 305 متر)', 'الشبكات', 520.00, 345.0000, 1, '2026-11-24 12:30:00'),
(21, 'SRV-CFG-021', 'حزمة تهيئة وتركيب ميداني', 'خدمات', 450.00, NULL, 1, '2026-11-25 09:00:00'),
(22, 'LEGACY-022', 'قارئ بطاقات قديم (متوقف)', 'أجهزة نقاط البيع', 150.00, NULL, 0, '2026-11-25 09:30:00');

-- Seed 44 Stock Balance Rows across 3 Warehouses
INSERT INTO `tbl_stock_qty` (`wh_id`, `item_code`, `qty_on_hand`, `qty_reserved`, `last_changed_at`) VALUES
(1, 1, 45.0000, 5.0000, '2026-11-25 10:00:00'),
(1, 2, 60.0000, 8.0000, '2026-11-25 10:05:00'),
(1, 3, 30.0000, 2.0000, '2026-11-25 10:10:00'),
(1, 4, 18.0000, 3.0000, '2026-11-25 10:15:00'),
(1, 5, 40.0000, 0.0000, '2026-11-25 10:20:00'),
(1, 6, 120.0000, 15.0000, '2026-11-25 10:25:00'),
(1, 7, 200.0000, 20.0000, '2026-11-25 10:30:00'),
(1, 8, 12.0000, 2.0000, '2026-11-25 10:35:00'),
(1, 9, 25.0000, 4.0000, '2026-11-25 10:40:00'),
(1, 10, 14.0000, 1.0000, '2026-11-25 10:45:00'),
(1, 11, 22.0000, 2.0000, '2026-11-25 10:50:00'),
(1, 12, 9.0000, 1.0000, '2026-11-25 10:55:00'),
(1, 13, 35.0000, 5.0000, '2026-11-25 11:00:00'),
(1, 14, 70.0000, 6.0000, '2026-11-25 11:05:00'),
(1, 15, 50.0000, 4.0000, '2026-11-25 11:10:00'),
(1, 16, 11.0000, 0.0000, '2026-11-25 11:15:00'),
(1, 17, 16.0000, 3.0000, '2026-11-25 11:20:00'),
(1, 18, 7.0000, 2.0000, '2026-11-25 11:25:00'),
(1, 19, 85.0000, 10.0000, '2026-11-25 11:30:00'),
(1, 20, 24.0000, 2.0000, '2026-11-25 11:35:00'),
(2, 1, 22.0000, 4.0000, '2026-11-25 12:00:00'),
(2, 2, 19.0000, 3.0000, '2026-11-25 12:05:00'),
(2, 3, 15.0000, 1.0000, '2026-11-25 12:10:00'),
(2, 4, 8.0000, 2.0000, '2026-11-25 12:15:00'),
(2, 5, 25.0000, 5.0000, '2026-11-25 12:20:00'),
(2, 6, 75.0000, 10.0000, '2026-11-25 12:25:00'),
(2, 7, 90.0000, 5.0000, '2026-11-25 12:30:00'),
(2, 8, 5.0000, 2.0000, '2026-11-25 12:35:00'),
(2, 9, 12.0000, 0.0000, '2026-11-25 12:40:00'),
(2, 10, 6.0000, 2.0000, '2026-11-25 12:45:00'),
(2, 11, 14.0000, 4.0000, '2026-11-25 12:50:00'),
(2, 17, 4.0000, 1.0000, '2026-11-25 12:55:00'),
(3, 1, 18.0000, 2.0000, '2026-11-25 13:00:00'),
(3, 2, 24.0000, 0.0000, '2026-11-25 13:05:00'),
(3, 4, 6.0000, 1.0000, '2026-11-25 13:10:00'),
(3, 6, 60.0000, 8.0000, '2026-11-25 13:15:00'),
(3, 8, 3.0000, 1.0000, '2026-11-25 13:20:00'),
(3, 10, 5.0000, 0.0000, '2026-11-25 13:25:00'),
(3, 12, 4.0000, 1.0000, '2026-11-25 13:30:00'),
(3, 14, 30.0000, 3.0000, '2026-11-25 13:35:00'),
(3, 16, 7.0000, 2.0000, '2026-11-25 13:40:00'),
(3, 18, 2.0000, 1.0000, '2026-11-25 13:45:00'),
(3, 19, 45.0000, 5.0000, '2026-11-25 13:50:00'),
(3, 20, 10.0000, 2.0000, '2026-11-25 13:55:00');

-- Seed 28 Invoices across branches and statuses (15% VAT separated explicitly)
INSERT INTO `tbl_invoices` (`inv_id`, `inv_number`, `br_id`, `client_no`, `inv_status`, `gross_amt`, `disc_amt`, `tax_amt`, `net_amt`, `paid_amt`, `currency_code`, `created_at`, `updated_at`) VALUES
(1,  'POS-2026-0001', 1, 1,  'completed', 1280.00, 80.00, 180.00, 1380.00, 1380.00, 'SAR', '2026-11-20 10:00:00', '2026-11-20 10:05:00'),
(2,  'POS-2026-0002', 1, 2,  'completed', 890.00,  0.00,  133.50, 1023.50, 1023.50, 'SAR', '2026-11-20 11:15:00', '2026-11-20 11:20:00'),
(3,  'POS-2026-0003', 2, 3,  'completed', 660.00,  60.00, 90.00,  690.00,  690.00,  'SAR', '2026-11-20 13:40:00', '2026-11-20 13:45:00'),
(4,  'POS-2026-0004', 2, 4,  'partial',   2540.00, 140.00,360.00, 2760.00, 1500.00, 'SAR', '2026-11-21 09:30:00', '2026-11-21 09:40:00'),
(5,  'POS-2026-0005', 3, 5,  'completed', 1120.00, 0.00,  168.00, 1288.00, 1288.00, 'SAR', '2026-11-21 12:10:00', '2026-11-21 12:15:00'),
(6,  'POS-2026-0006', 1, 6,  'completed', 1800.00, 100.00,255.00, 1955.00, 1955.00, 'SAR', '2026-11-21 15:20:00', '2026-11-21 15:25:00'),
(7,  'POS-2026-0007', 2, 7,  'unpaid',    960.00,  0.00,  144.00, 1104.00, 0.00,    'SAR', '2026-11-22 10:05:00', '2026-11-22 10:10:00'),
(8,  'POS-2026-0008', 1, 8,  'completed', 580.00,  30.00, 82.50,  632.50,  632.50,  'SAR', '2026-11-22 12:45:00', '2026-11-22 12:50:00'),
(9,  'POS-2026-0009', 3, 9,  'completed', 2075.00, 75.00, 300.00, 2300.00, 2300.00, 'SAR', '2026-11-22 16:30:00', '2026-11-22 16:35:00'),
(10, 'POS-2026-0010', 1, 10, 'refunded',  480.00,  0.00,  72.00,  552.00,  0.00,    'SAR', '2026-11-22 18:10:00', '2026-11-23 09:00:00'),
(11, 'POS-2026-0011', 2, 11, 'completed', 1350.00, 50.00, 195.00, 1495.00, 1495.00, 'SAR', '2026-11-23 09:15:00', '2026-11-23 09:20:00'),
(12, 'POS-2026-0012', 1, 12, 'completed', 3300.00, 300.00,450.00, 3450.00, 3450.00, 'SAR', '2026-11-23 11:40:00', '2026-11-23 11:45:00'),
(13, 'POS-2026-0013', 3, 13, 'partial',   1560.00, 60.00, 225.00, 1725.00, 1000.00, 'SAR', '2026-11-23 14:20:00', '2026-11-23 14:30:00'),
(14, 'POS-2026-0014', 2, 14, 'completed', 1940.00, 40.00, 285.00, 2185.00, 2185.00, 'SAR', '2026-11-23 17:00:00', '2026-11-23 17:05:00'),
(15, 'POS-2026-0015', 1, 15, 'completed', 745.00,  0.00,  111.75, 856.75,  856.75,  'SAR', '2026-11-24 09:10:00', '2026-11-24 09:15:00'),
(16, 'POS-2026-0016', 1, 16, 'completed', 2210.00, 110.00,315.00, 2415.00, 2415.00, 'SAR', '2026-11-24 10:50:00', '2026-11-24 10:55:00'),
(17, 'POS-2026-0017', 2, 1,  'completed', 1020.00, 20.00, 150.00, 1150.00, 1150.00, 'SAR', '2026-11-24 12:30:00', '2026-11-24 12:35:00'),
(18, 'POS-2026-0018', 3, 2,  'completed', 790.00,  0.00,  118.50, 908.50,  908.50,  'SAR', '2026-11-24 14:15:00', '2026-11-24 14:20:00'),
(19, 'POS-2026-0019', 1, 4,  'completed', 3780.00, 180.00,540.00, 4140.00, 4140.00, 'SAR', '2026-11-24 16:40:00', '2026-11-24 16:45:00'),
(20, 'POS-2026-0020', 2, 6,  'partial',   1450.00, 50.00, 210.00, 1610.00, 800.00,  'SAR', '2026-11-24 19:00:00', '2026-11-24 19:05:00'),
(21, 'POS-2026-0021', 1, 3,  'completed', 610.00,  10.00, 90.00,  690.00,  690.00,  'SAR', '2026-11-25 09:20:00', '2026-11-25 09:25:00'),
(22, 'POS-2026-0022', 3, 5,  'completed', 1360.00, 60.00, 195.00, 1495.00, 1495.00, 'SAR', '2026-11-25 10:45:00', '2026-11-25 10:50:00'),
(23, 'POS-2026-0023', 2, 9,  'completed', 930.00,  30.00, 135.00, 1035.00, 1035.00, 'SAR', '2026-11-25 12:15:00', '2026-11-25 12:20:00'),
(24, 'POS-2026-0024', 1, 12, 'completed', 2650.00, 150.00,375.00, 2875.00, 2875.00, 'SAR', '2026-11-25 14:30:00', '2026-11-25 14:35:00'),
(25, 'POS-2026-0025', 1, 14, 'unpaid',    1260.00, 60.00, 180.00, 1380.00, 0.00,    'SAR', '2026-11-25 16:00:00', '2026-11-25 16:05:00'),
(26, 'POS-2026-0026', 2, 15, 'completed', 480.00,  0.00,  72.00,  552.00,  552.00,  'SAR', '2026-11-25 17:45:00', '2026-11-25 17:50:00'),
(27, 'POS-2026-0027', 3, 16, 'completed', 1040.00, 40.00, 150.00, 1150.00, 1150.00, 'SAR', '2026-11-25 19:10:00', '2026-11-25 19:15:00'),
(28, 'POS-2026-0028', 1, 1,  'completed', 1890.00, 90.00, 270.00, 2070.00, 2070.00, 'SAR', '2026-11-26 00:30:00', '2026-11-26 00:35:00');

-- Seed 56 Invoice Lines matching gross amounts
INSERT INTO `tbl_invoice_lines` (`inv_id`, `item_code`, `qty`, `unit_price`, `line_total`) VALUES
(1, 1, 2.0000, 480.00, 960.00),
(1, 2, 1.0000, 320.00, 320.00),
(2, 4, 1.0000, 890.00, 890.00),
(3, 3, 2.0000, 260.00, 520.00),
(3, 6, 1.0000, 140.00, 140.00),
(4, 8, 1.0000, 1650.00, 1650.00),
(4, 4, 1.0000, 890.00, 890.00),
(5, 12, 1.0000, 1120.00, 1120.00),
(6, 17, 1.0000, 1350.00, 1350.00),
(6, 21, 1.0000, 450.00, 450.00),
(7, 1, 2.0000, 480.00, 960.00),
(8, 13, 2.0000, 290.00, 580.00),
(9, 8, 1.0000, 1650.00, 1650.00),
(9, 9, 1.0000, 425.00, 425.00),
(10, 1, 1.0000, 480.00, 480.00),
(11, 17, 1.0000, 1350.00, 1350.00),
(12, 8, 2.0000, 1650.00, 3300.00),
(13, 10, 2.0000, 680.00, 1360.00),
(13, 7, 2.0000, 100.00, 200.00),
(14, 11, 3.0000, 540.00, 1620.00),
(14, 2, 1.0000, 320.00, 320.00),
(15, 5, 2.0000, 340.00, 680.00),
(15, 7, 1.0000, 65.00, 65.00),
(16, 12, 1.0000, 1120.00, 1120.00),
(16, 11, 2.0000, 545.00, 1090.00),
(17, 5, 3.0000, 340.00, 1020.00),
(18, 16, 1.0000, 790.00, 790.00),
(19, 18, 2.0000, 1890.00, 3780.00),
(20, 6, 10.0000, 145.00, 1450.00),
(21, 13, 1.0000, 290.00, 290.00),
(21, 2, 1.0000, 320.00, 320.00),
(22, 10, 2.0000, 680.00, 1360.00),
(23, 15, 4.0000, 185.00, 740.00),
(23, 19, 2.0000, 95.00, 190.00),
(24, 8, 1.0000, 1650.00, 1650.00),
(24, 9, 2.0000, 420.00, 840.00),
(24, 14, 1.0000, 160.00, 160.00),
(25, 9, 3.0000, 420.00, 1260.00),
(26, 1, 1.0000, 480.00, 480.00),
(27, 20, 2.0000, 520.00, 1040.00),
(28, 18, 1.0000, 1890.00, 1890.00);
