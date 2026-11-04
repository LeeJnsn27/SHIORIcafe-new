# SHIORI Café Project Plan

Last updated: 2026-10-29

## Purpose and document roles

This file records the current application baseline, known technical risks, and setup dependencies.

- `docs/PHASE1_HANDOFF.md` is the source of truth for approved Release 1 scope and business rules.
- `docs/roadmap.md` is the Phase 0–11 progress checklist and delivery sequence.
- `AGENTS.md` defines the technology constraints and Codex/Cline responsibilities.
- `docs/feature-workflow.md` defines the implementation workflow for an approved, clearly scoped feature.

This plan must stay consistent with those documents. Do not copy unresolved business rules here as if they were decided.

## Approved target stack

- Three-tier web application.
- HTML5, CSS3, Bootstrap 5.3.3 dark theme, and vanilla JavaScript.
- PHP 8.2+ with PDO.
- PostgreSQL 18+, database `grp10_shioricafedb`, schema `shioricafe_schema`.
- All SQL table references use the `shioricafe_schema.` schema prefix.
- The eight-table list in `AGENTS.md` is a starting proposal. Phase 1 must review it against Release 1 requirements and obtain approval for any changes.

## Current project state

- Phase 1 planning is in progress. The unresolved decisions and remaining specification tasks are listed in the handoff and roadmap.
- The workspace contains root PHP pages and the `admin/`, `backend/`, and `frontend/` trees.
- The scratch application uses `mysqli` and MySQL-specific SQL. It does not yet meet the approved PDO/PostgreSQL target and is not counted as completed target-stack implementation.
- The Git working tree reports tracked legacy files deleted and the current PHP rewrite and documentation untracked. This is an unreconciled workspace state, not a completed migration or release.
- `public/vercelthings/README.md` describes an older Vercel/Node layout. Vercel is now the selected public class-project deployment target; this old layout still needs reconciliation with the approved PHP runtime plan.

## Technical risks to audit against the current source

These issues were recorded during earlier inspection. Verify them against the files before implementation planning relies on them:

- Reservation labels, seeded table identifiers, availability queries, and reservation inserts may not agree with one schema.
- Earlier reservation SQL used interpolation in some paths, and a unique start-time key alone does not prevent overlapping reservations. The target implementation needs server-side validation and concurrency-safe collision handling.
- Existing database configuration and SQL scripts may use different database names and MySQL assumptions.
- Contact and staff pages may rely on tables, statuses, or fields absent from the supplied SQL definitions, including inquiries and audit data.
- Some PHP pages may reference removed script paths instead of the current `frontend/` assets.
- Earlier pages showed character-encoding problems. Verify UTF-8 handling across source, database, and responses.
- Review endpoint feedback, authentication, authorization, and staff mutations before any public deployment.

## Setup decisions still required

- The owner selected Vercel Hobby for the public class-project deployment. Vercel lists PHP as a community runtime, not an official runtime; verify the `vercel-php` runtime against the server-side PHP app, session handling, and request routing before implementation relies on it.
- Hosting choices: Vercel Hobby, Neon Free PostgreSQL, and Google Apps Script for reminder emails. In Phase 1, check PHP compatibility and define one secure endpoint for scheduled reminders. Apps Script consumer accounts allow 100 email recipients per day. Free-tier limits can change.
- The group-project demo uses PayMongo test mode with QR Ph, with no live transactions or processing fees. Confirm live payment setup only if the project later moves to production.
- Local development and database setup steps that match the chosen tooling.

Do not put real credentials in source control. Write the root `README.md` and safe `.env.example` after the required local and production setup choices are known.

## Next work

Follow the unchecked Phase 0 and Phase 1 tasks in `docs/roadmap.md`. Do not begin implementation until the required business rules, schema/API contracts, acceptance criteria, and TDD matrix for the first slice are approved.
