---
name: feature-workflow
description: >-
  Standard plan-to-branch workflow for a feature, bug fix, or change whose scope is clear. Use when the user invokes `$feature-workflow` or an approved Shiori Café implementation slice is ready to plan. If scope is unclear, clarify and split it with the user first; use `project-workflow` only if that skill is installed.
---

# Feature Workflow

From the Shiori Café repository root, read and follow `docs/feature-workflow.md` before applying this skill. It contains the steps, skill prerequisites, conflict rulings, UI scope, test-integrity rules, browser credential boundaries, review gates, and branch completion guidance.

First read the project’s `AGENTS.md` and approved specification. Project rules override this workflow. For Shiori Café, Codex must not modify application code or database files; implementation belongs to Cline under the existing project governance.

Use the installed prerequisite skills by their Codex names:

- Superpowers: `writing-plans`, `executing-plans`, `test-driven-development`, `systematic-debugging`, `verification-before-completion`, `receiving-code-review`, `requesting-code-review`, `finishing-a-development-branch`, and `brainstorming` when an implementation choice is unresolved. `using-superpowers` is the Superpowers entry skill.
- Ponytail: `ponytail` and `ponytail-review`.
- Review passes: project-local `code-review` and `security-review`. Run the security pass only when the trust-boundary conditions in the full workflow apply.

If a named review skill is not installed, perform a clearly labeled manual review. Do not claim an unavailable skill ran.
