-- ============================================================
-- SHIORI CAFE
-- Migration 001: Core Database Schema
-- Database: grp10_shioricafedb
-- Schema: shioricafe_schema
-- ============================================================

BEGIN;

CREATE SCHEMA IF NOT EXISTS shioricafe_schema;

CREATE EXTENSION IF NOT EXISTS btree_gist;

-- 1. CUSTOMER

CREATE TABLE shioricafe_schema.customer (
    customer_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name TEXT NOT NULL CHECK (BTRIM(full_name) <> ''),
    email TEXT NOT NULL CHECK (
        LENGTH(email) <= 254
        AND POSITION('@' IN email) > 1
    ),
    password_hash TEXT NOT NULL,
    phone TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX uq_customer_email_lower
ON shioricafe_schema.customer (LOWER(email));


-- 2. STAFF

CREATE TABLE shioricafe_schema.staff (
    staff_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name TEXT NOT NULL CHECK (BTRIM(full_name) <> ''),
    role TEXT NOT NULL CHECK (
        role IN ('Cashier', 'Manager')
    ),
    email TEXT NOT NULL,
    password_hash TEXT NOT NULL,

    created_by_staff_id BIGINT
        REFERENCES shioricafe_schema.staff(staff_id)
        ON DELETE RESTRICT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT ck_staff_self_creation
        CHECK (created_by_staff_id IS DISTINCT FROM staff_id)
);

CREATE UNIQUE INDEX uq_staff_email_lower
ON shioricafe_schema.staff (LOWER(email));


-- 3. TABLE

CREATE TABLE shioricafe_schema."table" (
    table_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    table_number TEXT NOT NULL
        CHECK (table_number ~ '^TBL-[0-9]{2}$'),

    table_type TEXT NOT NULL CHECK (
        table_type IN (
            'Solo Study Nook',
            'Duo Nook',
            'Group Study Desk'
        )
    ),

    capacity SMALLINT NOT NULL,
    amenities TEXT[] NOT NULL DEFAULT '{}',

    operational_status TEXT NOT NULL DEFAULT 'Available'
        CHECK (
            operational_status IN ('Available', 'Maintenance')
        ),

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_table_number UNIQUE (table_number),

    CONSTRAINT ck_table_capacity CHECK (
        (table_type = 'Solo Study Nook' AND capacity = 1)
        OR
        (table_type = 'Duo Nook' AND capacity = 2)
        OR
        (table_type = 'Group Study Desk'
            AND capacity BETWEEN 4 AND 6)
    )
);


-- 4. CATEGORY

CREATE TABLE shioricafe_schema.category (
    category_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    category_name TEXT NOT NULL UNIQUE
        CHECK (BTRIM(category_name) <> ''),

    is_drink_category BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- 5. MENU ITEM

CREATE TABLE shioricafe_schema.menu_item (
    menu_item_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    category_id BIGINT NOT NULL
        REFERENCES shioricafe_schema.category(category_id)
        ON DELETE RESTRICT,

    item_name TEXT NOT NULL
        CHECK (BTRIM(item_name) <> ''),

    description TEXT,

    current_price NUMERIC(10,2) NOT NULL
        CHECK (current_price >= 0.00),

    stock_status TEXT NOT NULL DEFAULT 'In Stock'
        CHECK (stock_status IN ('In Stock', 'Out of Stock')),

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX uq_menu_item_name_lower
ON shioricafe_schema.menu_item (LOWER(item_name));


-- 6. RESERVATION

CREATE TABLE shioricafe_schema.reservation (
    reservation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    customer_id BIGINT
        REFERENCES shioricafe_schema.customer(customer_id)
        ON DELETE RESTRICT,

    table_id BIGINT
        REFERENCES shioricafe_schema."table"(table_id)
        ON DELETE RESTRICT,

    party_size SMALLINT
        CHECK (party_size IS NULL OR party_size > 0),

    reservation_start_at TIMESTAMPTZ NOT NULL
        CHECK (
            MOD(EXTRACT(EPOCH FROM reservation_start_at), 60) = 0
        ),

    reservation_end_at TIMESTAMPTZ
        CHECK (
            reservation_end_at IS NULL
            OR MOD(EXTRACT(EPOCH FROM reservation_end_at), 60) = 0
        ),

    status TEXT NOT NULL CHECK (
        status IN (
            'Confirmed',
            'Adjustment_Pending',
            'Occupied',
            'Cancelled',
            'Completed'
        )
    ),

    required_minimum_amount NUMERIC(12,2)
        CHECK (
            required_minimum_amount IS NULL
            OR required_minimum_amount >= 0.00
        ),

    is_walkin BOOLEAN NOT NULL DEFAULT FALSE,

    extension_count INTEGER NOT NULL DEFAULT 0
        CHECK (extension_count >= 0),

    cancellation_reason TEXT,
    cancelled_at TIMESTAMPTZ,

    checked_in_at TIMESTAMPTZ,

    checked_in_by_staff_id BIGINT
        REFERENCES shioricafe_schema.staff(staff_id)
        ON DELETE RESTRICT,

    completed_at TIMESTAMPTZ,

    completed_by_staff_id BIGINT
        REFERENCES shioricafe_schema.staff(staff_id)
        ON DELETE RESTRICT,

    attendance_confirmed_at TIMESTAMPTZ,
    reminder_sent_at TIMESTAMPTZ,
    checkin_followup_sent_at TIMESTAMPTZ,

    seat_notice_required BOOLEAN NOT NULL DEFAULT FALSE,
    seat_notice_acknowledged_at TIMESTAMPTZ,

    seat_notice_acknowledged_by_staff_id BIGINT
        REFERENCES shioricafe_schema.staff(staff_id)
        ON DELETE RESTRICT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT ck_reservation_walkin_customer CHECK (
        (is_walkin AND customer_id IS NULL)
        OR
        (NOT is_walkin AND customer_id IS NOT NULL)
    ),

    CONSTRAINT ck_reservation_online_complete CHECK (
        is_walkin
        OR (
            table_id IS NOT NULL
            AND party_size IS NOT NULL
            AND reservation_end_at IS NOT NULL
            AND required_minimum_amount IS NOT NULL
        )
    ),

    CONSTRAINT ck_reservation_walkin_no_minimum CHECK (
        NOT is_walkin OR required_minimum_amount IS NULL
    ),

    CONSTRAINT ck_reservation_walkin_status CHECK (
        NOT is_walkin
        OR status IN ('Occupied', 'Completed')
    ),

    CONSTRAINT ck_reservation_table_end CHECK (
        (table_id IS NULL) = (reservation_end_at IS NULL)
    ),

    CONSTRAINT ck_reservation_end_after_start CHECK (
        reservation_end_at IS NULL
        OR reservation_end_at > reservation_start_at
    ),

    CONSTRAINT ck_reservation_checkin CHECK (
        (checked_in_at IS NULL)
        = (checked_in_by_staff_id IS NULL)
    ),

    CONSTRAINT ck_reservation_completion CHECK (
        (status = 'Completed')
        = (completed_at IS NOT NULL)
    ),

    CONSTRAINT uq_reservation_id_customer
        UNIQUE (reservation_id, customer_id)
);


-- RESERVATION INDEXES

CREATE UNIQUE INDEX uq_reservation_one_active_per_customer
ON shioricafe_schema.reservation (customer_id)
WHERE customer_id IS NOT NULL
  AND is_walkin = FALSE
  AND status IN (
      'Confirmed',
      'Adjustment_Pending',
      'Occupied'
  );



-- PREVENT DOUBLE BOOKING

ALTER TABLE shioricafe_schema.reservation
ADD CONSTRAINT ex_reservation_no_overlap
EXCLUDE USING GIST (
    table_id WITH =,
    TSTZRANGE(
        reservation_start_at,
        reservation_end_at,
        '[)'
    ) WITH &&
)
WHERE (
    table_id IS NOT NULL
    AND status IN (
        'Confirmed',
        'Adjustment_Pending',
        'Occupied'
    )
);

COMMIT;

-- END OF MIGRATION 001