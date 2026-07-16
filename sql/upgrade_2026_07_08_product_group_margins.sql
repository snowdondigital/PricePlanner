ALTER TABLE product_groups
    ADD COLUMN preferred_margin DECIMAL(8,6) NULL AFTER name;

UPDATE product_groups SET preferred_margin = 0.80 WHERE name = 'Stamps';
UPDATE product_groups SET preferred_margin = 0.58 WHERE name = 'Cards';
UPDATE product_groups SET preferred_margin = 0.70 WHERE name = 'Accessories';
