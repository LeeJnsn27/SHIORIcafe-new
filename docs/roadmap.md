# SHIORI Caf� Roadmap � Phase 0 to Phase 11

Last updated: 2026-10-31
Project: SHIORI Caf� (?)

## Purpose

This roadmap lays out the work from product decisions through release and operations. It is a planning document for Codex, the project owner, and CLINE. It does not claim that planned features are implemented or production ready.

`docs/PHASE1_HANDOFF.md` is the source of truth for agreed Release 1 scope and business rules. This roadmap tracks phase progress. `docs/PROJECT_PLAN.md` records the current code baseline and technical risks. `AGENTS.md` defines project and agent governance.

## Progress tracker � 2026-10-31

Phase 0 is complete. The project is in **Phase 1**. The existing scratch application is not counted as completed implementation because it uses MySQL/`mysqli`, not the approved PostgreSQL/PDO stack.

**[Coding]** marks a progress task that changes application code, database files, or app configuration. CLINE handles those tasks under `AGENTS.md`.

### Phase 0 � Product scope and decisions: Complete

- [x] Confirm `docs/PHASE1_HANDOFF.md` as the Release 1 scope source.
- [x] Agree on the target technology stack and three-tier architecture.
- [x] Set the caf�'s operating hours to 05:00�00:00 and require customer bookings and table sessions to end by 23:30 for the 30-minute closing cleanup.
- [x] Agree on many reservation rules: timezone, hours, advance window, flexible duration, statuses, cancellation, check-in, extensions, reminders, and collision behavior.
- [x] Agree on the broad Release 1 scope and defer AI Study Companion, analytics, and email order receipts.
- [x] Decide that a proposed replacement table remains available to other customers until accepted. During `Adjustment_Pending`, show the original and proposed assignments to customer and staff; hold the original assignment only and recheck the proposed assignment on acceptance.
- [x] Decide that if another customer books the proposed table/time, the Manager explains it is no longer available and offers an earlier/later time, or searches for another suitable seat at the customer's original time. The customer must accept any time change.
- [x] Decide that customers may cancel while `Adjustment_Pending` without penalty and that cancellation immediately releases the hold on the original assignment.
- [x] Decide what happens when a customer does not respond to a Manager�s proposed change before the reservation starts: assign a suitable vacant seat after rechecking availability and set the reservation to `Confirmed`, or keep it `Adjustment_Pending` and show an urgent Manager dashboard alert if none is available. Flag automatic reassignments for staff to confirm the customer knows the new seat at check-in.
- [x] Decide the final status for a reservation that is never checked in: mark it `Cancelled` with reason `No response/no check-in`; release the table after the no-response wait if time remains, or at the scheduled end if it arrives first. Do not use `Completed` or add a separate `No_Show` status.
- [x] Decide �Confirm attendance� only records the customer's response; it does not change reservation status or count as Cashier check-in.
- [x] Define Group Study Desk capacity and party-size fit: each desk seats 4�6 as recorded for that table; smaller parties may use one if they fit and it is available.
- [x] Define demo payment scope and behavior: PayMongo test mode with QR Ph, no live charges; failure and refund behavior recorded in the handoff.
- [x] Decide that each reservation has one minimum spend, not a per-person minimum; aggregate eligible net purchases and drink subtotals from fully settled, non-voided linked orders using purchase-time drink classification. If drink subtotals reach at least 50% of the minimum, waive the ordinary shortfall; otherwise collect the minimum minus eligible net purchases, floored at ?0.
- [ ] **OWNER DECISIONS � order aggregation:** approve allocation of order-wide discounts and unitemized refunds across drink/non-drink lines; decide whether credits against other items are discounts or payment tender; and decide whether redeemed/issued store credit counts toward eligible purchases. Until resolved, preserve independently verifiable amounts and exclude only disputed adjustment amounts when both aggregates remain accurate; otherwise exclude the entire affected order from both aggregates. Fully settled, non-voided orders count at net purchase value after approved discounts/refunds; unpaid/partial-settlement/voided/fully-refunded orders count zero; payment reversals make an order ineligible if remaining successful payments do not fully settle it. Partial refunds reduce retained eligible purchase value, with drink subtotal reduced only by approved refund allocations to drink lines. A credit directly applied to minimum spend is a separate shortfall adjustment, not purchase/drink value, and is not applied again as a stockout waiver. Multiple successful payments/refunds are ledgered idempotently.
- [x] **Approve stockout drink-waiver Option B:** Trigger only when a verified stockout resolution causes drink subtotal to move from at least 50% of the required minimum immediately before the event to below 50% immediately after. Waive only the increase in regular shortfall attributable to that event, capped by current payable shortfall and net value removed. Example: minimum ?100, before total/drinks ?80/?50; after a ?10 drink stockout ?70/?40, regular shortfall rises from ?20 to ?30, so waive ?10 and leave the pre-existing ?20 deficiency payable. Record unique event keys and pre/post snapshots, process events sequentially per reservation, account for prior waivers, count accepted substitutes at actual price/category, and avoid counting a price reduction again as a waiver. Refund aggregation follows final valid/paid status rules.
- [x] Decide to use booking-duration bands and show the exact minimum, 50% drink-waiver rule, and shortfall rule before reservation confirmation.
- [x] Set the reservation minimum spend by duration band and table type: Solo Study Nook ?50/?100/?150; Duo Nook ?100/?150/?200; Group Study Desk ?200/?300/?400 for up to 2 hours, over 2 to 4 hours, and over 4 hours respectively. Amounts are per reservation, not per person.
- [x] Decide walk-in session and dine-in/takeaway assignment: every walk-in transaction gets a unique generic session (`customer_id = NULL`, `is_walkin = true`), and every order links to its session. Dine-in requires a table; takeaway has no table. Walk-ins have no table minimum and end by 23:30.
- [x] Define receipt and payment/refund permissions: itemized on-screen receipt after payment; Cashier records collected cash and shows receipts but cannot refund or override digital status; Manager-authorized PayMongo-confirmed digital refunds only, original method, recorded reason, and provider confirmation required; cash refunds and emailed order receipts are out of scope.

