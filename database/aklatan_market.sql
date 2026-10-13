-- =============================================================================
--  AKLATAN MARKET - Database schema and starter catalogue
--  MySQL 5.7+ / MariaDB 10.2+ (InfinityFree compatible)
--
--  HOW TO IMPORT
--    1. Open phpMyAdmin in your hosting control panel.
--    2. Select your database on the left (for example if0_38612345_aklatan).
--    3. Click the "Import" tab, choose this file, then press "Go".
--
--  The import is safe to run on an empty database. It does NOT drop your
--  existing data, but re-running it will fail on the CREATE TABLE statements
--  because the tables already exist. That is expected and harmless.
-- =============================================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- users : customers and administrators (never plain-text passwords)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- user_addresses : saved delivery addresses
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(60) NOT NULL DEFAULT 'Home',
  `recipient_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `address_line1` VARCHAR(180) NOT NULL,
  `address_line2` VARCHAR(180) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(12) DEFAULT NULL,
  `delivery_notes` VARCHAR(255) DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user` (`user_id`),
  KEY `idx_addresses_default` (`user_id`,`is_default`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- categories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(40) NOT NULL DEFAULT 'book',
  `accent` VARCHAR(20) NOT NULL DEFAULT 'ink',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categories_slug` (`slug`),
  KEY `idx_categories_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- products
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `sku` VARCHAR(60) NOT NULL,
  `creator` VARCHAR(160) DEFAULT NULL,
  `isbn` VARCHAR(20) DEFAULT NULL,
  `publisher` VARCHAR(160) DEFAULT NULL,
  `product_format` VARCHAR(60) DEFAULT NULL,
  `language` VARCHAR(60) DEFAULT 'English',
  `pages` INT DEFAULT NULL,
  `publication_date` DATE DEFAULT NULL,
  `short_description` VARCHAR(300) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `compare_at_price` DECIMAL(12,2) DEFAULT NULL,
  `cost_price` DECIMAL(12,2) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_bestseller` TINYINT(1) NOT NULL DEFAULT 0,
  `is_new_arrival` TINYINT(1) NOT NULL DEFAULT 0,
  `is_staff_pick` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_slug` (`slug`),
  UNIQUE KEY `uniq_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_flags` (`is_active`,`is_featured`,`is_bestseller`),
  KEY `idx_products_stock` (`stock_quantity`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- carts / cart_items : stored basket for signed-in customers
--   Guests use a session basket (documented strategy), merged on sign-in.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_carts_user` (`user_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cart_product` (`cart_id`,`product_id`),
  KEY `idx_cart_items_product` (`product_id`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- wishlists
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wishlist_item` (`user_id`,`product_id`),
  KEY `idx_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- discount_codes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `discount_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `description` VARCHAR(200) DEFAULT NULL,
  `type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `max_uses` INT DEFAULT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `per_user_limit` INT DEFAULT 1,
  `starts_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_discount_code` (`code`),
  KEY `idx_discount_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discount_redemptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_redemption_code` (`code_id`),
  KEY `idx_redemption_user` (`user_id`),
  CONSTRAINT `fk_redemption_code` FOREIGN KEY (`code_id`) REFERENCES `discount_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- orders
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` ENUM('cod','gcash','pickup','bank') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('unpaid','pending_verification','verified','rejected') NOT NULL DEFAULT 'unpaid',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_code` VARCHAR(40) DEFAULT NULL,
  `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `customer_name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `address_line1` VARCHAR(180) NOT NULL,
  `address_line2` VARCHAR(180) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(12) DEFAULT NULL,
  `delivery_notes` VARCHAR(500) DEFAULT NULL,
  `pickup_location` VARCHAR(190) DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `stock_restored` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_orders_number` (`order_number`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- order_items : keeps a snapshot so old orders stay correct after edits
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_sku` VARCHAR(60) DEFAULT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- payments : manual payment submissions and their verification trail
--   Never store GCash PINs, OTPs, card numbers or passwords here.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `method` ENUM('cod','gcash','pickup','bank') NOT NULL DEFAULT 'cod',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` VARCHAR(80) DEFAULT NULL,
  `payer_name` VARCHAR(160) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `proof_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('unpaid','pending_verification','verified','rejected') NOT NULL DEFAULT 'unpaid',
  `customer_note` VARCHAR(500) DEFAULT NULL,
  `admin_note` VARCHAR(500) DEFAULT NULL,
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_order` (`order_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- reviews : only shown when approved by an administrator
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `title` VARCHAR(140) DEFAULT NULL,
  `body` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` VARCHAR(300) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reviews_user_product` (`user_id`,`product_id`),
  KEY `idx_reviews_product` (`product_id`,`status`),
  KEY `idx_reviews_user` (`user_id`),
  KEY `idx_reviews_status` (`status`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- contact_messages : storefront enquiry form (no SMTP required)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `subject` VARCHAR(160) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `reply_note` TEXT DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_status` (`status`),
  KEY `idx_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- store_settings : everything the owner can edit without touching code
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `store_settings` (
  `setting_key` VARCHAR(60) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- audit_logs : who changed what, and when
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(40) DEFAULT NULL,
  `entity_id` VARCHAR(40) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- login_attempts : shared-hosting friendly brute-force throttling
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`,`attempted_at`),
  KEY `idx_attempts_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  STARTER DATA
-- =============================================================================

INSERT INTO `categories` (`id`,`name`,`slug`,`description`,`icon`,`accent`,`sort_order`,`is_active`) VALUES
(1,'Fiction & Novels','fiction-novels','Novels, short story collections and literary fiction from Filipino and international authors.','book','ink',1,1),
(2,'Academic & Reference','academic-reference','Textbooks, reviewers and reference titles for senior high school, college and board exam preparation.','clipboard','teal',2,1),
(3,'Filipino Books & Literature','filipino-books','Classics, poetry and contemporary writing in Filipino and other Philippine languages.','newspaper','amber',3,1),
(4,'Manga & Comics','manga-comics','Manga volumes, local komiks and graphic novel anthologies.','sparkle','sage',4,1),
(5,'Children''s Books','childrens-books','Picture books and early readers in English and Filipino for young readers.','gift','amber',5,1),
(6,'Magazines','magazines','Food, travel, design and hobby magazines — single issues and back numbers.','list','teal',6,1),
(7,'Music: CDs & Vinyl','music-cds-vinyl','OPM and international albums on compact disc and vinyl record.','music','ink',7,1),
(8,'Movies & DVDs','movies-dvds','Filipino classics, restored prints and documentary collections on DVD.','film','sage',8,1),
(9,'Stationery & Collectibles','stationery-collectibles','Notebooks, bookmarks, bookends and reading accessories for collectors.','pen','amber',9,1),
(10,'Book Bundles','book-bundles','Curated multi-book bundles at a friendlier price.','box','teal',10,1);

INSERT INTO `store_settings` (`setting_key`,`setting_value`) VALUES
('store_name','Aklatan Market'),
('tagline','Stories, knowledge, and discoveries in one place.'),
('meta_description','Aklatan Market is an independent online bookstore and media shop for Filipino readers, students and collectors. Books, manga, music and movies delivered nationwide.'),
('announcement_enabled','1'),
('announcement_text','Free nationwide shipping on orders over ₱1,500 — delivered in 3 to 7 working days.'),
('announcement_link','shipping-returns.php'),
('announcement_link_label','See how shipping works'),
('store_email','hello@aklatanmarket.example'),
('store_phone','(02) 8555 0100'),
('store_address','Unit 4, Katipunan Book Arcade, 118 Aurora Boulevard, Quezon City'),
('store_hours','Monday to Saturday, 9:00 AM to 7:00 PM (PHT)'),
('footer_note','A small independent bookshop for readers, students and collectors across the Philippines.'),
('currency_code','PHP'),
('shipping_fee','99.00'),
('free_shipping_threshold','1500.00'),
('low_stock_threshold','5'),
('products_per_page','12'),
('orders_per_page','20'),
('cod_enabled','1'),
('gcash_enabled','1'),
('pickup_enabled','1'),
('bank_transfer_enabled','0'),
('gcash_account_name','Aklatan Market Bookshop'),
('gcash_account_number','0917 000 0000'),
('gcash_instructions','Send the exact order total to the GCash number above, then enter the 13-digit reference number from your GCash receipt. Our team verifies every manual payment by hand, usually within one working day.'),
('pickup_address','Aklatan Market, Unit 4 Katipunan Book Arcade, 118 Aurora Boulevard, Quezon City'),
('pickup_instructions','Choose pickup, place the order, wait for the confirmation message, then collect your books from the counter. Bring your order number and a valid ID.'),
('payment_note','We never ask for your GCash PIN or OTP. Only the reference number is required.'),
('about_intro','Aklatan Market began as a stall of second-hand paperbacks and grew into a full online marketplace for books and media.'),
('company_legal_name','Aklatan Market Books & Media'),
('business_address','Unit 4, Katipunan Book Arcade, 118 Aurora Boulevard, Quezon City, Metro Manila'),
('dpo_contact','privacy@aklatanmarket.example');

INSERT INTO `discount_codes` (`code`,`description`,`type`,`value`,`min_subtotal`,`max_uses`,`used_count`,`per_user_limit`,`expires_at`,`is_active`) VALUES
('WELCOME10','10% off a first order over ₱800','percent',10.00,800.00,500,0,1,DATE_ADD(NOW(), INTERVAL 12 MONTH),1),
('STUDENT50','₱50 off student orders over ₱600','fixed',50.00,600.00,1000,0,1,DATE_ADD(NOW(), INTERVAL 12 MONTH),1),
('READMORE200','₱200 off baskets over ₱3,000','fixed',200.00,3000.00,200,0,1,DATE_ADD(NOW(), INTERVAL 6 MONTH),1);

-- -----------------------------------------------------------------------------
-- products : starter catalogue (every cover image is a local, original SVG)
-- -----------------------------------------------------------------------------
INSERT INTO `products`
  (`category_id`,`name`,`slug`,`sku`,`creator`,`isbn`,`publisher`,`product_format`,`language`,`pages`,
   `publication_date`,`short_description`,`description`,`price`,`compare_at_price`,`stock_quantity`,
   `is_active`,`is_featured`,`is_bestseller`,`is_new_arrival`,`is_staff_pick`) VALUES
(1,'The Sampaguita Letters','the-sampaguita-letters','AKM-01-001','Marisol R. Villaflor','9786214500117','Bagong Araw Publishing','Paperback','English',384,'2025-08-12','A hundred unsent letters, one old wooden box, and the summer that changed a family forever.','When Marisol Villaflor''s grandmother dies, she leaves behind a tin of letters addressed to no one. Tracing each one across three provinces becomes an unexpectedly funny, tender search for the woman her grandmother really was. Winner of the 2025 Araw Fiction Prize, this edition includes a reading group guide.',649.00,799.00,18,1,1,1,0,1),
(1,'Mango Season at Barangay Siete','mango-season-at-barangay-siete','AKM-01-002','Danilo P. Cruz','9786214500124','Bagong Araw Publishing','Paperback','English',312,'2025-02-19','Sugar, gossip and a heatwave — a barrio novel that smells like ripe mangoes.','The Delos Reyes family has held the last mango orchard in Barangay Siete for four generations. When a buyer arrives with a contract and a deadline, the whole neighbourhood picks a side. A warm, funny, deeply local novel about land, appetite and the ties that refuse to break.',529.00,NULL,24,1,0,1,0,0),
(1,'The Last Jeepney Ride','the-last-jeepney-ride','AKM-01-003','Elena Bautista','9786214500131','Tala Press','Paperback','English',276,'2026-03-04','A jeepney driver''s final route becomes a portrait of the city at dusk.','Told over one evening and eleven passengers, this slim, cinematic novel follows Mang Isko through the streets he has driven for thirty-one years. Each stop adds a passenger, a memory and a small reckoning. Quietly political, entirely human.',575.00,NULL,31,1,0,0,1,0),
(1,'Salt and Sunlight','salt-and-sunlight','AKM-01-004','Imelda Cortez','9786214500148','Tala Press','Hardcover','English',420,'2025-11-08','Two sisters, one fish port, and a reckoning forty years overdue.','In a coastal town where the catch has been shrinking for a decade, Mila returns home to sell the family boat. Her sister has other plans. Imelda Cortez writes salt air and stubbornness with equal precision, and lands an ending that readers argue about.',689.00,NULL,12,1,0,0,0,1),
(1,'Midnight in Intramuros','midnight-in-intramuros','AKM-01-005','Rafael Santos','9786214500155','Fort Santiago Books','Paperback','English',356,'2025-06-21','A night guard, a stolen manuscript, and a walled city that keeps its own records.','Every night at 2:00 AM, the stone walls of Intramuros seem to hold their breath. When a custodian finds a 19th-century manuscript missing from a locked cabinet, he has six hours to work out who took it and why. An atmospheric literary mystery.',720.00,850.00,9,1,0,0,0,0),
(1,'A Quiet House in Baguio','a-quiet-house-in-baguio','AKM-01-006','Nadia Lim','9786214500162','Highland Books','Paperback','English',298,'2024-12-01','A grieving architect rents a cold house and finds it already occupied.','Nadia Lim''s restrained, luminous debut follows a woman rebuilding her life above the fog line. The house has opinions. So do the neighbours. A gentle novel about grief that never once asks for pity.',560.00,NULL,3,1,0,0,0,0),
(2,'Philippine History: A Readable Survey','philippine-history-a-readable-survey','AKM-02-001','Dr. Aurora Mendoza','9786214500179','Katipunan Academic','Hardcover','English',512,'2025-05-30','The standard college survey, rewritten for students who have two jobs and no time.','Covers pre-colonial societies through the present in twenty-two chronological chapters, each with a timeline, key terms and source excerpts drawn from Philippine archives. Used widely in GE-PH courses and included in many senior high school reading lists.',1250.00,NULL,14,1,0,0,0,1),
(2,'College Algebra Worktext (Revised Edition)','college-algebra-worktext','AKM-02-002','Prof. Benjamin A. Roque','9786214500186','Katipunan Academic','Paperback','English',404,'2025-07-14','Solved examples, worked drills and a full answer key for first-year algebra.','Every chapter opens with a short diagnostic, moves through fully worked examples, and closes with graduated practice sets. The revised edition adds forty new word problems and a chapter on exponential models used in Philippine public health data.',890.00,NULL,22,1,0,1,0,0),
(2,'General Chemistry Laboratory Manual','general-chemistry-laboratory-manual','AKM-02-003','Dr. Carmela Ruiz','9786214500193','Katipunan Academic','Spiral bound','English',268,'2025-04-02','Twenty-eight safe, affordable laboratory experiments for large classes.','Designed for schools where reagents are tight: every experiment lists a minimum-equipment variant and a substitute for hard-to-source chemicals. Includes pre-lab quizzes, data sheets and a troubleshooting table for each procedure.',760.00,NULL,16,1,0,0,0,0),
(2,'Accounting for Small Businesses','accounting-for-small-businesses','AKM-02-004','Alicia Tan, CPA','9786214500209','Taas Publishing','Paperback','English',372,'2025-09-25','Bookkeeping, BIR compliance and cash flow for tiangge owners and freelancers.','Written for the sari-sari store owner and the freelance designer alike, this practical guide covers the books you actually need, how to keep them, and what the Bureau of Internal Revenue expects. Includes a full sample set of records for one business year.',980.00,NULL,11,1,0,0,0,0),
(2,'Technical Writing for Filipino Students','technical-writing-for-filipino-students','AKM-02-005','Prof. Noel Bautista','9786214500216','Taas Publishing','Paperback','English',300,'2025-01-17','Memoranda, reports, proposals and documentation — with Filipino workplace examples.','A writing course in one volume. Each chapter pairs a short principle with annotated before-and-after samples taken from Philippine offices, NGOs and engineering firms, then asks the reader to revise a real document.',640.00,NULL,19,1,0,0,0,0),
(2,'Nursing Fundamentals Review','nursing-fundamentals-review','AKM-02-006','Dr. Grace Villanueva','9786214500223','San Lorenzo Medical Press','Paperback','English',640,'2025-10-10','Nine hundred review questions with rationales for the Philippine Nurse Licensure Exam.','Organised by the official PNLE blueprint and updated for the current competencies. Every answer carries a rationale plus a plain-language explanation of the underlying concept. Includes five full practice tests and a readiness tracker.',1480.00,1650.00,7,1,0,0,0,0),
(3,'Mga Tula ng Dagat','mga-tula-ng-dagat','AKM-03-001','Liwayway Hernandez','9786214500230','Bagong Araw Publishing','Paperback','Filipino',152,'2025-03-11','Tatlumpu''t anim na tula tungkol sa dagat, paglisan at pagbabalik.','Sa unang koleksiyong ito, isinusulat ni Liwayway Hernandez ang dagat hindi bilang tanawin kundi bilang kamag-anak — maingay, matigas ang ulo, at hindi kailanman lubos na nakikilala. May mga tulang nasa Bikol at Tagalog.',420.00,NULL,26,1,0,1,0,0),
(3,'Ang Bahay na May Tsinelas','ang-bahay-na-may-tsinelas','AKM-03-002','Corazon dela Fuente','9786214500247','Bagong Araw Publishing','Paperback','Filipino',188,'2025-06-06','Isang maliit na nobela tungkol sa isang bahay na ayaw magpaalam.','Sa loob ng apat na dekada, isang pamilya sa Bulacan ang pumasok at lumabas sa isang bahay na may kakaibang patakaran: hindi ka maaaring umalis nang walang tsinelas. Nakakatawa, malungkot, at tapat sa diwa ng mga tahanang Pilipino.',380.00,NULL,30,1,1,0,0,0),
(3,'Florante at Laura (Annotated Student Edition)','florante-at-laura-annotated','AKM-03-003','Francisco Balagtas','9786214500254','Pamana Classics','Paperback','Filipino',214,'2024-06-15','Ang klasiko, may talababa, glosaryo at gabay sa pagsusuri.','Malinis na edisyon ng awit ni Balagtas na may modernong talababa sa bawat saknong, glosaryo ng lumang salita, at mga tanong sa pagsusuri para sa mga mag-aaral ng Filipino sa sekondarya.',295.00,NULL,40,1,0,1,0,0),
(3,'Noli Me Tangere (Student Edition)','noli-me-tangere-student-edition','AKM-03-004','Jose Rizal','9786214500261','Pamana Classics','Paperback','English',468,'2024-06-15','The Charles Derbyshire translation with margin notes and a student glossary.','A clean, affordable student printing with chapter summaries in the margins, a glossary of Spanish-era terms, and a short essay on the novel''s publication history. Widely used in Grade 9 Filipino and English classes.',350.00,NULL,35,1,0,0,0,0),
(3,'Dagat sa Silangan: Mga Kuwentong Bayan','dagat-sa-silangan-mga-kuwentong-bayan','AKM-03-005','Teresa Manalo','9786214500278','Pamana Classics','Paperback','Filipino',236,'2025-08-01','Labindalawang kuwentong bayan mula sa mga baybaying lalawigan, muling isinalaysay.','Inipon at muling isinalaysay ni Teresa Manalo ang labindalawang kuwento mula sa mga lalawigan ng Quezon, Samar at Surigao. May tala ang bawat kuwento tungkol sa pinagmulan nito at sa mga taong nagbahagi nito.',465.00,NULL,21,1,0,0,1,0),
(3,'Ang Mangagawa: Mga Sanaysay sa Hanapbuhay','ang-mangagawa-mga-sanaysay','AKM-03-006','Joel Marasigan','9786214500285','Tala Press','Paperback','Filipino',264,'2025-05-20','Labing-anim na sanaysay tungkol sa paggawa, sahod at karangalan.','Mula sa pabrika ng sapatos sa Marikina hanggang sa mga cloud kitchen ng Metro Manila, sinusuri ni Joel Marasigan ang mga trabahong madalas hindi napapansin. Personal at mapagmasid, hindi mabigat sa teorya.',510.00,NULL,13,1,0,0,0,1),
(4,'Kadena: Alagad ng Buwan Vol. 1','kadena-alagad-ng-buwan-vol-1','AKM-04-001','Kiko Yabut','9786214500292','Halo Komiks','Paperback','Filipino',192,'2025-09-01','Isang binatang may tanikalang pilak, isang lungsod na puno ng anino.','Nagsimula bilang webcomic na may milyong panonood, ngayon nasa print na. Si Kiko Yabut ay gumuhit ng lungsod na bahagyang Maynila, bahagyang panaginip, at isang bida na hindi sigurado kung siya ang bayani.',399.00,NULL,44,1,0,1,0,0),
(4,'Kadena: Alagad ng Buwan Vol. 2','kadena-alagad-ng-buwan-vol-2','AKM-04-002','Kiko Yabut','9786214500308','Halo Komiks','Paperback','Filipino',200,'2026-01-20','Nagtuloy ang laban sa ikalawang tomo — may bagong kalaban at lumang kasalanan.','Sa ikalawang tomo, kailangan nang harapin ng bida ang pinagmulan ng tanikalang pilak. Dalawampung bagong kabanata, kasama ang isang espesyal na kuwento tungkol sa unang Alagad ng Buwan.',399.00,NULL,38,1,0,0,1,0),
(4,'Barako Boys Vol. 1','barako-boys-vol-1','AKM-04-003','Ysa Ferrer','9786214500315','Halo Komiks','Paperback','Filipino',176,'2025-07-07','Tatlong magkakaibigan, isang basketball court, at isang napakalaking pustahan.','Isang nakakatawang serye tungkol sa pagkakaibigan at lokal na basketball. Ang unang tomo ay may kasamang sketchbook section at komentaryo ng may-akda sa bawat kabanata.',385.00,NULL,27,1,0,0,0,0),
(4,'The Cram School Club Vol. 1','the-cram-school-club-vol-1','AKM-04-004','Hana Mizuki','9786214500322','Shinbun Comics PH','Paperback','English',208,'2026-02-14','Five students, one review centre, and a study group that refuses to fail.','A warm school comedy about a tutoring centre where the tutors are barely older than the students. First volume of the series, with a bonus chapter set during enrolment week.',450.00,NULL,33,1,0,0,1,0),
(4,'Star Fisher Chronicles Vol. 3','star-fisher-chronicles-vol-3','AKM-04-005','Hana Mizuki','9786214500339','Shinbun Comics PH','Paperback','English',216,'2025-12-05','The fleet reaches the quiet sea, and the crew finally learns what they are carrying.','Volume three raises the stakes: a mutiny, a broken compass, and a chapter told entirely from the ship''s point of view. Collects chapters 18 to 27.',455.00,NULL,20,1,0,0,0,0),
(4,'Pinoy Superheroes: An Anthology','pinoy-superheroes-anthology','AKM-04-006','Various Artists','9786214500346','Halo Komiks','Hardcover','English',288,'2025-11-22','Eighteen creators reinvent the Filipino superhero in eighteen short comics.','An oversized hardcover anthology pairing established komiks artists with newer voices. Each story is introduced by its creator, and the volume closes with a gallery of character designs.',620.00,NULL,15,1,0,0,0,1),
(5,'Ang Maliit na Banga','ang-maliit-na-banga','AKM-05-001','Lourdes Aquino','9786214500353','Batang Aklat','Paperback','Filipino',32,'2025-04-18','Isang batang banga na natutong mag-ingat sa sarili.','Ang maliit na banga ay ayaw mahulog. Sa tulong ng lola at ng kanyang hardin, natutunan niyang ang pag-iingat ay hindi takot kundi pagmamahal. Mainam para sa edad 3 hanggang 7.',320.00,NULL,42,1,0,1,0,0),
(5,'Maya and the Mango Tree','maya-and-the-mango-tree','AKM-05-002','Cindy Reyes','9786214500360','Batang Aklat','Paperback','English',36,'2025-09-12','A girl, a tree, and a very patient carabao.','Maya is sure the mango tree in her lola''s yard is hiding something. It takes one rainy season, three arguments and a carabao named Bino to find out what. A gentle early-reader story about patience and harvest.',340.00,NULL,37,1,0,0,0,0),
(5,'Ang Buwaya na Ayaw Maligo','ang-buwaya-na-ayaw-maligo','AKM-05-003','Pia Gonzales','9786214500377','Batang Aklat','Paperback','Filipino',28,'2025-02-08','Ang buwayang ayaw maligo ay may isang napakalaking problema sa ilog.','Nakakatawang kuwento tungkol sa kalinisan at pagkakaibigan. May malalaking letra at paulit-ulit na berso para sa mga nagsisimula pa lamang magbasa.',295.00,NULL,29,1,0,0,0,0),
(5,'Laro Tayo! Tagalog Activity Book','laro-tayo-tagalog-activity-book','AKM-05-004','Studio Araw','9786214500384','Batang Aklat','Paperback','Filipino',64,'2026-03-18','Puzzles, mazes and word games that build Tagalog vocabulary.','Sixty-four pages of screen-free activities: salita searches, pattern games, colouring pages and simple crossword puzzles. Answer key at the back. Recommended for ages 5 to 9.',260.00,NULL,50,1,0,0,1,0),
(5,'The Brave Little Bangka','the-brave-little-bangka','AKM-05-005','Miguel Ferrer','9786214500391','Batang Aklat','Hardcover','English',40,'2025-06-30','A small outrigger boat proves that brave does not have to mean big.','When a storm scatters the fishing fleet, the smallest boat in the harbour is the one still afloat. A reassuring bedtime story with luminous illustrations.',330.00,NULL,23,1,0,0,0,0),
(6,'Filipino Food Monthly — Issue 118','filipino-food-monthly-issue-118','AKM-06-001','FFM Editorial','9770118001184','Filipino Food Monthly','Magazine','English',96,'2026-02-01','The heirloom rice issue: five provinces, eleven varieties, one very long table.','This month: heirloom rice from Ifugao to Antique, a guide to cooking with kalamansi peel, and a recipe diary from a family that has run the same carinderia since 1974.',180.00,NULL,60,1,0,0,1,0),
(6,'Travel Philippines Quarterly — Islands Edition','travel-philippines-quarterly-islands','AKM-06-002','TPQ Editorial','9770118002198','Travel Philippines Quarterly','Magazine','English',132,'2025-12-15','Twelve islands without an airport, mapped and photographed.','A quarterly devoted to slow travel. This edition profiles twelve islands reachable only by boat, with practical sailing schedules, guesthouse rates and a foldout regional map.',220.00,NULL,34,1,0,0,0,0),
(6,'Comics Review Annual 2026','comics-review-annual-2026','AKM-06-003','Comics Review','9770118003204','Comics Review','Magazine','English',168,'2026-01-05','The year in Philippine comics — reviews, interviews and the best-of list.','Annual roundup with forty reviews, six long-form interviews and the editorial team''s top fifty releases of the previous year. Includes a directory of komiks creators and presses.',350.00,NULL,25,1,0,0,0,0),
(6,'Home & Harvest Magazine No. 42','home-and-harvest-magazine-42','AKM-06-004','HH Editorial','9770118004211','Home & Harvest','Magazine','English',104,'2025-10-22','Small-space gardening for city balconies and compound walls.','Practical guides for growing talbos ng kamote, kangkong and herbs in containers, plus a feature on building a rain barrel for under ₱1,500.',195.00,NULL,31,1,0,0,0,0),
(7,'Himig Kundiman (Limited Vinyl LP)','himig-kundiman-limited-vinyl','AKM-07-001','Various Artists',NULL,'Isla Records','Vinyl LP','Filipino',NULL,'2025-11-14','Twenty kundiman classics, remastered and pressed on 180-gram vinyl.','A carefully sequenced anthology of kundiman standards from 1935 to 1962, restored from the original acetates and pressed on heavyweight vinyl. Gatefold sleeve with bilingual liner notes and full lyric transcriptions.',1899.00,2200.00,8,1,1,0,0,0),
(7,'OPM 90s Greatest Hits (2-CD)','opm-90s-greatest-hits-2cd','AKM-07-002','Various Artists',NULL,'Isla Records','2× Compact Disc','English',NULL,'2025-08-08','Thirty-six tracks that defined a decade of Filipino radio.','Two discs of the songs that played in every jeepney, canteen and school program of the 1990s, remastered for the first time. Includes a twenty-page booklet of band photographs.',620.00,NULL,19,1,0,0,0,0),
(7,'Tala Acoustic Sessions (CD)','tala-acoustic-sessions-cd','AKM-07-003','Tala',NULL,'Bughaw Music','Compact Disc','English',NULL,'2026-01-30','Nine songs recorded live in a Quezon City living room, one take each.','Stripped back arrangements, a borrowed upright piano and no overdubs. The album that convinced a lot of people that Tala sounds better without a band.',550.00,NULL,26,1,0,0,1,0),
(7,'Pagsibol (Vinyl EP)','pagsibol-vinyl-ep','AKM-07-004','Bughaw',NULL,'Bughaw Music','Vinyl EP','Filipino',NULL,'2025-05-16','Four songs about growing things, pressed on translucent green vinyl.','A four-track EP recorded over one rainy weekend in Laguna. Limited pressing of five hundred numbered copies, each with a seed-paper insert.',980.00,NULL,10,1,0,0,0,1),
(8,'Sa Ilalim ng Tulay (Restored Classic DVD)','sa-ilalim-ng-tulay-restored-dvd','AKM-08-001','Director: R. Villamor',NULL,'Sineng Pamana','DVD','Filipino',NULL,'2025-06-12','The 1976 classic, restored frame by frame from the only surviving print.','Long considered lost, this landmark drama was restored from a damaged 16mm print over two years. The disc includes a making-of documentary, an interview with the cinematographer, and English subtitles.',450.00,NULL,18,1,0,1,0,0),
(8,'Manila Film Archive Collection (3-DVD Box)','manila-film-archive-collection-dvd','AKM-08-002','Manila Film Archive',NULL,'Sineng Pamana','3× DVD Box Set','Filipino',NULL,'2025-10-01','Nine short films from 1952 to 1989, in one restored box set.','A three-disc set covering documentary, animation and experimental film from Philippine archives, with scholarly commentary tracks and a forty-page booklet.',1150.00,NULL,9,1,0,0,0,0),
(8,'The Sari-Sari Store: Short Films (DVD)','the-sari-sari-store-short-films-dvd','AKM-08-003','Various Directors',NULL,'Sineng Pamana','DVD','Filipino',NULL,'2026-02-20','Seven short films, one corner store, seven different neighbourhoods.','A collection of award-winning shorts from the last five years, each set in or around a neighbourhood sari-sari store. Total running time 96 minutes, with director commentaries.',390.00,NULL,22,1,0,0,1,0),
(9,'Hand-bound Katipunan Notebook','hand-bound-katipunan-notebook','AKM-09-001','Aklatan Studio',NULL,'Aklatan Studio','Notebook','English',160,'2025-03-01','160 pages of cream paper, hand-bound with a linen thread spine.','Made for people who still write their reading notes by hand. Bound in Quezon City with a linen thread spine that opens flat, a ribbon marker and 160 pages of 90gsm cream paper.',480.00,NULL,28,1,0,0,0,1),
(9,'Vintage Manuscript Bookmarks (Set of 6)','vintage-manuscript-bookmarks','AKM-09-002','Aklatan Studio',NULL,'Aklatan Studio','Set of 6','English',NULL,'2025-07-19','Six printed bookmarks reproducing pages from century-old Philippine manuscripts.','Each bookmark reproduces a different manuscript page from the national archives, printed on heavy card with a matte finish and a tassel in one of six colours.',220.00,NULL,64,1,0,0,0,0),
(9,'Brass Bookend Pair: Reading Carabao','brass-bookend-pair-reading-carabao','AKM-09-003','Aklatan Studio',NULL,'Aklatan Studio','Bookends','English',NULL,'2025-09-09','Solid brass bookends shaped like a carabao that has discovered literature.','Cast in brass, hand-polished and felted underneath so they will not scratch a shelf. Each pair weighs 1.9 kg and is packed in a cloth bag. A genuinely permanent gift for a reader.',1350.00,NULL,6,1,0,0,0,1),
(9,'Typewriter Key Keychains','typewriter-key-keychains','AKM-09-004','Aklatan Studio',NULL,'Aklatan Studio','Keychain','English',NULL,'2025-04-25','Genuine typewriter keys, cleaned and mounted on a leather loop.','Keys are salvaged from decommissioned Philippine office typewriters, so every keychain is a little different. Choose from A to Z and a few punctuation marks at checkout notes.',150.00,NULL,80,1,0,0,0,0),
(10,'Filipino Literature Starter Bundle','filipino-literature-starter-bundle','AKM-10-001','Aklatan Market Curation',NULL,'Aklatan Market','Bundle of 4','English',NULL,'2025-12-01','Four Filipino novels and story collections chosen as a first shelf.','Includes The Sampaguita Letters, Mango Season at Barangay Siete, Mga Tula ng Dagat and Ang Bahay na May Tsinelas. Bundled at a lower price than buying the four separately, with a printed reading guide.',1490.00,1750.00,12,1,1,1,0,1),
(10,'Student Survival Book Bundle','student-survival-book-bundle','AKM-10-002','Aklatan Market Curation',NULL,'Aklatan Market','Bundle of 4','English',NULL,'2025-12-01','The four titles students ask for most, at one friendlier price.','Includes Philippine History: A Readable Survey, College Algebra Worktext, Technical Writing for Filipino Students and Accounting for Small Businesses. Ideal for first-year college students.',2150.00,2480.00,10,1,0,1,0,0),
(10,'Kids'' Storytime Bundle (5 books)','kids-storytime-bundle','AKM-10-003','Aklatan Market Curation',NULL,'Aklatan Market','Bundle of 5','English',NULL,'2026-01-15','Five picture books for bedtime, in English and Filipino.','Includes Ang Maliit na Banga, Maya and the Mango Tree, Ang Buwaya na Ayaw Maligo, The Brave Little Bangka and Laro Tayo! Tagalog Activity Book. Gift-wrapped on request.',1395.00,1600.00,16,1,0,0,1,0);


-- -----------------------------------------------------------------------------
-- Example customers, orders, payments, reviews and enquiries
--
--  These records exist so the storefront, dashboard and reports look alive
--  straight after installation. The demo accounts all share one documented
--  password (see INSTALLATION_GUIDE.txt).
--
--  >>> DELETE THEM BEFORE SELLING FOR REAL <<<
--  setup_admin.php offers a one-click cleanup, or run:
--      DELETE FROM users WHERE email LIKE '%@example.com';
--  Orders, reviews, carts, wishlists and payments are removed with them.
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`,`first_name`,`last_name`,`email`,`phone`,`password_hash`,`role`,`status`,`created_at`) VALUES
(1,'Maria','Santos','maria.santos@example.com','09171234501','$2y$10$V456sqaUVYjD6ZXBFw4au.vBEjKt6VJlsCV40UFYomu5Wm6DVWc8W','customer','active',DATE_SUB(NOW(), INTERVAL 45 DAY)),
(2,'Juan','Dela Cruz','juan.delacruz@example.com','09181234502','$2y$10$V456sqaUVYjD6ZXBFw4au.vBEjKt6VJlsCV40UFYomu5Wm6DVWc8W','customer','active',DATE_SUB(NOW(), INTERVAL 38 DAY)),
(3,'Ana','Reyes','ana.reyes@example.com','09191234503','$2y$10$V456sqaUVYjD6ZXBFw4au.vBEjKt6VJlsCV40UFYomu5Wm6DVWc8W','customer','active',DATE_SUB(NOW(), INTERVAL 25 DAY)),
(4,'Paolo','Garcia','paolo.garcia@example.com','09201234504','$2y$10$V456sqaUVYjD6ZXBFw4au.vBEjKt6VJlsCV40UFYomu5Wm6DVWc8W','customer','active',DATE_SUB(NOW(), INTERVAL 11 DAY));

INSERT INTO `user_addresses` (`user_id`,`label`,`recipient_name`,`phone`,`address_line1`,`address_line2`,`city`,`province`,`postal_code`,`is_default`) VALUES
(1,'Home','Maria Santos','09171234501','24 Kalayaan Street','Barangay Poblacion','Makati City','Metro Manila','1210',1),
(2,'Home','Juan Dela Cruz','09181234502','118-B Sampaguita Avenue','Barangay San Roque','Quezon City','Metro Manila','1103',1),
(3,'Office','Ana Reyes','09191234503','Unit 905 Trade Hall Building','Ortigas Center','Pasig City','Metro Manila','1605',1),
(4,'Home','Paolo Garcia','09201234504','7 Mabini Extension','Barangay Bucal','Calamba','Laguna','4027',1);

INSERT INTO `wishlists` (`user_id`,`product_id`,`created_at`) VALUES
(1,23,DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1,25,DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2,37,DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3,9,DATE_SUB(NOW(), INTERVAL 9 DAY)),
(3,41,DATE_SUB(NOW(), INTERVAL 8 DAY));

INSERT INTO `orders`
 (`id`,`order_number`,`user_id`,`status`,`payment_method`,`payment_status`,`subtotal`,`discount_amount`,`discount_code`,`shipping_fee`,`total`,
  `customer_name`,`email`,`phone`,`address_line1`,`address_line2`,`city`,`province`,`postal_code`,`delivery_notes`,`stock_restored`,`created_at`) VALUES
(1,'AKL-20260915-4KPQ2',1,'delivered','gcash','verified',2388.00,0.00,NULL,0.00,2388.00,
 'Maria Santos','maria.santos@example.com','09171234501','24 Kalayaan Street','Barangay Poblacion','Makati City','Metro Manila','1210','Please leave with the guard if I am out.',0,DATE_SUB(NOW(), INTERVAL 34 DAY)),
(2,'AKL-20260928-8HMX3',2,'delivered','cod','verified',3138.00,0.00,NULL,0.00,3138.00,
 'Juan Dela Cruz','juan.delacruz@example.com','09181234502','118-B Sampaguita Avenue','Barangay San Roque','Quezon City','Metro Manila','1103','Cash ready, please bring change.',0,DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3,'AKL-20261001-3TNW7',3,'delivered','gcash','verified',2730.00,0.00,NULL,0.00,2730.00,
 'Ana Reyes','ana.reyes@example.com','09191234503','Unit 905 Trade Hall Building','Ortigas Center','Pasig City','Metro Manila','1605',NULL,0,DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4,'AKL-20261004-6CJK1',4,'delivered','pickup','verified',849.00,0.00,NULL,0.00,849.00,
 'Paolo Garcia','paolo.garcia@example.com','09201234504','7 Mabini Extension','Barangay Bucal','Calamba','Laguna','4027','Will collect on Saturday morning.',0,DATE_SUB(NOW(), INTERVAL 12 DAY)),
(5,'AKL-20261006-9QDF4',4,'delivered','gcash','verified',1099.00,0.00,NULL,99.00,1198.00,
 'Paolo Garcia','paolo.garcia@example.com','09201234504','7 Mabini Extension','Barangay Bucal','Calamba','Laguna','4027',NULL,0,DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6,'AKL-20261008-5LPA8',2,'shipped','cod','unpaid',1650.00,0.00,NULL,0.00,1650.00,
 'Juan Dela Cruz','juan.delacruz@example.com','09181234502','118-B Sampaguita Avenue','Barangay San Roque','Quezon City','Metro Manila','1103','Text me before delivery.',0,DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7,'AKL-20261009-2WQJ5',3,'processing','gcash','verified',1490.00,0.00,NULL,99.00,1589.00,
 'Ana Reyes','ana.reyes@example.com','09191234503','Unit 905 Trade Hall Building','Ortigas Center','Pasig City','Metro Manila','1605','Office hours only, 10am to 5pm.',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8,'AKL-20261011-7VBG6',1,'pending','gcash','pending_verification',635.00,0.00,NULL,99.00,734.00,
 'Maria Santos','maria.santos@example.com','09171234501','24 Kalayaan Street','Barangay Poblacion','Makati City','Metro Manila','1210',NULL,0,DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9,'AKL-20261012-4KRS2',2,'cancelled','cod','unpaid',529.00,0.00,NULL,99.00,628.00,
 'Juan Dela Cruz','juan.delacruz@example.com','09181234502','118-B Sampaguita Avenue','Barangay San Roque','Quezon City','Metro Manila','1103','Customer changed their mind before dispatch.',1,DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_sku`,`product_image`,`unit_price`,`quantity`,`line_total`) VALUES
(1,1,'The Sampaguita Letters','AKM-01-001','assets/images/covers/the-sampaguita-letters.svg',649.00,2,1298.00),
(1,22,'The Cram School Club Vol. 1','AKM-04-004','assets/images/covers/the-cram-school-club-vol-1.svg',450.00,1,450.00),
(1,13,'Mga Tula ng Dagat','AKM-03-001','assets/images/covers/mga-tula-ng-dagat.svg',420.00,1,420.00),
(1,42,'Vintage Manuscript Bookmarks (Set of 6)','AKM-09-002','assets/images/covers/vintage-manuscript-bookmarks.svg',220.00,1,220.00),
(2,13,'Mga Tula ng Dagat','AKM-03-001','assets/images/covers/mga-tula-ng-dagat.svg',420.00,2,840.00),
(2,19,'Kadena: Alagad ng Buwan Vol. 1','AKM-04-001','assets/images/covers/kadena-alagad-ng-buwan-vol-1.svg',399.00,1,399.00),
(2,34,'Himig Kundiman (Limited Vinyl LP)','AKM-07-001','assets/images/covers/himig-kundiman-limited-vinyl.svg',1899.00,1,1899.00),
(3,7,'Philippine History: A Readable Survey','AKM-02-001','assets/images/covers/philippine-history-a-readable-survey.svg',1250.00,1,1250.00),
(3,12,'Nursing Fundamentals Review','AKM-02-006','assets/images/covers/nursing-fundamentals-review.svg',1480.00,1,1480.00),
(4,2,'Mango Season at Barangay Siete','AKM-01-002','assets/images/covers/mango-season-at-barangay-siete.svg',529.00,1,529.00),
(4,25,'Ang Maliit na Banga','AKM-05-001','assets/images/covers/ang-maliit-na-banga.svg',320.00,1,320.00),
(5,1,'The Sampaguita Letters','AKM-01-001','assets/images/covers/the-sampaguita-letters.svg',649.00,1,649.00),
(5,22,'The Cram School Club Vol. 1','AKM-04-004','assets/images/covers/the-cram-school-club-vol-1.svg',450.00,1,450.00),
(6,8,'College Algebra Worktext (Revised Edition)','AKM-02-002','assets/images/covers/college-algebra-worktext.svg',890.00,1,890.00),
(6,9,'General Chemistry Laboratory Manual','AKM-02-003','assets/images/covers/general-chemistry-laboratory-manual.svg',760.00,1,760.00),
(7,45,'Filipino Literature Starter Bundle','AKM-10-001','assets/images/covers/filipino-literature-starter-bundle.svg',1490.00,1,1490.00),
(8,26,'Maya and the Mango Tree','AKM-05-002','assets/images/covers/maya-and-the-mango-tree.svg',340.00,1,340.00),
(8,27,'Ang Buwaya na Ayaw Maligo','AKM-05-003','assets/images/covers/ang-buwaya-na-ayaw-maligo.svg',295.00,1,295.00),
(9,3,'The Last Jeepney Ride','AKM-01-003','assets/images/covers/the-last-jeepney-ride.svg',529.00,1,529.00);

INSERT INTO `payments`
 (`order_id`,`method`,`amount`,`reference_number`,`payer_name`,`paid_at`,`status`,`customer_note`,`admin_note`,`verified_at`,`created_at`) VALUES
(1,'gcash',2388.00,'0024 118 337 905','Maria Santos',DATE_SUB(NOW(), INTERVAL 34 DAY),'verified','Sent from my GCash wallet.','Matched against the GCash transaction list.',DATE_SUB(NOW(), INTERVAL 33 DAY),DATE_SUB(NOW(), INTERVAL 34 DAY)),
(2,'cod',3138.00,NULL,NULL,DATE_SUB(NOW(), INTERVAL 27 DAY),'verified',NULL,'Cash collected by the courier.',DATE_SUB(NOW(), INTERVAL 27 DAY),DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3,'gcash',2730.00,'0024 991 004 118','Ana G. Reyes',DATE_SUB(NOW(), INTERVAL 20 DAY),'verified','Payment sent, please confirm.','Verified against the monthly statement.',DATE_SUB(NOW(), INTERVAL 19 DAY),DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4,'pickup',849.00,'COUNTER-0417','Paolo Garcia',DATE_SUB(NOW(), INTERVAL 12 DAY),'verified','Paid at the counter.','Counter sale, receipt 0417.',DATE_SUB(NOW(), INTERVAL 12 DAY),DATE_SUB(NOW(), INTERVAL 12 DAY)),
(5,'gcash',1198.00,'0025 330 887 012','Paolo Garcia',DATE_SUB(NOW(), INTERVAL 9 DAY),'verified','Reference from my GCash receipt.','Verified.',DATE_SUB(NOW(), INTERVAL 8 DAY),DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6,'cod',1650.00,NULL,NULL,NULL,'unpaid',NULL,'Cash to be collected on delivery.',NULL,DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7,'gcash',1589.00,'0026 004 551 900','Ana G. Reyes',DATE_SUB(NOW(), INTERVAL 4 DAY),'verified','Paid in full.','Verified against statement.',DATE_SUB(NOW(), INTERVAL 4 DAY),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8,'gcash',734.00,'0031 550 118 442','Maria Santos',DATE_SUB(NOW(), INTERVAL 2 DAY),'pending_verification','Reference from my GCash receipt. Waiting for confirmation.',NULL,NULL,DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9,'cod',628.00,NULL,NULL,NULL,'unpaid',NULL,'Order cancelled before dispatch.',NULL,DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `reviews` (`product_id`,`user_id`,`order_id`,`rating`,`title`,`body`,`status`,`created_at`) VALUES
(1,1,1,5,'Read it in two sittings','I bought this for a long bus ride and finished it before we reached Baguio. The letters chapter near the end completely undid me. Packed well, arrived in perfect condition.','approved',DATE_SUB(NOW(), INTERVAL 30 DAY)),
(22,1,1,4,'Good volume, thin paper','The story is excellent and the art is beautiful. The paper is a little thin so a fine-tip pen ghosts through, but for the price it is hard to complain.','approved',DATE_SUB(NOW(), INTERVAL 29 DAY)),
(42,1,1,5,'Lovely little gift','Bought a set for my mother, who keeps her receipts in books. The card stock is thicker than I expected and the tassels are properly finished.','approved',DATE_SUB(NOW(), INTERVAL 28 DAY)),
(13,2,2,5,'Binili ko para sa kapatid ko','Ang ganda ng mga tula, lalo na iyong tungkol sa paglisan. Mabilis ang delivery at maayos ang pagkakabalot.','approved',DATE_SUB(NOW(), INTERVAL 26 DAY)),
(19,2,2,5,'Worth the wait','I have been following this komiks online since the first chapter. The printed version is much better — the shading really comes through.','approved',DATE_SUB(NOW(), INTERVAL 25 DAY)),
(34,2,2,5,'Sounds incredible','Pressed beautifully and the liner notes are bilingual. Arrived in a proper mailer with corner protectors.','approved',DATE_SUB(NOW(), INTERVAL 24 DAY)),
(7,3,3,5,'Finally a history book I actually finished','Clear, fair and genuinely readable. The source excerpts at the end of each chapter are the best part of the whole book.','approved',DATE_SUB(NOW(), INTERVAL 17 DAY)),
(12,3,3,4,'Very useful for review','The rationales are detailed and the practice tests are close to the real thing. I wish the index were longer, hence four stars.','approved',DATE_SUB(NOW(), INTERVAL 16 DAY)),
(2,4,4,5,'Bought three copies','One for me, two for friends. Funny in the first half, quietly devastating in the second.','approved',DATE_SUB(NOW(), INTERVAL 10 DAY)),
(25,4,4,5,'My daughter asks for it every night','She has memorised the whole thing and now reads it to her cousins. A beautiful message about taking care of yourself.','approved',DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1,4,5,4,'Great novel, slow start','Took me about sixty pages to get into it and then I could not stop. Recommended.','approved',DATE_SUB(NOW(), INTERVAL 6 DAY)),
(22,4,5,5,'Waiting to be moderated','Excellent second read. I am leaving this one for the shop owner to approve so the moderation queue is not empty.','pending',DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `contact_messages` (`name`,`email`,`phone`,`subject`,`message`,`status`,`created_at`) VALUES
('Rina Villamor','rina.villamor@example.com','09171112233','Reserve a title for pickup','Good afternoon! Do you still have a copy of Nursing Fundamentals Review? I would like to reserve one for pickup this Saturday if possible. Thank you.','new',DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Mark Bautista','mark.bautista@example.com','09223334455','Bulk order for a school','We are a public high school in Laguna and would like to order 40 copies of Florante at Laura (Annotated Student Edition). May I ask for a quotation and the lead time?','read',DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO `audit_logs` (`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES
(NULL,'system.installed','settings',NULL,'Aklatan Market starter data imported','127.0.0.1',DATE_SUB(NOW(), INTERVAL 40 DAY)),
(NULL,'order.status_changed','order','1','pending → confirmed','127.0.0.1',DATE_SUB(NOW(), INTERVAL 33 DAY)),
(NULL,'order.payment_verified','payment','1','GCash reference 0024 118 337 905 verified','127.0.0.1',DATE_SUB(NOW(), INTERVAL 33 DAY)),
(NULL,'order.status_changed','order','1','shipped → delivered','127.0.0.1',DATE_SUB(NOW(), INTERVAL 30 DAY)),
(NULL,'order.status_changed','order','6','confirmed → shipped','127.0.0.1',DATE_SUB(NOW(), INTERVAL 3 DAY)),
(NULL,'product.updated','product','1','Price and stock reviewed','127.0.0.1',DATE_SUB(NOW(), INTERVAL 9 DAY));


-- =============================================================================
--  END OF FILE
-- =============================================================================
