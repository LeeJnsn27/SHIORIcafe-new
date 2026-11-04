# Feature Workflow

This is the standard workflow for a single feature, bug fix, or change whose scope is clear. It covers planning, the Ponytail cut gate, TDD implementation, real-system end-to-end coverage, verification, review, and branch completion. If the work is too unclear to name the questions or slices, use `project-workflow` if it is installed. Otherwise, clarify and split the work with the user before entering this workflow.

## Project authority

Read the project’s `AGENTS.md` and approved scope before acting. Project-specific rules override this workflow. For Shiori Café, Codex has zero application-code and database-file modification permission; this workflow supports specification and review work, while Cline implements approved plans.

## Constraints used throughout

### Ponytail scope: backend open, UI closed

Apply Ponytail fully to backend, server, build, and tooling code. Remove speculative abstractions, dependencies, and files there when they are not needed for the requested behavior.

Do not cut requested user-facing UI behavior. Treat these as explicit requirements in UI code:

- Loading, empty, and error states.
- Keyboard navigation, focus order, and Escape/Enter behavior.
- Undo and optimistic updates already used in the surrounding UI.
- States and transitions defined by the design system.

For UI, “lazy” means using fewer elements and reusing existing primitives and design tokens. It does not mean removing states or replacing a designed control with a plainer one. Trade UI behavior for performance only when measurement shows that the current version is too slow.

Ponytail 4.8.4 already protects design-system controls, but its first ladder rung can mistake a UI state for speculative scope. This UI rule resolves that gap.

### Ponytail re-arm

Ponytail’s full ruleset must be deliberately re-read at step 6 and after every context compaction. Invoke the installed Ponytail skill (`$ponytail` in Codex) to re-arm it. Do not rely on a status badge or flag file: those can outlive the rules in context. A `/ponytail` one-line banner is not equivalent to reloading the skill.

### Fixed conflict rulings

- If Ponytail’s “one runnable check, no frameworks” conflicts with Superpowers TDD, TDD wins.
- If Ponytail’s first ladder rung suggests cutting UI surface, the UI scope above wins.
- If an implementer says a finding was left “per YAGNI” and a reviewer identifies a defect, the reviewer’s severity stands. YAGNI can limit speculative construction; it cannot excuse a demonstrated defect.
- If an overlapping skill auto-invokes, use the skill explicitly named by the current step. Matt Pocock’s skills are reserved for explicit `wayfinder`, `to-spec`, and `to-tickets` requests; do not let its overlapping auto-invoked skills replace this workflow’s named Superpowers skills.

### Test integrity

A test that has never failed has not demonstrated that it detects the behavior it claims to cover. Before considering a test complete, deliberately break the covered behavior and confirm the test fails. Apply this mutation check to every level, including end-to-end tests. If it remains green, rewrite or remove it.

- Capture fixtures from real API responses, sessions, or uploads and commit the captured payload. Do not invent fixtures for data that can be captured.
- Mock only at an outer boundary such as a third-party service, clock, or network. Do not stub code between the entry point and assertion.
- Derive expected values independently by hand. Do not compute expected results with the code under test.

Superpowers’ mutation check applies to unit tests; this workflow applies it to E2E as well.

### Browser automation and credentials

Live browser automation is a probe, not a repeatable suite. Any issue it finds must become a committed, rerunnable test or specification.

Use the browser driver required by the project. If none is named, choose an available driver suited to the target. Do not assume Claude-specific browser tools exist in Codex. If the required browser driver is unavailable, state that and use the project’s real-process runner where appropriate; do not claim a live-browser test was performed.

Stop at a login wall. Ask the user to log in themselves in the browser, then continue after they say it is done. Never type credentials from the conversation, `.env`, environment variables, configuration, a password manager, or an earlier transcript. The same applies to MFA, SSO, and CAPTCHA. After user login, do not capture or transcribe pages that expose tokens, session cookies, or account secrets. Testing a login flow is the only exception: use a throwaway test account supplied by the user and ask before the first attempt.