- [x] Choose Vercel Hobby as the public class-project deployment target; record PHP community-runtime and scheduled-task constraints for Phase 1.
- [x] Choose Google Apps Script to schedule and send reminder emails. Its free consumer account has a 100-recipient daily limit; trigger timing may vary.
- [x] Choose Neon Free for the hosted PostgreSQL database.

### Phase 1 � Specifications and reproducible setup: In progress

- [x] Update `docs/PHASE1_HANDOFF.md` with the decisions and open questions known at the time.
- [x] Create this roadmap covering Phases 0�11.
- [x] Create the feature workflow reference and Codex skill entrypoint.
- [x] Reconcile `docs/PROJECT_PLAN.md` with the handoff and roadmap.
- [x] Finish the open Phase 0 decisions above.
- [ ] Write detailed `docs/BUSINESS_RULES.md` and `docs/USER_FLOWS.md` for each role (Guest, Customer, Cashier, and Manager), including normal, error, and alternate paths. Resolve owner decisions as part of this work or clearly mark them as open questions.
- [ ] Draft architecture and reservation, order, adjustment/chat, extension, and payment flowcharts.
- [ ] Review the eight-table proposal and document approved schema changes.
- [ ] Draft database and API/page contracts.
- [ ] Create the TDD test matrix, acceptance criteria, and prioritized CLINE implementation backlog.
- [ ] Prepare root `README.md` and safe `.env.example` with setup instructions for the selected environment.

**Canonical financial definitions:**

- `Gross Order Amount`: sum of quantity multiplied by each item's purchase-time unit price, before discounts and refunds.
- `Net Purchase Amount`: Gross Order Amount minus discounts allocated to the order's lines and confirmed purchase refunds allocated to the order or its lines. Payment tender does not reduce this amount.
- `Net Paid Amount`: successful collected/captured payment tender minus confirmed refunds and confirmed payment reversals. Store credit is included as tender only if the owner approves it.
- `Eligible Net Purchases`: sum of Net Purchase Amount for orders that are not voided and not fully refunded, and whose Net Paid Amount fully settles their Net Purchase Amount. Unpaid, pending, failed, abandoned, and partially settled orders contribute zero. A reversal makes an order ineligible if the remaining successful tender no longer covers its Net Purchase Amount.
- `Drink Subtotal`: sum of eligible net amounts for lines classified as drinks at purchase time, after discounts and confirmed purchase refunds allocated to those lines. Total purchases and Drink Subtotal use the same adjustments; non-drink line adjustments affect total purchases only.
- `Partial refund`: a confirmed refund of less than the order's remaining Net Purchase Amount. The order retains its eligible Net Purchase Amount after that refund's confirmed line allocation; refunded value is excluded. The Drink Subtotal falls only by the portion allocated to drink lines.
- `Full refund`: confirmed refunds equal the order's remaining Net Purchase Amount. The order contributes zero to Eligible Net Purchases and Drink Subtotal.
- `Payment reversal`: a confirmed reversal reduces Net Paid Amount. It does not independently change item line values or Drink Subtotal; exclude the entire order if remaining successful tender no longer fully settles Net Purchase Amount.
- `Purchase-time snapshots`: each order line preserves its unit price and drink classification at purchase, or references immutable versioned menu-item/category records with those values. Later menu price or category changes never recalculate historical orders.

**Adjustment treatment (canonical):**

