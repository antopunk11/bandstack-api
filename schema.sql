-- =============================================================
-- BandStack Manager — Esquema SQL Inicial (Fase 1)
-- Motor: MySQL 8.0+ / MariaDB 10.4+
-- Charset: utf8mb4 (soporte emoji y caracteres especiales)
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- USERS
-- Roles: 'admin' | 'member'
-- -------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL,
    `email`         VARCHAR(150)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `role`          ENUM('admin','member') NOT NULL DEFAULT 'member',
    `avatar_url`    VARCHAR(255)    NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- PRODUCT CATEGORIES
-- Ejemplos: Ropa, Música, Accesorios
-- -------------------------------------------------------------
CREATE TABLE `categories` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(80)     NOT NULL,
    `slug`          VARCHAR(80)     NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- PRODUCTS
-- Entidad padre: camiseta "Tour 2025", vinilo "Debut", etc.
-- -------------------------------------------------------------
CREATE TABLE `products` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED    NOT NULL,
    `name`              VARCHAR(150)    NOT NULL,
    `description`       TEXT            NULL,
    `base_price`        DECIMAL(8,2)    NOT NULL DEFAULT 0.00  COMMENT 'Precio de venta al público',
    `cost_price`        DECIMAL(8,2)    NOT NULL DEFAULT 0.00  COMMENT 'Coste de producción/compra',
    `low_stock_alert`   INT UNSIGNED    NOT NULL DEFAULT 5      COMMENT 'Umbral de stock bajo',
    `image_url`         VARCHAR(255)    NULL,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`        INT UNSIGNED    NOT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_products_category` (`category_id`),
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_products_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- VARIANTS
-- Una variante = combinación producto + atributo (talla, formato)
-- Ejemplos: Camiseta Tour 2025 / Talla M, Vinilo / Edición Especial
-- -------------------------------------------------------------
CREATE TABLE `variants` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED    NOT NULL,
    `sku`           VARCHAR(50)     NULL        COMMENT 'Código de referencia opcional',
    `attribute`     VARCHAR(50)     NOT NULL    COMMENT 'Ej: S, M, L, XL, Digital, Vinilo',
    `stock`         INT             NOT NULL DEFAULT 0,
    `price_override` DECIMAL(8,2)  NULL        COMMENT 'Precio especial; NULL hereda del producto',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_variant_sku` (`sku`),
    KEY `idx_variants_product` (`product_id`),
    CONSTRAINT `fk_variants_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- STOCK MOVEMENTS
-- Registro inmutable de cada entrada/salida de stock.
-- type: 'purchase' | 'sale' | 'gift' | 'adjustment' | 'return'
-- -------------------------------------------------------------
CREATE TABLE `stock_movements` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `variant_id`    INT UNSIGNED    NOT NULL,
    `type`          ENUM('purchase','sale','gift','adjustment','return') NOT NULL,
    `quantity`      INT             NOT NULL    COMMENT 'Positivo = entrada; Negativo = salida',
    `notes`         VARCHAR(255)    NULL,
    `created_by`    INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sm_variant` (`variant_id`),
    KEY `idx_sm_created_at` (`created_at`),
    CONSTRAINT `fk_sm_variant`
        FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- EVENTS
-- type: 'concert' | 'festival' | 'rehearsal'
-- status: 'planned' | 'open' | 'closed'
-- -------------------------------------------------------------
CREATE TABLE `events` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(150)    NOT NULL,
    `type`          ENUM('concert','festival','rehearsal') NOT NULL DEFAULT 'concert',
    `status`        ENUM('planned','open','closed') NOT NULL DEFAULT 'planned',
    `venue`         VARCHAR(200)    NULL,
    `city`          VARCHAR(100)    NULL,
    `country`       VARCHAR(80)     NULL DEFAULT 'España',
    `event_date`    DATE            NOT NULL,
    `cache_amount`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Caché cobrado por la actuación',
    `notes`         TEXT            NULL,
    `created_by`    INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_date` (`event_date`),
    KEY `idx_events_status` (`status`),
    CONSTRAINT `fk_events_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SALES
-- Cabecera de venta. Una venta tiene uno o más sale_items.
-- payment_method: 'cash' | 'bizum' | 'card' | 'free'
-- -------------------------------------------------------------
CREATE TABLE `sales` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `event_id`          INT UNSIGNED    NULL        COMMENT 'NULL = venta fuera de concierto',
    `payment_method`    ENUM('cash','bizum','card','free') NOT NULL DEFAULT 'cash',
    `total_amount`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `notes`             VARCHAR(255)    NULL,
    `sold_by`           INT UNSIGNED    NOT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sales_event` (`event_id`),
    KEY `idx_sales_created_at` (`created_at`),
    CONSTRAINT `fk_sales_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_user`
        FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SALE ITEMS
-- Línea de detalle de cada venta
-- -------------------------------------------------------------
CREATE TABLE `sale_items` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `sale_id`       INT UNSIGNED    NOT NULL,
    `variant_id`    INT UNSIGNED    NOT NULL,
    `quantity`      INT UNSIGNED    NOT NULL DEFAULT 1,
    `unit_price`    DECIMAL(8,2)    NOT NULL COMMENT 'Precio en el momento de la venta',
    `subtotal`      DECIMAL(10,2)   GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
    PRIMARY KEY (`id`),
    KEY `idx_si_sale` (`sale_id`),
    KEY `idx_si_variant` (`variant_id`),
    CONSTRAINT `fk_si_sale`
        FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_si_variant`
        FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- EXPENSES
-- Tickets de gasto vinculados (o no) a un evento.
-- category: 'diet' | 'fuel' | 'toll' | 'promo' | 'gear' | 'other'
-- -------------------------------------------------------------
CREATE TABLE `expenses` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `event_id`      INT UNSIGNED    NULL,
    `category`      ENUM('diet','fuel','toll','promo','gear','other') NOT NULL DEFAULT 'other',
    `description`   VARCHAR(255)    NOT NULL,
    `amount`        DECIMAL(10,2)   NOT NULL,
    `receipt_url`   VARCHAR(255)    NULL        COMMENT 'Foto del ticket subida',
    `expense_date`  DATE            NOT NULL,
    `created_by`    INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expenses_event` (`event_id`),
    KEY `idx_expenses_date` (`expense_date`),
    CONSTRAINT `fk_expenses_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_expenses_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- REFRESH TOKENS
-- Soporte para el flujo de refresh (7 días de persistencia)
-- -------------------------------------------------------------
CREATE TABLE `refresh_tokens` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `token_hash`    VARCHAR(255)    NOT NULL COMMENT 'SHA-256 del token real',
    `expires_at`    DATETIME        NOT NULL,
    `revoked`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rt_user` (`user_id`),
    KEY `idx_rt_hash` (`token_hash`),
    CONSTRAINT `fk_rt_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- SEED DATA — Categorías base y usuario admin inicial
-- Contraseña del admin: "BandStack2025!" (cámbiala tras el primer login)
-- =============================================================
INSERT INTO `categories` (`name`, `slug`) VALUES
    ('Ropa',        'ropa'),
    ('Música',      'musica'),
    ('Accesorios',  'accesorios');

INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
    ('Admin BandStack',
     'admin@bandstack.local',
     '$2y$12$placeholder_run_hash_script',  -- Ver instrucciones en README
     'admin');
