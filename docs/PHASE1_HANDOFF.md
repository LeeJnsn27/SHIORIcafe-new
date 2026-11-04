# Phase 1 Planning Handoff

Last updated: 2026-10-31
Project: SHIORI Cafe

## Purpose and source of truth

This file is the current source of truth for agreed Release 1 scope and business rules. The user confirmed this on 2026-10-26. `docs/roadmap.md` tracks phase progress. `docs/PROJECT_PLAN.md` records the current code baseline and technical risks and must remain consistent with this handoff.

This is a planning document. No application code or database implementation has been changed as part of this planning work.

## Architecture and governance

- Codex role: planning, architecture, SDD/TDD drafting, and audits; no application-code or database-file modifications.
- CLINE role: implementation and execution from approved specifications.
- Architecture: three-tier web application.
- Frontend: HTML5, CSS3, Bootstrap 5.3.3 dark theme, vanilla JavaScript.
- Backend: PHP 8.2+ with PDO.
- Database: PostgreSQL 18+, database `grp10_shioricafedb`, schema `shioricafe_schema`.
- SQL must explicitly qualify table names with `shioricafe_schema`.
- Target normalized model currently lists eight tables. Any schema additions must be reviewed and approved before implementation.
- Hosting: Vercel Hobby runs the PHP app, Neon Free hosts PostgreSQL, and Google Apps Script schedules and sends reminder emails. Verify Vercel PHP compatibility and define one protected endpoint for reminders during Phase 1. Apps Script consumer accounts allow 100 email recipients per day, and trigger timing may vary. Gmail phone alerts depend on customer device settings.

## Release 1 scope

### Included

- Customer registration, login, and profile updates.
- Cashier and Manager login with role-protected routes. First Manager is provisioned during setup; Managers can create Cashier accounts.
- Customers browse availability and sign in to reserve a table. Customers can view bookings, cancel, and request changes.
- Manager-proposed booking adjustments require an explanation and offer accept, communicate/request a seat change, reject while keeping the original booking, or cancel. Proposals have statuses and timestamps; only acceptance applies proposed values.
- Categorized menu, dynamic cart, customer table-side ordering, walk-in orders entered by Cashiers, order history at lower priority, and a receipt after payment.
- Cashier order queue and reservation check-in/payment operations.
- Manager menu/category/stock/table/account management and one-to-one reservation-linked customer/Manager chat.
- One-hour extension requests, with complimentary time and fees governed by the approved drink-based allowance; prompt before the session end.
- Basic alternative table/time suggestions when a requested slot is unavailable.
- Cash and PayMongo test-mode QR Ph payments for the group-project demo; no live transactions.

### Deferred

- AI Study Companion.
- Analytics and sales reporting.
- Email notifications and email receipts were previously deferred, but Gmail reservation reminders are now in the agreed reservation requirements below. Order email receipts remain deferred unless later approved.

## Confirmed reservation and table rules

### Time and booking limits

- Timezone: `Asia/Manila` (PHT, UTC+8). Use the IANA timezone name in specifications; avoid the ambiguous abbreviation PST.
- Opening hours: 05:00 to 00:00 (midnight), every day. Customer reservations and table sessions must end by 23:30, leaving 30 minutes for cleaning before closing.
- Store reservation starts and ends as timezone-aware timestamps interpreted and displayed in `Asia/Manila`. Use minute precision with seconds set to zero; the end must be later than the start.
- No minimum booking duration; customers choose their duration, within operating hours.
- Bookings may start at least 30 minutes from the current time and no later than the same time 14 days later, measured in `Asia/Manila`. Use minute precision with seconds zero; the end must be later than the start.
- No overnight reservations. Start and end must fall within operating hours.

### Statuses and booking flow

