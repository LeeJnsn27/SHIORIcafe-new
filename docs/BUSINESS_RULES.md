# SHIORI Café — Business Rules and Operational Specifications

Status: Consolidated for owner review
Last updated: 2026-11-04

## Purpose and document roles

This document defines Release 1 café policies and expected system behavior. `docs/PHASE1_HANDOFF.md` records the approved scope; this document expands those decisions for implementation planning. `docs/USER_FLOWS.md` will describe how each role follows these rules.

Project stack, hosting, PostgreSQL locking, SQL syntax, endpoint paths, authentication helper names, and provider webhook verification belong in `AGENTS.md`, `docs/PROJECT_PLAN.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE_SPEC.md`, or `docs/API_CONTRACTS.md`. No application code or database files are changed by this document.

## 1. Operating time and booking limits

- Use `Asia/Manila` for café hours, booking rules, reminders, and customer-facing times. Do not use the ambiguous abbreviation PST.
- The café operates daily from 05:00 until midnight. Customer reservations and table sessions must end by 23:30 to leave 30 minutes for cleaning before closing. Reservations must start and end within the same operating day. No overnight reservations are allowed.
- Customers may book a start time from 30 minutes after the current time through the same time 14 days later, measured in `Asia/Manila`.
- Reservation start/end values use full timestamps interpreted in `Asia/Manila`, with minute precision and seconds set to zero. Store timestamps as timezone-aware instants; display and validate them in `Asia/Manila`.
- Customers choose the reservation duration. No business minimum duration is imposed; the end must be later than the start, making one minute the shortest representable booking.
- Store reservation start/end values as timezone-aware timestamps. Validate and display them using `Asia/Manila`; reservation times use minute precision with seconds set to zero.
- The exact reservation date, start time, and end time determine availability. Adjacent reservations are allowed; overlapping reservations on the same table are not.

## 2. Tables, capacity, and minimum spend

### Table inventory

- Table identifiers use `TBL-XX`, such as `TBL-01`.
- A table's operational status is `Available` or `Maintenance`. `Available` means it is fit for use; it does not mean it has no booking or current occupant.
- Live occupancy is represented separately by an `Occupied` reservation/session.
- Solo Study Nooks seat one person and have a power outlet, desk lamp, and quiet zone.
- Duo Nooks seat two people and have dual outlets and a window view.
- Each Group Study Desk has a recorded capacity from four to six people and has a power strip and whiteboard access. Smaller parties may use one if available and within its recorded capacity. Prefer a smaller suitable table when available; there is no minimum party size for a Group Study Desk.
- A table under maintenance, occupied during the requested interval, or reserved during that interval cannot be assigned.

### Minimum spend

Minimum spend is one amount per reservation/table, not per person. Evaluate it once using the following canonical definitions:

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

For reservation calculation, `Base Shortfall = max(0, Required Minimum Spend - Eligible Net Purchases)`. If Drink Subtotal is at least 50% of the applicable table minimum, waive the ordinary shortfall and collect ₱0. Otherwise, payable shortfall is `max(0, Base Shortfall - Approved Direct Minimum-Spend Credits - Previously Applied Waivers)`. Direct minimum-spend credits reduce shortfall only; they are not purchases or drink subtotal. Do not allow negative line, order, or reservation aggregates.

| Order/payment condition | Reservation total purchases | Drink subtotal |
|---|---:|---:|
| Fully paid, not voided, and not fully refunded | Net Purchase Amount after allocated discounts and confirmed refunds | Drink Subtotal after allocated discounts and confirmed refunds |
| Unpaid, pending, failed, abandoned, or partially settled | ₱0 until fully settled | ₱0 until fully settled |
| Voided | ₱0 | ₱0 |
| Fully refunded | ₱0 | ₱0 |
| Partially refunded | Retained eligible Net Purchase Amount after confirmed refund allocation | Retained eligible drink-line amount after confirmed refund allocation |
| Payment reversed | Recalculate net settled amount; if successful remaining payments no longer cover net purchase amount, the order contributes ₱0 until fully settled again | Same eligibility rule; do not independently change drink-line value for a payment reversal |

