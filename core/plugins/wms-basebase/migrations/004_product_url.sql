-- Connector 0.6: the address of the product on Basebase (the Missions component offers it in a "Buy product" task).
ALTER TABLE {p}wmsbb_products ADD COLUMN bb_url VARCHAR(255) NULL;
