
-- =========================================================
-- SHIORI CAFE
-- Migration 003: Order Discount Tables
-- Database: grp10_shioricafedb
-- Schema: shioricafe_schema
--
-- Requires:
-- 001_core_schema.sql
-- 002_order_tables.sql
-- =========================================================

BEGIN;

-- 1. ORDER DISCOUNT
-- Stores order-level promotional or staff discounts.


CREATE TABLE shioricafe_schema.order_discount (
    order_discount_id bigint GENERATED ALWAYS AS IDENTITY,

    order_id bigint NOT NULL,

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0.00),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT pk_order_discount
        PRIMARY KEY (order_discount_id),

    CONSTRAINT fk_order_discount_order
        FOREIGN KEY (order_id)
        REFERENCES shioricafe_schema."order"(order_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    -- Supports validation that an allocation belongs
    -- to the same order as its discount.
    CONSTRAINT uq_order_discount_order_pair
        UNIQUE (order_id, order_discount_id)
);


-- 2. ORDER DISCOUNT ALLOCATION
-- Records the portion of a discount assigned to each item.


CREATE TABLE shioricafe_schema.order_discount_allocation (
    order_discount_allocation_id bigint
        GENERATED ALWAYS AS IDENTITY,

    order_id bigint NOT NULL,
    order_discount_id bigint NOT NULL,
    order_item_id bigint NOT NULL,

    allocated_amount numeric(10,2) NOT NULL
        CHECK (allocated_amount > 0.00),

    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT pk_order_discount_allocation
        PRIMARY KEY (order_discount_allocation_id),

    CONSTRAINT fk_discount_allocation_discount
        FOREIGN KEY (order_id, order_discount_id)
        REFERENCES shioricafe_schema.order_discount
            (order_id, order_discount_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    CONSTRAINT fk_discount_allocation_order_item
        FOREIGN KEY (order_item_id)
        REFERENCES shioricafe_schema.order_item(order_item_id)
        ON DELETE RESTRICT
        ON UPDATE NO ACTION,

    CONSTRAINT uq_discount_allocation_item
        UNIQUE (order_discount_id, order_item_id)
);


-- 3. INDEXES


CREATE INDEX ix_order_discount_order_id
    ON shioricafe_schema.order_discount(order_id);

CREATE INDEX ix_discount_allocation_order_id
    ON shioricafe_schema.order_discount_allocation(order_id);

CREATE INDEX ix_discount_allocation_discount_id
    ON shioricafe_schema.order_discount_allocation(order_discount_id);

CREATE INDEX ix_discount_allocation_item_id
    ON shioricafe_schema.order_discount_allocation(order_item_id);

COMMIT;


-- END OF MIGRATION 003
