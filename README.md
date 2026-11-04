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

The following are some of the main rules:

- The system uses the `Asia/Manila` time zone.
- Café operating hours are 05:00 to midnight.
- Every reservation and table session must end by 23:30. Overnight reservations are not allowed.
- Reservations must start at least 30 minutes from the current time and may be booked up to 14 days in advance.
- Reservation times use minute precision.
- Reservation statuses are `Confirmed`, `Adjustment_Pending`, `Occupied`, `Cancelled`, and `Completed`.
- A registered customer may have only one active reservation at a time.

**Minimum Spending Requirements**

The minimum spend is calculated per reservation, not per person.

| Table Type | Up to 2 Hours | Over 2 to 4 Hours | Over 4 Hours |
|---|---:|---:|---:|
| Solo Study Nook | ₱50 | ₱100 | ₱150 |
| Duo Nook | ₱100 | ₱150 | ₱200 |
| Group Study Desk | ₱200 | ₱300 | ₱400 |

**Additional Rules:**

- If eligible drink purchases reach at least 50% of the required minimum spend, the remaining shortfall is waived.
- Extension fees are charged per 30 minutes after the complimentary period.
- Extension rates are ₱12.50 for Solo, ₱25 for Duo, and ₱50 for Group tables.
- Complimentary extension time is 30 minutes, or 90 minutes with a qualifying settled drink order.
- Reservations are not prepaid. Customers pay at the café.

### 1.5 Technologies Used

| Layer | Current Implementation | Planned Target |
|---|---|---|
| Frontend | HTML5, CSS3, JavaScript | HTML5, CSS3, Bootstrap 5.3.3, JavaScript |
| Backend | PHP | PHP 8.2+ with PDO |
| Database | MySQL (local XAMPP configuration) | PostgreSQL 18+ |
| Database Hosting | Local development environment | Neon |
| Local Development | XAMPP | PHP development environment |
| Reminder Emails | Google Apps Script (planned integration) | Google Apps Script |
| Payment Integration | PayMongo (planned integration) | PayMongo QR Ph (Test Mode) |
| Deployment | Not yet finalized | Vercel (subject to PHP compatibility) |

**Implementation Note:** The current database connection file, `backend/config/db.php`, uses PHP MySQLi and a local MySQL configuration. Migration to PostgreSQL using PDO is part of the planned development work.

### 1.6 Project Structure

The current project structure is organized as follows:

```text
SHIORIcafe/
│
├── .agents/
│   └── skills/
│
├── admin/
│   ├── audit-logs.php
│   ├── index.php
│   ├── inquiries.php
│   ├── login.php
│   ├── orders.php
│   └── reservations.php
│
├── backend/
│   ├── api/
│   ├── auth/
│   ├── config/
│   └── database/
│       ├── archive/
│       ├── migrations/
│       └── seed.sql
│
├── docs/
│   ├── backup/
│   ├── BUSINESS_RULES.md
│   ├── DATABASE_SPEC.md
│   ├── PHASE1_HANDOFF.md
│   ├── PROJECT_PLAN.md
│   ├── TROUBLESHOOTING.md
│   ├── USER_FLOWS.md
│   ├── USER_ROLES.md
│   ├── USER_ROLE_SETUP.md
│   ├── feature-workflow.md
│   └── roadmap.md
│
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   ├── images/
│   │   └── js/
│   └── includes/
│
├── .env.example
├── .gitignore
├── AGENTS.md
├── README.md
├── about.php
├── contact.php
├── index.php
├── login.php
├── logout.php
├── register.php
├── reservation.php
└── services.php
```

This structure represents the current repository. It may change as the project develops.

---

## 2. Prerequisites

### 2.1 Required Software

The following software is needed for the current local development environment:

| Software | Purpose |
|---|---|
| XAMPP | Provides Apache, PHP, and MySQL for local development. |
| PHP | Runs the application's backend code. |
| MySQL | Provides the database used by the current MySQLi configuration. |
| Git | Version control and repository management. |
| Web Browser | Accessing and testing the application. |
| Visual Studio Code (Optional) | Code editing and project development. |

**Planned Database Migration Requirements:**

The following will be required when the project transitions to PostgreSQL:

- PostgreSQL 18 or newer.
- PostgreSQL client (`psql`).
- PHP PDO PostgreSQL extension (`pdo_pgsql`).

**PHP Extensions:**

The current database connection uses PHP MySQLi. The PostgreSQL PDO extension (`pdo_pgsql`) will be required after the planned database migration.

To check your PHP version:

```bash
php -v
```

To check the installed PHP extensions:

```bash
php -m
```

Make sure `mysqli` is available for the current database connection. The `pdo_pgsql` extension is required for the planned PostgreSQL implementation.

