SHIORI Café — Database Documentation
Project: SHIORI Café
Release: Release 1
DBMS: PostgreSQL 18+ (Neon PostgreSQL)
Database name: grp10_shioricafedb
Application schema: shioricafe_schema
Time zone: Asia/Manila
Status: Database design documentation; proposed details are subject to owner review before SQL implementation
Last updated: 2026-11-04
-- ============================================================================================================


1. Purpose
This document describes the SHIORI Café relational database: its database and schema names, table inventory, table purposes, relationships, primary keys, foreign keys, important constraints, indexes, triggers, views, and migration dependencies.

BUSINESS_RULES.md is the authoritative source for café policies and financial behavior. This document translates those rules into a database design. Where a technical detail is not fully specified in the approved source documents, it is identified as [PROPOSED] or [OWNER REVIEW] and must not be treated as approved executable SQL.
2. Database and schema
Setting	Value	Description
DBMS	PostgreSQL 18+	Relational database management system
Hosting target	Neon PostgreSQL	Hosted PostgreSQL environment
Database	grp10_shioricafedb	Main project database
Schema	shioricafe_schema	Namespace for application tables, views, and functions
Time zone	Asia/Manila	Business-rule evaluation and customer-facing time zone
Required extension	btree_gist	Supports the GiST equality/range exclusion constraint for reservation conflicts


Create the application namespace after connecting to the target database:
CREATE SCHEMA IF NOT EXISTS shioricafe_schema;
CREATE EXTENSION IF NOT EXISTS btree_gist;
All application objects should be schema-qualified. The reserved-word table names must be quoted: shioricafe_schema."table" and shioricafe_schema."order".
3. Naming and data-type conventions
- Table and column names use lowercase snake_case; table names are singular.
- Primary keys use bigint GENERATED ALWAYS AS IDENTITY; corresponding foreign keys use bigint.
- Monetary values use numeric(12,2), except menu prices and order-line subtotals, which use numeric(10,2). Do not use floating-point or PostgreSQL money for financial values.
- Currency uses char(3) NOT NULL DEFAULT 'PHP' with a check that the value is PHP.
- Event timestamps use timestamptz; business interpretation and display use Asia/Manila.
- Enumerated business states use text with CHECK constraints rather than custom PostgreSQL enum types.
- SHA-256 hashes use char(64) containing lowercase hexadecimal characters.
- Mutable tables use created_at and updated_at timestamps. A shared update trigger maintains updated_at.
- Foreign keys use ON DELETE RESTRICT ON UPDATE NO ACTION. Records are retained through status flags or soft-deactivation; hard deletion of referenced operational and financial records is not part of the design.
4. Table inventory
The Release 1 model contains 24 application tables.
#	Table	Purpose	Main relationship area
1	customer	Registered customer accounts	Reservations, store credit, chat
2	staff	Cashier and Manager accounts	Staff actions, reservations, adjustments, refunds
3	table	Physical study-nook inventory and operating status	Reservations
4	category	Menu categories and drink classification	Menu items
5	menu_item	Menu catalog, price, and stock status	Order items
6	reservation	Online bookings and walk-in sessions	Customer, table, orders, extensions, adjustments, payments
7	order	Dine-in and takeaway orders	Reservation, table, order items, discounts, payments
8	order_item	Purchased order lines and purchase-time snapshots	Order, menu item, discount/refund allocations
9	order_discount	Order-wide discounts	Order, discount allocations
10	order_discount_allocation	Distribution of order discounts to lines	Order discount, order item, order
11	reservation_extension	Extension requests, approval, time, and fee	Reservation, payment attempts
12	reservation_adjustment	Manager-proposed reservation changes	Reservation, staff
13	reservation_action_token	Hashed one-time email action tokens	Reservation
14	reservation_chat	One-to-one customer/Manager messages	Reservation, customer/staff sender
15	reservation_minimum_credit	Direct credits against minimum-spend shortfall	Reservation, authorizing staff
16	reservation_minimum_waiver	Auditable Option B stockout waiver event	Reservation, source event
17	reservation_minimum_waiver_item	Items affected by a stockout waiver	Waiver, order item
18	store_credit_ledger	Append-only issued/redeemed store-credit entries	Customer, order and staff where applicable
19	payment_attempt	Initiated payment collection against an order or extension	Order XOR extension, transactions
20	payment_transaction	Provider-confirmed or collected payment tender	Payment attempt, refunds, reversals
21	payment_refund	Manager-authorized refund request and provider result	Payment transaction, staff
22	payment_refund_item_allocation	Allocation of confirmed purchase refunds to order lines	Refund, order item
23	payment_reversal	Provider-confirmed reversal/chargeback record	Payment transaction
24	provider_event	Provider webhook event and idempotency log	Provider object/event references


