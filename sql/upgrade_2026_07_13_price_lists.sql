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
