# SHIORI Café — Release 1 User Flows

Status: Draft for team review
Last updated: 2026-10-31

## Purpose

These flows describe how Guests, Customers, Cashiers, and Managers use Release 1 features. They follow `docs/BUSINESS_RULES.md` and the approved scope in `docs/PHASE1_HANDOFF.md`. Items that still need a decision are listed at the end rather than assumed.

## 1. Guest registration and sign-in

**Actor:** Guest or account holder.

**Preconditions:** Public pages are available. A Guest does not have to sign in to browse public pages or the menu.

1. Guest opens the café website and browses public information and the menu.
2. To reserve a table, Guest chooses customer registration or signs in to an existing customer account.
3. A new customer enters the required registration information and submits it.
4. The system validates the information and creates the customer account, or displays field errors and lets the Guest correct them.
5. Customer signs in and opens customer reservation and ordering features.

**Staff sign-in:** Cashiers and Managers sign in using staff accounts. Guests and Customers cannot use staff sign-in. Managers create Cashier accounts; the first Manager is provisioned during setup.

## 2. Customer finds and books a table

**Actor:** Signed-in Customer.

**Preconditions:** Table inventory and operational status are available to the booking service.

1. Customer selects a date, start time, duration, and party size.
2. System checks that the reservation is within 05:00–23:30, on one local operating day, no more than 14 days ahead, and starts at least 30 minutes from now.
3. System filters out tables under maintenance, over capacity, occupied, or reserved during any overlapping part of the requested interval. For a reservation on the same table and date, intervals overlap exactly when `existing.start_time < requested.end_time AND existing.end_time > requested.start_time`; adjacent intervals are allowed.
4. System shows suitable available tables and any alternatives when the requested choice is unavailable.
5. Customer selects a table. Server calculates the duration-based minimum from the table type and duration amounts in `docs/BUSINESS_RULES.md`, and shows the amount and 50% drink-waiver rule before confirmation. The browser does not calculate or supply the authoritative minimum.
6. Customer reviews and confirms. Server recalculates the minimum, performs the final conflict check, and saves the reservation as `Confirmed` only if the table is still available.
7. System shows the confirmation and repeats the minimum-spend rule.

**Alternate paths:** Invalid dates, times, duration, party size, or a newly booked table produce a clear error and return the Customer to availability selection. Reservations are not prepaid online.

## 3. Customer views or cancels a reservation

**Actor:** Signed-in Customer.

1. Customer opens their reservation list and selects a reservation.
2. System shows the current status, assigned table/time, and any pending Manager proposal.
3. Customer may cancel before the reservation starts without penalty. A Customer may also cancel any time while the status is `Adjustment_Pending`.
4. System changes the status to `Cancelled` and immediately releases the original assignment hold.

Customers may only view and change their own account and reservation information. They cannot directly edit a confirmed table or time.

## 4. Manager proposes an adjustment; Customer responds

**Actors:** Manager and Customer.

**Preconditions:** Reservation is `Confirmed`; the proposal is made before its start time.

1. Manager opens the reservation, enters a reason, and proposes a table or time change.
2. System changes the reservation to `Adjustment_Pending` and displays both assignments to the Customer and staff. The original assignment remains held. The proposed replacement remains available to other customers and is labeled **not reserved**.
3. Customer reviews the reason, proposed assignment, and any minimum-spend change. The proposal remains separate from the original reservation values. Customer may:
   - **Accept Proposed Change:** Server starts an atomic PDO transaction, locks the reservation and affected table records using `SELECT ... FOR UPDATE`, then rechecks hours, notice, booking horizon, capacity, operational status, and conflicts. If the proposal is available, server applies it, releases the original hold, and returns the reservation to `Confirmed` before committing. If validation fails, server rolls back and leaves the reservation `Adjustment_Pending`.
   - **Communicate with Manager / Request Seat Change:** System opens one-to-one chat linked to the reservation. Status remains `Adjustment_Pending` while the request is resolved or the start-time fallback runs.
   - **Reject Proposal:** Mark the proposal `Rejected`, leave the original reservation's table/time unchanged, and return the reservation to `Confirmed`.
   - **Cancel Reservation:** System changes status to `Cancelled` without penalty and releases the original hold.
4. System records each proposal with status `Pending`, `Accepted`, `Rejected`, or `Expired`, plus creation, expiration, and resolution timestamps. Rejection or expiration never applies proposed values. A pending proposal expires at reservation start; the start-time automatic reassignment flow then runs.
5. If another Customer books the proposed table/time before acceptance, system does not apply the stale proposal. Status remains `Adjustment_Pending`; Manager explains the conflict and offers an earlier or later time for Customer acceptance. If the Customer wants the originally booked time, Manager searches for another suitable operational table available for that same time.
6. System does not change the Customer's time without acceptance. If there is no suitable option, Manager is alerted and Customer may cancel without penalty.