- Remove `Pending`: new available bookings become `Confirmed` immediately after a collision-safe database operation.
- Current agreed reservation statuses: `Confirmed`, `Adjustment_Pending`, `Occupied`, `Cancelled`, and `Completed`.
- A registered customer may have at most one active reservation. Active statuses are `Confirmed`, `Adjustment_Pending`, and `Occupied`; walk-in sessions do not count toward this limit.
- Cashier check-in changes a reservation from `Confirmed` to `Occupied`.
- `Occupied` is a reservation status. Table operational status remains `Available` or `Maintenance`; live occupancy must be distinguished from operational status.
- Customers may cancel before the session starts without penalty. Customers cannot directly edit a confirmed booking's time or assigned table.
- Customers may cancel at any time while a reservation is `Adjustment_Pending`, without penalty. Release the hold on the original assignment immediately; the proposed replacement assignment is not held and remains available to other customers.
- Store Manager adjustments as separate proposals with `proposed_table_id`, `proposed_start_time`, proposed end/duration, reason, status, and timestamps. Preserve the original reservation assignment while the proposal is pending. On acceptance, recheck and atomically apply the proposal. Rejection or expiration leaves the original assignment unchanged; rejection returns the reservation to `Confirmed`, while cancellation remains a separate penalty-free action. Proposal statuses are `Pending`, `Accepted`, `Rejected`, and `Expired`; unanswered proposals expire at reservation start before the automatic seat-reassignment fallback.
- While an adjustment is pending, show the original and proposed assignments to the customer and staff, clearly labeled. Mark the proposed assignment as not reserved. Assign it only after the customer accepts and the system rechecks availability and conflicts.
- If another customer books the proposed table/time before acceptance, keep `Adjustment_Pending`. The Manager explains that the proposed option was taken and may offer an earlier or later time for customer acceptance. If the customer wants the originally booked time, look for another suitable operational table available for that same time. Do not change the customer's time without acceptance. If no suitable option is available, alert the Manager and allow the customer to cancel without penalty.
- If the customer does not respond to a Manager's adjustment proposal by session start, automatically assign a suitable available seat when possible after rechecking availability, set the reservation to `Confirmed`, and flag it so staff confirm at check-in that the customer knows the new seat. If no suitable seat is available, keep `Adjustment_Pending` and show an urgent alert on the Manager counter dashboard.
- Customer chooses “I’m leaving now” to notify staff. The on-duty Manager confirms the nook is empty and marks the reservation `Completed`.
- A system prompt appears 15 minutes before the current session end. The Cashier reminds the customer 5 minutes before the end. At end time, alert the Cashier or Manager. If the customer remains physically present, keep the nook treated as occupied until staff resolve it.

### Collision safety

- Reservation overlap on the same table/date exists when `existing.start_time < requested.end_time AND existing.end_time > requested.start_time`. Adjacent reservations are allowed.
- A simple count-before-insert is not sufficient for simultaneous requests. The implementation specification must require a transaction/concurrency control strategy so two simultaneous requests cannot both confirm the same slot.
- Each extension adds one hour and may be repeated if each added hour is clear and ends no later than 23:30.
- Extension requests open at the 15-minute-before-end prompt and are accepted through the current scheduled end. Cumulative complimentary extension time is 30 minutes without a qualifying drink order or 90 minutes with at least one drink item in a fully settled, non-voided, non-refunded order linked to the reservation. Check eligibility at each request.
- Charge only extension time beyond the remaining complimentary allowance, in prorated 30-minute blocks: ₱12.50 Solo Study Nook, ₱25 Duo Nook, and ₱50 Group Study Desk per block. Extension fees are separate from minimum spend and must be shown before acceptance.
- Extension availability must check any reservation overlapping `[current_end_time, proposed_end_time)`, using `existing.start_time < proposed_end_time AND existing.end_time > current_end_time`; checking only reservation start times can miss overlaps.
- Store `extension_count INTEGER NOT NULL DEFAULT 0` and increment it once after each successfully approved extension. Give each extension request a unique idempotency key to prevent duplicate processing. Recalculate total duration and the minimum-spend band after every approved extension.

### Gmail reservation notifications