### 2.2 Required Accounts

The following accounts are needed for the planned integrations and deployment:

| Account / Service | Purpose | Status |
|---|---|---|
| Neon | Hosting the planned PostgreSQL database. | Planned |
| PayMongo | Testing QR Ph payment integration. | Planned |
| Google | Supporting reservation reminder emails through Google Apps Script. | Planned |
| Vercel | Hosting and deploying the application, subject to PHP compatibility. | Optional / Planned |

These accounts are not all required to work on the current local XAMPP setup. They will be needed when their corresponding integrations are implemented and configured.

### 2.3 Database Permissions

For the current local development environment, the MySQL database account must have sufficient permissions to access the application's database and perform the required database operations.

For the planned PostgreSQL implementation, the database setup account must have permission to:

- Create the application schema.
- Create tables, constraints, indexes, and other required database objects.
- Run the approved migration scripts.
- Insert the initial seed data.
- Enable the required PostgreSQL extension.

The `btree_gist` extension is required for the reservation overlap constraint described in `docs/DATABASE_SPEC.md`.

The PostgreSQL permissions and extension requirements must be verified before database setup begins.

---

## 3. Installation and Setup

### 3.1 Clone the Repository

To download the project after it has been published to a remote Git repository, run:

```bash
git clone <repository-url>
```

Navigate to the downloaded project folder:

```bash
cd SHIORIcafe
```

Replace `<repository-url>` with the actual URL of the project's remote repository.

**For an existing local development copy:** If you already have the project folder, open it directly in Visual Studio Code instead of cloning it again.

### 3.2 Install Dependencies

The project does not currently have a confirmed Composer dependency setup.

If a `composer.json` file is added in the future, install the PHP dependencies using:

```bash
composer install
```

### 3.3 Configure the Environment

The repository includes an `.env.example` file as a reference for environment configuration.

If the application is updated to load its configuration from `.env`, create a local environment file using:

```bash
Copy-Item .env.example .env
```

Open `.env` and configure the required database and application credentials according to the implemented configuration.

Do not commit the `.env` file to the repository. It is excluded through `.gitignore`.

**Important:** The presence of `.env.example` does not mean that the current PHP code already loads environment variables. Verify the application's configuration implementation before relying on these settings.

### 3.4 Current Local Database Configuration

The current database connection file is:

```text
backend/config/db.php
```

It currently uses PHP MySQLi with the following local configuration:

| Setting | Current Value |
|---|---|
| Database System | MySQL |
| Host | localhost |
| Username | root |
| Password | Empty |
| Database Name | shiori_cafe_db |

The database must exist in the local MySQL server, and its tables must match the application's queries before database-dependent features can work.

Do not assume that the planned PostgreSQL migration scripts can initialize the current MySQL database.

### 3.5 Planned PostgreSQL Database Setup

SHIORI Café is designed to transition to PostgreSQL with the following target configuration:

| Setting | Value |
|---|---|
| Database Name | `grp10_shioricafedb` |
| Schema Name | `shioricafe_schema` |
| Database System | PostgreSQL |
| Database Hosting | Neon |

**Important:** The database design is documented in `docs/DATABASE_SPEC.md`. The migration scripts and seed data must be finalized and approved before they are executed against a database.

**Step 1: Create the Database**

Create a PostgreSQL database named:

```text
grp10_shioricafedb
```

If using Neon, create the database through the Neon project or use the database provided by the project configuration.

**Step 2: Create the Database Schema**

After the database owner approves the schema and authorizes setup, connect to the database and create the application schema:

```sql
CREATE SCHEMA IF NOT EXISTS shioricafe_schema;
```

Enable the required extension:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

**Step 3: Review the Database Migrations**

The migration scripts are stored in:

```text
backend/database/migrations/
```

The current migration files are:

```text
001_core_schema.sql
002_order_tables.sql
003_order_discount_tables.sql
004_financial_tables.sql
005_triggers_and_views.sql
```

These scripts must be reviewed against `docs/DATABASE_SPEC.md` and the approved business rules before execution.

The existence of these files does not mean that the complete database implementation has been approved or verified.

**Step 4: Review the Seed Data**

The seed data file is:

```text
backend/database/seed.sql
```

The seed data should include:

- Initial café table records with table numbers, types, capacities, amenities, and availability status.
- Menu categories and menu items with their corresponding prices.
- The initial Manager account with a securely hashed password.

The actual table inventory, menu contents, and initial Manager account details must be supplied and confirmed before finalizing the seed data.

**Step 5: Execute Approved Database Scripts**

Only after the database migrations and seed data have been finalized and approved should the SQL scripts be executed in their required dependency order.

For example:

```bash
psql "$DATABASE_URL_DIRECT" -v ON_ERROR_STOP=1 -f backend/database/migrations/001_core_schema.sql
```

