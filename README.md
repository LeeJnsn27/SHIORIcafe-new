# SHIORI Café

SHIORI Café is a PHP web application for a study café. It is designed to support study nook reservations, table-side ordering, walk-in service, and staff operations.

## Technology and database

- **Application:** PHP 8.2+ with PDO
- **Database:** PostgreSQL 18+
- **PostgreSQL database:** `grp10_shioricafedb`
- **PostgreSQL schema:** `shioricafe_schema`
- **Web server for local development:** Apache/PHP from XAMPP
- **Database driver:** PHP `pdo_pgsql`
- **Frontend:** HTML, CSS, Bootstrap, and vanilla JavaScript

XAMPP's MySQL service is not the application database. Use PostgreSQL, either installed locally or provided by a database host. The current PHP connection creates a PostgreSQL PDO connection in [backend/config/db.php](backend/config/db.php).

## Features and roles

The application is intended to support:

- Guest browsing and customer registration/login.
- Customer reservations, ordering, and reservation-related actions.
- Cashier check-in, point-of-sale ordering, and order queue operations.
- Manager administration of staff, menu, tables, and reservation operations.
- Reservation reminders and payment-provider integration as planned work.

The complete business rules are in [docs/BUSINESS_RULES.md](docs/BUSINESS_RULES.md); user flows are in [docs/USER_FLOWS.md](docs/USER_FLOWS.md). Feature descriptions are not a claim that every flow has been integration-tested.

## Requirements

1. **Windows 10/11** (steps below use PowerShell).
2. **Git** to clone the repository, unless you already have a checkout.
3. **XAMPP with PHP 8.2+ and Apache** for the local web server.
4. **PostgreSQL 18+** installed locally or provided by a database host.
5. PHP's **`pdo_pgsql` extension** enabled for the PHP runtime used by Apache.
6. **`psql`** if you are authorized to review and apply the database scripts.
7. A modern web browser.

There is no Composer dependency manifest in this repository, so `composer install` is not part of setup.

## Windows setup and run instructions

### Step 1: Install and verify PHP, Apache, and PostgreSQL

Install XAMPP with PHP 8.2 or later, and install PostgreSQL 18 or later separately if using a local database. In the PostgreSQL installer, include the command-line tools and pgAdmin if offered. Keep track of the PostgreSQL service, port (usually `5432`), and administrator account. Do not use the PostgreSQL administrator/superuser account as the website's runtime account.

In the XAMPP Control Panel, start Apache. The PostgreSQL server is a separate service and must also be running. Do not start XAMPP's MySQL service for this application.

Open PowerShell and check the command-line PHP version and extensions:

```powershell
php -v
php --ini
php -m
```

Confirm PHP is 8.2+ and `pdo_pgsql` appears in the module list. If it does not, open the PHP configuration used by XAMPP Apache from the Control Panel's **Apache > Config > PHP (php.ini)** menu. Enable the PostgreSQL extensions by removing the leading `;` from the existing `extension=pdo_pgsql` and `extension=pgsql` lines, if those lines and DLLs are present. Save the file, restart Apache, and check again. Do not add duplicate extension entries. Command-line PHP and Apache can use different configuration files, so `php -m` alone does not verify Apache's PHP configuration.

Check that the PostgreSQL command-line client is available:

```powershell
psql --version
```

If PowerShell says `psql` is not recognized, add the PostgreSQL `bin` folder to your Windows `PATH` or invoke `psql.exe` using its full path, typically under `C:\Program Files\PostgreSQL\<version>\bin\psql.exe`.

### Step 2: Get the project files

If you already have the project at `C:\xampp\htdocs\SHIORIcafe`, open PowerShell there and skip the clone command. Otherwise, clone the `staging` branch into XAMPP's `htdocs` directory:

```powershell
Set-Location C:\xampp\htdocs
git clone --branch staging --single-branch https://github.com/LeeJnsn27/SHIORIcafe-new.git SHIORIcafe
Set-Location C:\xampp\htdocs\SHIORIcafe
```

If Git says the destination folder already exists, do not delete or overwrite it; use the existing checkout or choose another folder.

### Step 3: Create the local environment file

From `C:\xampp\htdocs\SHIORIcafe`, copy the example:

```powershell
Copy-Item .env.example .env
notepad .env
```