- Send Gmail-addressed emails with customer choices: **Confirm attendance**, **Cancel reservation**, and **Contact the Manager**. Phone push notifications depend on the customer’s Gmail/app notification settings.
- Send the reservation action reminder 90 minutes before start, or immediately after confirmation if that time has passed. Generate unpredictable secure tokens, store only SHA-256 hashes linked to the reservation and permitted action, expire at reservation start, and allow one-time use. Invalidate tokens after use or when the reservation becomes ineligible. GET displays a confirmation page only; the action requires explicit POST and server-side token validation.
- **Confirm attendance** records the customer's response only. It does not change reservation status or count as Cashier check-in. The Manager adjustment notice is a separate notification with its own choices.
- Base no-show follow-ups and cancellation on whether the customer has checked in, even if they previously selected Confirm attendance. If there is no check-in one hour after the start, send another reminder. If after another hour the customer is still not checked in and booked time remains, mark the reservation `Cancelled` with reason `No response/no check-in` and release the table.
- If an unoccupied reservation reaches its scheduled end before that two-hour follow-up period finishes, make the nook available for the next reservation at its scheduled end and mark the reservation `Cancelled` with reason `No response/no check-in`.
- A reservation that was never checked in must not be marked `Completed`. No separate `No_Show` status is wanted.
- For an already checked-in/`Occupied` reservation, physical occupancy still requires staff confirmation before marking `Completed`.

### Table inventory

- Table identifiers use `TBL-XX` (for example, `TBL-01`).
- Solo Study Nook: capacity 1; power outlet, desk lamp, quiet zone.
- Duo Nook: capacity 2; dual outlets, window view.
- Group Study Desk: each desk has an exact capacity from 4 to 6 people; power strip, whiteboard access. Parties smaller than 4 may use a Group Study Desk when available, as long as the party size does not exceed that desk's capacity. Prefer a smaller suitable table when available; there is no minimum party size for a Group Study Desk.
- Operational table statuses: `Available`, `Maintenance`.

### Manager adjustments and chat

- Managers may propose changes up until the reservation start time; each proposal requires a reason.
- Customer choices: accept proposed change, open one-to-one chat/request a seat change, reject the proposal while keeping the original booking, or cancel the reservation.
- A proposed replacement table is not held while the customer is deciding and may be booked by someone else. If that happens, the Manager explains the conflict and offers an earlier/later time for acceptance or searches for another suitable table at the customer's original time. The customer must accept any time change.
- If the customer has not responded by the reservation start, automatically assign a vacant seat that fits the party, preferring one with the same features as the original, after rechecking availability. Set the reservation to `Confirmed`, notify the customer, and flag it for staff to confirm at check-in that the customer knows the new seat. If no suitable seat is available, keep `Adjustment_Pending` and show an urgent alert on the Manager counter dashboard.
- Customers may request a move to a preferred vacant seat. The Cashier or on-duty Manager must check that the seat is suitable, operational, and has no overlapping reservation for the rest of the customer's stay before updating the assignment.
- Chat is linked by `reservation_id` and is planned for `shioricafe_schema.reservation_chat`.

## Confirmed payment and order decisions

