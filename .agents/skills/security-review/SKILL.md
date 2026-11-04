---
name: security-review
description: Review changed code and its supporting paths for security defects at trust boundaries. Use as the gated security pass in feature-workflow or when explicitly requested.
---

# Security Review

Review the diff and trace relevant data and authorization paths through the supporting code. Read the project’s `AGENTS.md` and security guidance first. Report findings only; do not make fixes as part of the review.

Check applicable risks, including injection, authentication and authorization, secrets, cryptography, input validation, time-of-check/time-of-use issues, unsafe deserialization, cross-site scripting, and dependency supply chain. Follow trust boundaries identified by the project. Do not imply that this pass covered denial of service, rate limiting, or resource exhaustion unless those were explicitly reviewed.

For every finding, identify the file and line, attacker-controlled input or trust boundary, exploit path, impact, and a focused correction. Distinguish evidence-backed defects from concerns that need confirmation. Do not report generic hardening suggestions as vulnerabilities.

For Shiori Café, respect `AGENTS.md`: Codex may inspect and review code but must not modify application code or database files. Do not spawn subagents unless the user or applicable project instructions explicitly authorize delegation.
