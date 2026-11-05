-- =========================================================================
-- SHIORI Café - Clean Seed Data Script
-- =========================================================================

SET search_path TO shioricafe_schema, public;

-- 1. Seed Staff / Admin
INSERT INTO shioricafe_schema.staff (username, password_hash, role) VALUES
    ('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFv5gL3n8jH9k3Yl2xV8zQ3x5m7wK9W6', 'manager'),
    ('staff_shiraine', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFv5gL3n8jH9k3Yl2xV8zQ3x5m7wK9W6', 'barista')
ON CONFLICT DO NOTHING;

-- 2. Seed Customers
INSERT INTO shioricafe_schema.customer (username, email, password_hash) VALUES
    ('student_baliga', 'baliga@example.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFv5gL3n8jH9k3Yl2xV8zQ3x5m7wK9W6'),
    ('worker_sofia', 'sofia@example.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFv5gL3n8jH9k3Yl2xV8zQ3x5m7wK9W6')
ON CONFLICT DO NOTHING;

-- 3. Seed Menu Categories
INSERT INTO shioricafe_schema.category (category_name, description) VALUES
    ('Coffee & Espresso', 'Handcrafted artisanal coffees brewed for deep focus.'),
    ('Teas & Matcha', 'Calming green teas and authentic Japanese matcha lattes.'),
    ('Pastries & Bakery', 'Freshly baked goods to accompany your study session.')
ON CONFLICT DO NOTHING;

-- 4. Seed Menu Items
INSERT INTO shioricafe_schema.menu_item (category_id, item_name, description, current_price, stock_status, is_active) VALUES
    (1, 'Signature Shiori Latte', 'Rich espresso with steamed milk and a hint of vanilla.', 140.00, 'AVAILABLE', true),
    (1, 'Cold Brew Focus', 'Smooth 18-hour slow-steeped cold brew coffee.', 130.00, 'AVAILABLE', true),
    (2, 'Ceremonial Uji Matcha', 'Authentic whisked Japanese green tea latte.', 160.00, 'AVAILABLE', true),
    (3, 'Matcha Croissant', 'Flaky butter croissant filled with sweet matcha cream.', 95.00, 'AVAILABLE', true)
ON CONFLICT DO NOTHING;