**Adjustment treatment (canonical):**

- Allocate order-wide discounts proportionally across pre-discount line values. Use deterministic currency rounding so line allocations equal the discount total.
- Allocate an itemized refund to the affected item. Allocate an unitemized partial refund proportionally across remaining net line values, including drink and non-drink items. Use deterministic rounding so allocations equal the confirmed refund.
- A credit applied to other order items is a discount allocated to the lines it benefits. Do not also record it as tender, refund, or minimum-spend credit.
- Confirmed purchase refunds reduce Net Purchase Amount by the refunded value allocated to affected order/item lines. Pending, failed, or cancelled refunds have no effect.
- Payment tender settles an amount owed and does not reduce Net Purchase Amount. Only successful collected/captured tender contributes to Net Paid Amount.
- Direct minimum-spend credits reduce payable shortfall only. They do not change Eligible Net Purchases or Drink Subtotal and cannot also be recorded as a stockout waiver.
- Issued store credit is a separate balance, not a purchase or minimum-spend credit. Redeemed store credit is tender toward the order it settles; eligible purchase value remains the net value of purchased items.
- Record each amount once; do not double-count an amount as discount, purchase refund, payment tender, direct minimum-spend credit, or stockout waiver.

**Refund/reversal control:** keep refunds and payment reversals as separate records. Apply each provider-confirmed event once. Their combined confirmed effect per payment transaction cannot exceed the original successful payment amount. Never record one provider event as both a refund and a reversal. Partial settlements still contribute zero until fully settled. Preserve independently verifiable values and exclude the entire affected order from both aggregates if an unresolved adjustment prevents either aggregate from being accurate.

| Table type       | Up to 2 hours | Over 2 to 4 hours | Over 4 hours |
| ---------------- | -------------: | -----------------: | ------------: |
| Solo Study Nook  | ₱50           | ₱100              | ₱150         |
| Duo Nook         | ₱100          | ₱150              | ₱200         |
| Group Study Desk | ₱200          | ₱300              | ₱400         |

- The duration bands include exactly 2 hours in the first band and exactly 4 hours in the second band.
- Show the exact amount and explain the 50% drink-waiver and shortfall rule on the final reservation review before confirmation. Repeat the rule in the confirmation. Food purchases count toward eligible net purchases but do not count toward drink subtotals.
- If an extension moves the total booked duration into a higher band, update the required minimum. Before the customer accepts the extension, show the new minimum and any additional shortfall. Collect any remaining shortfall at checkout.
- If a Manager proposes a time change that changes the duration band, show the updated minimum before the customer accepts the change.

## 3. Reservation statuses and transitions

The Release 1 reservation statuses are `Confirmed`, `Adjustment_Pending`, `Occupied`, `Cancelled`, and `Completed`. Do not add a generic `Pending` or `No_Show` status.

A registered customer may have at most one active reservation at a time. Active statuses are `Confirmed`, `Adjustment_Pending`, and `Occupied`. Walk-in sessions do not count toward this limit.

