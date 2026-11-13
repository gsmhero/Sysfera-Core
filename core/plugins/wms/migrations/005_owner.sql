-- Every product and document belongs to one user (multi-vendor behaviour). Warehouses stay shared infrastructure of the administrator.
ALTER TABLE {p}wms_products ADD COLUMN owner_id INT UNSIGNED NULL;
ALTER TABLE {p}wms_products ADD KEY owner_id (owner_id);
ALTER TABLE {p}wms_docs ADD COLUMN owner_id INT UNSIGNED NULL;
ALTER TABLE {p}wms_docs ADD KEY owner_id (owner_id);
UPDATE {p}wms_docs SET owner_id = created_by WHERE created_by IS NOT NULL;
UPDATE {p}wms_products SET owner_id = (SELECT MIN(id) FROM {p}users WHERE role = 'admin') WHERE owner_id IS NULL;
UPDATE {p}wms_docs SET owner_id = (SELECT MIN(id) FROM {p}users WHERE role = 'admin') WHERE owner_id IS NULL;