- Discounts reduce Net Purchase Amount. The discount must be allocated to lines to calculate Drink Subtotal accurately.
- Confirmed purchase refunds reduce Net Purchase Amount by the refunded value allocated to the affected order/item lines. Pending, failed, or cancelled refunds have no effect.
- Payment tender settles an amount owed and does not reduce Net Purchase Amount. Only successful collected/captured tender contributes to Net Paid Amount.
- Direct minimum-spend credits reduce payable shortfall only. They do not change Eligible Net Purchases or Drink Subtotal and cannot also be recorded as a stockout waiver.
- Store credit treatment remains an owner decision: determine whether redeemed store credit counts as payment tender and whether store credit issued for a refund/stockout reduces eligible purchase value.
- Record each amount once; do not double-count an amount as discount, purchase refund, payment tender, direct minimum-spend credit, or stockout waiver.

**Unresolved allocation fallback (canonical):** order-wide discount allocation, unitemized refund allocation, credits against other items, and store-credit treatment require owner approval. Preserve independently verifiable amounts and exclude only disputed adjustment amounts when both Eligible Net Purchases and Drink Subtotal remain accurate. If a disputed amount cannot be isolated without making either aggregate inaccurate, exclude the entire affected order from both aggregates until resolved. Never include a disputed amount in only one aggregate. Partial settlements contribute zero until fully settled. Multiple successful payments and refunds may exist per order; count successful captures/collections, subtract confirmed refunds/reversals only, ignore pending/failed entries, ensure cumulative confirmed refunds/reversals do not exceed settled value, and process provider events idempotently.

### Phase 2 � Technical foundation and database alignment: Not started

- [ ] Choose the canonical PHP entry point, page layout, API layout, and asset paths.
- [ ] [Coding] Align the database schema and migrations with PostgreSQL and the approved data model.
- [ ] [Coding] Replace MySQL/`mysqli` assumptions with PDO and schema-qualified PostgreSQL queries.
- [ ] [Coding] Add safe configuration, error handling, logging, validation, JSON responses, and UTF-8 behavior.
- [ ] [Coding] Establish session security, password hashing, role guards, CSRF protection, and secure cookie settings.
- [ ] [Coding] Resolve obsolete Vercel/Node references in the app against the selected PHP deployment target.

### Phase 3 � Public caf� website and shared interface: Not started

- [ ] [Coding] Standardize navigation, shared page layout, responsive behavior, and Bootstrap styling.
- [ ] Approve and align caf� address, hours, menu, currency, contact details, and imagery.
- [ ] [Coding] Fix routes, form actions, asset paths, and frontend script loading.
- [ ] [Coding] Add accessible labels, keyboard navigation, focus states, contrast, alt text, and clear feedback.
- [ ] [Coding] Correct encoding issues and add graceful image fallbacks.

### Phase 4 � Authentication and role boundaries: Not started

- [x] Include customer, Cashier, and Manager accounts and role protection in the approved scope.
- [ ] [Coding] Implement customer registration, login, logout, and profile updates.
- [ ] [Coding] Implement Cashier and Manager authentication and protected routes.
- [ ] Define first-Manager provisioning and Manager-created Cashier accounts.
- [ ] [Coding] Implement secure session behavior, authorization checks, and account validation.

### Phase 5 � Reservations, availability, and check-in: Not started

- [x] Document reservation hours, lead time, booking horizon, flexible duration, no-overnight rule, and statuses.
- [x] Document the overlap formula and adjacent-booking behavior.
- [ ] [Coding] Implement table inventory, availability, and capacity-aware selection.
- [ ] [Coding] Implement server-side validation and collision-safe reservation creation.
- [ ] [Coding] Implement customer reservation history and cancellation.
- [ ] [Coding] Implement Cashier check-in and `Occupied` state.
- [ ] Verify availability and creation use the same rules, including simultaneous booking attempts.

### Phase 6 � Changes, chat, reminders, and extensions: Not started

- [x] Document Manager proposals, required reason, proposal status/timestamps, and four customer choices: accept, communicate/request seat change, reject while retaining the original booking, or cancel.
- [x] Document reservation-linked chat intent, reminder timing/actions, and extension rules.
- [x] Resolve adjustment cancellation, original/proposed assignment display, proposed-table availability until acceptance, the `Adjustment_Pending` status after automatic reassignment, and the urgent Manager alert when no suitable seat is available.
- [ ] Complete the notification and action-link behavior for reservation adjustments and reminders.
- [ ] Approve the chat and repeated-extension data model.
- [ ] [Coding] Implement chat, email reminders/actions, Manager adjustments, and notifications.
- [ ] [Coding] Implement repeatable one-hour extension checks, the 15-minute customer prompt, and five-minute Cashier reminder.
- [ ] [Coding] Implement end-time staff alerts and Manager confirmation that the nook is empty before completion.