| Current status | Event | Result |
|---|---|---|
| New | Customer completes a valid, conflict-free booking | `Confirmed` |
| `Confirmed` | Manager submits a change proposal with a reason | `Adjustment_Pending` |
| `Adjustment_Pending` | Customer accepts a proposal whose proposed table/time is still available | Recheck and assign the proposed table/time, release the original assignment hold, and return to `Confirmed` |
| `Adjustment_Pending` | Customer accepts a proposal whose proposed table/time was booked by someone else | Keep `Adjustment_Pending`; explain that the proposed option is no longer available and offer alternatives |
| `Adjustment_Pending` | Customer rejects the proposal | Proposal becomes `Rejected`; reservation returns to `Confirmed` with original assignment unchanged |
| `Adjustment_Pending` | Customer cancels reservation | `Cancelled`, without penalty |
| `Adjustment_Pending` | Proposal expires at reservation start | Proposal becomes `Expired`; original assignment remains until automatic seat reassignment runs |
| `Adjustment_Pending` | Customer opens chat or requests a seat change | Remain `Adjustment_Pending` until resolved or the start-time fallback runs |
| `Adjustment_Pending` | No response by start; system assigns a suitable seat | Update the seat, return to `Confirmed`, and notify the customer; flag the reservation for staff to confirm the customer knows the new seat at check-in |
| `Adjustment_Pending` | No suitable seat can be assigned by start | Remain `Adjustment_Pending`; show an urgent alert on the Manager counter dashboard |
| `Confirmed` | Authorized staff checks in the arriving customer | `Occupied` |
| `Occupied` | Customer signals departure and the on-duty Manager confirms the table is empty | `Completed` |
| Active booking before start | Customer cancels, including while an adjustment is pending | `Cancelled`, without a cancellation penalty |
| Not checked in | No-show timeout is reached | `Cancelled` with reason `No response/no check-in` |

- Customer acceptance applies only after the proposed table/time passes the operating-hours, capacity, and conflict checks. Any proposed new start must still satisfy the 30-minute notice and 14-day booking window.
- A customer may cancel at any time while the reservation is `Adjustment_Pending`, without penalty. Cancellation immediately releases the hold on the original assignment.
- While an adjustment is pending, show the customer and staff the original assignment and the proposed assignment, clearly labeled. The original assignment remains held. The proposed replacement table/time stays available to other customers until the customer accepts it; clearly label the proposal as not reserved.
- When the customer accepts, recheck and assign the proposed table/time only if it is still available and conflict-free. If another customer has booked it, keep the reservation `Adjustment_Pending` and have the Manager explain that the proposed option was taken. The Manager may offer a different time earlier or later, subject to the customer's acceptance and normal booking validation. If the customer wants to keep the originally booked time, look for another suitable operational table available for that same time. Do not change the customer's time without acceptance. If no suitable option is available, alert the Manager and let the customer cancel without penalty.
- When a reservation was automatically reassigned, show the customer the new table at check-in and show staff a notice to confirm the customer has been informed. Clear the staff notice after staff acknowledge it.
- When staff resolve an unassigned adjustment for a customer who is already present, assign a suitable conflict-free operational table and check the customer in as `Occupied`. If staff assign a table before arrival, set the reservation to `Confirmed` until check-in.
- If no suitable seat is available at arrival, the customer may cancel without penalty.
- Customers cannot directly edit the time or table on a confirmed reservation. They may request a table change through the Manager/chat process.

## 4. Booking conflicts and no-show handling

- Two reservations conflict when they use the same table on the same date and their time intervals overlap. An interval overlaps when the existing start is before the requested end and the existing end is after the requested start. A reservation ending exactly when another begins does not overlap.
- Cancelled and completed reservations do not block availability. Confirmed and occupied sessions block their assigned intervals. An `Adjustment_Pending` reservation continues to hold its original assignment, while an unaccepted proposed replacement assignment remains available and does not block availability.
- The system must prevent two simultaneous requests from confirming the same table/time. The database specification will define the concurrency-safe PostgreSQL method.
- If a customer has not checked in one hour after the reservation start, send a follow-up email, even if they previously confirmed attendance.
- If the customer remains unchecked-in after another hour and booked time remains, cancel with reason `No response/no check-in` and release the table.
- If the scheduled end arrives first, cancel with the same reason and release the table at the scheduled end. A never-checked-in reservation is never marked `Completed`.
- “Confirm Attendance” records the customer's response only. It does not change reservation status or count as a Cashier check-in.
- An `Occupied` session keeps the table unavailable until staff confirm it is physically empty and complete the session, even if its scheduled end has passed.
- If the no-show or adjustment process cannot run on time, do not assign the table based solely on a missing customer response. Staff must verify the table is unoccupied before treating it as free.

