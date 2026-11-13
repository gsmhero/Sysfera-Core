-- The variation the buyer chose (variable products), so a retry can still find the right product.
ALTER TABLE {p}wmsbb_items ADD COLUMN variant VARCHAR(120) NULL AFTER title;
