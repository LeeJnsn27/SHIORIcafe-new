## SHIORI Café — Database Specification (docs/REVISED_DATABASE_SPEC.md)
## Status: Release 1 Database Design — business rules approved; complete table definitions and SQL implementation require final review

Last Updated: 2026-11-04

Target Platform: PostgreSQL 18+ on Neon PostgreSQL

Database: grp10_shioricafedb | Schema: shioricafe_schema
0. Document Status, Legend, and Source Hierarchy
This is the canonical Release 1 database specification for Shiori Café. It serves as the authoritative data architecture blueprint prior to generating executable SQL migration scripts. BUSINESS_RULES.md is authoritative for business behavior, and PHASE1_HANDOFF.md verifies decision continuity. When this document conflicts with docs/BUSINESS_RULES.md on a business policy or amount, BUSINESS_RULES.md takes precedence; this specification must be updated to match it.
Status Tags Legend
Tag	Meaning
[APPROVED]	Officially approved business rule or architectural decision.
[PROPOSED]	Technical implementation design chosen to implement approved business rules.
[VERIFY]	Platform-compatibility item to verify against the target Neon PostgreSQL environment.


1. Purpose and Scope
This specification defines the complete 24-table PostgreSQL relational schema required by BUSINESS_RULES.md. It defines exact column definitions, data types, primary and foreign keys, check constraints, indexes, foreign key delete behaviors, concurrency-safe reservation overlap prevention via exclusion constraints, financial ledger integrity, and dependency-based migration order.
2. Global Conventions
2.1 Naming and Qualification [APPROVED]
- All SQL references are schema-qualified with shioricafe_schema..
- PDO prepared statements and explicit transaction blocks (BEGIN ... COMMIT) are mandatory for all state changes.
- Payment processing state is isolated from kitchen order preparation state.
2.2 Identifier Names [APPROVED]
- Identifiers use lowercase snake_case in singular form.
- Reserved word tables table and order are schema-qualified and double-quoted (shioricafe_schema."table", shioricafe_schema."order").
- Standardized constraint and index prefixes: pk_, fk_, uq_, ck_, ex_, ix_.
- Foreign key names are normalized (reservation_extension_id, reservation_adjustment_id, reservation_action_token_id, reservation_minimum_credit_id).
2.3 Primary-Key Strategy [APPROVED]
- Identity primary keys use bigint GENERATED ALWAYS AS IDENTITY.
- Foreign keys use bigint.
- Opaque UUIDs or secure hashes are maintained in separate columns for public tokens or provider references.
2.4 Data Types [APPROVED]
Concept	Type	Notes
Money (Peso)	numeric(12,2)	Menu items and line subtotals use numeric(10,2). Never float or money.
Currency	char(3) NOT NULL DEFAULT 'PHP'	Enforced via CHECK (currency = 'PHP').
Instants / Timestamps	timestamptz	Stored in UTC, evaluated in Asia/Manila.
Enumerations	text + CHECK (col IN (...))	Preferred over custom ENUM types for simpler migrations.
Counts & Minutes	integer / smallint	Constrained with non-negative checks.
Hashes	char(64)	Lowercase hex SHA-256 strings.


