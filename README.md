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
```

### 2.2 Required Accounts
- **Neon:** Hosting the PostgreSQL database (if remote).
- **PayMongo:** Testing QR Ph payment integration.
- **Google:** Supporting reservation reminder emails through Google Apps Script.

---

## 3. Installation and Setup

### 3.1 Clone the Repository
```bash
git clone <repository-url>
cd SHIORIcafe
```

### 3.2 Configure the Environment
Copy the example environment file and configure your local settings:
```bash
cp .env.example .env
```
*(Do not commit the `.env` file to the repository.)*

### 3.3 PostgreSQL Database Setup
SHIORI Café uses PostgreSQL with the following target configuration:
- **Database Name:** `grp10_shioricafedb`
- **Schema Name:** `shioricafe_schema`

**Step 1: Create the Database & Schema**
Connect to PostgreSQL and run:
```sql
CREATE DATABASE grp10_shioricafedb;
\c grp10_shioricafedb;
CREATE SCHEMA IF NOT EXISTS shioricafe_schema;
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

**Step 2: Run Database Migrations & Seed Data**
Execute the SQL scripts in dependency order from `backend/database/migrations/`, followed by `backend/database/seed.sql`. Use the direct database connection for setup.

---

## 4. Configuration

### 4.1 Database Connection (`backend/config/db.php`)
The application connects to PostgreSQL using PHP PDO. It utilizes exception-based error handling and prepared statements for security.

Example PDO setup (loaded securely from `.env`):
```php
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $username, $password, $options);
```

### 4.2 PayMongo & Webhooks
1. Use PayMongo **test mode** only.
2. Configure the test secret key and webhook secret in `.env`.
3. Verify payment confirmations strictly on the server side via webhooks.

---

## 5. Running the Application

1. Place the `SHIORIcafe` folder inside your XAMPP `htdocs` directory.
2. Start Apache via the XAMPP Control Panel.
3. Ensure your local PostgreSQL server is running.
4. Open a web browser and navigate to: `http://localhost/SHIORIcafe/`

---

## 6. Legacy Database (Archive)
Earlier versions of SHIORI Café used MySQL/MySQLi. The legacy SQL scripts are retained in `backend/database/archive/` for historical reference only and **must not** be executed against the new PostgreSQL architecture.

---

## 7. Security Considerations
- Store passwords using `password_hash()` and verify with `password_verify()`.
- Use PDO prepared statements to prevent SQL injection.
- Apply strict role-based access control (RBAC) to protected endpoints.
- Store API keys, tokens, and DB credentials exclusively in environment variables.

---

## 8. Project Documentation
- `AGENTS.md` - Development instructions.
- `docs/BUSINESS_RULES.md` - Detailed café policies and reservation logic.
- `docs/DATABASE_SPEC.md` - PostgreSQL DB entities and relationships.
- `docs/USER_ROLES.md` - User roles and permissions structure.

---

## 9. License and Project Information
SHIORI Café is an academic software development project intended for educational and demonstration purposes as part of the team's academic requirements.