5. Relationship overview
The following relationship map summarizes the intended logical relationships. A crow's-foot description such as 1-to-many means one parent record can be associated with multiple child records.
Parent	Child	Relationship	Foreign-key meaning
customer	reservation	1-to-many; customer optional for walk-ins	reservation.customer_id
table	reservation	1-to-many over time; reservation table may be null for takeaway walk-ins	reservation.table_id
staff	reservation	1-to-many for check-in, completion, and seat-notice acknowledgement	Staff audit columns on reservation
category	menu_item	1-to-many	menu_item.category_id
reservation	order	1-to-many; every order belongs to a reservation session	order.reservation_id
table	order	1-to-many; nullable for takeaway	order.table_id
order	order_item	1-to-many	order_item.order_id
menu_item	order_item	1-to-many	order_item.menu_item_id
order	order_discount	1-to-many	order_discount.order_id
order_discount	order_discount_allocation	1-to-many	order_discount_allocation.order_discount_id
order_item	order_discount_allocation	1-to-many across discount allocations	order_discount_allocation.order_item_id
reservation	reservation_extension	1-to-many	reservation_extension.reservation_id
reservation	reservation_adjustment	1-to-many proposals over time	reservation_adjustment.reservation_id
staff	reservation_adjustment	1-to-many proposals created by Managers	Proposed creator/resolver staff foreign keys
reservation	reservation_action_token	1-to-many tokens over time	reservation_action_token.reservation_id
reservation	reservation_chat	1-to-many messages	reservation_chat.reservation_id
customer / staff	reservation_chat	Sender relationship	Sender identity must be represented and validated; exact polymorphic FK design requires owner review
reservation	reservation_minimum_credit	1-to-many credits	reservation_minimum_credit.reservation_id
reservation	reservation_minimum_waiver	1-to-many verified stockout events	reservation_minimum_waiver.reservation_id
reservation_minimum_waiver	reservation_minimum_waiver_item	1-to-many affected items	reservation_minimum_waiver_item.reservation_minimum_waiver_id
order_item	reservation_minimum_waiver_item	1-to-many event-item references as applicable	reservation_minimum_waiver_item.order_item_id
customer	store_credit_ledger	1-to-many ledger entries	store_credit_ledger.customer_id
order XOR reservation_extension	payment_attempt	Exactly one payable source per attempt	payment_attempt.order_id XOR payment_attempt.reservation_extension_id
payment_attempt	payment_transaction	1-to-many transaction records if provider/payment model requires them	payment_transaction.payment_attempt_id
payment_transaction	payment_refund	1-to-many refund records	payment_refund.payment_transaction_id
payment_refund	payment_refund_item_allocation	1-to-many line allocations	payment_refund_item_allocation.payment_refund_id
order_item	payment_refund_item_allocation	1-to-many refund allocations	payment_refund_item_allocation.order_item_id
payment_transaction	payment_reversal	1-to-many reversal records subject to cumulative limits	payment_reversal.payment_transaction_id
Provider	provider_event	External event log; provider object reference is not necessarily an internal FK	Provider event/object identifiers and unique idempotency key


