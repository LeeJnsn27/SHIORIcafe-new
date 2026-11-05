
# AGENTS.md — Shiori Café (栞)

## 1. Project overview

Shiori Café is a quiet, study-focused café for students, remote workers, and book lovers. It provides time-based study nook reservations and table-side digital ordering.

The application uses a classic three-tier architecture: presentation, business logic, and database. Development is specification-driven. Prepare software design documents and test matrices before implementation.

## 2. Technology and environment

- Presentation: HTML5, CSS3, Bootstrap 5.3.3 with a dark theme, and vanilla JavaScript (ES6+, Fetch API/AJAX). Do not use heavy frontend frameworks such as React or Vue.
- Application: PHP 8.2+, server-side routing, input validation and sanitization, session authentication guards, and PDO database access.
- Database: PostgreSQL 18+, database `grp10_shioricafedb`, schema `shioricafe_schema`. The current eight-table model is a proposal, not an approved final schema.

## 3. Agent responsibilities and boundaries

### Codex — strategy, planning, and architecture

**Zero application-code and database-file modification permission.** Codex must not edit, write, refactor, or delete application code or database files.

Codex may:

1. Work with the user on feature workflows and business rules.
2. Write and maintain Markdown specifications, software design documents, API contracts, database-query designs, and system boundaries.
3. Prepare TDD matrices, edge cases, collision cases, and validation rules.
4. Review architecture and code without modifying application code or database files.

### Cline — implementation and execution

Cline implements features from user-approved SDDs and TDD matrices. Cline reads this file and the relevant specification, writes PHP/PDO backend code and endpoints, creates Bootstrap and vanilla JavaScript views, and runs approved migrations and tests. Security work should use PDO prepared statements and clear separation of concerns.

These boundaries take precedence over a general workflow skill. A workflow may plan implementation, but it does not grant Codex application-code or database-file write permission.

## 4. Normalized database schema

All SQL written by agents must qualify tables with `shioricafe_schema.`. The current eight-table proposal is the starting point for Phase 1 schema review. Review required tables and fields against approved features, and obtain owner approval for changes before implementation. The proposed tables are:

1. `shioricafe_schema.customer`: `customer_id` (PK), `name`, `email` (unique), `password`, `phone`.
2. `shioricafe_schema.table`: `table_id` (PK), `table_number` (unique), `capacity`, `status`.
3. `shioricafe_schema.staff`: `staff_id` (PK), `name`, `role`, `email` (unique), `password`.
4. `shioricafe_schema.category`: `category_id` (PK), `category_name` (unique).
5. `shioricafe_schema.reservation`: `reservation_id` (PK), `customer_id` (FK), `table_id` (FK), `reservation_date`, `start_time`, `end_time`, `status`, `cancellation_reason`.
6. `shioricafe_schema.menu_item`: `menu_item_id` (PK), `category_id` (FK), `name`, `description`, `price`.
7. `shioricafe_schema.order`: `order_id` (PK), `reservation_id` (nullable FK), `table_id` (nullable FK), `staff_id` (nullable FK), `order_time`, `status`, `total_amount`.
8. `shioricafe_schema.order_item`: `order_item_id` (PK), `order_id` (FK), `menu_item_id` (FK), `quantity`, `subtotal`.

## 5. Reservation overlap rule

Before inserting a study nook reservation, the backend must check for overlapping reservations on the same table and date. Cancelled and completed reservations do not block availability.

```sql
SELECT COUNT(*)
FROM shioricafe_schema.reservation
WHERE table_id = :table_id
  AND reservation_date = :reservation_date
  AND status NOT IN ('Cancelled', 'Completed')
  AND (start_time < :end_time AND end_time > :start_time);
```

## 6. Communication and writing

- Put the most important information where the reader will see it first.
- Use plain, specific language. State each fact once.
- Correct mistaken assumptions directly and explain the reason.
- Optimize for clarity and engineering value.
- Prefer the simplest domain term that communicates the meaning. Avoid overloaded terms.
- Be concise when speaking to the user. Prefer one clear paragraph or sentence when it preserves the needed detail.
- Avoid these phrases: “load-bearing,” “worth stating plainly,” “here’s the honest truth,” “the real tension,” and “carry the argument.”
- Avoid excessive em dashes and chained dashes, semicolons, fragments, nonstandard punctuation, flattery, praise without reason, and motivational language.

## 7. Operational boundaries

- Deliver only the requested scope. Do not expand work into adjacent cleanup, refactoring, documentation, or features unless requested or necessary to complete the task.
- Do not add speculative abstractions for possible future requirements.
- Do not claim work is complete without evidence. Run the relevant command and inspect its output before reporting verification.
- Never add a co-author to a commit message.
