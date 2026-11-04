---
name: code-review
description: Review a code change for correctness, requirements coverage, reuse, simplification, and efficiency. Use for an explicit code review or as the correctness pass in feature-workflow.
---

# Code Review

Review the requested diff and the supporting code needed to understand its behavior. First read the project instructions and the originating specification or issue. Report findings only. Do not edit code or apply fixes during review.

Check for:

- Incorrect behavior, edge cases, regressions, and missing error handling.
- Requirements that are missing, partial, or implemented incorrectly.
- Unrequested behavior or scope growth.
- Existing helpers or patterns that should be reused.
- Clear simplifications or efficiency problems supported by the code.

For every finding, provide the file and line, the concrete behavior that causes the problem, its impact, and a focused correction. Separate confirmed defects from lower-confidence suggestions. Do not report style preferences as defects. Do not duplicate security findings handled by the security-review pass.

For Shiori Café, respect `AGENTS.md`: Codex may inspect and review code but must not modify application code or database files. Do not spawn subagents unless the user or applicable project instructions explicitly authorize delegation.
