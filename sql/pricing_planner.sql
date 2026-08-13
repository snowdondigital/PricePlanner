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
 discount_basis ENUM('retail','trade') NOT NULL DEFAULT 'retail',
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
-- Fictional sample records for evaluating a fresh installation.
INSERT INTO product_groups(name,preferred_margin) VALUES
('Homeware',0.65),('Stationery',0.72),('Gift Sets',0.60);

INSERT INTO products(group_id,sku,product_name,product_code,unit_cost,labour_cost,target_margin,preferred_price_override,msrp,competitor_price,retail_price,trade_discount,trade_price,minimum_margin,is_wholesale) VALUES
(1,'DEMO-HW-1042','Speckled Ceramic Planter','DHW-4821',4.35,0.80,0.65,NULL,16.00,14.50,13.25,0.35,8.60,0.22,1),
(1,'DEMO-HW-2187','Woven Cotton Coaster Set','DHW-7354',2.10,0.45,0.65,NULL,9.50,8.99,8.00,0.35,5.20,0.22,1),
(1,'DEMO-HW-6631','Amber Glass Bud Vase','DHW-1968',3.75,0.60,0.65,12.95,15.00,13.49,12.50,0.35,8.10,0.22,1),
(2,'DEMO-ST-3095','Recycled Paper Notebook','DST-6247',1.85,0.25,0.72,NULL,8.50,7.99,7.50,0.40,4.50,0.20,1),
(2,'DEMO-ST-4578','Fine Line Pen Trio','DST-3086',1.20,0.15,0.72,NULL,6.50,5.95,5.25,0.40,3.15,0.20,1),
(2,'DEMO-ST-8924','Undated Weekly Desk Pad','DST-9513',2.65,0.35,0.72,NULL,12.00,10.99,10.50,0.40,6.30,0.20,1),
(3,'DEMO-GS-1256','Tea Break Gift Box','DGS-4072',7.80,1.50,0.60,NULL,26.00,24.95,23.50,0.30,16.45,0.25,1),
(3,'DEMO-GS-5743','Mini Self-Care Collection','DGS-8639',9.25,1.75,0.60,29.95,34.00,31.50,29.00,0.30,20.30,0.25,1),
(3,'DEMO-GS-7460','New Home Welcome Set','DGS-2154',11.40,2.10,0.60,NULL,42.00,NULL,37.50,0.30,26.25,0.25,0);