Set the connection values for your PostgreSQL server:

```dotenv
DB_HOST=localhost
DB_PORT=5432
DB_NAME=grp10_shioricafedb
DB_SCHEMA=shioricafe_schema
DB_USER=your_runtime_role
DB_PASSWORD=replace_with_a_nonempty_password
```

Replace `DB_USER` and `DB_PASSWORD` with the actual dedicated runtime login you will create or receive from the database administrator. The password must be non-empty. Do not include surrounding quotes. For a remote database, use the host, port, database name, and login provided by its administrator. The current PHP connection does not consume `DB_SSLMODE` or a connection URI, so a hosted database is not ready to use until its TLS requirements are supported and verified.

`backend/config/db.php` parses the root `.env` file as simple `KEY=VALUE` lines. It reads `DB_HOST`, `DB_PORT`, `DB_NAME`, and `DB_SCHEMA` from that file, and reads `DB_USER`/`DB_PASSWORD` from `.env` or the PHP process environment. The code has a fallback port of `5173`, so keep `DB_PORT=5432` (or your PostgreSQL server's actual port) explicitly set. `.env` is ignored by Git; never commit it or share its secrets.

### Step 4: Set up the PostgreSQL database and roles

The application expects database `grp10_shioricafedb` and schema `shioricafe_schema`. For local PostgreSQL, open pgAdmin, connect to the local server, and create a database named `grp10_shioricafedb`. Create separate login roles for migrations and application runtime. In pgAdmin, these are under **Login/Group Roles > Create > Login/Group Role**; enable login, set each password through the pgAdmin password field, and do not grant superuser status. Give the runtime role only the approved privileges required by the application; do not make it a schema owner by default. See [docs/USER_ROLE_SETUP.md](docs/USER_ROLE_SETUP.md) for the intended role model.

Do not grant schema/table privileges based on guesswork. The owner must approve the role privileges against the finalized schema. The migration role also needs permission for the schema and extension operations required by the approved migration scripts. Do not manually create `shioricafe_schema` unless the database owner directs you to; migration 001 currently attempts to create it.

**Important: the repository does not currently have a verified, ready-to-run database setup.** The schema is a proposal, migration 004 labels its financial tables as proposed, migration 005 is marked as a draft, and the seed data does not match the current core migration: `seed.sql` names `base_price`, whereas migration 001 defines `current_price`. The seed also contains sample account records and hashes. Do not run these scripts as-is or use the sample accounts. The schema, migration dependencies, runtime grants, and corrected seed data must first be reviewed and approved by the database owner.

The migration files, in their current order, are:

1. `backend/database/migrations/001_core_schema.sql`
2. `backend/database/migrations/002_order_tables.sql`
3. `backend/database/migrations/003_order_discount_tables.sql`
4. `backend/database/migrations/004_financial_tables.sql` (proposed)
5. `backend/database/migrations/005_triggers_and_views.sql` (draft)

Migration 001 creates the schema and attempts to enable the `btree_gist` extension. Confirm the authorized migration account has the required permissions before running it. Do not apply any migration to a database that contains data you need to preserve unless the database owner has approved a backup and change plan.

**Only after the database owner has approved the scripts:** connect to the target database using the direct PostgreSQL endpoint and the migration role. From the repository root, run each approved migration in dependency order. For a local PostgreSQL server, a migration command has this form; replace the role name with the approved role:

```powershell
psql -h localhost -p 5432 -U your_migration_role -d grp10_shioricafedb -W -v ON_ERROR_STOP=1 -f .\backend\database\migrations\001_core_schema.sql
```

`-W` prompts for the role's password instead of putting it in the command. Repeat for each reviewed and approved migration. Stop if any command fails; do not continue to later migrations or run the seed after a failure. Only run `.\backend\database\seed.sql` after its columns and sample data have been corrected and reviewed against the schema actually installed. The commands above are examples and are not an approval to apply the current draft files.

### Step 5: Start the website

1. Confirm PostgreSQL is running and reachable.
2. Confirm `.env` has the correct host, port, database, schema, runtime username, and non-empty password.
3. Start Apache in the XAMPP Control Panel. The PostgreSQL database must run separately; XAMPP MySQL is not used.
4. Open [http://localhost/SHIORIcafe/](http://localhost/SHIORIcafe/) in your browser.

The root PHP page may load without exercising the database connection. Database-backed pages require a working PostgreSQL connection, compatible schema, runtime-role grants, and Apache's `pdo_pgsql` extension. Until the migration/seed issues above are resolved, a complete database-backed setup cannot be guaranteed from the repository alone.

### Step 6: Basic verification and shutdown

- Open the landing page and check that PHP renders it through Apache at `http://localhost/SHIORIcafe/`.
- Visit a feature that reads the database only after the database and schema are ready. A database configuration/connection error means one or more connection settings, server availability, network permissions, credentials, or PHP extensions need attention.
- Check the Apache/PHP error log and PostgreSQL server log for the detailed local error. The application deliberately does not show database connection details in the browser.
- When finished, stop Apache from the XAMPP Control Panel. Stop the PostgreSQL service only if you started it for this local session and no other application depends on it.

### Common setup problems

| Symptom | Checks |
|---|---|
| `DB_USER and DB_PASSWORD must be set` | Confirm both names are present in the root `.env`, contain values, and the password is non-empty. |
| Database connection failure in browser | Check PostgreSQL is running, `.env` host/port/database and credentials are correct, remote firewall rules permit access, and Apache's PHP has `pdo_pgsql` enabled. Check server logs for the private details. |
| `could not find driver` | Enable `pdo_pgsql` for XAMPP Apache, save the correct `php.ini`, and restart Apache. |
| Connection refused | Confirm the PostgreSQL service is running and `DB_HOST`/`DB_PORT` match its listening address and port. |
| Relation/column does not exist | The installed schema is missing, from a different version, or incompatible with the application query. Do not try random migrations; review the migration and schema with the database owner. |
| Works in CLI but not in browser (or vice versa) | CLI PHP and Apache may load different `php.ini` files. Verify and enable the extension in the configuration used by Apache. |

## Environment and integrations

The current `.env.example` contains only the six database settings used by the database connection. `DB_HOST`, `DB_PORT`, `DB_NAME`, and `DB_SCHEMA` are read from `.env`; credentials can be read from `.env` or the PHP process environment.

The chat endpoint reads `GEMINI_API_KEY` and optional `GEMINI_MODEL` using PHP `getenv()`. The simple `.env` parser in `db.php` does not export other `.env` values to `getenv()`. Configure those values in the PHP/Apache process environment if enabling the chat integration; do not assume placing them in `.env` is sufficient.

PayMongo payments and Google Apps Script reminders are planned integrations. Their secrets are not included in `.env.example` and should not be configured as active features until the corresponding implementation is in place.

## Security notes

- Keep `.env`, database connection strings, API keys, and real credentials private.
- Use a least-privilege PostgreSQL runtime role with a non-empty password.
- Use a separate authorized role for schema migrations.
- Do not use sample seed accounts in a real environment.
- Apply approved SQL only after backing up any database that contains data.
- Validate authorization, input handling, and payment confirmations on the server.
- Keep payment-provider work in test mode until the implementation and deployment are reviewed.

## Testing and development status

The repository does not currently document a configured automated test runner. Run relevant checks and manual integration tests against a disposable PostgreSQL database before relying on changes. Do not treat the documented functional scenarios or migration files as evidence that tests have passed.

SHIORI Café remains under development. The database connection is PostgreSQL/PDO, but the schema proposal, migration set, seed compatibility, and complete application flows still require review and verification. Obtain owner approval before changing the proposed schema or applying database scripts.

## Project references

- [docs/BUSINESS_RULES.md](docs/BUSINESS_RULES.md) — reservation, ordering, and café rules.
- [docs/DATABASE_SPEC.md](docs/DATABASE_SPEC.md) — proposed database entities and constraints.
- [docs/USER_FLOWS.md](docs/USER_FLOWS.md) — customer and staff workflows.
- [docs/USER_ROLES.md](docs/USER_ROLES.md) — application roles and permissions.
- [docs/USER_ROLE_SETUP.md](docs/USER_ROLE_SETUP.md) — PostgreSQL role setup guidance.
- [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) — troubleshooting notes.
- [AGENTS.md](AGENTS.md) — repository development guidance.

## License and project information

SHIORI Café is an academic software development project intended for educational and demonstration purposes.