### Phase 7 � Menu catalog and customer ordering: Not started

- [x] Record categorized menu, customer ordering, and atomic multi-item saving in Release 1 scope.
- [ ] Define remaining ordering rules, including item availability and order/table association.
- [ ] [Coding] Implement the database-backed menu and category catalog.
- [ ] [Coding] Implement cart editing, server-side quantity/item validation, and trusted price calculation.
- [ ] [Coding] Save orders and order items transactionally; implement confirmation, receipt, and lower-priority history.

### Phase 8 � Cashier order queue and payments: Not started

- [x] Include Cash and digital payment in Release 1; record that Cashier marks cash paid after collection.
- [x] Record the order lifecycle: `Pending -> Preparing -> Ready -> Served -> Completed`.
- [x] Define demo digital payment provider/method and success, failure, pending, and refund behavior: PayMongo test mode with QR Ph; no live charges.
- [x] Define receipt rules and refund authorization.
- [x] Define walk-in occupancy/duration details: Cashier records the customer's expected stay and occupied end time; Manager or Cashier may update it after checking reservation conflicts.
- [ ] [Coding] Implement the Cashier order queue and order status actions.
- [ ] [Coding] Implement walk-in ordering, payment recording, and approved digital payment integration.
- [ ] [Coding] Keep payment state separate from order preparation state and make records auditable.

### Phase 9 � Manager console and operational support: Not started

- [x] Include Manager menu, stock, table, Cashier-account, reservation-adjustment, and support responsibilities in scope.
- [ ] Finalize Manager/Cashier permission boundaries and audit-log requirements.
- [ ] [Coding] Implement Manager management pages and protected mutations.
- [ ] [Coding] Add Manager views for reservations, orders, chats, adjustments, payment state, and alerts.
- [ ] [Coding] Add appropriate search, pagination, empty states, and error states.

### Phase 10 � Integrated verification and release readiness: Not started

- [ ] Run the approved TDD matrix and acceptance checks across customer and staff workflows.
- [ ] Verify clean database setup, migrations, concurrency, transaction handling, and error paths.
- [ ] Review security, accessibility, responsive behavior, browser/server logs, and UTF-8 content.
- [ ] Verify configuration/secrets, HTTPS, backup/restore, logging, and privacy requirements.
- [ ] Complete owner acceptance review and resolve critical defects.
- [ ] Reconcile Git deletions and untracked rewrite before release.

### Phase 11 � Deployment, launch, and operations: Not started

- [ ] Select and configure the production host for the approved PHP/PostgreSQL stack.
- [ ] Configure production secrets, HTTPS, database access, migrations, outgoing mail, and the approved payment provider.
- [ ] Set up database backups, test restoration, logging, monitoring, and incident ownership.
- [ ] Run post-deployment smoke checks for authentication, reservations, check-in, ordering, payments, reminders, and staff access.
- [ ] Complete operations documentation and Release 1 handoff.
- [ ] Maintain a Release 2 backlog for AI Study Companion, analytics/reporting, and email order receipts.

**Status note:** checked items represent agreed or completed planning work unless they explicitly describe implementation or verification. The scratch application does not count as completed target-stack implementation. A phase is complete only when it meets its exit gate below.

## Project constraints

- **Architecture:** three-tier web application.
- **Frontend:** HTML5, CSS3, Bootstrap 5.3.3 dark theme, vanilla JavaScript.
- **Backend:** PHP 8.2+ with PDO.
- **Database:** PostgreSQL 18+, database `grp10_shioricafedb`, schema `shioricafe_schema`.
- **SQL:** explicitly qualify table names using `shioricafe_schema.table_name`.
- **Development process:** spec-driven development. Approve business rules and schema changes before implementation; prepare acceptance criteria and a TDD test matrix before CLINE builds each feature.
- **Agent roles:** Codex writes and reviews plans, specifications, and audits; CLINE implements application and database code and runs the agreed checks.
- **Current application state:** the scratch application uses PHP with `mysqli` and MySQL SQL files. It is not yet aligned with the target PostgreSQL/PDO architecture. Git also has legacy tracked deletions and the PHP rewrite as untracked files; reconcile this deliberately before commit or deployment.

## Release 1 scope

Release 1 includes customer accounts, reservations, menu and ordering, cashier operations, manager operations, reservation-linked support chat, reservation extensions, basic availability suggestions, cash, and PayMongo test-mode QR Ph for the group-project demo. No live transactions are in scope.

AI Study Companion, analytics/reporting, and email order receipts are deferred. Reservation reminder emails are part of the current reservation requirements; outgoing mail delivery configuration still needs to be specified.

---

## Phase 0 � Product scope and decisions

**Purpose:** agree what Release 1 means and settle business decisions that affect the data model and user flows.

**Work:**