## 5. Adjustment is unanswered at session start

**Actors:** System, Customer, Manager, and on-arrival Cashier or Manager.

1. At session start, system rechecks available operational tables for capacity, amenities, and conflicts over the reservation interval.
2. If a suitable table is available, system assigns it, changes the reservation to `Confirmed`, notifies the Customer, and flags the reservation for staff to confirm at check-in that the Customer knows the new seat.
3. At check-in, staff see the notice, tell the Customer the assigned seat, and acknowledge the notice after the Customer has been informed. Staff check the Customer in and set the status to `Occupied`.
4. If no suitable table is available, reservation remains `Adjustment_Pending` and an urgent alert appears on the Manager counter dashboard. The alert shows that the Customer has arrived, the original and proposed assignments, party size, required features, and the time the reservation started.
5. Manager checks operational tables and presents any suitable, conflict-free alternatives to the Customer. A table or time change requires Customer agreement. If the Customer is present, staff assign the accepted table and then check the Customer in as `Occupied`; if the Customer has not arrived, staff assign the accepted table and leave the reservation `Confirmed` until check-in.
6. If no suitable seat or accepted alternative is available, keep the reservation `Adjustment_Pending`, do not check the Customer in or mark it `Occupied`, and keep the urgent Manager alert active. Manager explains the situation and the Customer may wait while staff continue resolving it or cancel without penalty. Never displace another booking or assign a conflicting table to clear the alert.

## 6. Reminders, check-in, and no-show handling

**Actors:** Customer, system, and Cashier or Manager.

1. System sends a reminder email 90 minutes before the start, or immediately after confirmation if that time has passed, with **Confirm Attendance**, **Cancel Reservation**, and **Contact Manager** actions. Confirmation and cancellation each use a cryptographically secure, unpredictable action token; the database stores only its SHA-256 hash, linked to the reservation and permitted action.
2. Tokens expire at reservation start and are single-use. Successful use or a reservation becoming ineligible invalidates the token; issuing a replacement invalidates the earlier unused token for the same action. The server validates the token hash, action, reservation, expiration, unused state, and eligibility. HTTPS is required; raw tokens must not be logged or leaked through referrer data.
3. Opening a link with GET only displays a confirmation page. GET never confirms attendance or cancels. The Customer must explicitly submit a POST; the server consumes the token atomically with the requested action. Confirm Attendance records only the Customer's response; it does not check them in or change reservation status.
4. Cashier or Manager verifies the arriving Customer's reservation and changes `Confirmed` to `Occupied` at check-in.
5. If Customer has not checked in one hour after start, system sends a follow-up email even if they confirmed attendance.
6. An automated cron job checks reservations that remain unchecked in after the additional hour. If booked time remains, it transitions the reservation directly to `Cancelled`, records reason `No response/no check-in`, and releases the table.
7. If scheduled end occurs first, the cron job transitions the reservation directly to `Cancelled` at scheduled end with the same reason and releases the table. These jobs must never transition a never-checked-in reservation to `Completed`; only staff-confirmed departure from an `Occupied` session can result in `Completed`.
8. If a reminder or adjustment process is late, staff verify the table is physically unoccupied before treating it as available.

## 7. Customer table-side order and payment

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

- Allocate order-wide discounts proportionally across pre-discount line values with deterministic rounding. Allocate itemized refunds to affected items and unitemized partial refunds proportionally across remaining net line values, including drinks and non-drinks.
- A credit against other order items is a discount allocated to the lines it benefits. Confirmed purchase refunds reduce Net Purchase Amount by their item allocation. Pending, failed, or cancelled refunds have no effect.
- Payment tender settles an amount owed and does not reduce Net Purchase Amount. Only successful collected/captured tender contributes to Net Paid Amount.
- Direct minimum-spend credits reduce payable shortfall only. They do not change Eligible Net Purchases or Drink Subtotal and cannot also be recorded as a stockout waiver.
- Issued store credit is a separate balance, not a purchase or minimum-spend credit. Redeemed store credit is tender toward the order it settles; eligible purchase value remains the net value of purchased items.
- Record each amount once; do not double-count an amount as discount, purchase refund, payment tender, direct minimum-spend credit, or stockout waiver.

