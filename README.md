# SHIORI Café

A web application for a study café where customers can reserve study nooks and order food and drinks from their tables. Cashiers manage customer check-ins, walk-in orders, and payments, while Managers oversee the menu, tables, staff accounts, reservation adjustments, refunds, and store credit.

---

## 1. Project Overview

### 1.1 Description

SHIORI Café is designed to make study café operations more organized and convenient through an online reservation and ordering system. Customers can browse the menu, check table availability, make reservations, and place orders. Staff members can manage reservations, orders, payments, and other café operations based on their assigned roles.

### 1.2 User Roles and Capabilities

| User Role | Main Capabilities |
|---|---|
| **Guest** | Browse the landing page, menu, floor plan, and table availability. Register or sign in. |
| **Customer** | Reserve a study nook, order food and drinks, request reservation extensions, respond to Manager proposals, chat with the Manager, and view bookings and receipts. |
| **Cashier** | Check in customers, create walk-in dine-in and takeaway orders, manage the order queue, and record cash payments. |
| **Manager** | Manage menu items, tables, Cashier accounts, reservation adjustments, digital refunds, and store credit. |

### 1.3 Release 1 Scope

The first release focuses on the core reservation, ordering, and café management functions.

- Customer registration, login, and profile management.
- Cashier and Manager login with role-based access.
- Study nook reservations with minimum spending requirements.
- Drink-based and stockout-related reservation waivers.
- Reservation reminder emails through Google Apps Script.
- Secure, one-time reservation action links.
- Table-side food and drink ordering.
- Walk-in orders managed by Cashiers.
- Cash payments and PayMongo QR Ph payments in test mode only.
- One-hour reservation extensions with complimentary time and prorated fees.
- One-to-one customer and Manager chat linked to a reservation.

**Deferred Features:**
- AI Study Companion.
- Analytics and sales reporting.
- Order email receipts.

### 1.4 Key Business Rules

The detailed and authoritative business rules are documented in `docs/BUSINESS_RULES.md`.

- The system uses the `Asia/Manila` time zone.
- Café operating hours are 05:00 to midnight.
- Every reservation and table session must end by 23:30. Overnight reservations are not allowed.
- Reservations must start at least 30 minutes from the current time and may be booked up to 14 days in advance.
- Reservation times use minute precision.
- Reservation statuses are `Confirmed`, `Adjustment_Pending`, `Occupied`, `Cancelled`, and `Completed`.
- A registered customer may have only one active reservation at a time.

**Minimum Spending Requirements** (Calculated per reservation, not per person):

| Table Type | Up to 2 Hours | Over 2 to 4 Hours | Over 4 Hours |
|---|---:|---:|---:|
| Solo Study Nook | ₱50 | ₱100 | ₱150 |
| Duo Nook | ₱100 | ₱150 | ₱200 |
| Group Study Desk | ₱200 | ₱300 | ₱400 |

### 1.5 Technologies Used

| Layer | Implementation |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5.3.3, JavaScript |
| Backend | PHP 8.2+ with PDO |
| Database | PostgreSQL 18+ |
| Database Hosting | Local / Neon |
| Reminder Emails | Google Apps Script |
| Payment Integration | PayMongo QR Ph (Test Mode) |

---

## 2. Prerequisites

### 2.1 Required Software

| Software | Purpose |
|---|---|
| XAMPP / PHP Env | Runs the application's backend code locally (Apache/PHP). |
| PostgreSQL 18+ | The core database system. |
| pgAdmin / psql | PostgreSQL client for database management. |
| Git | Version control and repository management. |

**Required PHP Extensions:**
Ensure the PostgreSQL PDO extension (`pdo_pgsql`) is enabled in your `php.ini`.
```bash
php -m