- Confirm customer, Cashier, and Manager roles and their responsibilities.
- Agree the Release 1 feature boundary and deferred features.
- Record caf� hours, booking limits, reservation statuses, cancellation rules, table inventory, payment scope, order lifecycle, and notification behavior.
- Choose deployment environment and establish authoritative project documentation.
- Identify unresolved decisions instead of silently assuming defaults.

**Decisions already recorded:**

- `docs/PHASE1_HANDOFF.md` is the current scope source of truth.
- Caf� timezone is `Asia/Manila` (PHT, UTC+8); hours are 05:00�00:00 daily.
- Reservations have no minimum duration, can be booked up to 14 days ahead, require at least 30 minutes� notice, cannot cross midnight, and must end by 23:30 to leave 30 minutes for cleaning before the midnight close.
- New available reservations are confirmed immediately after a collision-safe save; generic `Pending` is removed.
- Reservation statuses are `Confirmed`, `Adjustment_Pending`, `Occupied`, `Cancelled`, and `Completed`.
- Cashier check-in changes a reservation to `Occupied`. Table operational state is `Available` or `Maintenance`; occupancy is separate.
- Customers can cancel before the session starts without penalty and cannot directly edit a confirmed table/time.
- �Confirm attendance� records the customer's response only; it does not change reservation status or count as Cashier check-in. No-show follow-ups depend on actual check-in.
- Manager change proposals require a reason and can be made before session start. Keep proposed values separate from the original booking until acceptance; record proposal status and timestamps. Customers can accept, communicate/request a seat change, reject and keep the original booking, or cancel. Rejection/expiry leaves original table/time unchanged.
- While `Adjustment_Pending`, show the original and proposed assignments to the customer and staff. The original assignment stays held, but the proposed replacement remains available to other customers until accepted. Recheck the proposed assignment on acceptance. If another customer has booked it, the Manager explains this and offers an earlier/later time for the customer's acceptance, or looks for another suitable seat at the originally booked time. Do not change the customer's time without acceptance. Customers may cancel without penalty; release the original assignment hold. By reservation start, automatically assign a suitable vacant seat after rechecking availability, preferring one with the same features. Set the reservation to `Confirmed`, notify the customer, and flag it for staff to confirm the customer knows the new seat at check-in. If none is available, keep `Adjustment_Pending` and show an urgent Manager counter dashboard alert. A requested move to another vacant seat must be checked and applied by the Cashier or on-duty Manager, after confirming suitability, operational availability, and no overlap for the rest of the stay.
- Customers may extend in free one-hour increments repeatedly if each added hour is conflict-free and ends by 23:30. Track `extension_count` (integer, default 0), increment only after successful approval, recalculate duration pricing each time, and use idempotency keys to prevent duplicate extensions.
- Cash remains available. The group-project demo uses PayMongo test mode with QR Ph and no live charges; use the provider's test simulation URL rather than scanning a generated QR with a real app. Reservations are not prepaid; customers pay at the caf�. Each reservation has one minimum spend, not a per-person minimum: Solo Study Nook ?50/?100/?150, Duo Nook ?100/?150/?200, and Group Study Desk ?200/?300/?400 for up to 2 hours, over 2 to 4 hours, and over 4 hours respectively. Aggregate fully settled, non-voided, non-fully-refunded orders at net purchase value after approved discounts/refunds; unpaid, partial-settlement, voided, or fully refunded orders count zero. Aggregate drinks from purchase-time drink snapshots with discounts/refunds allocated to drink lines. Order-wide discount/refund allocation and store-credit treatment remain owner decisions. If drink subtotals reach at least 50% of the minimum, waive the ordinary shortfall; otherwise collect the minimum minus eligible net purchases, floored at ?0. For a verified stockout that causes a crossing from at least 50% drinks to below 50%, apply approved Option B: waive only the additional regular shortfall attributable to the event, capped by current payable shortfall and net value removed; unrelated deficiencies remain due. Before confirmation, show the exact minimum, 50% drink-waiver rule, and shortfall rule prominently on the final review step; repeat the rule in the confirmation. Failed payments remain unpaid and may be retried or paid in cash; only provider-confirmed payments count as paid. Show an itemized on-screen receipt after payment; no emailed order receipt in Release 1. Cashiers record collected cash and show receipts but cannot refund or override digital payment status. Managers authorize and record refunds; only PayMongo-confirmed digital refunds are complete after provider confirmation. Cash refunds are out of scope.
- Each Group Study Desk has a recorded capacity from 4 to 6. Smaller parties may use one if available and within its capacity; prefer a smaller suitable table when available.
- Walk-in orders are marked dine-in or takeaway. Create a generic walk-in reservation/session (`customer_id = NULL`, `is_walkin = true`) for either type and link all counter/table orders to it with `reservation_id` and nullable `table_id` in `shioricafe_schema.order`; takeaway orders have no table. For dine-in, record the expected stay and avoid tables occupied, under maintenance, or reserved during that interval. Managers or Cashiers can update the occupied time slot after checking conflicts. Walk-in dine-in customers pay for their orders, have no reservation or table minimum, and must end their session by 23:30.
- Send the reservation reminder email 90 minutes before start, with Confirm attendance, Cancel reservation, and Contact Manager choices. Secure action tokens are unpredictable, stored only as SHA-256 hashes, linked to an allowed action, one-time use, and expire at reservation start. GET renders confirmation only; explicit POST is required after server-side validation. Gmail phone push behavior depends on the customer�s device settings.
- If there is no check-in one hour after start, send a follow-up, even if the customer previously confirmed attendance. If another hour passes and booked time remains, mark the reservation `Cancelled` with reason `No response/no check-in` and release the table. If the scheduled end comes first, release the table then and use the same status and reason. A reservation that was never checked in is not marked `Completed`; no separate `No_Show` status is wanted.
- Before a checked-in session ends, the system prompts 15 minutes ahead; Cashier reminds the customer five minutes ahead; staff are alerted at end time. The customer can choose �I�m leaving now� or request an extension. The on-duty Manager confirms the nook is empty and marks the reservation completed.

