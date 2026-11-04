
-- =========================================================
-- SHIORI CAFE
-- Migration 005: Triggers and Views
-- Database: grp10_shioricafedb
-- Schema: shioricafe_schema
--
-- Requires migrations 001-004.
--
-- DRAFT:
-- Review dependencies and business rules before execution.
-- =========================================================

BEGIN;

-- ---------------------------------------------------------
-- 1. GENERIC UPDATED_AT FUNCTION
-- ---------------------------------------------------------

CREATE OR REPLACE FUNCTION shioricafe_schema.set_updated_at()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$;

-- ---------------------------------------------------------
-- 2. UPDATED_AT TRIGGERS
-- ---------------------------------------------------------

CREATE TRIGGER trg_order_updated_at
BEFORE UPDATE ON shioricafe_schema."order"
FOR EACH ROW
EXECUTE FUNCTION shioricafe_schema.set_updated_at();

CREATE TRIGGER trg_payment_attempt_updated_at
BEFORE UPDATE ON shioricafe_schema.payment_attempt
FOR EACH ROW
EXECUTE FUNCTION shioricafe_schema.set_updated_at();

-- ---------------------------------------------------------
-- 3. ORDER ITEM GROSS TOTAL VALIDATION
-- ---------------------------------------------------------
-- Recalculates gross_order_amount from order items.
-- A later deferred trigger may be needed if multiple item
-- rows are inserted in one transaction.

CREATE OR REPLACE FUNCTION shioricafe_schema.refresh_order_gross_amount()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    target_order_id bigint;
BEGIN
    target_order_id := COALESCE(NEW.order_id, OLD.order_id);

    UPDATE shioricafe_schema."order"
    SET gross_order_amount = (
        SELECT COALESCE(SUM(line_gross_amount), 0.00)
        FROM shioricafe_schema.order_item
        WHERE order_id = target_order_id
    )
    WHERE order_id = target_order_id;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE TRIGGER trg_refresh_order_gross_amount
AFTER INSERT OR UPDATE OR DELETE
ON shioricafe_schema.order_item
FOR EACH ROW
EXECUTE FUNCTION shioricafe_schema.refresh_order_gross_amount();

-- ---------------------------------------------------------
-- 4. PAYMENT REFUND ALLOCATION VALIDATION
-- ---------------------------------------------------------
-- Ensures the refund allocation does not exceed the refund.
-- Full cross-order and remaining-line-value checks still
-- require additional business-rule validation.

CREATE OR REPLACE FUNCTION shioricafe_schema.validate_refund_allocation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    refund_total numeric(12,2);
    allocated_total numeric(12,2);
BEGIN
    SELECT amount
    INTO refund_total
    FROM shioricafe_schema.payment_refund
    WHERE payment_refund_id = NEW.payment_refund_id;

    SELECT COALESCE(SUM(allocated_amount), 0.00)
    INTO allocated_total
    FROM shioricafe_schema.payment_refund_item_allocation
    WHERE payment_refund_id = NEW.payment_refund_id
      AND payment_refund_item_allocation_id
          <> COALESCE(NEW.payment_refund_item_allocation_id, -1);

    allocated_total := allocated_total + NEW.allocated_amount;

    IF allocated_total > refund_total THEN
        RAISE EXCEPTION
            'Refund allocations cannot exceed the refund amount.';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_validate_refund_allocation
BEFORE INSERT OR UPDATE
ON shioricafe_schema.payment_refund_item_allocation
FOR EACH ROW
EXECUTE FUNCTION shioricafe_schema.validate_refund_allocation();

-- ---------------------------------------------------------
-- 5. REPORTING VIEW: ORDER SUMMARY
-- ---------------------------------------------------------

CREATE OR REPLACE VIEW shioricafe_schema.v_order_summary AS
SELECT
    o.order_id,
    o.reservation_id,
    o.table_id,
    o.order_type,
    o.preparation_status,
    o.gross_order_amount,
    o.currency,
    o.voided_at,
    o.created_at
FROM shioricafe_schema."order" o;

-- ---------------------------------------------------------
-- 6. REPORTING VIEW: ORDER ITEM SUMMARY
-- ---------------------------------------------------------

CREATE OR REPLACE VIEW shioricafe_schema.v_order_item_summary AS
SELECT
    oi.order_item_id,
    oi.order_id,
    oi.menu_item_id,
    oi.item_name_at_purchase,
    oi.quantity,
    oi.unit_price_at_purchase,
    oi.is_drink_category_at_purchase,
    oi.line_gross_amount
FROM shioricafe_schema.order_item oi;

-- ---------------------------------------------------------
-- 7. REPORTING VIEW: PAYMENT SUMMARY
-- ---------------------------------------------------------

CREATE OR REPLACE VIEW shioricafe_schema.v_payment_summary AS
SELECT
    pt.payment_transaction_id,
    pt.order_id,
    pt.reservation_extension_id,
    pt.payment_method,
    pt.amount,
    pt.currency,
    pt.status,
    pt.captured_at
FROM shioricafe_schema.payment_transaction pt;

COMMIT;

-- =========================================================
-- END OF MIGRATION 005
-- =========================================================
