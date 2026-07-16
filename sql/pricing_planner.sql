SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','manager','editor','viewer') NOT NULL DEFAULT 'viewer',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_groups (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL UNIQUE,
 preferred_margin DECIMAL(8,6) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 group_id INT UNSIGNED NULL,
 sku VARCHAR(100) NULL,
 product_name VARCHAR(255) NOT NULL,
 product_code VARCHAR(100) NULL,
 unit_cost DECIMAL(14,5) NULL,
 labour_cost DECIMAL(14,5) NOT NULL DEFAULT 0,
 target_margin DECIMAL(8,6) NOT NULL DEFAULT .8,
 preferred_price_override DECIMAL(14,4) NULL,
 msrp DECIMAL(14,4) NULL,
 competitor_price DECIMAL(14,4) NULL,
 retail_price DECIMAL(14,4) NULL COMMENT 'Excluding VAT',
 trade_discount DECIMAL(8,6) NOT NULL DEFAULT .4,
 trade_price DECIMAL(14,4) NULL,
 minimum_margin DECIMAL(8,6) NOT NULL DEFAULT .2,
 is_wholesale TINYINT(1) NOT NULL DEFAULT 0,
 archived_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_product_name(product_name), INDEX idx_sku(sku), INDEX idx_code(product_code),
 INDEX idx_archived(archived_at), INDEX idx_updated(updated_at),
 CONSTRAINT fk_product_group FOREIGN KEY(group_id) REFERENCES product_groups(id) ON DELETE SET NULL,
 CONSTRAINT fk_product_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_product_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pricing_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL,
 updated_by INT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_setting_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_lists (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(255) NOT NULL,
 valid_from DATE NULL,
 valid_to DATE NULL,
 status ENUM('draft','internal','live') NOT NULL DEFAULT 'draft',
 custom_pricing_enabled TINYINT(1) NOT NULL DEFAULT 0,
 global_discount DECIMAL(8,6) NULL,
 columns_json JSON NULL,
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_price_list_status(status), INDEX idx_price_list_dates(valid_from, valid_to),
 CONSTRAINT fk_price_list_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_price_list_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_list_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 price_list_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 custom_discount DECIMAL(8,6) NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_price_list_product(price_list_id, product_id),
 INDEX idx_price_list_item_product(product_id),
 CONSTRAINT fk_price_list_item_list FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
 CONSTRAINT fk_price_list_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 product_id BIGINT UNSIGNED NULL,
 action VARCHAR(40) NOT NULL,
 field_name VARCHAR(100) NULL,
 old_value TEXT NULL,
 new_value TEXT NULL,
 ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_created(created_at), INDEX idx_audit_product(product_id), INDEX idx_audit_user(user_id),
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_audit_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial administrator: username admin, password ChangeMeNow!2026
-- Change this password immediately after the first login.
INSERT INTO users(username,email,password_hash,role) VALUES
('admin','admin@example.com','$2y$12$ImJWvX04hKMFEs/Zw/FzduayHJilXQnH41.4D3GWnL9e8S.Jln.7C','admin');
INSERT INTO pricing_settings(setting_key,setting_value) VALUES
('vat_rate','0.20'),('target_margin','0.80'),('trade_discount','0.40'),('minimum_margin','0.20');
INSERT INTO product_groups(name,preferred_margin) VALUES ('Stamps',0.80),('Cards',0.58),('Accessories',0.70);
INSERT INTO products(group_id,sku,product_name,product_code,unit_cost,labour_cost,target_margin,retail_price,trade_discount,trade_price,minimum_margin,is_wholesale) VALUES
(1,'LS-STO-26594','Wispens Stamp','LAV1065',0.67,0,0.80,4.00,0.40,2.70,0.25,1),
(1,'LS-STO-26593','Tumbles Lantern Stamp','LAV1064',0.83,0,0.80,5.75,0.40,3.32,0.25,1),
(1,'LS-STO-26592','Tumble Small Stamp','LAV1063',0.86,0,0.80,6.00,0.40,3.60,0.25,1),
(2,'LS-STO-26627','Wishing You The Happiest of Birthdays Card','KG08NT',NULL,0,0.58,NULL,0.40,NULL,0.25,0);