Important: The relationship overview is a logical design summary. Exact column names and composite foreign-key choices for the 18 tables whose full DDL is not yet present must be confirmed in the detailed schema design before migrations are generated.
6. Primary keys, foreign keys, and table-level constraints
6.1 Core tables with existing column-level definitions
The existing database specification provides detailed definitions for the following six tables. The primary key and principal foreign keys are summarized here.
Table	Primary key	Foreign keys	Important documented constraints / indexes
customer	customer_id	None	Nonblank name; email shape/length check; unique case-insensitive email index
staff	staff_id	created_by_staff_id → staff.staff_id	Role limited to Cashier or Manager; creator cannot be same staff member; unique case-insensitive email index
table	table_id	None	TBL-XX format; table type allow-list; capacity must match table type; unique table number
category	category_id	None	Nonblank unique category name; is_drink_category flag
menu_item	menu_item_id	category_id → category.category_id	Nonblank item name; nonnegative numeric(10,2) price; stock status allow-list; unique case-insensitive item name index
reservation	reservation_id	customer_id → customer; table_id → table; check-in/completion/seat-notice staff IDs → staff	Status allow-list; positive party size when supplied; nonnegative extension count; online/walk-in consistency checks; end-after-start; check-in pair consistency; completion timestamp consistency; one active reservation per registered customer partial unique index; composite uniqueness (reservation_id, customer_id) for scoped references


