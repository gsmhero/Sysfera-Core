-- Warehouses belong to their user too: everyone has their own places for their own goods.
ALTER TABLE {p}wms_warehouses ADD COLUMN owner_id INT UNSIGNED NULL;
ALTER TABLE {p}wms_warehouses ADD KEY owner_id (owner_id);
UPDATE {p}wms_warehouses SET owner_id = (SELECT MIN(id) FROM {p}users WHERE role = 'admin') WHERE owner_id IS NULL;