**Still open:**

- Hosting: Vercel Hobby, Neon Free, and Google Apps Script. Check the PHP runtime and secure reminder endpoint in Phase 1. Free-tier limits apply.

**Deliverable:** updated `docs/PHASE1_HANDOFF.md` with decisions and open items.
**Exit gate:** the owner confirms Release 1 scope and high-impact business rules before detailed specifications proceed.

## Phase 1 � Specifications and reproducible project setup

**Purpose:** convert approved scope into instructions that CLINE can implement without inventing business rules.

**Work:**

- Keep `docs/PROJECT_PLAN.md` aligned with `docs/PHASE1_HANDOFF.md` and this roadmap.
- Document user roles, page flows, business rules, statuses, and alternate/error paths.
- Define the canonical PostgreSQL schema, relationships, indexes, status values, and migration approach. Review the eight-table proposal against accounts, chat, payments, unified order sessions, drink classification, reminder tokens, stockout waivers, repeated extensions, adjustment proposals, and staff operations; submit any unapproved additions for review.
- Define page and API contracts: method, route, authentication, inputs, outputs, validation, and errors.
- Write flowcharts for reservation, ordering, adjustment/chat, extension, and payment processes.
- Write a TDD matrix, acceptance criteria, and prioritized CLINE implementation backlog.
- Select the deployment target, local prerequisites, configuration approach, and mail/payment integration boundaries.
- Prepare root `README.md` and safe `.env.example`; never put real secrets in source control.

**Planned documents:** `docs/BUSINESS_RULES.md`, `docs/USER_FLOWS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE_SPEC.md`, `docs/API_CONTRACTS.md`, `docs/TDD_TEST_MATRIX.md`, `docs/IMPLEMENTATION_BACKLOG.md`, root `README.md`, and `.env.example`.

**Exit gate:** all documents agree with the handoff and AGENTS guidance; schema/API changes and unresolved decisions are approved; clean setup instructions are defined.

## Phase 2 � Technical foundation and database alignment

**Purpose:** establish the target stack and a stable base before feature implementation.

**Work:**

- Choose one canonical PHP entry point, page layout, API layout, and asset paths.
- Replace MySQL-specific assumptions with the approved PostgreSQL schema and PDO connection strategy.
- Define safe configuration loading and fail-safe database errors.
- Establish shared request validation, JSON response format, logging, and UTF-8 handling.
- Define session settings, password hashing, authorization guards, CSRF protection, and secure cookie behavior.
- Create approved schema/migration files with schema-qualified references and seed data for tables/menu as appropriate.
- Resolve old Vercel/Node references versus the selected PHP deployment target.

**Exit gate:** application can start from documented configuration and connect to a clean PostgreSQL database; no feature code depends on the obsolete MySQL schema or removed paths.

## Phase 3 � Public caf� website and shared interface

**Purpose:** make the public site coherent and usable before transactional features are layered on.

**Work:**

- Standardize navigation, header/footer, page layout, mobile behavior, and Bootstrap styling.
- Align home, about, services/menu, and contact content with approved address, hours, menu, currency, and imagery.
- Fix page routes, form actions, asset references, and frontend script loading.
- Add accessible labels, keyboard navigation, focus states, readable contrast, and clear feedback/errors.
- Correct text encoding and image alt text/fallbacks.

**Exit gate:** public pages render consistently at supported screen sizes, all navigation/form destinations work, and business information is approved and consistent.

## Phase 4 � Authentication and role boundaries