## 5. Reminders, extensions, and departure

### Customer reminders

- Send a reservation reminder email 90 minutes before the start. If that time has already passed when the reservation is confirmed, send it immediately. Confirmation and cancellation links use action tokens associated with the reservation and permitted action. Generate cryptographically secure, unpredictable tokens; store only each token's SHA-256 hash. Tokens expire at the reservation start, are one-time use, and are invalidated after successful use or when the reservation becomes ineligible. Issuing a replacement token invalidates the previous unused token for the same reservation and action.
- Opening an action link with GET must only display a confirmation page. It must never directly confirm or cancel a reservation. Require an explicit POST; validate the token server-side, verify its hash, reservation, action, expiration, unused state, and current reservation eligibility, then consume it atomically with the requested action. Use HTTPS, avoid logging raw tokens, and do not expose tokens in referrer data.
- The reminder offers **Confirm Attendance**, **Cancel Reservation**, and **Contact Manager**.
- Gmail's phone push behavior depends on the customer's device and email settings.
- Manager change proposals are separate notifications. They offer **Accept Proposed Change**, **Communicate with Manager / Request Seat Change**, **Reject Proposal**, and **Cancel Reservation**. Rejection preserves the original booking; cancellation cancels it. Every proposal requires an explanation and can be made only before the reservation starts.
- A Manager proposal does not reuse the reminder email's three choices. Display the change proposal in the customer's dashboard; send any additional channel notification only as specified in the approved notification design.

### Extensions and departure

- Fifteen minutes before the current session end, show the customer an in-app prompt to leave or request a one-hour extension. Requests may be submitted from that prompt through the current scheduled end.
- Complimentary extension time is cumulative beyond the original reservation end: up to 30 minutes without a qualifying drink order, or up to 90 minutes when at least one drink item belongs to a fully settled, non-voided, non-refunded order linked to the reservation. Check drink eligibility when each extension is requested. A later qualifying drink can enable the 90-minute allowance for future requests, but does not refund fees already paid.
- Charge only for extension time beyond the remaining complimentary allowance. Calculate in 30-minute blocks and prorate a request that crosses the free-time limit so only the overage is charged. Per-block rates are ₱12.50 for a Solo Study Nook, ₱25 for a Duo Nook, and ₱50 for a Group Study Desk. Extension fees are separate from minimum spend. Show the fee and obtain customer acceptance before applying paid time.
- Store `extension_count INTEGER NOT NULL DEFAULT 0` on each reservation. Give each extension request a unique idempotency key so duplicate submissions cannot extend the reservation twice. Approve an extension only if the added interval is conflict-free on the assigned table and ends by 23:30. On successful approval, atomically extend the reservation, increment `extension_count` once, recalculate total duration and its minimum-spend band, and show any revised minimum/shortfall and extension fee before acceptance. Extensions may be repeated until a conflict or the 23:30 cutoff prevents another hour.
- If the current table conflicts with the requested hour, do not extend the customer on that table. Show suitable available table suggestions. A suggested table is not assigned until the customer requests the move and authorized staff verify capacity, operational status, and no conflict for the remaining stay.
- Five minutes before the session end, alert the Cashier to remind the customer. At the scheduled end, alert the Cashier or Manager.
- The customer may select **I'm leaving now** to notify staff. The on-duty Manager must physically confirm the nook is empty before marking the reservation `Completed`.
- If the customer remains in the nook at the scheduled end, keep it treated as occupied until staff resolve the situation. Do not mark it `Completed` or assign the same physical seat to an arriving customer while it remains occupied.
- Record extension-fee payments directly against the corresponding reservation extension using `reservation_extension_id`. Do not create a synthetic dining order or extension-fee menu item. Each payment must reference exactly one payable source: either `order_id` or `reservation_extension_id`, never both or neither. Extension fees must not count toward dining sales, eligible purchases, drink subtotals, or minimum-spend calculations. Refunds and reversals must remain traceable to the original payment transaction.

