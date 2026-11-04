# APPLICATION USER ROLES DOCUMENTATION

## 1. Overview

The SHIORI Café system uses role-based access control to manage the permissions and activities of different users within the application.

Each application role has specific responsibilities and access permissions depending on the user's purpose in the system. These permissions help protect customer information, maintain operational security, and ensure that users can only perform actions authorized for their assigned roles.

The application has four main user roles: Guest, Customer, Cashier, and Manager.

Application roles are managed by the system's authentication and authorization logic. They are separate from PostgreSQL database roles. The application backend connects to the database through a dedicated runtime database account rather than giving every customer or staff member an individual PostgreSQL login.

## 2. Application User Roles

### 2.1 Guest

A Guest is a visitor who uses the application without registering or signing in.

Responsibilities:
- Browse public pages and the café menu.
- View publicly available menu information.
- Access the customer registration and sign-in pages.

Functions and Data Access:
- Can view public information.
- Can register for a customer account.
- Cannot create reservations or access customer account information without signing in.
- Cannot access staff functions.

### 2.2 Customer

A Customer is a registered user who can access reservation services and manage their own account.

Responsibilities:
- Register and sign in to a customer account.
- Browse table availability.
- Create and cancel personal reservations.
- Respond to reservation reminders and adjustment proposals.
- Communicate with the Manager through reservation-linked chat.
- Request reservation extensions.
- Notify staff when leaving the assigned table.

Functions and Data Access:
- Can view and update only their own account information.
- Can create and manage their own reservations.
- Can respond to reservation actions and communicate with the Manager.
- Can request extensions and signal departure.
- Cannot access other customers' private information.
- Cannot access staff management, menu management, or financial administration functions.

### 2.3 Cashier

A Cashier is an authorized staff member responsible for handling customer transactions and daily café operations.

Responsibilities:
- Sign in using an authorized staff account.
- Check in arriving customers.
- Create walk-in orders and assign tables.
- Record customer orders.
- Update order preparation status.
- Record cash payments after collecting the payment.
- Monitor reservation and session notifications.
- Remind customers about their scheduled departure time.

Functions and Data Access:
- Can access operational reservation and order records needed for cashier duties.
- Can check in customers and manage walk-in transactions.
- Can create and update orders.
- Can advance order preparation statuses.
- Can record collected cash payments.
- Cannot issue refunds.
- Cannot override provider-confirmed digital payment status.
- Cannot manage staff accounts or perform Manager-only administrative actions.

### 2.4 Manager

A Manager is an authorized staff member responsible for supervising café operations and performing restricted administrative actions.

Responsibilities:
- Sign in using an authorized Manager account.
- Supervise reservations and daily café operations.
- Propose reservation table or time adjustments.
- Resolve reservation concerns and customer requests.
- Confirm that customers have physically left their assigned tables before completing occupied sessions.
- Manage menu items, stock, and table information.
- Manage Cashier accounts.
- Authorize digital refunds.
- Perform other restricted operational actions.

Functions and Data Access:
- Can access operational records required for management.
- Can perform the operational functions available to Cashiers.
- Can propose reservation changes and manage reservation-related concerns.
- Can manage menu information, stock, and tables.
- Can create Cashier accounts.
- Can authorize digital refunds and provide the required refund reason.
- Cannot treat an unconfirmed digital payment or refund as successful.
- Does not automatically receive PostgreSQL superuser privileges.

## 3. Application Role Permissions

The following table summarizes the permissions assigned to each application role.

| Application Function | Guest | Customer | Cashier | Manager |
|---|---|---|---|---|
| View public pages and menu | Yes | Yes | Yes | Yes |
| Register and sign in as customer | Yes | Yes | No | No |
| Sign in to staff account | No | No | Yes | Yes |
| Browse availability and create or cancel own reservations | No | Yes | No | No |
| Respond to own reminders and adjustments | No | Yes | No | No |
| Use reservation-linked chat | No | Yes | No | No |
| Request own reservation extension | No | Yes | No | No |
| Signal departure | No | Yes | No | No |
| Check in arriving customers | No | No | Yes | Yes |
| Create walk-in orders and assign tables | No | No | Yes | Yes |
| Advance order preparation status | No | No | Yes | Yes |
| Record collected cash payments | No | No | Yes | Yes |
| Propose reservation changes | No | No | No | Yes |
| Manage menu and stock | No | No | No | Yes |
| Manage table information | No | No | No | Yes |
| Create Cashier accounts | No | No | No | Yes |
| Authorize digital refunds | No | No | No | Yes |

## 4. Application Roles and Intended Database Roles

Application roles and PostgreSQL database roles have different purposes.

Application roles determine what a person can do after signing in to the SHIORI Café application. PostgreSQL database roles determine what a database connection is permitted to do directly within PostgreSQL.

The application backend uses a dedicated database runtime account to communicate with PostgreSQL. Individual customers and staff members do not need separate PostgreSQL login accounts.

The intended database role mapping is as follows:

### 4.1 Application Role Mapping

Guest:
- Application access: Public pages and registration.
- Database access: No direct database login.
- Database role mapping: No individual PostgreSQL role.

Customer:
- Application access: Own account, reservations, reservation actions, and linked chat.
- Database access: Through authorized application backend operations.
- Database role mapping: Uses the application's runtime database connection, app_runtime_grp.

Cashier:
- Application access: Reservation check-in, walk-in orders, order preparation, and collected cash payment recording.
- Database access: Through authorized application backend operations.
- Database role mapping: Uses the application's runtime database connection, app_runtime_grp.

Manager:
- Application access: Operational management, reservation adjustments, menu and stock management, Cashier account management, and digital refund authorization.
- Database access: Through authorized application backend operations.
- Database role mapping: Uses the application's runtime database connection, app_runtime_grp.

### 4.2 Intended PostgreSQL Database Roles

app_runtime_grp

Purpose:
The database role used by the SHIORI Café application backend during normal system operation.

Responsibilities:
- Allows the application to perform approved database operations.
- Provides the database access required by the application.
- Serves as the database connection role for Guest, Customer, Cashier, and Manager application workflows.

Security Restrictions:
- Must not have PostgreSQL superuser privileges.
- Must receive only the database permissions required by the application.
- Must not be used as a personal login by individual customers or staff members.

A separate database migration or administration role may be configured for authorized database maintenance and schema changes. It must not be used as the normal application runtime account.

## 5. Role-Based Access Control and Security

The SHIORI Café system follows role-based access control to prevent unauthorized access to application functions and data.

The following security rules apply:

1. Users must authenticate before accessing protected application functions.
2. Customers can only view and manage their own account and reservation information.
3. Cashiers are restricted to the operational functions assigned to their role.
4. Cashiers cannot issue refunds or override digital payment status.
5. Managers are authorized to perform restricted management and refund actions.
6. Only authorized Managers can create Cashier accounts.
7. Database credentials must remain on the server and must never be exposed in frontend code.
8. The application must validate user permissions on the server side before performing restricted operations.
9. PostgreSQL database permissions must follow the principle of least privilege.
10. Real database passwords, API keys, and other credentials must not be included in project documentation or committed to version control.

## 6. Summary

The SHIORI Café application uses four application roles: Guest, Customer, Cashier, and Manager. Each role has specific responsibilities and permissions based on the functions required by the system.

The application handles user authentication and authorization, while PostgreSQL database roles control the permissions of database connections.

The app_runtime_grp role is intended for normal application database access. Individual application users do not require separate PostgreSQL login accounts.

This separation helps maintain application security, protect database credentials, and ensure that users can only perform authorized activities within the SHIORI Café system.