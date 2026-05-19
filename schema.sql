-- =============================================================
-- BandStack Manager — Esquema SQL Completo (Actualizado)
-- Motor: MySQL 8.0+ / MariaDB 10.4+
-- Charset: utf8mb4 (soporte emoji y caracteres especiales)
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- BANDS (Grupos / Tenants)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `bands`;
CREATE TABLE `bands` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settings` JSON DEFAULT NULL,
  `logo_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- USERS
-- Roles: 'superadmin' | 'admin' | 'member'
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` ENUM('superadmin','admin','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `avatar_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `fk_users_band` (`band_id`),
  CONSTRAINT `fk_users_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- PRODUCT CATEGORIES
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `name` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `fk_categories_band` (`band_id`),
  CONSTRAINT `fk_categories_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- PRODUCTS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `base_price` DECIMAL(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Precio de venta al público',
  `cost_price` DECIMAL(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Coste de producción/compra',
  `low_stock_alert` INT UNSIGNED NOT NULL DEFAULT '5' COMMENT 'Umbral de stock bajo',
  `image_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  KEY `fk_products_user` (`created_by`),
  KEY `fk_products_band` (`band_id`),
  CONSTRAINT `fk_products_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_products_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- VARIANTS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `variants`;
CREATE TABLE `variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código de referencia opcional',
  `attribute` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: S, M, L, XL, Digital, Vinilo',
  `stock` INT NOT NULL DEFAULT '0',
  `price_override` DECIMAL(8,2) DEFAULT NULL COMMENT 'Precio especial; NULL hereda del producto',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_sku` (`sku`),
  KEY `idx_variants_product` (`product_id`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- STOCK MOVEMENTS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id` INT UNSIGNED NOT NULL,
  `type` ENUM('purchase','sale','gift','adjustment','return') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` INT NOT NULL COMMENT 'Positivo = entrada; Negativo = salida',
  `notes` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_variant` (`variant_id`),
  KEY `idx_sm_created_at` (`created_at`),
  KEY `fk_sm_user` (`created_by`),
  CONSTRAINT `fk_sm_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sm_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- EVENTS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `name` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` ENUM('concert','festival','rehearsal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'concert',
  `status` ENUM('planned','open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `venue` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` VARCHAR(80) COLLATE utf8mb4_unicode_ci DEFAULT 'España',
  `event_date` DATE NOT NULL,
  `cache_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Caché cobrado por la actuación',
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_date` (`event_date`),
  KEY `idx_events_status` (`status`),
  KEY `fk_events_user` (`created_by`),
  KEY `fk_events_band` (`band_id`),
  CONSTRAINT `fk_events_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SALES
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `event_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = venta fuera de concierto',
  `payment_method` ENUM('cash','bizum','card','free') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `notes` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sold_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sales_event` (`event_id`),
  KEY `idx_sales_created_at` (`created_at`),
  KEY `fk_sales_user` (`sold_by`),
  KEY `fk_sales_band` (`band_id`),
  CONSTRAINT `fk_sales_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SALE ITEMS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` DECIMAL(8,2) NOT NULL COMMENT 'Precio en el momento de la venta',
  `subtotal` DECIMAL(10,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_si_sale` (`sale_id`),
  KEY `idx_si_variant` (`variant_id`),
  CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_si_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- EXPENSES
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `event_id` INT UNSIGNED DEFAULT NULL,
  `category` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Otros',
  `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `receipt_url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Foto del ticket subida',
  `expense_date` DATE NOT NULL,
  `is_paid` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0 = Pendiente, 1 = Pagado',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_event` (`event_id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `fk_expenses_user` (`created_by`),
  KEY `fk_expenses_band` (`band_id`),
  CONSTRAINT `fk_expenses_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- INCOMES (Ingresos Extra / Saldo Inicial)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `incomes`;
CREATE TABLE `incomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL DEFAULT '1',
  `category` ENUM('initial_balance','sponsorship','royalties','member_contribution','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `income_date` DATE NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incomes_band` (`band_id`),
  KEY `idx_incomes_date` (`income_date`),
  KEY `fk_incomes_user` (`created_by`),
  CONSTRAINT `fk_incomes_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_incomes_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- EVENT TASKS (Checklist de Eventos)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `event_tasks`;
CREATE TABLE `event_tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `status` ENUM('pending','in_progress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_event` (`event_id`),
  KEY `fk_tasks_user` (`assigned_to`),
  CONSTRAINT `fk_tasks_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- RECURRING EXPENSES (Plantillas de gastos)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `recurring_expenses`;
CREATE TABLE `recurring_expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `band_id` INT NOT NULL,
  `category` ENUM('diet','fuel','toll','promo','gear','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `recurrence_type` ENUM('weekly','monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_due_date` DATE NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_re_band` (`band_id`),
  KEY `fk_re_user` (`created_by`),
  CONSTRAINT `fk_re_band` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_re_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- REFRESH TOKENS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `refresh_tokens`;
CREATE TABLE `refresh_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 del token real',
  `expires_at` DATETIME NOT NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rt_user` (`user_id`),
  KEY `idx_rt_hash` (`token_hash`),
  CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- ACCESS LOGS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `access_logs`;
CREATE TABLE `access_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `email_attempt` VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` ENUM('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- SEED DATA — Datos Semilla Iniciales
-- =============================================================
INSERT INTO `bands` (`id`, `name`) VALUES
    (1, 'Banda Principal');

INSERT INTO `categories` (`id`, `band_id`, `name`, `slug`) VALUES
    (1, 1, 'Ropa',        'ropa'),
    (2, 1, 'Música',      'musica'),
    (3, 1, 'Accesorios',  'accesorios');

INSERT INTO `users` (`band_id`, `name`, `email`, `password_hash`, `role`) VALUES
    (1, 
     'SuperAdmin BandStack',
     'admin@bandstack.local',
     '$2y$12$R.S4w.2P7e7N2.r8B45qEuR8c/G4r9l0yYp1F11W8S1s4C5hVb2Oa', -- Contraseña: "BandStack2025!"
     'superadmin');