## 6. Manager adjustments and reservation chat

- A Manager may propose a table or time change before the reservation start. Every proposal requires an explanation and is stored with `proposed_table_id`, `proposed_start_time`, proposed end/duration, status, and timestamps. While pending, keep the proposal separate from the reservation's assigned table and time; preserve the original reservation values.
- The proposal displays the proposed change and its effect on the minimum spend, if any.
- Customer choices are:
  1. **Accept Proposed Change:** apply the validated change and return the reservation to `Confirmed`.
  2. **Communicate with Manager / Request Seat Change:** open one-to-one chat linked to the reservation. Keep the reservation `Adjustment_Pending` until the request is resolved or the start-time fallback applies.
  3. **Reject Proposal:** mark the proposal `Rejected`; leave the original reservation table/time unchanged and return the reservation to `Confirmed`.
  4. **Cancel Reservation:** cancel without penalty, including while `Adjustment_Pending`.
- Proposal statuses are `Pending`, `Accepted`, `Rejected`, and `Expired`, with created, expiry, and resolution timestamps. Rejection or expiry never applies proposed values. Expire unanswered proposals at reservation start, then run the agreed automatic seat-reassignment fallback.
- If the customer has not responded by the start time, assign a suitable vacant operational table that fits the party, preferring one with the same amenities, after rechecking availability. Set the reservation to `Confirmed`, notify the customer of the new seat, and flag it for staff to confirm the customer knows the new seat at check-in. If none is available, keep the reservation `Adjustment_Pending` and show an urgent alert on the Manager counter dashboard.
- At arrival, the Cashier or on-duty Manager may resolve the seat assignment after verifying table suitability, operational status, and no conflict for the rest of the stay.
- Customer-requested table moves use the same checks. Chat is one-to-one and linked to the reservation. Schema details, message retention, and authorization are defined in the database/API specifications.

## 7. Menu, orders, and walk-ins

### Menu and orders