2.5 Timestamp Convention [APPROVED]
- Timestamps use timestamptz. Interpretation and display adhere strictly to Asia/Manila (PHT, UTC+8).
- Reservation start and end times use minute precision (MOD(EXTRACT(EPOCH FROM col), 60) = 0).
- Calendar and time checks evaluate explicitly using AT TIME ZONE 'Asia/Manila'.
- created_at defaults to now(). Mutable tables include updated_at maintained by trigger T-01.
2.6 Foreign-Key Delete Behavior [APPROVED]
- Every foreign key enforces ON DELETE RESTRICT ON UPDATE NO ACTION. Soft deletes and status flags are used exclusively; hard deletes are prohibited.
3. Approved Business Rules Carried into the Schema
3.1 Reservation Lifecycle & Operating Schedule [APPROVED]
- Operating hours are 05:00 to 00:00 (midnight) daily. Bookings and sessions must end by 23:30.
- Bookings require at least 30 minutes advance notice and can be made up to 14 days ahead.
- Reservation statuses: Confirmed, Adjustment_Pending, Occupied, Cancelled, Completed.
- Registered customers are limited to at most one active booking (Confirmed, Adjustment_Pending, Occupied) at a time.
- Reminders trigger at -90 minutes. If unchecked in after +1 hour from start, a follow-up is dispatched; after +2 hours, the reservation transitions to Cancelled with reason No response/no check-in. A reservation that was never checked in is never marked Completed.
3.2 Overtime Extensions [APPROVED]
- Extensions add 60 minutes if the table is conflict-free and ends by 23:30.
- Complimentary allowance: up to 30 minutes without a qualifying drink order; up to 90 minutes with at least one drink item in a fully settled reservation order.
- Paid extension rates per 30 minutes: ₱12.50 (Solo), ₱25.00 (Duo), ₱50.00 (Group).
- Option A Payment Ledger: Extension fees link directly to reservation_extension_id in payment attempts and transactions without synthetic dining orders or menu items.
3.3 Minimum Spend Rules [APPROVED]
- One minimum spend applies per online table reservation, not per person. The authoritative amounts are taken from docs/BUSINESS_RULES.md and supersede any conflicting values in earlier database drafts.
Table type	Up to 2 hours	Over 2 to 4 hours	Over 4 hours
Solo Study Nook	₱50	₱100	₱150
Duo Nook	₱100	₱150	₱200
Group Study Desk	₱200	₱300	₱400


- Exactly 2 hours belongs to the first band; exactly 4 hours belongs to the second band. Walk-in sessions have no table minimum spend.
- If an extension moves the reservation into a higher duration band, the required minimum must be recalculated and displayed before customer acceptance.
3.4 Canonical Financial Definitions [APPROVED]
- Gross Order Amount: Sum of quantity \(\times\) purchase-time unit price.
- Net Purchase Amount: Gross amount minus allocated line discounts and confirmed purchase refunds.
- Net Paid Amount: Successful collected tender minus confirmed refunds and reversals.
- 50% Drink Waiver Rule: If total eligible drink purchases \(\ge 50\%\) of the required minimum spend, the table shortfall is waived to ₱0.
3.5 Stockout Waiver (Option B) [APPROVED]
- If a verified item stockout drops drink totals below the 50% threshold, the attributable shortfall increase is automatically waived via reservation_minimum_waiver.
4. Entity Inventory & Schema Overview
#	Table	Purpose
1	customer	Registered customer accounts
2	staff	Cashier and Store Manager operational credentials
3	table	Physical seating inventory and operational status
4	category	Menu categories with is_drink_category flag
5	menu_item	Active catalog items, pricing, and stock status
6	reservation	Online bookings, walk-in sessions, and status tracking
7	order	Orders linked to a reservation session
8	order_item	Normalized order lines with purchase-time price snapshots
9	order_discount	Order-level promotional or staff discounts
10	order_discount_allocation	Line-item allocations of order discounts
11	reservation_extension	Overtime extension requests, fees, and idempotency
12	reservation_adjustment	Manager booking change proposals
13	reservation_action_token	Hashed tokens for email action links
14	reservation_chat	1-on-1 customer–manager support discussions
15	reservation_minimum_credit	Direct minimum-spend credits
16	reservation_minimum_waiver	Option B stockout waiver event outcomes
17	reservation_minimum_waiver_item	Line items affected by stockout waivers
18	store_credit_ledger	Customer store credit balance ledger
19	payment_attempt	Payment collection attempt initialization
20	payment_transaction	Verified captured tender ledger
21	payment_refund	Manager-authorized digital refunds
22	payment_refund_item_allocation	Line-item allocations of refunds
23	payment_reversal	Gateway chargeback/reversal records
24	provider_event	Webhook idempotency event log