Refunds and payment reversals are separate records. Apply each provider-confirmed event once; their combined effect per payment cannot exceed the original successful payment amount. Never record one provider event as both a refund and a reversal. If an unresolved adjustment prevents accurate calculation of either aggregate, exclude the affected order from both aggregates.

**Actors:** Customer, Cashier, and order system.

1. Customer browses menu categories and items with current stock status and prices.
2. Customer adds items and quantities to the cart. On submission, server retrieves current menu prices from the database and calculates every line subtotal and the order total exclusively from those prices; it ignores client-submitted prices and totals.
3. Customer submits the order. System validates item availability and quantities, rejects an empty or invalid order, and saves the order and all items atomically. It links the order to the customer's active session using `reservation_id` and `table_id` foreign keys in `shioricafe_schema.order`.
4. Customer selects an available payment method. For cash, Cashier records payment only after collecting it. For digital payment, only the PayMongo test-mode flow is used. Server verifies asynchronous PayMongo webhooks and marks payment `Paid` only from a valid webhook; webhook processing is idempotent so duplicate events cannot duplicate or reverse payment effects. A browser return page must never mark payment `Paid`.
5. Customer can retry a failed or abandoned digital payment or pay cash. A browser return may display a pending state while awaiting the webhook. Order preparation status remains separate from payment status.
6. Cashier advances preparation through `Pending` → `Preparing` → `Ready` → `Served` → `Completed`.
7. System shows an itemized on-screen receipt after payment, including gross item amount, discounts, confirmed refunds, net purchase amount, payments, net paid amount, and any reservation minimum-spend shortfall. Gross is based on purchase-time line prices before discounts/refunds; minimum-spend aggregation uses eligible net purchase amounts only for fully settled, non-voided orders. Unpaid, pending, failed, abandoned, partially settled, voided, and fully refunded orders contribute zero; a partial refund removes its approved item allocation. A payment reversal reduces settlement and excludes the order if remaining successful payments no longer cover its net purchase amount. Drink subtotal uses the same net basis and purchase-time drink classification, so allocated refunds/discounts to drink lines reduce both total and drink subtotal. Non-drink adjustments reduce total only. Calculate base shortfall from eligible net purchases, then subtract approved direct minimum-spend credits and previous waivers; these credits are recorded separately and are not purchases. Apply approved Option B stockout waivers after recalculating aggregates. Order receipts are not emailed in Release 1.
8. Multiple successful payments may settle one order; pending or failed attempts contribute zero. Multiple confirmed refunds reduce the allocated item/order purchase amount; payment reversals reduce settlement and make an order ineligible if remaining successful payments no longer cover net purchase amount. Duplicate provider events are ignored, and cumulative refunds/reversals cannot exceed the settled amount. Do not count refunded value as a valid purchase. Use purchase-time item price and drink classification; current menu edits do not change past receipts or reservation aggregates.
9. Apply the approved discount, refund, item-credit, and store-credit allocation rules above. Partially settled orders contribute zero until fully settled. A credit explicitly applied to the reservation minimum shortfall is recorded separately from purchases and drink subtotals, and cannot also be claimed as a stockout waiver. Refer exceptions that cannot be allocated accurately to a Manager and exclude the entire affected order from both aggregates until resolved.

## 8. Unavailable paid item, substitution, and refund/credit

**Actors:** Manager and Customer.

1. Manager discovers that an ordered item is unavailable and contacts the Customer before substituting.
2. If Customer accepts a substitute, system records the replacement at its actual purchase-time price and drink classification. If it costs less, a confirmed purchase refund reduces Net Purchase Amount; a credit against other ordered items is allocated as a discount to the lines it benefits. Recalculate total purchase and drink aggregates using the substitute and each adjustment exactly once; do not continue counting the unavailable item's original value. Allocate itemized refunds to affected items and unitemized partial refunds proportionally across remaining net line values.
3. Manager verifies and records the stockout resolution, including any accepted substitute or refund adjustment. System snapshots aggregate drink subtotal and eligible paid total immediately before and after the resolution. A waiver trigger exists only if the before drink subtotal was at least 50% of the required minimum and this resolution causes the after subtotal to be below 50%. If it was already below 50%, this event does not trigger a waiver. Apply approved Option B: waive only the increase in regular shortfall attributable to the resolution, capped by current payable shortfall and net value removed. Example: minimum ₱100; before total/drinks ₱80/₱50; after a ₱10 drink stockout total/drinks ₱70/₱40. Regular shortfall rises from ₱20 to ₱30, so waive ₱10 and leave the pre-existing ₱20 deficiency payable. Record a unique event key and before/after values; serialize events per reservation, account for prior waivers, and return the existing result on retry. Accepted substitutes count at actual price and category. Refunds affect aggregates according to final valid/paid status; do not count a price reduction both as a credit/refund and as a waiver.
4. If Customer declines all substitutes for a digitally paid item, Manager may request a partial refund through the original method, recording a reason. Refund is complete only after provider confirmation.
5. If Customer declines all substitutes for a cash-paid item, Manager manually credits the amount against other ordered items or issues store credit. No cash refund is issued.