6.2 Remaining 18 tables — required key and constraint design
The current canonical specification lists these tables and their responsibilities but does not yet contain complete column-level DDL for them. The following is the required key/constraint design checklist. Items marked [PROPOSED] are implementation design proposals derived from the business rules and require review before being treated as final.
Table	Primary key	Required foreign keys / references	Important constraints to define
order	order_id [PROPOSED]	reservation_id → reservation (required); table_id → table (nullable)	order_type allow-list (Dine-In, Takeaway); preparation status allow-list; dine-in requires table; takeaway requires null table; nonnegative gross amount; currency PHP; void metadata consistency. Every order must reference its reservation session.
order_item	order_item_id [PROPOSED]	order_id → order; menu_item_id → menu_item	Positive quantity; nonnegative purchase-time unit price; required purchase-time item name and drink-category snapshot; line gross amount must equal quantity × purchase-time price; line subtotal precision numeric(10,2).
order_discount	order_discount_id [PROPOSED]	order_id → order	Nonnegative amount; PHP currency; discount cannot exceed applicable order/line value; retain reason/creator where required for audit.
order_discount_allocation	order_discount_allocation_id [PROPOSED]	order_id → order; order_discount_id → order_discount; order_item_id → order_item	Nonnegative allocated amount; composite FK should ensure the discount belongs to the same order; unique discount/item allocation; total allocations must reconcile to discount amount using transaction/trigger validation.
reservation_extension	reservation_extension_id [PROPOSED]	reservation_id → reservation	Unique idempotency key; status allow-list; one-hour request; fee and complimentary-minute checks; requested/approved interval must be conflict-free and end by 23:30; customer acceptance required for paid time.
reservation_adjustment	reservation_adjustment_id [PROPOSED]	reservation_id → reservation; proposed table → table; proposing/resolving staff → staff	Status in Pending, Accepted, Rejected, Expired; required explanation; proposal expiry/resolution timestamps; proposed assignment must not replace original reservation fields until acceptance and revalidation.
reservation_action_token	reservation_action_token_id [PROPOSED]	reservation_id → reservation	SHA-256 hash only; action allow-list; expiration; consumed/invalidated timestamps; one unused token per reservation/action; token hash unique; atomic one-time consumption.
reservation_chat	reservation_chat_id [PROPOSED]	reservation_id → reservation; sender customer/staff reference design [OWNER REVIEW]	Online reservations only; exactly one sender identity; message body nonblank; sent timestamp; authorization must enforce one-to-one customer/Manager participation. A polymorphic sender FK cannot be enforced by a simple single FK and needs an explicit design decision.
reservation_minimum_credit	reservation_minimum_credit_id [PROPOSED]	reservation_id → reservation; authorizing staff → staff	Nonnegative amount; reason/source; credit reduces shortfall only, never purchase totals or drink subtotal; prevent duplicate application and double-counting against waivers.
reservation_minimum_waiver	reservation_minimum_waiver_id [PROPOSED]	reservation_id → reservation	Unique source-event key; before/after aggregate snapshots; nonnegative waived amount; Option B amount formula; idempotent retry behavior; one outcome per verified stockout event.
reservation_minimum_waiver_item	reservation_minimum_waiver_item_id [PROPOSED]	reservation_minimum_waiver_id → reservation_minimum_waiver; order_item_id → order_item	Unique waiver/item pair; record affected quantity/value and actual substitute/refund effect as needed; prevent repeated counting of the same removed value.
store_credit_ledger	store_credit_ledger_id [PROPOSED]	customer_id → customer; optional order/staff source references [OWNER REVIEW]	Append-only entries; positive issuance/negative redemption convention must be fixed; balance may never fall below zero; idempotency/source key; redeemed credit is tender, not a purchase or minimum-spend credit.
payment_attempt	payment_attempt_id [PROPOSED]	order_id → order nullable XOR reservation_extension_id → reservation_extension	Exactly one payable source (order_id XOR reservation_extension_id); payment method/status allow-lists; nonnegative amount; currency PHP; provider reference uniqueness where present; idempotency.
payment_transaction	payment_transaction_id [PROPOSED]	payment_attempt_id → payment_attempt	Only provider-confirmed/collected tender is successful; nonnegative amount; unique provider transaction reference; status allow-list; currency PHP; preserve provider identifiers and timestamps.
payment_refund	payment_refund_id [PROPOSED]	payment_transaction_id → payment_transaction; authorizing staff → staff	Status allow-list; positive/nonnegative refund amount; required reason; cumulative confirmed refunds plus reversals cannot exceed original successful payment; provider refund reference idempotency; pending/failed/cancelled refunds do not change financial aggregates.
payment_refund_item_allocation	payment_refund_item_allocation_id [PROPOSED]	payment_refund_id → payment_refund; order_item_id → order_item	Nonnegative allocation; unique refund/item allocation; total allocations reconcile to refund amount where line allocation is required; allocation cannot exceed remaining net line amount; extension-fee refunds do not allocate to dining lines.
payment_reversal	payment_reversal_id [PROPOSED]	payment_transaction_id → payment_transaction	Provider reversal reference unique; cumulative confirmed refunds and reversals cannot exceed original successful transaction; never duplicate a refund event; append-only confirmed ledger.
provider_event	provider_event_id [PROPOSED]	External provider object reference; internal payment link [OWNER REVIEW]	Unique provider event ID/idempotency key; event type and processing status; received/processed timestamps; retain enough payload/reference data for audit without storing unnecessary sensitive data; apply each event once.