Continue with the remaining migrations in numerical order, then execute the seed file:

```bash
psql "$DATABASE_URL_DIRECT" -v ON_ERROR_STOP=1 -f backend/database/seed.sql
```

Use the direct, non-pooled database connection for database setup and migrations.

**Do not execute incomplete, unapproved, or legacy SQL scripts.**

---

## 4. Configuration

### 4.1 Environment Variables

The `.env.example` file provides reference configuration for the planned application environment.

Example configuration:

```env
APP_ENV=local
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila

DB_HOST=your_database_host
DB_PORT=5432
DB_NAME=grp10_shioricafedb
DB_USER=your_database_user
DB_PASSWORD=your_database_password
DB_SSLMODE=require

DATABASE_URL_DIRECT=your_direct_database_connection_string

PAYMONGO_MODE=test
PAYMONGO_SECRET_KEY=your_paymongo_test_secret_key
PAYMONGO_WEBHOOK_SECRET=your_paymongo_webhook_secret

REMINDER_ENDPOINT_TOKEN=your_reminder_endpoint_token
```

Replace the example values with actual credentials when the corresponding integrations are implemented.

Never upload real passwords, API keys, or database connection strings to a public repository.

### 4.2 Current Database Connection

The current database connection is implemented in:

```text
backend/config/db.php
```

It uses PHP MySQLi and a local MySQL database configuration.

### 4.3 Planned PostgreSQL Connection

The application is designed to transition to PostgreSQL through PHP PDO.

The planned database connection should:

- Use the PostgreSQL PDO driver (`pdo_pgsql`).
- Enable exception-based error handling.
- Use prepared statements for database queries.
- Use transactions for operations that require multiple related database changes.
- Use the `shioricafe_schema` schema when accessing application tables.
- Properly quote reserved table names such as `"order"` and `"table"`.

Example PDO connection format:

```php
<?php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $username, $password, $options);
```

This is an example of the planned connection format, not the current implementation. The final connection should load credentials securely from the environment.

### 4.4 PayMongo Configuration

PayMongo is the planned digital payment integration.

For Release 1:

1. Use PayMongo test mode only.
2. Configure the test secret key in the environment file.
3. Register the application's payment webhook endpoint.
4. Configure the webhook secret.
5. Verify payment confirmations on the server side.

Do not use real payment credentials or process live transactions during development.

### 4.5 Google Apps Script Configuration

Google Apps Script is planned to support reservation reminder emails.

The integration should:

1. Use the café's Google account.
2. Configure the scheduled trigger.
3. Send requests to the application's protected reminder endpoint.
4. Include the configured `REMINDER_ENDPOINT_TOKEN`.
5. Reject unauthorized requests.

Reservation action links must use secure, one-time tokens. Opening an action link should display a confirmation page; the action itself should only occur after an explicit POST request.

### 4.6 Deployment Configuration

Vercel is the planned deployment platform.

Before deployment, verify that the selected hosting configuration supports the PHP version and extensions required by the application, particularly `pdo_pgsql`.

Configure all required environment variables through the hosting platform's project settings.

---

## 5. Running the Application

### 5.1 Start the Current Local Development Environment

The project currently uses a root-level `index.php` file and is organized for local XAMPP development.

To run the project:

1. Place the project folder inside the XAMPP `htdocs` directory.
2. Open the XAMPP Control Panel.
3. Start Apache.
4. Start MySQL if the application requires database access.
5. Open the project in a web browser.

For the current project directory:

```text
C:\xampp\htdocs\SHIORIcafe
```

Open:

```text
http://localhost/SHIORIcafe/
```

**Note:** This starts the PHP application through Apache. Database-dependent features will only work when the local MySQL database and required tables are properly configured.

### 5.2 Initial Account Setup

Once the database and initial records are ready:

1. Sign in using the initial Manager account.
2. Create Cashier accounts through the Manager interface.
3. Register a customer account through the public application.
4. Use the customer account to explore the reservation and ordering features.

The initial Manager account must be configured securely before use.

### 5.3 Functional Testing

The following are the main functional scenarios intended for testing:

| Test Scenario | Expected Result |
|---|---|
| Browse the menu as a guest | Menu is displayed without requiring login. |
| View table availability | Available and unavailable tables are displayed correctly. |
| Reserve a study nook | A valid reservation is created with `Confirmed` status. |
| Reserve an overlapping time slot | The reservation is rejected. |
| Submit an order with modified client-side prices | The server uses the actual menu prices. |
| Access a Cashier-only endpoint without authorization | Access is denied. |
| Submit an order containing an invalid item | The transaction is rolled back without partial records. |
| Qualify for the drink waiver | The eligible minimum-spend shortfall is waived. |

