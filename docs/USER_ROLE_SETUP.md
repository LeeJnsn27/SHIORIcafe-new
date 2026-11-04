# USER AND ROLE SETUP DOCUMENTATION

## 1. Overview

The SHIORI Café system uses PostgreSQL roles to manage and control access to the database. These roles determine which database operations are permitted for each authorized database account.

The database uses group roles to organize privileges and login accounts to establish database connections.

Application users such as Guests, Customers, Cashiers, and Managers do not require individual PostgreSQL login accounts. Instead, the application backend connects to the database using a dedicated runtime account. The application controls each user's permissions through its own authentication and authorization system.

## 2. Intended PostgreSQL Group Roles

The following group roles are proposed for the SHIORI Café database.

### 2.1 app_runtime_grp

Role Type: Group role

Login Permission: NOLOGIN

Purpose:

Provides the database permissions required by the SHIORI Café application during normal operation.

Intended Privileges:

- Connect to the application database.
- Access the application schema.
- Read required application records.
- Insert and update records required by authorized application operations.
- Access required database sequences.

Restrictions:

- Must not have PostgreSQL superuser privileges.
- Must not own the application database or schema.
- Must only receive the permissions necessary for normal application operations.
- Must not be used as a direct login account.

### 2.2 app_readonly_grp

Role Type: Group role

Login Permission: NOLOGIN

Purpose:

Provides read-only database access for authorized reporting, auditing, and database inspection.

Intended Privileges:

- Connect to the application database.
- Access approved database tables and views.
- Read authorized records and reports.

Restrictions:

- Must not insert, update, or delete records.
- Must not modify database structures.
- Must not have PostgreSQL superuser privileges.

### 2.3 app_migration_grp

Role Type: Group role

Login Permission: NOLOGIN

Purpose:

Provides database privileges required for authorized database maintenance and migration activities.

Intended Privileges:

- Execute approved database migration procedures.
- Create and modify authorized database objects.
- Maintain tables, indexes, constraints, functions, and views.

Restrictions:

- Must not be used by the normal application runtime.
- Membership must be limited to authorized database maintainers.
- Must not have PostgreSQL superuser privileges unless separately approved.

## 3. Intended PostgreSQL Login Accounts

The following login accounts are proposed for the SHIORI Café database.

### 3.1 shioricafe_app_login

Account Type: Application runtime login

Assigned Group Role: app_runtime_grp

Purpose:

Used by the SHIORI Café backend to establish a database connection during normal application operation.

Responsibilities:

- Establishes the application's database connection.
- Executes authorized application queries.
- Performs database operations permitted by app_runtime_grp.

Security Restrictions:

- Must not have superuser privileges.
- Must not own the application schema.
- Credentials must be stored securely on the server.
- Must never expose database credentials in frontend code.

### 3.2 shioricafe_readonly_login

Account Type: Read-only login

Assigned Group Role: app_readonly_grp

Purpose:

Provides database access for authorized users or tools that only need to inspect approved records and reports.

Responsibilities:

- Reads approved database records.
- Views authorized database reports.
- Supports read-only database inspection.

Security Restrictions:

- Must not modify database records.
- Must not create or alter database objects.
- Must not have superuser privileges.

This account is optional and should only be created when read-only database access is required.

### 3.3 shioricafe_migration_login

Account Type: Database maintenance login

Assigned Group Role: app_migration_grp

Purpose:

Used by authorized personnel or deployment procedures to execute approved database migrations and structural updates.

Responsibilities:

- Executes approved migration scripts.
- Maintains database structures and related objects.
- Supports controlled database updates.

Security Restrictions:

- Must not be used by the normal application runtime.
- Access must be limited to authorized maintainers.
- Credentials must be stored securely.
- Must not have superuser privileges unless separately approved.

## 4. Application Role and Database Role Mapping

Application roles determine what users can do inside the SHIORI Café application. PostgreSQL roles determine what database connections can do within PostgreSQL.

The intended mapping is as follows:

| Application Role | Database Access | Intended Database Role |
|---|---|---|
| Guest | Public application access through the backend | No individual PostgreSQL role |
| Customer | Access through the application backend | app_runtime_grp |
| Cashier | Access through the application backend | app_runtime_grp |
| Manager | Access through the application backend | app_runtime_grp |

All authenticated application operations use the dedicated application runtime login, shioricafe_app_login.

The application backend checks the signed-in user's application role before allowing protected operations.

Individual customers and staff members do not receive PostgreSQL login credentials.

## 5. Database Privilege Summary

The following table summarizes the intended privileges of each group role.

| Database Role | Intended Privileges | Restrictions |
|---|---|---|
| app_runtime_grp | Required application data access and operations | No superuser or unnecessary ownership privileges |
| app_readonly_grp | Read-only access to approved tables and views | No insert, update, delete, or schema modification |
| app_migration_grp | Authorized database structure and migration operations | Restricted to authorized maintainers |

The login accounts inherit privileges through their assigned group roles.

The final table-level permissions must be determined after the database table definitions and application operations have been finalized.

## 6. Application-Level Authorization

Because Guests, Customers, Cashiers, and Managers use the same application runtime database connection, PostgreSQL does not automatically distinguish their individual application permissions.

The application backend is responsible for checking each user's role before executing protected operations.

Examples include:

- Customers can only access their own account and reservation information.
- Cashiers can record collected cash payments but cannot authorize digital refunds.
- Managers can perform approved administrative actions and authorize digital refunds.
- Guests cannot access protected customer or staff functions.

These permissions must be validated on the server side and must not rely only on hiding buttons or pages in the frontend.

## 7. Security and Credential Management

The following security practices apply to all database accounts:

1. Database passwords must not be written directly into source code.
2. Real database credentials must not be committed to version control.
3. The .env.example file must contain placeholder values only.
4. Actual database credentials must be stored in the server environment or an approved secrets manager.
5. Frontend files must never contain database credentials.
6. Database accounts must receive only the permissions required for their assigned purpose.
7. Unused login accounts must be disabled or removed by authorized administrators.
8. Database credentials must be changed when compromise is suspected or access is no longer authorized.

## 8. Implementation Status

The PostgreSQL group roles, login accounts, and privileges described in this document represent the intended database access structure for SHIORI Café.

The actual PostgreSQL roles, account memberships, and privilege assignments will be created during database role setup.

The exact permissions will be reviewed against the finalized database structure before implementation.

No actual database passwords, API keys, or other credentials are included in this document.

## 9. Summary

The SHIORI Café database uses group roles and login accounts to organize database access.

The app_runtime_grp role supports normal application operations, app_readonly_grp provides restricted read-only access, and app_migration_grp supports authorized database maintenance.

The application backend uses a dedicated runtime login account, while individual Guests, Customers, Cashiers, and Managers are authorized through application-level permission checks.

This structure separates application permissions from database permissions and helps protect the database from unauthorized access.