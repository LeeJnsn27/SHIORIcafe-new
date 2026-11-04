-- =========================================================
-- SHIORI CAFE - DEMO / SAMPLE SEED DATA
-- Target database: grp10_shioricafedb
-- PostgreSQL schema: shioricafe_schema
--
-- Run AFTER backend/database/schema.sql.
--
-- IMPORTANT:
-- These are DEMONSTRATION records, not a verified physical
-- inventory or final approved production menu. Table numbers
-- are sample identifiers. Menu names, descriptions, and
-- prices are copied from the current services.php menu page.
-- Review/replace sample seating details and catalog before
-- production use.
--
-- This script is safe to run more than once:
-- existing table numbers, category names, and menu item names
-- are left unchanged (including manager-edited values).
--
-- No customer accounts, staff accounts, passwords, orders,
-- reservations, payments, or financial transactions are seeded.
-- Create accounts through the application's secure workflows.
-- =========================================================

BEGIN;

-- ---------------------------------------------------------
-- 1. SAMPLE SEATING INVENTORY
-- Table types, capacities, and amenities follow the project
-- handoff. TBL-01 to TBL-03 are DEMO table numbers only.
-- ---------------------------------------------------------

INSERT INTO shioricafe_schema."table" (
    table_number,
    table_type,
    capacity,
    amenities,
    operational_status
)
VALUES
    (
        'TBL-01',
        'Solo Study Nook',
        1,
        ARRAY['Power outlet', 'Desk lamp', 'Quiet zone'],
        'Available'
    ),
    (
        'TBL-02',
        'Duo Nook',
        2,
        ARRAY['Dual outlets', 'Window view'],
        'Available'
    ),
    (
        'TBL-03',
        'Group Study Desk',
        4,
        ARRAY['Power strip', 'Whiteboard access'],
        'Available'
    )
ON CONFLICT (table_number) DO NOTHING;

-- ---------------------------------------------------------
-- 2. SAMPLE MENU CATEGORIES
-- Category labels are demonstration labels for the existing
-- menu. Drink classification is used by minimum-spend rules.
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
-- Names, descriptions, and prices match services.php.
-- ON CONFLICT DO NOTHING preserves existing catalog edits.
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