6.3 Cross-table integrity rules
The database implementation must enforce or transactionally validate the following:
1. A reservation with a table cannot overlap another blocking reservation on that table.
2. A registered customer may have at most one active online reservation.
3. An online reservation must have a customer, table, party size, end time, and required minimum. A walk-in session has customer_id = NULL, is_walkin = true, and no minimum spend.
4. Every order references one reservation session. Dine-in orders require a table; takeaway orders have no table.
5. Each order line preserves its purchase-time unit price and drink classification. Later menu changes must not rewrite historical order values.
6. An order-wide discount is allocated across order lines with deterministic currency rounding; allocations must sum to the discount total.
7. Eligible purchases and drink subtotals include only nonvoided, fully settled, not-fully-refunded orders, adjusted for confirmed discounts and purchase refunds.
8. Payment tender does not reduce Net Purchase Amount. Confirmed refunds and reversals affect Net Paid Amount; confirmed purchase refunds also affect Net Purchase Amount according to their line allocation.
9. A payment attempt must reference exactly one payable source: an order or a reservation extension, never both and never neither.
10. Confirmed refunds and reversals combined cannot exceed the original successful payment amount. One provider event cannot be recorded as both a refund and a reversal.
11. Direct minimum-spend credits, stockout waivers, discounts, purchase refunds, payment tender, and store credit must remain distinct to prevent double counting.
12. Financial ledger records are auditable and append-only after confirmation; corrections use compensating records rather than destructive edits.
7. Reservation overlap and concurrency constraint
The documented PostgreSQL design uses btree_gist and an exclusion constraint over the table identifier and half-open reservation time range. Half-open ranges [) allow adjacent bookings where one reservation ends exactly when another begins.
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE shioricafe_schema.reservation
ADD CONSTRAINT ex_reservation_no_overlap
EXCLUDE USING gist (
    table_id WITH =,
    tstzrange(reservation_start_at, reservation_end_at, '[)') WITH &&
)
WHERE (
    table_id IS NOT NULL
    AND status IN ('Confirmed', 'Adjustment_Pending', 'Occupied')
);
Implementation review required: The approved business rules state that while an adjustment is pending, the original table/time remains held and the proposed replacement is not held until accepted. The exclusion constraint above protects the reservation's stored assignment; application logic must keep proposed values in reservation_adjustment and recheck availability atomically when accepting a proposal.
8. Indexes and uniqueness requirements
The existing specification explicitly defines these indexes:
Index	Table	Purpose
uq_customer_email_lower	customer	Case-insensitive unique customer email
uq_staff_email_lower	staff	Case-insensitive unique staff email
uq_table_number	table	Unique table identifier
uq_menu_item_name_lower	menu_item	Case-insensitive unique menu-item name
uq_reservation_one_active_per_customer	reservation	At most one active online reservation per registered customer
ex_reservation_no_overlap	reservation	Prevent overlapping blocking reservations on the same table


Additional indexes [PROPOSED; finalize with detailed DDL and query patterns] should cover frequently joined foreign-key columns and operational lookups, including reservation time/status, order reservation/status/creation time, order-item order ID, payment-attempt source IDs, payment-transaction attempt ID, refund transaction ID, provider event ID, and ledger customer/time. Avoid adding duplicate or unused indexes without query-plan review.
9. Triggers and database functions
The canonical specification identifies the following trigger responsibilities. The trigger list describes required behavior; it does not by itself mean all trigger functions have already been implemented or tested.
ID	Scope	Responsibility
T-01	Mutable tables, BEFORE UPDATE	Set updated_at = now()
T-02	reservation, BEFORE UPDATE	Enforce valid reservation status transitions
T-03	reservation, BEFORE INSERT/UPDATE	Validate notice window, booking horizon, operating hours, and availability rules
T-04	order, BEFORE INSERT	Validate reservation session status and table requirements
T-05	order, deferred constraint	Reconcile gross order amount with order-item totals at transaction commit
T-06	payment_attempt, BEFORE INSERT	Enforce exactly one payment source: order XOR extension
T-07	payment_refund, BEFORE INSERT	Enforce cumulative refund limit against original transaction
T-08	payment_refund_item_allocation, BEFORE INSERT	Prevent allocations beyond remaining net line amounts
T-09	payment_reversal, BEFORE INSERT	Enforce unique gateway reversal references across refund/reversal records
T-10	Sensitive actions, BEFORE INSERT/UPDATE	Require Manager role where business rules restrict the action
T-11	reservation_extension, BEFORE INSERT	Validate extension timing and complimentary-minute calculations
T-12	reservation_adjustment, BEFORE UPDATE	Revalidate proposed slot atomically when accepted
T-13	reservation_action_token, BEFORE INSERT	Invalidate previous unused token for the same reservation/action
T-14	reservation_minimum_credit, BEFORE INSERT	Prevent duplicate credit application and overlap with waiver treatment
T-15	store_credit_ledger, BEFORE INSERT	Prevent customer store-credit balance from becoming negative
T-16	reservation_chat, BEFORE INSERT	Restrict chat records to eligible online reservations
T-17	Financial ledger tables, BEFORE UPDATE/DELETE	Enforce append-only immutability after financial records are confirmed