**Purpose:** provide reliable customer and staff identity before requiring accounts for bookings or operations.

**Work:**

- Implement customer registration, login, logout, and profile updates.
- Implement separate or clearly separated Cashier and Manager authentication and role checks.
- Provision the first Manager during setup; allow authorized Managers to create Cashier accounts.
- Regenerate sessions after authentication; enforce authorization on every protected page and mutation.
- Add account validation, password recovery policy if approved, session expiry, and safe account error states.

**Exit gate:** customer and staff routes enforce the correct roles; password and session behavior meets the approved security requirements.

## Phase 5 � Reservations, availability, and check-in

**Purpose:** deliver collision-safe table reservations based on approved hours and statuses.

**Work:**

- Build the table inventory and availability views using `TBL-XX`, capacities, and operational status.
- Let customers browse availability; require login to reserve.
- Validate timezone, operating window, 14-day horizon, 30-minute lead time, table capacity, and start/end values on the server.
- Confirm reservations immediately when the protected overlap check succeeds.
- Use a transaction/concurrency strategy so simultaneous requests cannot double-book a table. Use the same overlap definition for availability and creation: existing start `<` requested end and existing end `>` requested start; adjacent reservations are allowed.
- Support customer booking list/history, cancellation before start, and staff reservation views.
- Let Cashiers verify a booking at arrival and move it to `Occupied`.
- Ensure availability excludes maintenance tables and respects live occupancy as well as scheduled intervals.

**Exit gate:** reservation workflows pass collision, boundary-time, capacity, cancellation, and simultaneous-request acceptance checks; availability and creation agree.

## Phase 6 � Reservation changes, chat, reminders, and extensions

**Purpose:** support operational changes and end-of-session handling without breaking reservation integrity.

**Work:**

- Let Managers propose changes before session start with a required reason.
- Keep proposed table/time in a separate proposal record with `proposed_table_id`, `proposed_start_time`, status, and timestamps. Preserve the reservation assignment until acceptance. Rejection or expiration leaves the original reservation unchanged. Expire unanswered proposals at start before running automatic seat reassignment. Present accept, communicate/request seat change, reject while retaining the original booking, or cancel.
- Provide one-to-one chat linked to the reservation. Resolve the schema for `shioricafe_schema.reservation_chat` and define authorization/retention before implementation.
- Implement the 90-minute reminder with secure one-time action links. Store only SHA-256 token hashes, bind tokens to reservation/action, expire at reservation start, invalidate after use or ineligibility, validate server-side, and require explicit POST; GET must not mutate reservation state.
- Send the extra reminder one hour after start when the customer has not checked in, even if they previously confirmed attendance; after another hour cancel remaining booked time if it remains. Make an unoccupied reservation�s slot available at its scheduled end if that comes first.
- Show the 15-minute end prompt and allow repeated one-hour extensions only when the added interval does not overlap another reservation and stays within opening hours. Track `extension_count`, recalculate the duration band after each approval, and prevent duplicate extension processing.
- Send the Cashier�s five-minute reminder and end-time staff alert. Customer �I�m leaving now� notifies staff; on-duty Manager confirms the nook is empty and completes the reservation.

**Exit gate:** change/chat/extension/reminder paths respect the status model and cannot introduce overlapping bookings; notifications and late/no-response behavior are documented and accepted.

## Phase 7 � Menu catalog and customer ordering

**Purpose:** deliver consistent menu browsing and customer table-side ordering.

**Work:**

- Establish a single database-backed menu/category source with current prices, availability/stock rules, and required `is_drink_category` Boolean classification.
- Build category browsing, cart add/remove/quantity editing, and clear totals.
- Associate every Release 1 order with its owning customer reservation or generic walk-in session through `reservation_id`; associate dine-in/table-side orders with `table_id`. Preserve purchase-time unit price and drink classification on each order item (or use immutable versioned menu/category records). Aggregate fully settled, non-voided, non-fully-refunded orders at net purchase value after approved allocations; apply the same allocated net amounts to drink-classified purchase-time lines.
- Validate item IDs and quantities server-side and calculate prices from trusted database values only.
- Save order header and all order items in one transaction; reject empty/invalid orders.
- Provide order confirmation and receipt display; keep order history lower priority as scoped.

**Exit gate:** menu prices are consistent across UI and backend; cart and order persistence meet validation, transaction, and total-calculation acceptance criteria.

## Phase 8 � Cashier order queue and payments

**Purpose:** let Cashiers process dine-in and walk-in orders and record payments correctly.

**Work:**

