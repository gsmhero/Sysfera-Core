<?php
return [
    'menu' => 'Basebase', 'nav_overview' => 'Overview', 'nav_catalog' => 'Catalog', 'nav_orders' => 'Orders', 'nav_settings' => 'Connection',

    'all' => 'All', 'find' => 'Find', 'search' => 'Name or SKU', 'save' => 'Save', 'saved' => 'Saved.', 'phone' => 'Phone', 'status' => 'Status', 'product' => 'Product', 'price' => 'Price', 'item' => 'item',

    'setup_title' => 'Connect your warehouse to Basebase in three steps', 'step_connect' => 'Enter the Agent ID and API secret from your Basebase cabinet', 'step_seller' => 'Choose or create the seller your products will be sold under',
    'step_publish' => 'Tick the products to sell in the catalog', 'open_settings' => 'Open connection', 'ask_admin' => 'The connection is set up by an administrator.',
    'kpi_published' => 'Products on Basebase', 'kpi_errors' => 'With errors', 'kpi_errors_hint' => 'could not be sent', 'kpi_open' => 'Orders in progress', 'kpi_open_hint' => 'waiting to ship', 'kpi_orders' => 'Orders total',
    'manage' => 'Manage', 'pull_now' => 'Check orders', 'pulled' => 'New orders: {n}.', 'last_poll' => 'Last check', 'attention' => 'Needs attention',
    'flow_hint' => 'New orders arrive on their own; stock is updated whenever a document is posted.',

    'catalog_hint' => 'Tick the products you sell on Basebase and choose a category. The price comes from the product card, the quantity is the available stock of the warehouse chosen in the connection.',
    'not_connected_note' => 'The connection is not finished — products are saved but not sent.', 'only_published' => 'On Basebase only', 'no_products' => 'No products', 'no_products_hint' => 'Add products in the Warehouse section first.',
    'sell' => 'Sell', 'category' => 'Category', 'category_ph' => 'e.g. Kitchen', 'on_sale' => 'Available', 'state' => 'State', 'photos' => 'Photos (https links)', 'no_price' => 'no price',
    'state_live' => 'Live', 'state_wait' => 'Waiting to send', 'state_error' => 'Error', 'save_publish' => 'Save and send', 'save_hint' => 'Only changed rows are sent.',
    'sync_all' => 'Resend everything', 'synced' => 'Sent: {ok}, with errors: {bad}.',

    'orders_hint' => 'Every Basebase order item becomes a customer order in the warehouse and reserves the goods. When you post the shipment, Basebase is told automatically.',
    'no_orders' => 'No orders yet', 'no_orders_hint' => 'They appear here as soon as a buyer orders on Basebase.', 'basebase_order' => 'Basebase order', 'buyer' => 'Buyer', 'amount' => 'Amount',
    'you_get' => 'you get', 'wms_order' => 'Warehouse order', 'retry' => 'Retry', 'retry_ok' => 'Done: the order was created and posted.', 'retry_failed' => 'Did not work — the reason is shown in the row.',
    'mark_shipped' => 'Mark shipped', 'confirm_shipped' => 'Tell Basebase this order has been shipped?',
    'st_pending' => 'New', 'st_prepare' => 'Preparing', 'st_shipped' => 'Shipped', 'st_on_delivery' => 'On delivery', 'st_delivered' => 'Delivered', 'st_archived' => 'Archived', 'st_returned' => 'Returned',
    'status_draft' => 'Draft', 'status_posted' => 'Posted', 'status_cancelled' => 'Cancelled',

    's_connection' => 'Access to Basebase', 's_connection_hint' => 'Take these from Basebase: Cabinet → Partner site → API access (the agent must be approved by a Basebase administrator).',
    's_warehouse_hint' => 'Tick one or several warehouses: Basebase sees the total available quantity, and an order is taken from the first warehouse that has enough.', 'pf_h' => 'Basebase', 'pf_cat' => 'Category on Basebase', 'pf_cat_hint' => 'Choose from the Basebase list or start typing. Required to sell the product there.', 'pf_cat_hint0' => 'Type the category name exactly as on Basebase. The list appears after “Test connection”.', 'pf_pub' => 'Marketplace', 'pf_pub_l' => 'Sell on Basebase', 'pf_pub_hint' => 'The product is sent to Basebase when you save. Untick to hide it there.', 'pf_push_fail' => 'Saved, but not sent to Basebase: {e}', 'api_address' => 'API address', 'secret_saved' => 'saved — type a new one to replace', 's_seller' => 'Seller and warehouse', 's_warehouse' => 'Warehouse for sales',
    's_test' => 'Test the connection', 's_test_hint' => 'Sends a signed request and loads the Basebase categories.', 's_test_btn' => 'Test connection',
    's_key_hint' => 'Copy this key into the “AGENT_KEY” field of the Basebase form (Cabinet → Partner site). It only identifies this site while Basebase links the agent; it is not used to sign requests.',
    'ping_ok' => 'Connected. Agency fee: {fee}%, platform commission: {pc}%.', 'ping_fail' => 'Error: {e}',
    's_create' => 'Create a seller on Basebase', 's_create_hint' => 'The seller gets an email with a link to set a password. If the seller already exists, enter their Seller ID above.',
    's_name' => 'Name', 's_shop' => 'Shop name', 's_create_btn' => 'Create seller', 'seller_created' => 'Seller created, Seller ID: {id}.',
    's_howto' => 'How to connect', 'how1' => 'In Basebase: Cabinet → Partner site — enter the address of this site.', 'how2' => 'Wait for a Basebase administrator to approve it.',
    'how3' => 'Copy the Agent ID and API secret here and press “Test connection”.', 'how4' => 'Create a seller (or enter an existing one) and tick products in the catalog.',
    's_webhook' => 'Notification address', 's_webhook_hint' => 'Basebase calls this address on your site by itself when orders arrive — nothing to enter.',
    's_cron' => 'Scheduled fallback check', 's_cron_hint' => 'If a notification is lost, orders are still picked up. Add a cron job on your hosting (every 5–10 minutes):',
    'new_token' => 'Issue a new key', 'confirm_token' => 'The old cron link will stop working. Continue?',

    'err_nostock' => 'Not enough “{name}”: {have} available, {need} needed.', 'err_unknown_product' => 'The product is not published from the warehouse (no such SKU).', 'err_no_warehouse' => 'No warehouse chosen for sales.',
    'err_has_shipment' => 'The order is already partly shipped — cancel the shipment manually.', 'err_grouped' => 'A grouped product is not sold as one item: publish its products one by one.', 'err_parts' => 'Add the variations or parts in the WMS product card.', 'err_variant' => 'The variation the buyer chose is not found among the variations of the product.', 'err_category' => 'Choose a category.', 'err_price' => 'Set a selling price.', 'err_name' => 'No name.',
    'mail_order_subject' => 'New Basebase order → {n}', 'mail_order_text' => "Basebase order #{order} was accepted; warehouse document {n} was created and posted.\n\nOpen orders: {url}",
    'mail_fail_subject' => 'Basebase order #{order} needs attention', 'mail_fail_text' => "Basebase order #{order} could not be processed in the warehouse.\nReason: {why}\n\nFix it and press “Retry”: {url}",
    'not_connected' => 'Not connected.', 'err_state' => 'The document has another status.', 'err_warehouse' => 'No warehouse set.', 'err_no_lines' => 'The order has no lines.', 'err_qty' => 'Wrong quantity.', 'err_customer' => 'No customer set.',
];