These are intended test scenarios. Actual results must be verified during testing.

### 5.4 Reservation Reminder Jobs

The scheduled reminder process is intended to handle:

- Sending a reminder email 90 minutes before the reservation starts.
- Sending a follow-up email if the customer has not checked in one hour after the reservation starts.
- Automatically cancelling reservations based on the applicable no-response and no-check-in rules.
- Expiring unanswered Manager proposals at reservation start and processing the corresponding seat reassignment rules.

### 5.5 Test Documentation

The project test scenarios use the following test case identifiers:

| Test Case ID | Description |
|---|---|
| `TC-RES-01` | Reservation overlap rejection |
| `TC-ORD-01` | Server-side order pricing |
| `TC-SEC-01` | Role-based access control |
| `TC-DBX-01` | Database transaction rollback |
| `TC-WVR-01` | Drink-based minimum-spend waiver |

Test execution commands will be added when the project's test runner is available.

---

## 6. Legacy Database and Code Migration

Earlier versions of the SHIORI Café project used MySQL and PHP MySQLi. The target database architecture uses PostgreSQL and PHP PDO.

The legacy SQL scripts are retained for reference only and must not be executed together with the new database migrations.

### 6.1 Database Technology Changes

| Legacy Implementation | Target Implementation |
|---|---|
| MySQL | PostgreSQL |
| PHP MySQLi | PHP PDO |
| `mysqli_connect()` | PDO database connection |
| `mysqli_query()` | PDO prepared statements |
| `AUTO_INCREMENT` | `GENERATED ALWAYS AS IDENTITY` |
| MySQL `ENUM` | PostgreSQL text columns with `CHECK` constraints |
| MySQL `DATETIME` | PostgreSQL `timestamptz` where appropriate |
| Backtick identifiers | Double-quoted identifiers |
| `mysqli_insert_id()` | `INSERT ... RETURNING` |
| Legacy `seats`, `reservations`, and `orders` tables | New `shioricafe_schema` database model |

### 6.2 Legacy SQL Files

The following files are retained as historical references:

```text
backend/database/archive/
├── schema.sql
├── orders-schema.sql
└── migrate-reservation-times.sql
```

These files belong to the older database structure. They should not be used to initialize or update the new PostgreSQL database.

---

## 7. Security Considerations

The application should follow these security practices:

- Store passwords using PHP's `password_hash()` function.
- Verify passwords using `password_verify()`.
- Keep database credentials and API keys in environment variables.
- Never commit `.env` files or sensitive credentials to version control.
- Use prepared statements to prevent SQL injection.
- Apply role-based access control to protected endpoints.
- Validate all user input on the server side.
- Verify payment confirmations through the payment provider's webhook.
- Keep PayMongo in test mode during development.
- Store only hashed reservation action tokens.
- Never expose raw action tokens in application logs.

These practices should be verified against the actual implementation before the system is considered production-ready.

---

## 8. Project Documentation

The following documents provide additional information about the project:

| File | Description |
|---|---|
| `README.md` | Project overview, prerequisites, installation, configuration, and execution instructions. |
| `AGENTS.md` | Project-specific development instructions and workflow guidance. |
| `docs/BUSINESS_RULES.md` | Detailed café policies, reservation rules, payment rules, and business logic. |
| `docs/DATABASE_SPEC.md` | Database entities, relationships, constraints, and database design specifications. |
| `docs/PHASE1_HANDOFF.md` | Project handoff and implementation reference. |
| `docs/PROJECT_PLAN.md` | Project planning information. |
| `docs/USER_FLOWS.md` | User workflows and system interaction flows. |
| `docs/USER_ROLES.md` | User roles and permissions. |
| `docs/USER_ROLE_SETUP.md` | User role setup reference. |
| `docs/TROUBLESHOOTING.md` | Troubleshooting reference. |
| `docs/roadmap.md` | Planned project development and milestones. |
| `docs/feature-workflow.md` | Feature development workflow. |

---

## 9. Current Development Status

SHIORI Café is under development for Release 1 (Activity 3).

The following items must be completed or verified before the planned PostgreSQL database can be used for full system testing:

- Final review and approval of the PostgreSQL database schema.
- Completion and verification of all database migration scripts.
- Preparation of the initial seed data.
- Confirmation of the café's table inventory and menu records.
- Preparation of the initial Manager account.
- Verification of the Neon database configuration and required PostgreSQL extensions.
- Completion of the migration from legacy MySQL/MySQLi code to PostgreSQL/PDO.

**Important:** The database specification is the reference for the intended database design. Database scripts must be consistent with the approved specification before execution.

---

## 10. License and Project Information

SHIORI Café is an academic software development project. It is intended for educational and demonstration purposes as part of the team's project requirements.