## 9. Extension and departure

**Actors:** Customer, Cashier, Manager, and system.

1. Fifteen minutes before the current session end, system prompts the Customer to leave or request a one-hour extension. Requests can be submitted through the current scheduled end.
2. If requested, system checks the additional interval on the assigned table and verifies the new end is no later than 23:30.
3. If available, server recalculates the minimum using table type and total reservation duration, including the extension. If the new duration crosses into a higher band, system shows the new minimum and remaining shortfall before acceptance. Extension time is cumulatively free for 30 minutes without a qualifying drink order or 90 minutes with at least one drink in a fully settled, non-voided, non-refunded order linked to the reservation. Check eligibility at each request. Charge only the overage in 30-minute blocks: ₱12.50 Solo, ₱25 Duo, and ₱50 Group per block. Extension fees are separate from minimum spend; show the fee before acceptance.
4. Each extension request has a unique idempotency key. On successful approval, system increments reservation `extension_count` once, recalculates total duration and pricing band, and records the processed request so a retry cannot apply the extension twice.
5. If the assigned table conflicts, system does not extend the Customer on that table. It shows alternatives; authorized staff verify suitability, operational status, and conflicts before moving the Customer.
6. Extensions may repeat while each added hour is conflict-free and ends by 23:30.
7. Five minutes before end, system alerts Cashier to remind the Customer. At end, it alerts Cashier or Manager.
8. Customer may select **I'm leaving now** to notify staff. Manager physically confirms the nook is empty and changes `Occupied` to `Completed`.
9. If Customer remains, table stays unavailable until staff resolve the situation.

## 10. Walk-in order

**Actors:** Walk-in Customer and Cashier.

1. Walk-in Customer places an order through Cashier without creating an account.
2. Cashier marks it **Takeaway** or **Dine-In**. System creates a unique generic walk-in reservation/session for the transaction with `customer_id = NULL` and `is_walkin = true`.
3. For dine-in, Cashier asks how long the Customer expects to stay and records that duration and expected end time. Cashier assigns a suitable operational table that is not under maintenance, occupied, or reserved during the expected interval. Manager or Cashier can update the table's occupied time slot when the estimate changes, after checking that the updated interval does not conflict with another booking. Takeaway has no table assignment.
4. Every walk-in order links to its corresponding unique session through `reservation_id` in `shioricafe_schema.order`. Dine-in requires `table_id`; takeaway has `table_id = NULL`. A dine-in walk-in session is recorded as `Occupied` through the estimated end time and blocks overlapping reservations. It must end by 23:30. Walk-ins pay for their ordered items; no table or reservation minimum applies.
5. At the estimated end, Cashier checks whether the Customer has left. If they remain, keep the table unavailable and have Manager or Cashier update the occupied time slot only after checking conflicts; if it would overlap an online booking, staff must resolve the occupancy before assigning the table to the booked Customer.
6. Cashier records cash as paid only after collection or uses the approved test-mode digital payment flow. The on-screen receipt follows the same payment rules as other orders.

## 11. Manager operations

**Actor:** Manager.

- Manage menu categories, item descriptions, prices, and stock status.
- Manage table records, capacity, amenities, and operational status. Changing a table to Maintenance must not silently erase an existing reservation or occupancy; resolve affected bookings through the adjustment flow.
- Review reservations, proposed adjustments, chats, urgent no-seat alerts, orders, and payment states.
- Advance order statuses when covering Cashier operations.
- Create Cashier accounts. First Manager is provisioned during setup.
- Authorize digital refunds with a reason; mark refunded only after PayMongo confirmation. Cash refunds remain out of scope.

## Remaining review items

1. Review and approve the proposed supporting schema for chat, walk-in sessions, repeated extensions, adjustment proposals, reminder tokens, stockout waiver records, and payment/refund tracking before implementation.
2. Confirm the complete PostgreSQL schema, constraints, indexes, and payment-ledger reconciliation in `docs/DATABASE_SPEC.md`. The financial allocation and store-credit rules are approved there; partial settlements contribute zero until fully settled.
