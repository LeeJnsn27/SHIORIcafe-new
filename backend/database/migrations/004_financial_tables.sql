
-- =========================================================
-- SHIORI CAFE
-- Migration 004: Financial Tables
-- Database: grp10_shioricafedb
-- Schema: shioricafe_schema
--
-- Requires:
-- 001_core_schema.sql
-- 002_order_tables.sql
-- 003_discount_and_reservation_rules.sql
--
-- PROPOSED SCHEMA:
-- Confirm provider-specific and financial requirements
-- before production use.
-- =========================================================

BEGIN;

-- 1. STORE CREDIT LEDGER
-- Records issued, redeemed, and adjusted store credits.

CREATE TABLE shioricafe_schema.store_credit_ledger (
    store_credit_ledger_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    customer_id bigint NULL,
    reservation_id bigint NULL,
    order_id bigint NULL,

    transaction_type text NOT NULL
        CHECK (
            transaction_type IN (
                'ISSUED',
                'REDEEMED',
                'ADJUSTED',
                'REFUNDED',
                'EXPIRED'
            )
        ),

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    reference_number text NULL,
    description text NULL,

    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT fk_credit_customer
        FOREIGN KEY (customer_id)
        REFERENCES shioricafe_schema.customer(customer_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_credit_reservation
        FOREIGN KEY (reservation_id)
        REFERENCES shioricafe_schema.reservation(reservation_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_credit_order
        FOREIGN KEY (order_id)
        REFERENCES shioricafe_schema."order"(order_id)
        ON DELETE RESTRICT
);

-- 2. PAYMENT ATTEMPT
-- Records an attempt to pay for an order or extension.

CREATE TABLE shioricafe_schema.payment_attempt (
    payment_attempt_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    order_id bigint NULL,
    reservation_extension_id bigint NULL,

    provider text NOT NULL,
    provider_reference text NULL,

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    status text NOT NULL DEFAULT 'PENDING'
        CHECK (
            status IN (
                'PENDING',
                'SUCCEEDED',
                'FAILED',
                'ABANDONED',
                'CANCELLED'
            )
        ),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT fk_attempt_order
        FOREIGN KEY (order_id)
        REFERENCES shioricafe_schema."order"(order_id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_attempt_payment_source
        CHECK (
            (order_id IS NOT NULL AND reservation_extension_id IS NULL)
            OR
            (order_id IS NULL AND reservation_extension_id IS NOT NULL)
        )
);

-- 3. PAYMENT TRANSACTION
-- Records successful captured payment tenders.

CREATE TABLE shioricafe_schema.payment_transaction (
    payment_transaction_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    payment_attempt_id bigint NULL,
    order_id bigint NULL,
    reservation_extension_id bigint NULL,

    transaction_reference text NULL,

    payment_method text NOT NULL,

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    status text NOT NULL DEFAULT 'CAPTURED'
        CHECK (
            status IN (
                'CAPTURED',
                'PENDING',
                'FAILED'
            )
        ),

    captured_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT fk_transaction_attempt
        FOREIGN KEY (payment_attempt_id)
        REFERENCES shioricafe_schema.payment_attempt(payment_attempt_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_transaction_order
        FOREIGN KEY (order_id)
        REFERENCES shioricafe_schema."order"(order_id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_transaction_payment_source
        CHECK (
            (order_id IS NOT NULL AND reservation_extension_id IS NULL)
            OR
            (order_id IS NULL AND reservation_extension_id IS NOT NULL)
        )
);

-- 4. PAYMENT REFUND
-- Records refunds against captured payment transactions.

CREATE TABLE shioricafe_schema.payment_refund (
    payment_refund_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    payment_transaction_id bigint NOT NULL,

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    status text NOT NULL DEFAULT 'PENDING'
        CHECK (
            status IN (
                'PENDING',
                'CONFIRMED',
                'FAILED',
                'CANCELLED'
            )
        ),

    reason text NULL,
    provider_refund_reference text NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    confirmed_at timestamptz NULL,

    CONSTRAINT fk_refund_transaction
        FOREIGN KEY (payment_transaction_id)
        REFERENCES shioricafe_schema.payment_transaction(
            payment_transaction_id
        )
        ON DELETE RESTRICT
);

-- 5. PAYMENT REFUND ITEM ALLOCATION
-- Identifies which order items a refund affects.

CREATE TABLE shioricafe_schema.payment_refund_item_allocation (
    payment_refund_item_allocation_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    payment_refund_id bigint NOT NULL,
    order_item_id bigint NOT NULL,

    allocated_amount numeric(10,2) NOT NULL
        CHECK (allocated_amount > 0),

    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT fk_refund_allocation_refund
        FOREIGN KEY (payment_refund_id)
        REFERENCES shioricafe_schema.payment_refund(
            payment_refund_id
        )
        ON DELETE RESTRICT,

    CONSTRAINT fk_refund_allocation_item
        FOREIGN KEY (order_item_id)
        REFERENCES shioricafe_schema.order_item(order_item_id)
        ON DELETE RESTRICT,

    CONSTRAINT uq_refund_item
        UNIQUE (payment_refund_id, order_item_id)
);

-- 6. PAYMENT REVERSAL
-- Records reversals of captured payment transactions.

CREATE TABLE shioricafe_schema.payment_reversal (
    payment_reversal_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    payment_transaction_id bigint NOT NULL,

    amount numeric(12,2) NOT NULL
        CHECK (amount > 0),

    currency char(3) NOT NULL DEFAULT 'PHP'
        CHECK (currency = 'PHP'),

    reason text NULL,

    status text NOT NULL DEFAULT 'PENDING'
        CHECK (
            status IN (
                'PENDING',
                'CONFIRMED',
                'FAILED'
            )
        ),

    created_at timestamptz NOT NULL DEFAULT now(),
    confirmed_at timestamptz NULL,

    CONSTRAINT fk_reversal_transaction
        FOREIGN KEY (payment_transaction_id)
        REFERENCES shioricafe_schema.payment_transaction(
            payment_transaction_id
        )
        ON DELETE RESTRICT
);

-- 7. PAYMENT PROVIDER EVENT
-- Stores incoming provider notifications for reconciliation.


CREATE TABLE shioricafe_schema.provider_event (
    provider_event_id bigint
        GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    provider text NOT NULL,
    provider_event_reference text NOT NULL,

    event_type text NOT NULL,

    payload jsonb NOT NULL,

    processing_status text NOT NULL DEFAULT 'RECEIVED'
        CHECK (
            processing_status IN (
                'RECEIVED',
                'PROCESSED',
                'FAILED',
                'IGNORED'
            )
        ),

    received_at timestamptz NOT NULL DEFAULT now(),
    processed_at timestamptz NULL,

    CONSTRAINT uq_provider_event_reference
        UNIQUE (provider, provider_event_reference)
);


-- 8. INDEXES

CREATE INDEX ix_credit_customer
    ON shioricafe_schema.store_credit_ledger(customer_id);

CREATE INDEX ix_credit_order
    ON shioricafe_schema.store_credit_ledger(order_id);

CREATE INDEX ix_attempt_order
    ON shioricafe_schema.payment_attempt(order_id);

CREATE INDEX ix_attempt_status
    ON shioricafe_schema.payment_attempt(status);

CREATE INDEX ix_transaction_order
    ON shioricafe_schema.payment_transaction(order_id);

CREATE INDEX ix_transaction_attempt
    ON shioricafe_schema.payment_transaction(payment_attempt_id);

CREATE INDEX ix_refund_transaction
    ON shioricafe_schema.payment_refund(payment_transaction_id);

CREATE INDEX ix_refund_allocation_refund
    ON shioricafe_schema.payment_refund_item_allocation(
        payment_refund_id
    );

CREATE INDEX ix_reversal_transaction
    ON shioricafe_schema.payment_reversal(payment_transaction_id);

CREATE INDEX ix_provider_event_status
    ON shioricafe_schema.provider_event(processing_status);

COMMIT;

-- =========================================================
-- END OF MIGRATION 004
-- =========================================================
