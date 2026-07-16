-- Replace the original spreadsheet-derived examples with fictional demo records.
-- Safe to run more than once: demo products are identified by their reserved DEMO- SKUs.
DELETE FROM products WHERE sku IN ('LS-STO-26594','LS-STO-26593','LS-STO-26592','LS-STO-26627');
DELETE FROM product_groups
WHERE name IN ('Stamps','Cards','Accessories')
  AND NOT EXISTS (SELECT 1 FROM products WHERE products.group_id = product_groups.id);

INSERT INTO product_groups(name,preferred_margin) VALUES
('Homeware',0.65),('Stationery',0.72),('Gift Sets',0.60)
ON DUPLICATE KEY UPDATE preferred_margin = VALUES(preferred_margin);

CREATE TEMPORARY TABLE demo_product_seed (
 group_name VARCHAR(150), sku VARCHAR(100), product_name VARCHAR(255), product_code VARCHAR(100),
 unit_cost DECIMAL(14,5), labour_cost DECIMAL(14,5), target_margin DECIMAL(8,6),
 preferred_price_override DECIMAL(14,4), msrp DECIMAL(14,4), competitor_price DECIMAL(14,4),
 retail_price DECIMAL(14,4), trade_discount DECIMAL(8,6), trade_price DECIMAL(14,4),
 minimum_margin DECIMAL(8,6), is_wholesale TINYINT(1)
);

INSERT INTO demo_product_seed VALUES
('Homeware','DEMO-HW-1042','Speckled Ceramic Planter','DHW-4821',4.35,0.80,0.65,NULL,16.00,14.50,13.25,0.35,8.60,0.22,1),
('Homeware','DEMO-HW-2187','Woven Cotton Coaster Set','DHW-7354',2.10,0.45,0.65,NULL,9.50,8.99,8.00,0.35,5.20,0.22,1),
('Homeware','DEMO-HW-6631','Amber Glass Bud Vase','DHW-1968',3.75,0.60,0.65,12.95,15.00,13.49,12.50,0.35,8.10,0.22,1),
('Stationery','DEMO-ST-3095','Recycled Paper Notebook','DST-6247',1.85,0.25,0.72,NULL,8.50,7.99,7.50,0.40,4.50,0.20,1),
('Stationery','DEMO-ST-4578','Fine Line Pen Trio','DST-3086',1.20,0.15,0.72,NULL,6.50,5.95,5.25,0.40,3.15,0.20,1),
('Stationery','DEMO-ST-8924','Undated Weekly Desk Pad','DST-9513',2.65,0.35,0.72,NULL,12.00,10.99,10.50,0.40,6.30,0.20,1),
('Gift Sets','DEMO-GS-1256','Tea Break Gift Box','DGS-4072',7.80,1.50,0.60,NULL,26.00,24.95,23.50,0.30,16.45,0.25,1),
('Gift Sets','DEMO-GS-5743','Mini Self-Care Collection','DGS-8639',9.25,1.75,0.60,29.95,34.00,31.50,29.00,0.30,20.30,0.25,1),
('Gift Sets','DEMO-GS-7460','New Home Welcome Set','DGS-2154',11.40,2.10,0.60,NULL,42.00,NULL,37.50,0.30,26.25,0.25,0);

INSERT INTO products(group_id,sku,product_name,product_code,unit_cost,labour_cost,target_margin,preferred_price_override,msrp,competitor_price,retail_price,trade_discount,trade_price,minimum_margin,is_wholesale)
SELECT g.id,s.sku,s.product_name,s.product_code,s.unit_cost,s.labour_cost,s.target_margin,s.preferred_price_override,s.msrp,s.competitor_price,s.retail_price,s.trade_discount,s.trade_price,s.minimum_margin,s.is_wholesale
FROM demo_product_seed s
JOIN product_groups g ON g.name = s.group_name
LEFT JOIN products p ON p.sku = s.sku
WHERE p.id IS NULL;

DROP TEMPORARY TABLE demo_product_seed;
