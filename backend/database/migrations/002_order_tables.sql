
-- =========================================================
-- SHIORI CAFE
-- Migration 002: Order and Order Item Tables
-- Database: grp10_shioricafedb
-- Schema: shioricafe_schema
--
-- Requires Migration 001:
-- customer, staff, "table", category, menu_item, reservation
-- =========================================================

BEGIN;


-- 1. ORDER TABLE


CREATE TABLE shioricafe_schema."order" (
    order_id bigint GENERATED ALWAYS AS IDENTITY,

    reservation_id bigint NOT NULL,
    table_id bigint NULL,

    order_type text NOT NULL
        CHECK (order_type IN ('Dine-In', 'Takeaway')),

    preparation_status text NOT NULL DEFAULT 'Pending'
        CHECK (
            preparation_status IN (
                'Pending',
                'Preparing',
                'Ready',
                'Served',
                'Completed'
            )
        ),

    gross_order_amount numeric(12,2) NOT NULL DEFAULT 0.00
        CHECK (gross_order_amount >= 0.00),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    voided_at timestamptz NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT pk_order
        PRIMARY KEY (order_id),

    CONSTRAINT fk_order_reservation
        FOREIGN KEY (reservation_id)
        REFERENCES shioricafe_schema.reservation(reservation_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    CONSTRAINT fk_order_table
        FOREIGN KEY (table_id)
        REFERENCES shioricafe_schema."table"(table_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    CONSTRAINT ck_order_service_table
        CHECK (
            (order_type = 'Dine-In' AND table_id IS NOT NULL)
            OR
            (order_type = 'Takeaway' AND table_id IS NULL)
        )
);

-- 2. ORDER ITEM TABLE

CREATE TABLE shioricafe_schema.order_item (
    order_item_id bigint GENERATED ALWAYS AS IDENTITY,

    order_id bigint NOT NULL,
    menu_item_id bigint NOT NULL,

    item_name_at_purchase text NOT NULL
        CHECK (btrim(item_name_at_purchase) <> ''),

    quantity integer NOT NULL
        CHECK (quantity > 0),

    unit_price_at_purchase numeric(10,2) NOT NULL
        CHECK (unit_price_at_purchase >= 0.00),

    is_drink_category_at_purchase boolean NOT NULL,

    line_gross_amount numeric(10,2)
    GENERATED ALWAYS AS (
        quantity * unit_price_at_purchase
    ) STORED,

    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT pk_order_item
        PRIMARY KEY (order_item_id),

    CONSTRAINT fk_order_item_order
        FOREIGN KEY (order_id)
        REFERENCES shioricafe_schema."order"(order_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    CONSTRAINT fk_order_item_menu_item
        FOREIGN KEY (menu_item_id)
        REFERENCES shioricafe_schema.menu_item(menu_item_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION
);


-- 3. INDEXES


CREATE INDEX ix_order_reservation_id
    ON shioricafe_schema."order"(reservation_id);

CREATE INDEX ix_order_table_id
    ON shioricafe_schema."order"(table_id);

CREATE INDEX ix_order_preparation_status
    ON shioricafe_schema."order"(preparation_status);

CREATE INDEX ix_order_created_at
    ON shioricafe_schema."order"(created_at);

CREATE INDEX ix_order_item_order_id
    ON shioricafe_schema.order_item(order_id);

CREATE INDEX ix_order_item_menu_item_id
    ON shioricafe_schema.order_item(menu_item_id);

COMMIT;

-- END OF MIGRATION 002
