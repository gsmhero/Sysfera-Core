-- Product structure aligned with Basebase: short description, photos (https URLs, one per line), options (JSON [{name,price,stock}]), specifications (JSON [{section,name,value}]).
ALTER TABLE {p}wms_products ADD COLUMN short_desc VARCHAR(500) NULL AFTER description;
ALTER TABLE {p}wms_products ADD COLUMN images TEXT NULL AFTER short_desc;
ALTER TABLE {p}wms_products ADD COLUMN variants TEXT NULL AFTER images;
ALTER TABLE {p}wms_products ADD COLUMN specs TEXT NULL AFTER variants;