- Managers manage menu categories, descriptions, prices, and stock status (`In Stock` or `Out of Stock`).
- The order preparation lifecycle is `Pending` → `Preparing` → `Ready` → `Served` → `Completed`.
- Calculate new order prices from server-side menu data at purchase time. Ignore client-submitted prices. Preserve each line's purchase-time unit price and drink classification so later menu price/category changes cannot alter historical orders.
- Save an order and all its items as one transaction. Keep payment status separate from order preparation status.
- Cashiers manage the order queue and advance order statuses. Managers may do so when covering cashier operations.
- If an item in a digitally paid order is unavailable, the Manager asks the customer before substituting it. Record the actual substitute and its purchase-time price/category; the order's Net Purchase Amount and Drink Subtotal use the substitute, not the unavailable item's original value. Allocate an itemized refund to its item and an unitemized partial refund proportionally across remaining net line values. A credit against other ordered items is a discount allocated to the lines it benefits. Record the reason and update refunded status only after provider confirmation. If the customer declines or no suitable substitute is available, the Manager may request a partial refund through the original PayMongo method.
- **Stockout drink-waiver trigger:** Compare the reservation's aggregated drink subtotal immediately before and after applying a verified stockout, including its accepted substitute or confirmed refund adjustment. A stockout qualifies only when the before amount is at least 50% of the required minimum and the after amount is below 50%, and the affected stockout caused that crossing. If the subtotal was already below 50% before this event, this event does not trigger a waiver. Recalculate using fully settled, non-voided reservation orders and purchase-time item snapshots; refunds and substitutions affect aggregates according to their approved line allocation and final recorded state.
- **Approved stockout waiver amount — Option B:** For each qualifying crossing, calculate regular shortfall as `max(0, Required Minimum Spend - Eligible Net Purchases)`, before any drink waiver. The event-attributable increase is `max(0, regular_shortfall_after - regular_shortfall_before)`. Waive `min(event_attributable_increase, current_payable_shortfall, net_value_removed_from_eligible_paid_purchases)`. This waives only the additional shortfall attributable to the stockout; pre-existing or otherwise unrelated deficiencies remain payable. Option A, which would waive the entire remaining payable shortfall, was rejected because it could erase unrelated deficiencies. Example: minimum ₱100; before, eligible net purchases total ₱80 and drinks total ₱50; a verified stockout removes ₱10 of drinks, leaving eligible net purchases ₱70 and drinks ₱40. Regular shortfall rises from ₱20 to ₱30, so Option B waives ₱10 and leaves the pre-existing ₱20 deficiency payable.
- Apply one outcome per unique verified stockout event. Process multiple stockouts sequentially and atomically per reservation, comparing each event against the aggregate immediately before and after it. Store a unique source-event key and pre/post snapshots; retries return the existing outcome. Previous waivers reduce the current payable shortfall and cannot be issued again. A later stockout that does not itself cross the threshold does not qualify. Treat multiple items resolved together as one event with one before/after comparison.
- An accepted substitute contributes its actual purchase-time price and drink-category snapshot; compare the net change, not the original item's full price. A refund changes aggregates only after provider confirmation and according to its approved item allocation. Never count the same removal both as a purchase reduction/refund and as a stockout waiver.
- Cash refunds are out of scope. The Cashier must verify item availability before collecting cash. If a cash-paid item becomes unavailable afterward and the customer declines all substitutes, the Manager manually credits the amount against other ordered items or issues store credit.
- For a lower-cost substitute in a cash-paid order, the Manager manually credits the price difference toward other ordered items, any remaining minimum-spend shortfall, or store credit. Apply credits against ordered items as discounts and record direct minimum-spend credits separately. Issued store credit is a separate balance; redeemed store credit counts as tender for the order it settles but does not change the purchased items' net value. Do not issue a cash refund.

### Walk-in orders and table occupancy

- Walk-in customers do not need an account.
- Represent each walk-in transaction, including takeaway, with its own unique generic walk-in reservation session using `customer_id = NULL` and `is_walkin = true`. Link every order in that transaction to that session's `reservation_id` in `shioricafe_schema.order`. Dine-in requires `table_id`; takeaway requires `table_id = NULL`. Walk-in sessions have no table minimum or reservation fee. Online reservations retain their existing customer and reservation relationships.
- A dine-in walk-in session is `Occupied` while it uses a table. A tableless takeaway session is `Completed` when its transaction ends.
- The Cashier marks the order **Takeaway** or **Dine-In**. Takeaway has no table assignment.
- For dine-in, the Cashier asks how long the customer expects to stay, records the duration and expected end time, and assigns a suitable operational table that is not occupied or reserved during that interval.
- Record dine-in occupancy with an `Occupied` session through the expected end time so it blocks overlapping online reservations. Manager or Cashier can update the occupied time slot when the estimate changes, after checking that the updated interval does not conflict with another booking. If the customer remains at the expected end, keep the table unavailable until staff confirm departure or update the occupied time slot after checking for conflicts.
- Walk-in sessions must end by 23:30 to leave 30 minutes for cleaning before closing. Walk-in dine-in customers do not pay a reservation or table minimum. They pay for the items they order.

## 8. Payments, receipts, and refunds

