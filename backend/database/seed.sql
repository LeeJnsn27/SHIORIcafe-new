-- 1. Seed Categories (Removed 'description' column)
INSERT INTO shioricafe_schema.category (category_name) 
VALUES 
('Hot Beverages'),
('Cold Beverages'),
('Pastries')
ON CONFLICT DO NOTHING;

-- 2. Seed Menu Items
INSERT INTO shioricafe_schema.menu_item
    (category_id, item_name, description, current_price, stock_status, is_active)
VALUES
    (1, 'Signature Shiori Latte', 'Rich espresso with steamed milk and a hint of vanilla.', 140.00, 'In Stock', true),
    (2, 'Matcha Cold Brew', 'Premium Kyoto matcha over ice.', 160.00, 'In Stock', true),
    (3, 'Sakura Cheesecake', 'Light cheesecake with subtle cherry blossom flavors.', 180.00, 'In Stock', true)
ON CONFLICT DO NOTHING;

-- 3. Seed Staff (Changed 'username' to 'email', and roles to 'Manager' / 'Cashier')
INSERT INTO shioricafe_schema.staff (full_name, email, password_hash, role)
VALUES 
('Admin Manager', 'manager@shioricafe.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager'),
('Jane Barista', 'cashier@shioricafe.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier')
ON CONFLICT DO NOTHING;

-- 4. Seed Customers (Changed 'username' to 'email')
INSERT INTO shioricafe_schema.customer (full_name, email, password_hash)
VALUES 
('Shiraine Bituin', 'shirainebituin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Test Customer', 'customer@shioricafe.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON CONFLICT DO NOTHING;

-- 5. Seed Tables (Required to prevent foreign key errors during reservations)
INSERT INTO shioricafe_schema."table" (table_id, table_number, table_type, capacity)
OVERRIDING SYSTEM VALUE
VALUES 
(1, 'TBL-01', 'Duo Nook', 2),
(2, 'TBL-02', 'Duo Nook', 2),
(3, 'TBL-03', 'Duo Nook', 2),
(4, 'TBL-04', 'Solo Study Nook', 1),
(5, 'TBL-05', 'Solo Study Nook', 1),
(6, 'TBL-06', 'Solo Study Nook', 1),
(7, 'TBL-07', 'Group Study Desk', 4),
(8, 'TBL-08', 'Group Study Desk', 4),
(9, 'TBL-09', 'Group Study Desk', 4)
ON CONFLICT (table_id) DO UPDATE 
SET table_number = EXCLUDED.table_number, 
    table_type = EXCLUDED.table_type,
    capacity = EXCLUDED.capacity;