## Entry gate

- Bug or small fix: start at step 7, with `systematic-debugging` first.
- Spec or plan already decided: start at step 5.
- Scope too unclear to name the questions: pause this workflow and use `project-workflow` if installed; otherwise clarify and slice the work with the user first.

## 5. Plan one slice

Use Superpowers `writing-plans` to turn the decided scope into a sequence of changes and files, one plan per slice. Do not repeat brainstorming when a map or spec already settled the design. Use brainstorming only for implementation choices that remain open.

## 6. Re-arm Ponytail and inspect the plan

Before any code is written, invoke `$ponytail` to reload the complete rules. Review the plan against the ladder. Remove speculative abstractions, files, dependencies, and configuration for values that never change, while respecting the UI scope rules above. Planning is the right time to trim speculative work; understanding the full task is still required.

## 7. Execute with TDD

Use Superpowers `executing-plans` and `test-driven-development`. For a bug, run `systematic-debugging` first. Write a failing test, confirm it fails for the expected reason, implement the smallest complete change, and confirm it passes. TDD takes precedence over Ponytail’s single-check suggestion.

## 8. Prove the user stories against the real system

Derive scenarios from numbered user stories and the existing test matrix. One story maps to one E2E scenario. If there is no spec, enumerate the user-visible outcomes before testing.

Keep the path real from fixture through endpoint/handler and processing to an independently derived expected result. Use the project’s prescribed driver. Otherwise:

- Web app in a real browser session: use an available real-browser driver.
- Desktop or Electron app: use Playwright over CDP.
- HTTP service, CLI, or worker: use the project runner against a real process.

Enumerate relevant cases: empty, zero, one, many; malformed and wrong-type input; unauthorized and expired authentication; slow, failed, or partial network; duplicate and concurrent submissions; Unicode, very long strings, and injection-like payloads; offline or interrupted flows.

Apply the fixture, mock-boundary, hand-derived expectation, and mutation-check constraints above. Superpowers 6.3.0 does not provide E2E/browser coverage, so this step is separate.

## 9. Verify before completion

Use Superpowers `verification-before-completion`. Run the relevant commands, read their output, and report only what the evidence supports. Respect project rules about whether tests may be run.

## 10. Review in order

Use these separate review passes and report findings before changing the code:

1. `$ponytail-review`: over-engineering and dead flexibility.
2. `$code-review`: correctness, requirements coverage, reuse, simplification, and efficiency. Do not use an auto-fix mode.
3. `$security-review`: run when the diff touches authentication, sessions, tokens, permissions, input parsing, deserialization, file/path handling, shell or SQL construction, cryptography, secrets, credential storage, network-facing handlers, or new dependencies. Include any trust boundary named by the project. If a review skill is unavailable, perform and label a manual review rather than claiming the skill ran.

The passes have distinct scopes. Ponytail review does not replace correctness or security review. Code review excludes security. Security review covers injection, authentication/authorization, secrets, crypto, input validation, TOCTOU, deserialization, XSS, and supply chain. It does not cover DoS, rate limiting, or resource exhaustion unless the project adds those requirements.

Do not use `/simplify` as a substitute: it applies changes before the findings are reviewed. When acting on findings, use Superpowers `receiving-code-review`; check real usage before implementing a suggestion that may be speculative.

## 11. Finish the branch

Use Superpowers `finishing-a-development-branch` to decide whether to merge, rebase, or abandon according to the user’s stated intent and project permissions. Do not commit, merge, or publish without authorization.

## Failure modes to avoid

- Applying Ponytail to UI without the UI scope rule and deleting required states.
- Trusting a badge or flag instead of re-arming the skill.
- Accepting an E2E test that was never shown to fail after mutation.
- Running `/simplify` before reading and approving review findings.
- Claiming a Claude-specific browser run when no such driver is available.