- Reservations are not prepaid online. Orders and any minimum-spend shortfall are paid at the café.
- Cash remains available. The Cashier records a cash payment as paid only after collecting it.
- PayMongo QR Ph is used only in test mode for the group-project demo. Do not accept live transactions or ask anyone to scan the test QR with a real wallet or bank app. Mark a digital payment as paid only after server-side provider confirmation.
- A failed or abandoned digital attempt remains unpaid. The customer may retry or pay cash. Order preparation status remains separate from this payment result.
- Show an itemized on-screen receipt with items, amounts, payment method, paid status, and any reservation minimum-spend shortfall. Do not email order receipts in Release 1.
- Cashiers cannot issue refunds or override digital payment status. Managers authorize digital refunds, record a reason, and use the original payment method. A refund is recorded as complete only after PayMongo confirms it.
- Preserve gross order amount, net purchase amount, and net settled amount separately. Support multiple payment and refund records per order. Only successful collected/captured payments count as settled; pending/failed entries count as zero; confirmed refunds and payment reversals reduce settled value; duplicate provider events must not apply twice. Distinguish partial from full refunds and keep each refund linked to the payment/order and, when resolved, its affected item lines.

## 9. Role permissions

| Capability | Guest | Customer | Cashier | Manager |
|---|---:|---:|---:|---:|
| View public pages and menu | Yes | Yes | Yes | Yes |
| Register and sign in as customer | Yes | Yes | No | No |
| Sign in to staff account | No | No | Yes | Yes |
| Browse availability and create/cancel own reservations | No | Yes | No | No |
| Respond to own reminders/adjustments and use linked chat | No | Yes | No | No |
| Request own extension or signal departure | No | Yes | No | No |
| Check in arriving customers | No | No | Yes | Yes |
| Create walk-in orders and assign tables | No | No | Yes | Yes |
| Advance order preparation status | No | No | Yes | Yes |
| Propose reservation changes | No | No | No | Yes |
| Manage menu, stock, tables, and Cashier accounts | No | No | No | Yes |
| Authorize digital refunds | No | No | No | Yes |

- Customers can view and change only their own account and reservation data.
- Cashiers cannot issue refunds or override provider-confirmed digital payment state.
- Managers can create Cashier accounts and perform the operational actions listed above. The first Manager is provisioned during setup.
- Authentication guard function names and endpoint-by-endpoint authorization checks belong in `docs/API_CONTRACTS.md`.

## 10. Data-model implications for review

These rules require a schema review before implementation. They do not authorize SQL or database-file changes.

- Add a reservation-linked chat table to store messages and their reservation/sender context.
- The reservation model must allow a null `customer_id`, include `is_walkin`, and include `extension_count INTEGER NOT NULL DEFAULT 0`. Every Release 1 sale must link to an online reservation or generic walk-in session through required `reservation_id` in `shioricafe_schema.order`. The order model also has a nullable `table_id` foreign key; each walk-in transaction has a unique session, all its orders link to it, dine-in requires a table, and takeaway has no table.
- The category entity must include `is_drink_category BOOLEAN NOT NULL DEFAULT FALSE`. Each `order_item` must preserve `unit_price_at_purchase` and `is_drink_category_at_purchase` (or reference immutable, versioned menu-item/category records) so historical prices and drink classification cannot change when the menu changes.
- Persist extension requests with unique idempotency keys. Persist Manager adjustment proposals separately with proposed values, status, and timestamps so original reservation values remain unchanged until acceptance.
- Persist reminder action-token hashes, reservation/action association, expiration, and consumed/invalidated timestamps. Persist verified stockout waiver events with a unique source event so waivers are auditable and cannot be claimed twice.
- Payment method, payment status, and provider transaction reference belong with the order/payment record because reservations are not prepaid.
- Add a payment/refund ledger that permits multiple payments and refunds per order, with provider/event identifiers and idempotency.
- The financial allocation and store-credit rules above are approved. Complete their schema representation, constraints, indexes, and payment-ledger reconciliation details in `docs/DATABASE_SPEC.md` before implementation.
- Owner approval is required for schema additions before implementation.

## Owner decisions still required

1. Approve schema additions after the database specification assigns each field/table and confirms the minimum required structure.