5. Detailed Table Definitions (shioricafe_schema)
5.1 Core Domain Tables
customer
CREATE TABLE shioricafe_schema.customer (
    customer_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name text NOT NULL CHECK (btrim(full_name) <> ''),
    email text NOT NULL CHECK (length(email) <= 254 AND position('@' in email) > 1),
    password_hash text NOT NULL,
    phone text NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_customer_email_lower ON shioricafe_schema.customer (lower(email));

staff
CREATE TABLE shioricafe_schema.staff (
    staff_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name text NOT NULL CHECK (btrim(full_name) <> ''),
    role text NOT NULL CHECK (role IN ('Cashier', 'Manager')),
    email text NOT NULL,
    password_hash text NOT NULL,
    created_by_staff_id bigint NULL REFERENCES shioricafe_schema.staff(staff_id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ck_staff_self_creation CHECK (created_by_staff_id <> staff_id)
);
CREATE UNIQUE INDEX uq_staff_email_lower ON shioricafe_schema.staff (lower(email));

table
CREATE TABLE shioricafe_schema."table" (
    table_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    table_number text NOT NULL CHECK (table_number ~ '^TBL-[0-9]{2}$'),
    table_type text NOT NULL CHECK (table_type IN ('Solo Study Nook', 'Duo Nook', 'Group Study Desk')),
    capacity smallint NOT NULL,
    amenities text[] NOT NULL DEFAULT '{}',
    operational_status text NOT NULL DEFAULT 'Available' CHECK (operational_status IN ('Available', 'Maintenance')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_table_number UNIQUE (table_number),
    CONSTRAINT ck_table_capacity CHECK (
        (table_type = 'Solo Study Nook' AND capacity = 1) OR
        (table_type = 'Duo Nook' AND capacity = 2) OR
        (table_type = 'Group Study Desk' AND capacity BETWEEN 4 AND 6)
    )
);

category
CREATE TABLE shioricafe_schema.category (
    category_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_name text NOT NULL UNIQUE CHECK (btrim(category_name) <> ''),
    is_drink_category boolean NOT NULL DEFAULT false,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

menu_item
CREATE TABLE shioricafe_schema.menu_item (
    menu_item_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_id bigint NOT NULL REFERENCES shioricafe_schema.category(category_id) ON DELETE RESTRICT,
    item_name text NOT NULL CHECK (btrim(item_name) <> ''),
    description text NULL,
    current_price numeric(10,2) NOT NULL CHECK (current_price >= 0.00),
    stock_status text NOT NULL DEFAULT 'In Stock' CHECK (stock_status IN ('In Stock', 'Out of Stock')),
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_menu_item_name_lower ON shioricafe_schema.menu_item (lower(item_name));

5.2 Reservation Domain Tables
reservation
CREATE TABLE shioricafe_schema.reservation (
    reservation_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id bigint NULL REFERENCES shioricafe_schema.customer(customer_id) ON DELETE RESTRICT,
    table_id bigint NULL REFERENCES shioricafe_schema."table"(table_id) ON DELETE RESTRICT,
    party_size smallint NULL CHECK (party_size IS NULL OR party_size > 0),
    reservation_start_at timestamptz NOT NULL,
    reservation_end_at timestamptz NULL,
    status text NOT NULL CHECK (status IN ('Confirmed', 'Adjustment_Pending', 'Occupied', 'Cancelled', 'Completed')),
    required_minimum_amount numeric(12,2) NULL CHECK (required_minimum_amount IS NULL OR required_minimum_amount >= 0.00),
    is_walkin boolean NOT NULL DEFAULT false,
    extension_count integer NOT NULL DEFAULT 0 CHECK (extension_count >= 0),
    cancellation_reason text NULL,
    cancelled_at timestamptz NULL,
    checked_in_at timestamptz NULL,
    checked_in_by_staff_id bigint NULL REFERENCES shioricafe_schema.staff(staff_id) ON DELETE RESTRICT,
    completed_at timestamptz NULL,
    completed_by_staff_id bigint NULL REFERENCES shioricafe_schema.staff(staff_id) ON DELETE RESTRICT,
    attendance_confirmed_at timestamptz NULL,
    reminder_sent_at timestamptz NULL,
    checkin_followup_sent_at timestamptz NULL,
    seat_notice_required boolean NOT NULL DEFAULT false,
    seat_notice_acknowledged_at timestamptz NULL,
    seat_notice_acknowledged_by_staff_id bigint NULL REFERENCES shioricafe_schema.staff(staff_id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ck_reservation_walkin_customer CHECK ((is_walkin AND customer_id IS NULL) OR (NOT is_walkin AND customer_id IS NOT NULL)),
    CONSTRAINT ck_reservation_online_complete CHECK (is_walkin OR (table_id IS NOT NULL AND party_size IS NOT NULL AND reservation_end_at IS NOT NULL AND required_minimum_amount IS NOT NULL)),
    CONSTRAINT ck_reservation_walkin_no_minimum CHECK (NOT is_walkin OR required_minimum_amount IS NULL),
    CONSTRAINT ck_reservation_walkin_status CHECK (NOT is_walkin OR status IN ('Occupied', 'Completed')),
    CONSTRAINT ck_reservation_table_end CHECK ((table_id IS NULL) = (reservation_end_at IS NULL)),
    CONSTRAINT ck_reservation_end_after_start CHECK (reservation_end_at IS NULL OR reservation_end_at > reservation_start_at),
    CONSTRAINT ck_reservation_checkin CHECK ((checked_in_at IS NULL) = (checked_in_by_staff_id IS NULL)),
    CONSTRAINT ck_reservation_completion CHECK ((status = 'Completed') = (completed_at IS NOT NULL)),
    CONSTRAINT uq_reservation_id_customer UNIQUE (reservation_id, customer_id)
);

CREATE UNIQUE INDEX uq_reservation_one_active_per_customer
ON shioricafe_schema.reservation (customer_id)
WHERE customer_id IS NOT NULL AND is_walkin = false AND status IN ('Confirmed', 'Adjustment_Pending', 'Occupied');

6. Overlap Prevention & Exclusion Constraint
To enforce zero double-bookings at the database layer, the schema applies a PostgreSQL exclusion constraint:
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE shioricafe_schema.reservation
ADD CONSTRAINT ex_reservation_no_overlap
EXCLUDE USING gist (
  table_id WITH =,
  tstzrange(reservation_start_at, reservation_end_at, '[)') WITH &&
)
WHERE (table_id IS NOT NULL AND status IN ('Confirmed', 'Adjustment_Pending', 'Occupied'));

7. Financial Summary Database Views
7.1 v_order_financials
CREATE OR REPLACE VIEW shioricafe_schema.v_order_financials AS
SELECT
    o.order_id,
    o.reservation_id,
    o.gross_order_amount,
    COALESCE(SUM(od.amount), 0.00) AS total_discounts,
    (o.gross_order_amount - COALESCE(SUM(od.amount), 0.00)) AS net_order_amount,
    COALESCE(pt.total_paid, 0.00) AS total_paid,
    COALESCE(pr.total_refunded, 0.00) AS total_refunded,
    (COALESCE(pt.total_paid, 0.00) - COALESCE(pr.total_refunded, 0.00)) AS net_collected
FROM shioricafe_schema."order" o
LEFT JOIN shioricafe_schema.order_discount od ON o.order_id = od.order_id
LEFT JOIN (
    SELECT pa.order_id, SUM(ptx.amount) AS total_paid
    FROM shioricafe_schema.payment_attempt pa
    JOIN shioricafe_schema.payment_transaction ptx ON pa.payment_attempt_id = ptx.payment_attempt_id
    GROUP BY pa.order_id
) pt ON o.order_id = pt.order_id
LEFT JOIN (
    SELECT pa.order_id, SUM(prf.amount) AS total_refunded
    FROM shioricafe_schema.payment_attempt pa
    JOIN shioricafe_schema.payment_transaction ptx ON pa.payment_attempt_id = ptx.payment_attempt_id
    JOIN shioricafe_schema.payment_refund prf ON ptx.payment_transaction_id = prf.payment_transaction_id
    WHERE prf.status = 'Confirmed'
    GROUP BY pa.order_id
) pr ON o.order_id = pr.order_id
WHERE o.voided_at IS NULL
GROUP BY o.order_id, o.reservation_id, o.gross_order_amount, pt.total_paid, pr.total_refunded;

7.2 v_reservation_financials
CREATE OR REPLACE VIEW shioricafe_schema.v_reservation_financials AS
WITH order_aggregates AS (
    SELECT
        o.reservation_id,
        COALESCE(SUM(vof.net_collected), 0.00) AS total_paid_purchases,
        COALESCE(SUM(
            CASE WHEN oi.is_drink_category_at_purchase = true THEN oi.line_gross_amount ELSE 0.00 END
        ), 0.00) AS drink_subtotal
    FROM shioricafe_schema."order" o
    JOIN shioricafe_schema.v_order_financials vof ON o.order_id = vof.order_id
    JOIN shioricafe_schema.order_item oi ON o.order_id = oi.order_id
    WHERE o.voided_at IS NULL
    GROUP BY o.reservation_id
)
SELECT
    r.reservation_id,
    r.required_minimum_amount,
    COALESCE(oa.total_paid_purchases, 0.00) AS total_paid_purchases,
    COALESCE(oa.drink_subtotal, 0.00) AS drink_subtotal,
    CASE
        WHEN r.required_minimum_amount IS NULL OR r.is_walkin = true THEN 0.00
        WHEN COALESCE(oa.drink_subtotal, 0.00) >= (0.50 * r.required_minimum_amount) THEN 0.00
        WHEN COALESCE(oa.total_paid_purchases, 0.00) >= r.required_minimum_amount THEN 0.00
        ELSE (r.required_minimum_amount - COALESCE(oa.total_paid_purchases, 0.00))
    END AS minimum_spend_shortfall
FROM shioricafe_schema.reservation r
LEFT JOIN order_aggregates oa ON r.reservation_id = oa.reservation_id;

8. Cross-Table Database Triggers (T-01 to T-17)
ID	Trigger Scope	Description
T-01	BEFORE UPDATE	Updates updated_at = now() on mutable tables.
T-02	BEFORE UPDATE on reservation	Enforces valid status state machine transitions.
T-03	BEFORE INSERT/UPDATE on reservation	Validates 30-min notice, 14-day booking window, and table availability.
T-04	BEFORE INSERT on order	Validates active reservation status and table presence.
T-05	DEFERRED CONSTRAINT on order	Verifies gross_order_amount matches line items sum at commit.
T-06	BEFORE INSERT on payment_attempt	Enforces payment source XOR (order_id XOR reservation_extension_id).
T-07	BEFORE INSERT on payment_refund	Ensures refund cumulative total \(\le\) original transaction amount.
T-08	BEFORE INSERT on payment_refund_item_allocation	Validates refund allocations do not exceed net line amounts.
T-09	BEFORE INSERT on payment_reversal	Ensures unique gateway reversal references across refunds and reversals.
T-10	BEFORE INSERT/UPDATE	Enforces Manager staff role requirement for sensitive actions.
T-11	BEFORE INSERT on reservation_extension	Validates extension window and complimentary minute calculations.
T-12	BEFORE UPDATE on reservation_adjustment	Atomically revalidates proposal slot availability upon acceptance.
T-13	BEFORE INSERT on reservation_action_token	Invalidates old action tokens upon new issuance.
T-14	BEFORE INSERT on reservation_minimum_credit	Prevents duplicate credit application across waivers.
T-15	BEFORE INSERT on store_credit_ledger	Verifies customer store credit balance never drops below ₱0.
T-16	BEFORE INSERT on reservation_chat	Restricts chat creation to online bookings.
T-17	BEFORE UPDATE/DELETE	Enforces append-only immutability on financial transaction ledgers.


9. Migration Execution Order
Implementation Completeness Note
The 24-table inventory and dependency sequence below define the intended Release 1 model. In this document, detailed column-level SQL definitions are currently present for the six core tables (customer, staff, table, category, menu_item, and reservation). The remaining 18 tables are listed in the inventory and dependency order but still require complete column definitions, primary keys, foreign keys, unique/check constraints, and indexes before executable migrations can be considered complete. Do not treat the inventory alone as complete DDL.
Dependency Order
1. customer
2. staff
3. table
4. category
5. menu_item
6. reservation
7. reservation_action_token
8. reservation_adjustment
9. reservation_extension
10. reservation_chat
11. order
12. order_item
13. order_discount
14. order_discount_allocation
15. reservation_minimum_credit
16. reservation_minimum_waiver
17. reservation_minimum_waiver_item
18. store_credit_ledger
19. payment_attempt
20. payment_transaction
21. payment_refund
22. payment_refund_item_allocation
23. payment_reversal
24. provider_event
25. Database Triggers & Financial Views (v_order_financials, v_reservation_financials)
