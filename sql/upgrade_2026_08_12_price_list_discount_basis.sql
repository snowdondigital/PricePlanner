ALTER TABLE price_lists
 ADD COLUMN discount_basis ENUM('retail','trade') NOT NULL DEFAULT 'retail' AFTER global_discount;
