# DATABASE RESOURCE ORGANIZATION

## 1. Overview

The SHIORI Café system uses PostgreSQL as its database management system. Since the backend is developed using PHP rather than Laravel, database structures and initialization procedures are organized using SQL scripts.

The database resources are stored separately from the application source code to make database setup, maintenance, and future updates easier to manage.

## 2. Database Directory Structure

The proposed database directory structure is:

backend/
    database/
        README.md
        migrations/
            001_create_database_schema.sql
            002_create_core_tables.sql
            003_create_reservation_tables.sql
            004_create_order_tables.sql
            005_create_discount_tables.sql
            006_create_minimum_spend_tables.sql
            007_create_payment_tables.sql
            008_create_financial_tables.sql
            009_create_constraints_and_indexes.sql
            010_create_functions_and_triggers.sql
            011_create_views.sql
        seeds/
            .gitkeep
        archive/
            schema.sql
            orders-schema.sql
            migrate-reservation-times.sql

Note: The filenames above represent a proposed organization. They must only be treated as actual migration files after the scripts have been created, reviewed, and approved. The exact migration grouping may be adjusted to follow the finalized database specification.

## 3. Database Migration Scripts

The migrations directory contains SQL scripts responsible for creating and updating the database structure.

The scripts are organized according to their purpose and dependencies.

The planned responsibilities include:

- Database schema creation: Creates and prepares the application schema.
- Core table creation: Creates customer, staff, table, category, and menu item tables.
- Reservation table creation: Creates reservation-related tables and their relationships.
- Order table creation: Creates order and order item tables.
- Discount table creation: Creates order discount and discount allocation tables.
- Minimum-spend table creation: Creates minimum-spend credit and waiver tables.
- Payment table creation: Creates payment attempts, transactions, refunds, reversals, and provider event tables.
- Financial structure creation: Creates other required financial database structures.
- Constraint and index creation: Adds required database constraints and indexes.
- Function and trigger creation: Adds database functions and triggers.
- View creation: Creates financial views used by the application.

The scripts must follow the dependency order defined in DATABASE_SPEC.md.

## 4. Database Seed Files

The seeds directory is reserved for SQL scripts containing initial or sample records used during development and testing.

Possible seed data may include:

- Initial table records.
- Menu categories.
- Sample menu items.
- Development test records.

Seed scripts must use approved sample values and must not contain real customer information, production credentials, or sensitive payment information.

Seed data must not be treated as production data.

## 5. Archived Database Files

The archive directory contains older database scripts retained for reference.

These files are not part of the current database initialization process:

- schema.sql
- orders-schema.sql
- migrate-reservation-times.sql

The archived scripts represent previous database structures and must not be executed together with the new database migration scripts.

The current DATABASE_SPEC.md is the reference for the revised database design.

## 6. Database Setup Order

The planned database initialization process is:

1. Create or select the target PostgreSQL database.
2. Enable the required PostgreSQL extensions.
3. Create the shioricafe_schema application schema.
4. Execute the approved table creation scripts in dependency order.
5. Create the required constraints and indexes.
6. Create database functions and triggers.
7. Create the required database views.
8. Insert approved initial records when seed scripts are available.
9. Verify that the database structure matches DATABASE_SPEC.md.

Database initialization scripts must be reviewed before execution.

## 7. Database Resource Maintenance

To maintain an organized and reliable database structure, the following practices must be followed:

- Use numbered SQL filenames to maintain execution order.
- Keep database migration scripts separate from application source code.
- Update DATABASE_SPEC.md whenever an approved database design change is made.
- Keep outdated SQL scripts in the archive directory.
- Do not execute archived scripts as part of the current database setup.
- Keep real database credentials outside version control.
- Review database changes before applying them to the target database.
- Maintain a clear record of completed database migrations.

## 8. Summary

The SHIORI Café project organizes its PostgreSQL database resources using SQL initialization and migration scripts instead of framework-specific Laravel migrations.

The migrations directory contains scripts for creating and maintaining database structures, the seeds directory is reserved for approved initial or sample data, and the archive directory retains older database scripts for reference.

This organization supports consistent database setup, easier maintenance, and controlled future database updates.