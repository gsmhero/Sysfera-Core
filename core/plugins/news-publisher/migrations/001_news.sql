-- Core News posts written in the admin panel; the public feed is GET /api/news

CREATE TABLE IF NOT EXISTS {p}news_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL,
  type ENUM('news','update','warning') NOT NULL DEFAULT 'news',
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  post_date DATE NOT NULL,
  expires DATE NULL,
  title_en VARCHAR(160) NOT NULL DEFAULT '',
  title_ru VARCHAR(160) NOT NULL DEFAULT '',
  body_en TEXT NULL,
  body_ru TEXT NULL,
  url VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug),
  KEY ix_pub (published, post_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO {p}news_posts (slug, type, post_date, title_en, title_ru, body_en, body_ru) VALUES
('2026-11-12-welcome', 'news', '2026-11-12', 'Welcome to Sysfera', 'Добро пожаловать в Sysfera',
 'Sysfera Core is up and running. Install components from the Components page. News, update notes and important warnings from the Sysfera team will appear here.',
 'Sysfera Core установлен и работает. Компоненты ставятся на странице «Компоненты». Здесь будут новости, заметки об обновлениях и важные предупреждения от команды Sysfera.'),
('2026-11-12-wms', 'update', '2026-11-12', 'New component: Sysfera WMS', 'Новый компонент: Sysfera WMS',
 'Warehouse management is available: products, warehouses with cells, stock, receipts, orders and reports. Install it in Components. A separate connector sells your stock on Basebase.',
 'Доступен складской учёт: товары, склады с ячейками, остатки, приход, заказы и отчёты. Установите его в разделе «Компоненты». Отдельный коннектор позволяет продавать товары со склада на Basebase.');