Cross-row financial rules should be enforced using appropriately locked transactions, deferred constraints, or carefully designed trigger functions. A simple row-level CHECK constraint cannot validate aggregate totals across multiple rows.
10. Views and derived financial values
The current specification names two reporting views:
View	Intended purpose
v_order_financials	Summarize order gross value, discounts, net purchase value, collected tender, refunds, and net collected amount
v_reservation_financials	Summarize reservation eligible purchases, drink subtotal, and minimum-spend shortfall


Important implementation status: The view definitions in the earlier draft are not yet sufficient to implement all canonical financial rules. Before they are used, they must account for:
- order-line discount allocations rather than only order-level discount totals;
- confirmed purchase refunds and their item-level allocations;
- successful/collected payment transactions only;
- confirmed payment reversals;
- partially settled orders contributing zero until fully settled;
- fully refunded orders contributing zero;
- drink subtotal after discounts and confirmed drink-line refunds;
- direct minimum-spend credits and previously applied stockout waivers;
- exclusion of voided orders and unresolved adjustments that prevent accurate calculation.
The authoritative definitions of Gross Order Amount, Net Purchase Amount, Net Paid Amount, Eligible Net Purchases, Drink Subtotal, and shortfall are in BUSINESS_RULES.md. The final SQL views must reproduce those definitions exactly. Do not use the earlier draft view SQL as production-ready financial logic.
11. Minimum-spend calculation reference
The authoritative minimum-spend amounts are from BUSINESS_RULES.md:
Table type	Up to 2 hours	Over 2 to 4 hours	Over 4 hours
Solo Study Nook	₱50	₱100	₱150
Duo Nook	₱100	₱150	₱200
Group Study Desk	₱200	₱300	₱400


Exactly 2 hours is in the first band; exactly 4 hours is in the second band. Minimum spend applies once per online reservation/table, not per person. Walk-in sessions have no table minimum.

The ordinary shortfall is based on Eligible Net Purchases. If Drink Subtotal is at least 50% of the applicable required minimum, the ordinary shortfall is waived. Approved direct minimum-spend credits and previously applied waivers reduce payable shortfall only; they are not purchases or drink subtotal.
12. Migration and creation order
Create parent tables before child tables, then create cross-table triggers and views after all referenced tables exist. The documented dependency order is:
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
25. Triggers, functions, exclusion constraints (where deferred), and financial views
The migration files should be numbered to reflect the actual dependency groups. The current five-file migration draft set is not a complete implementation of all 24 tables and must not be described as complete until every table, constraint, index, trigger, and view is reconciled with this document.
13. Items requiring final review before SQL implementation
1. Complete column-level definitions for the 18 tables listed in Section 6.2.
2. Exact sender identity design for reservation_chat (customer versus staff) and exact authorization model.
3. Exact composite foreign keys for discount allocations and other same-parent consistency rules.
4. Exact payment attempt and transaction cardinality, provider references, statuses, and idempotency keys.
5. Store-credit ledger entry types, sign convention, source references, and balance calculation.
6. Exact stockout waiver source-event schema and before/after snapshot columns.
7. Financial view/query logic matching every canonical definition in BUSINESS_RULES.md.
8. Full trigger function definitions and concurrency/locking behavior for cross-row checks.
9. Verify btree_gist availability and extension permissions in the target Neon PostgreSQL project.
10. Owner approval of all proposed schema details before executable SQL migrations are generated or run.
14. Related project documentation
- README.md — project overview, prerequisites, installation, configuration, database setup, and execution instructions.
- docs/BUSINESS_RULES.md — authoritative café policies and financial/business calculations.
- docs/DATABASE_SPEC.md — canonical database design and data dictionary.
- docs/PHASE1_HANDOFF.md — Release 1 scope and decision continuity.
Documentation note: This document is a database design deliverable, not proof that the database has been created or that all migrations have been executed. The 24-table inventory and business requirements are documented; proposed details and outstanding implementation decisions must be approved and verified before deployment.