- Cash and digital payments are in Release 1.
- Use PayMongo test mode for the group-project demo, with QR Ph as the primary digital method. Do not process live payments or incur live transaction fees. The sandbox simulates payments without moving real money. For QR Ph, use the provider's test simulation URL; do not scan the generated QR with a real wallet or bank app.
- Reservations are not prepaid; customers pay at the café.
- Minimum spend is one total per reservation/table, not per person. Use these duration bands and amounts:

  | Reserved table | Up to 2 hours | Over 2 to 4 hours | Over 4 hours |
  |---|---:|---:|---:|
  | Solo Study Nook | ₱50 | ₱100 | ₱150 |
  | Duo Nook | ₱100 | ₱150 | ₱200 |
  | Group Study Desk | ₱200 | ₱300 | ₱400 |

  **Canonical financial definitions:**

  - `Gross Order Amount`: sum of quantity multiplied by each item's purchase-time unit price, before discounts and refunds.
  - `Net Purchase Amount`: Gross Order Amount minus discounts allocated to the order's lines and confirmed purchase refunds allocated to the order or its lines. Payment tender does not reduce this amount.
  - `Net Paid Amount`: successful collected/captured payment tender, including redeemed store credit, minus confirmed refunds and confirmed payment reversals.
  - `Eligible Net Purchases`: sum of Net Purchase Amount for orders that are not voided and not fully refunded, and whose Net Paid Amount fully settles their Net Purchase Amount. Unpaid, pending, failed, abandoned, and partially settled orders contribute zero. A reversal makes an order ineligible if the remaining successful tender no longer covers its Net Purchase Amount.
  - `Drink Subtotal`: sum of eligible net amounts for lines classified as drinks at purchase time, after discounts and confirmed purchase refunds allocated to those lines. Total purchases and Drink Subtotal use the same adjustments; non-drink line adjustments affect total purchases only.
  - `Partial refund`: a confirmed refund of less than the order's remaining Net Purchase Amount. The order retains its eligible Net Purchase Amount after that refund's confirmed line allocation; refunded value is excluded. The Drink Subtotal falls only by the portion allocated to drink lines.
  - `Full refund`: confirmed refunds equal the order's remaining Net Purchase Amount. The order contributes zero to Eligible Net Purchases and Drink Subtotal.
  - `Payment reversal`: a confirmed reversal reduces Net Paid Amount. It does not independently change item line values or Drink Subtotal; exclude the entire order if remaining successful tender no longer fully settles Net Purchase Amount.
  - `Purchase-time snapshots`: each order line preserves its unit price and drink classification at purchase, or references immutable versioned menu-item/category records with those values. Later menu price or category changes never recalculate historical orders.

  **Adjustment treatment (canonical):**

  - Allocate order-wide discounts proportionally across pre-discount line values with deterministic rounding. Allocate itemized refunds to affected items and unitemized partial refunds proportionally across remaining net line values, including drink and non-drink lines.
  - A credit against other order items is a discount allocated to the lines it benefits. Confirmed purchase refunds reduce Net Purchase Amount by allocated value; pending, failed, or cancelled refunds have no effect.
  - Payment tender settles an amount owed and does not reduce Net Purchase Amount. Only successful collected/captured tender contributes to Net Paid Amount.
  - Direct minimum-spend credits reduce payable shortfall only. They do not change Eligible Net Purchases or Drink Subtotal and cannot also be recorded as a stockout waiver.
  - Issued store credit is a separate balance, not a purchase or minimum-spend credit. Redeemed store credit is tender toward the order it settles; eligible purchase value remains the net value of purchased items.
  - Record each amount once; do not double-count an amount as discount, purchase refund, payment tender, direct minimum-spend credit, or stockout waiver.

  **Refund/reversal control:** keep refunds and payment reversals separate. Apply each provider-confirmed event once. Their combined confirmed effect per payment transaction cannot exceed the original successful payment amount; never record one provider event as both a refund and a reversal. Partial settlements contribute zero until fully settled. Exclude an affected order from both aggregates if an unresolved adjustment prevents accurate calculation.

  Evaluate minimum spend once per reservation using those definitions. If Drink Subtotal reaches at least 50% of the table minimum, the ordinary shortfall is waived. For a verified stockout resolution, compare drink subtotal immediately before and after applying it; only a transition from at least 50% to below 50% caused by that event triggers waiver consideration. Apply approved Option B: waive only the additional regular shortfall attributable to the event, capped by current payable shortfall and net value removed. Record unique event keys and before/after snapshots; serialize stockout events per reservation, respect prior waivers, and make retries idempotent. A price reduction cannot also be counted as a waiver.