- Build a polling or refreshable queue for table-side and walk-in orders.
- Implement order transitions `Pending -> Preparing -> Ready -> Served -> Completed` with role-appropriate actions and visible timestamps.
- Give every walk-in transaction a unique generic session (`customer_id = NULL`, `is_walkin = true`) and link every order to its `reservation_id`. Require `table_id` for dine-in and null for takeaway; assign suitable available tables to dine-in using approved rules.
- Record payment method/state separately from order preparation status.
- Aggregate eligible net purchase amounts and drink-line net amounts across fully settled, non-voided, non-fully-refunded orders per reservation. Unpaid/partial-settlement/voided/fully-refunded orders count zero; payment reversals that leave an order not fully settled exclude it. Support multiple payment/refund ledger entries and idempotent events. Apply the 50% waiver and calculate shortfall once at reservation level.
- For each verified stockout, compare the drink subtotal immediately before and after its resolution; waiver consideration requires a crossing from at least 50% to below 50% caused by that event. Apply approved Option B: waive only the attributable increase in regular shortfall, capped by current payable shortfall and net value removed. Persist unique event keys and snapshots, process sequentially per reservation, and prevent duplicate waivers.
- For cash-at-table, record customer selection and let the Cashier mark paid after collection.
- Integrate PayMongo test mode with QR Ph for the group-project demo; handle success, failure, pending confirmation, simulated refunds, and reconciliation according to the approved contract. Do not use live credentials or process real charges.
- Produce an in-app receipt after payment. Email receipts remain deferred unless approved.

**Exit gate:** orders, items, status transitions, totals, and payment records are atomic and auditable; cash and approved digital paths meet acceptance criteria.

## Phase 9 � Manager console and operational support

**Purpose:** provide Managers the control tools needed to operate the caf� and resolve issues.

**Work:**

- Manage menu categories/items, stock, table records/capacity, operational status, and Cashier accounts.
- Provide Manager visibility into reservations, orders, adjustment requests, chats, payment state, and staff alerts.
- Implement the approved no-response seat reassignment, Manager alert when no suitable seat is available, customer notification, and staff-verified seat-change requests.
- Add authorization checks and CSRF protections to every mutation.
- Define audit events and an approved audit-log schema or remove unsupported screens until implemented.
- Add practical filters, search, pagination, and clear empty/error states.

**Exit gate:** Manager/Cashier permission matrix is enforced; operational actions are recorded and auditable; no interface exposes actions outside the user�s role.

## Phase 10 � Integrated verification and release readiness

**Purpose:** verify the full product against approved requirements and prepare a clean handoff.

**Work:**

- Run the agreed TDD matrix across customer, Cashier, and Manager paths.
- Verify clean database installation and migrations, reservation concurrency, order transactions, payment states, session controls, authorization, CSRF, and input validation.
- Check accessibility, responsive behavior, browser console/server logs, error handling, and UTF-8 content.
- Review secret handling, least-privilege database access, HTTPS requirements, backup/restore plan, logging, and privacy/retention behavior.
- Complete acceptance review with the owner; record defects and defer only explicitly approved items.
- Reconcile Git deletions/untracked rewrite intentionally; prepare a release branch/commit only after the owner�s normal workflow permits it.

**Exit gate:** release checklist is green, clean install is reproducible from documentation, and no unresolved critical defects or scope decisions remain.

## Phase 11 � Deployment, launch, and ongoing operations

**Purpose:** deploy the selected PHP/PostgreSQL release safely and keep it maintainable.

**Work:**

- Deploy to the approved host with production PHP/PostgreSQL compatibility, HTTPS, secrets, and least-privilege credentials.
- Apply approved migrations and verify seed/configuration values without exposing secrets.
- Configure mail delivery and the approved digital payment provider in production, including callback/return URLs and error monitoring.
- Set up automated database backups and test restoration; define log retention, alert ownership, and incident response.
- Run post-deploy smoke checks for sign-in, reservation availability/creation, check-in, ordering, payment, notifications, and staff access.
- Monitor operational issues, document support procedures, and collect owner-approved improvements.
- Maintain a Release 2 backlog for deferred AI Study Companion, analytics/reporting, and email order receipts.

**Exit gate:** production smoke checks pass, backups and monitoring are active, operational ownership is clear, and the Release 1 handoff is complete.

---

## Cross-phase rules

- Do not begin implementation for a feature until its business rules, schema/API contract, acceptance criteria, and TDD matrix are approved.
- Do not silently add tables, statuses, payment behavior, or notification rules; document proposals and get owner approval.
- Keep all database queries schema-qualified.
- Use server-side validation and prepared statements; never trust browser-submitted prices, totals, roles, or reservation availability.
- Treat reservation availability as a concurrency-sensitive operation, not a UI-only check.
- Keep deferred features out of Release 1 unless the owner changes scope and the documentation is updated.

## Completion definition

A phase is complete when its decisions and deliverables are approved, the implementation matches those specifications, and its acceptance checks can be followed on a clean environment without undocumented state or secrets.
