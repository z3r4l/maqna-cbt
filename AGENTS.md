# Repository instructions for Codex — maqna-cbt

This repository is the **maqna-cbt** school/madrasah examination system. Read `plan/README.md`, `plan/control.json`, `plan/DECISIONS.md`, and `plan/ONE_SHOT_CODEX_PROMPT.md` before making changes. `plan/` is the human-editable source of truth for delivery.

## How to work
1. Inspect current code and actual tooling first. Existing root folders include `frontend/`, `backend-overlay/`, `design/`, `setup-backend.sh`. Reuse safe code; migrate unsafe demo auth instead of layering it.
2. Implement enabled stages S0 → S7 in order. Follow `plan/stages/S*.md`; pass acceptance gates before marking each stage completed.
3. Respect `plan/control.json`: do not implement disabled features/stages. Never silently enable a dependency excluded by the user; record a blocker.
4. Before and after each stage, maintain `plan/STATUS.md`, `plan/PROGRESS.json`, `plan/QA_REPORT.md`, and `plan/CHANGELOG.md` with **actual results only**. If tests cannot run, mark `blocked`/`unverified`, not `done`.
5. CodeIgniter 4 + Shield session auth via same-origin `/api/v1`, React + TS + Vite, shadcn/ui + Tailwind v4. MySQL/MariaDB target. Use policies for resource ownership, teacher's subject+rombel, school scope.
6. Requirements and security invariants: `plan/DECISIONS.md`, `plan/ARCHITECTURE_RULES.md`, `plan/TEST_SCENARIOS.md`. No keys in student API, server authoritative deadlines, idempotent submit, CAS/revisions for saves, result release MANUAL.
7. Prefer additive migrations; NEVER drop/reset production data, leak secrets, deploy, or change external services without explicit permission. No real user credentials in git. Use fake fixtures.
8. Run appropriate checks: `php spark migrate`, PHPUnit/API suite, `npm run build`, frontend tests/lint (once configured), e2e smoke tests; record evidence.
9. Use only your actual tools and environment. Do not pretend you ran commands. If network blocked, document the exact commands and complete independent work.
10. No branding copied from original Testora assets. App name **maqna-cbt** only; existing visual reference is inspiration with independent offline SVGs.

## One-shot user instruction
Start from `plan/ONE_SHOT_CODEX_PROMPT.md`. If the user supplies only "Implementasikan plan/ONE_SHOT_CODEX_PROMPT.md", act on that file; do not merely rewrite the plan.
