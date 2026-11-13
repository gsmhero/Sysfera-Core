-- Promo price: what the seller receives for a unit sold through Basebase (the margin up to the selling price goes to Basebase and the agent).
ALTER TABLE {p}wms_products ADD COLUMN promo_price DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER sell_price;