- Before the customer confirms a reservation, show a prominent notice on the final review step with the exact minimum spend and the 50% drink-waiver and shortfall rules for that booking. Repeat the rule in the reservation confirmation.
- A failed, cancelled, or expired digital payment remains unpaid and can be retried or paid in cash. Mark a payment paid only after server-side provider confirmation, not from the browser return page alone.
- Show an itemized on-screen receipt after payment, separating gross item amount, discounts, confirmed refunds, net purchase amount, successful payments, net paid amount, and reservation minimum-spend shortfall. Calculate base shortfall from eligible net purchases, then subtract separately recorded approved minimum-spend credits and prior waivers. Do not email order receipts in Release 1.
- Cashiers may collect cash, record it as paid after collection, and show the receipt. Cashiers cannot issue refunds or override a digital payment status.
- Refunds require Manager authorization and a recorded reason. In Release 1, only a PayMongo-confirmed digital payment can be refunded through its original method; show it as refunded only after provider confirmation. Cash refunds are out of scope.
- If a cash-paid item becomes unavailable after payment and the customer declines all substitutes, the Manager manually credits the amount against other ordered items or issues store credit. For a lower-cost substitute, record its actual purchase-time price/category and apply the difference as a credit or confirmed refund exactly once. Follow the approved item-credit and store-credit treatment above.
- For cash-at-table orders, customer selects cash; Cashier marks paid after collecting it.
- Walk-in customers can order through a Cashier without a customer account.
- For every walk-in transaction, the system creates a unique generic reservation/session with `customer_id = NULL` and `is_walkin = true`. Every order links to that session by `reservation_id`. Dine-in requires `table_id`; takeaway requires `table_id = NULL`. For dine-in, ask how long the customer expects to stay, record the expected end time, and assign a suitable available table. Do not assign a table that is under maintenance, already occupied, or reserved during the expected stay. Manager or Cashier can update the occupied time slot when the estimate changes after checking for conflicts. Walk-in sessions must end by 23:30 to leave 30 minutes for cleaning before closing. Walk-in dine-in customers pay for items ordered and do not pay a reservation or table minimum.
- Multi-item orders must be saved atomically. Preserve purchase-time unit price and drink classification on each order item (or use immutable versioned menu records). Aggregate only fully settled, non-voided, non-fully-refunded orders using net purchase value after approved allocations. Unpaid/partial-settlement orders contribute zero. Confirmed partial refunds reduce total and affected drink lines by approved allocation; payment reversals reduce settlement and exclude the order if remaining successful payments no longer cover its net purchase amount. Support multiple successful payments/refunds with idempotent provider events.
- Order lifecycle scoped as `Pending -> Preparing -> Ready -> Served -> Completed`.

### Approved extension-fee payment recording

- **Owner-approved decision: Option A — Direct Ledger Reference.**
- Extension-fee payment attempts and successful payment transactions reference the corresponding reservation extension directly through `reservation_extension_id`.
- Do not generate synthetic dining orders or special extension-fee menu items.
- Each payment attempt and successful payment transaction must reference exactly one payable source: either `order_id` or `reservation_extension_id`, never both and never neither.
- Refunds and reversals reference the original payment transaction so extension-fee adjustments remain traceable.
- Extension fees are operational charges. They do not count toward dining sales, Eligible Net Purchases, Drink Subtotal, or reservation minimum-spend calculations.

## Open decisions and remaining Phase 1 work

1. Finish any remaining reservation edge cases beyond the now-agreed cancellation and automatic seat-reassignment rules.
2. Complete and review `docs/DATABASE_SPEC.md`, including sandbox payment, walk-in session, stockout-waiver, reminder-token, extension, and Manager-proposal records. The owner has approved Option A for direct extension-fee payment references; the complete supporting schema still requires review and approval.
3. Financial allocation and store-credit treatment are approved; finish schema representation and reconciliation constraints in `docs/DATABASE_SPEC.md`.
4. Draft guest/customer/Cashier/Manager flows, including errors and alternate paths.
5. Draft architecture and reservation, order, adjustment/chat, and extension flowcharts.
6. Cross-check the proposed supporting entities against the original eight-table schema and approved Release 1 features. Confirm which supporting entities and relationships require schema additions, and obtain approval before implementation.
7. Draft endpoint/page contracts, TDD test matrix, acceptance criteria, and prioritized CLINE implementation backlog.
8. Verify the handoff, roadmap, project plan, and `AGENTS.md` agree before implementation.
9. Check the selected hosting and reminder setup, including PHP compatibility and protection from duplicate or unauthorized email sends.

## Existing implementation mismatch and repository state

- The scratch PHP app currently uses `mysqli`/MySQL and existing SQL scripts use MySQL, while the target requires PDO/PostgreSQL. Treat this as a future implementation migration/specification issue.
- Existing code has path/schema inconsistencies and is not yet a verified implementation of these decisions.
- Earlier Git inspection showed tracked legacy files deleted and the current PHP tree/docs untracked on `main` at `df9ea81` (`Prepare website for Vercel`), matching `origin/main`. Reconcile this state intentionally before committing or deploying.
