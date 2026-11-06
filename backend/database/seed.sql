-- =========================================================
-- SHIORI CAFE - DEMO / SAMPLE SEED DATA
-- Target database: grp10_shioricafedb
-- PostgreSQL schema: shioricafe_schema
--
-- Run AFTER backend/database/schema.sql.
-- =========================================================

BEGIN;

-- ---------------------------------------------------------
-- 1. SAMPLE SEATING INVENTORY (TBL-01 to TBL-09 for POS & Reservations)
-- ---------------------------------------------------------

INSERT INTO shioricafe_schema."table" (
    table_id,
    table_number,
    table_type,
    capacity,
    amenities,
    operational_status
)
OVERRIDING SYSTEM VALUE
VALUES
    (1, 'TBL-01', 'Solo Study Nook', 1, ARRAY['Power outlet', 'Desk lamp', 'Quiet zone'], 'Available'),
    (2, 'TBL-02', 'Duo Nook', 2, ARRAY['Dual outlets', 'Window view'], 'Available'),
    (3, 'TBL-03', 'Group Study Desk', 4, ARRAY['Power strip', 'Whiteboard access'], 'Available'),
    (4, 'TBL-04', 'Solo Study Nook', 1, ARRAY['Power outlet', 'Quiet zone'], 'Available'),
    (5, 'TBL-05', 'Duo Nook', 2, ARRAY['Dual outlets'], 'Available'),
    (6, 'TBL-06', 'Solo Study Nook', 1, ARRAY['Desk lamp'], 'Available'),
    (7, 'TBL-07', 'Group Study Desk', 4, ARRAY['Power strip'], 'Available'),
    (8, 'TBL-08', 'Group Study Desk', 4, ARRAY['Whiteboard access'], 'Available'),
    (9, 'TBL-09', 'Group Study Desk', 4, ARRAY['Power strip', 'Whiteboard access'], 'Available')
ON CONFLICT (table_id) DO UPDATE
SET table_number = EXCLUDED.table_number,
    table_type = EXCLUDED.table_type,
    capacity = EXCLUDED.capacity,
    amenities = EXCLUDED.amenities,
    operational_status = EXCLUDED.operational_status;

-- ---------------------------------------------------------
-- 2. SAMPLE MENU CATEGORIES
-- ---------------------------------------------------------

INSERT INTO shioricafe_schema.category (
    category_name,
    is_drink_category
)
VALUES
    ('Drinks', TRUE),
    ('Food', FALSE)
ON CONFLICT (category_name) DO NOTHING;

-- ---------------------------------------------------------
-- 3. SAMPLE MENU ITEMS
-- ---------------------------------------------------------

INSERT INTO shioricafe_schema.menu_item (
    category_id,
    item_name,
    description,
    current_price,
    stock_status,
    is_active
)
SELECT
    c.category_id,
    sample.item_name,
    sample.description,
    sample.current_price,
    sample.stock_status,
    sample.is_active
FROM (
    VALUES
        ('Drinks', 'Hojicha Latte', 'Roasted green tea latte with steamed milk', 150.00, 'In Stock', TRUE),
        ('Drinks', 'Spanish Latte', 'Espresso with textured milk and sweet condensed milk', 140.00, 'In Stock', TRUE),
        ('Drinks', 'Matcha', 'Ceremonial-grade Japanese green tea', 160.00, 'In Stock', TRUE),
        ('Drinks', 'Genmaicha', 'Green tea blended with roasted popped brown rice', 130.00, 'In Stock', TRUE),
        ('Drinks', 'Yuzu Honey Tea', 'Warm citrus citron tea with natural honey', 135.00, 'In Stock', TRUE),
        ('Food', 'Castella Cake', 'Traditional Japanese honey sponge cake', 120.00, 'In Stock', TRUE),
        ('Food', 'Dorayaki', 'Sweet red bean paste between fluffy castella pancakes', 110.00, 'In Stock', TRUE),
        ('Food', 'Onigiri', 'Handcrafted Japanese rice ball with savory filling', 95.00, 'In Stock', TRUE)
) AS sample(category_name, item_name, description, current_price, stock_status, is_active)
JOIN shioricafe_schema.category c
    ON c.category_name = sample.category_name
ON CONFLICT (LOWER(item_name)) DO NOTHING;

COMMIT;