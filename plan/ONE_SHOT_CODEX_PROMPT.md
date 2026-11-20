# MASTER PROMPT — IMPLEMENT MAQNA-CBT S0–S7 (ONE SHOT)

You are the **lead full-stack engineer, security reviewer, QA engineer, and technical writer** for this repository. **IMPLEMENT THE APPLICATION**; do not stop after generating plans, mock UI, or placeholder endpoints. Execute as many stages S0 through S7 as the current workspace/tool constraints permit, respecting `plan/control.json`. Work autonomously; do not ask routine permission after each stage when `require_review_after_each_stage=false`.

## 1) READ FIRST (before editing code)
- `AGENTS.md`
- `plan/README.md`
- `plan/control.json`
- `plan/DECISIONS.md`, `plan/ARCHITECTURE_RULES.md`, `plan/DATA_MODEL.md`, `plan/API_CONTRACT.md`, `plan/UI_PAGES.md`, `plan/TEST_SCENARIOS.md`
- `plan/FEATURE_MATRIX.csv`, **all** `plan/stages/S0.md` through `S7.md`
- `plan/references/FRS_SEKOLAH_MADRASAH_v1.1.md`
- `plan/references/architecture/MAQNA_CBT_ERD_PERMISSION_MODULES_v1.2.md`
- `plan/references/design/MAQNA_CBT_DESIGN_SYSTEM.md`
- Inspect actual repo source, package versions, existing migrations/routes/components, and Git status; do not overwrite unrelated work.

## 2) MISSION & SCOPE
Build a **fully functional, responsive online examination platform for one school/madrasah**, named **maqna-cbt**, using **CodeIgniter 4 PHP REST API + CodeIgniter Shield**, MySQL/MariaDB, **React + TypeScript + Vite + Tailwind v4 + actual shadcn/ui components**. Design styling inspired by offline design tokens, but no copying external copyrighted assets. Roles: `superadmin`, `school_admin`, `teacher`, `proctor`, `student`.

Implement all **enabled** features FR-01..FR-38 by stages S0–S7. Treat security, data integrity, accessibility and mobile responsiveness as acceptance criteria, NOT optional extras. If feature/stage disabled, do not implement it except minimal interfaces required by an enabled dependency, and explicitly record that exception if unavoidable.

## 3) NON-NEGOTIABLE PRODUCT RULES
1. Student login by **username OR email + password**, with optional student email; Shield identities, no duplicate custom password store.
2. Teacher may **publish an exam** only for their authorized subject + **all target rombel** in active teaching assignments, and within authorized ownership; school admin can publish school-wide.
3. Results are **manually released by authorized teacher/admin**. Before `released_at`, students see no grade, key, explanation, essay rubric details or hidden evaluation data. No immediate result after submit.
4. Attempt deadline is **server authoritative**. Late save rejected; expiry finalized by server/command without relying on open browser.
5. One active attempt per assigned student/exam; maximum attempts enforced atomically. Stable order for shuffled questions/options and exact resume after reload.
6. Autosave is reliable: debounced/queued with answer revision or compare-and-swap; stale requests do not override newer answers; error visible; no false 'saved' state.
7. Exam scoring is backend-only; submit is idempotent; grading uses **frozen exam snapshots**, not editable live question bank. Essay grading manual and blocks result release while incomplete.
8. RBAC MUST enforce **group + granular permission + school_id + object ownership/teaching assignment/student assignment** on backend endpoints; UI guards are supplementary only. Student must never see others' data.
9. Sensitive operations audited with redaction; protect upload MIME/size, XSS, CSRF, session fixation, privilege escalation, rate limits; use same-origin session with Vite /api proxy in dev.
10. All dashboard and exam pages accessible/responsive for desktop and mobile; native-semantic shadcn components and keyboard navigation.

## 4) EXECUTION LOOP (repeat S0 → S7)
A. Read gate/DoD for current stage and allowed FRs in `control.json`.
B. Set stage `in_progress` in `plan/STATUS.md` / `plan/PROGRESS.json`; list intended files and test commands **before work**.
C. Implement end-to-end **real backend, migrations/seeders, policies, API contracts, React screens, and tests**, replacing demo-only behavior as needed. No TODO stubs advertised as done.
D. Run the stage's checks and negative/security tests. When they fail, **fix** and rerun, then record actual command/result/time in `QA_REPORT.md`.
E. Only mark stage `done` if acceptance criteria pass. Record changed paths, FR IDs, risks, known limitations in `CHANGELOG.md`. Commit only if repo/workflow permits; do not require git commits to claim progress.
F. If blocked by missing package/network/approval, document exact blocker, files implemented, validation not run, and safe next action; continue only with independent nonblocked work. **Never claim tests ran or stages finished when they did not.**
G. Observe `stop_after_stage` and `require_review_after_each_stage`; when either requests pause, stop after updating files instead of silently continuing.

**Stages:** S0 Auth/Shield/Foundation → S1 School Master Data → S2 Question Bank → S3 Exam Builder & Assignment → S4 Secure Attempt Engine → S5 Scoring & Manual Release & Reports → S6 Imports/Essay/Math/Access Code/Review/Export/Audit → S7 Versioning/Packages/Tab Events/Monitoring/Item Analysis.

## 5) DELIVERY AND QUALITY
- Use `plan/FEATURE_MATRIX.csv` as the full feature checklist, with `plan/TEST_SCENARIOS.md` as the minimal negative/positive test contract.
- API under `/api/v1`; validate inputs server side and return consistent `{data,meta,error}` envelopes; 401 vs 403 vs 409/422/429 correct; pagination/filtering for lists.
- Migrations idempotent and safe for an existing dev DB; **never drop production data**. Use clearly documented local development seed data, only fake accounts.
- Include real local dev setup and `.env.example`, database migration + seed commands, build/test instructions in repo README. Keep secrets out of git.
- Use official shadcn/ui components where feasible; keep `src/components/ui/` installable and app-local. Preserve the maqna-cbt teal token scheme.
- Prefer **small cohesive services/policies and tests** instead of one giant controller.
- Do not deploy, publish packages, buy services, or use external credentials.

## 6) FIRST ACTION AND FINAL ANSWER
First: inspect source and output an implementation inventory; reconcile old demo bearer auth with Shield session auth, then start S0 (not just a plan). After completing all allowed stages, report:
1. Actual completed S0–S7 stages, each with validation evidence.
2. Implemented/missing FRs by ID.
3. File paths created/changed, real run/test commands and results.
4. Blocking issues, security risks, and how to run the project.
5. The updated `plan/STATUS.md` and `plan/PROGRESS.json` as definitive progress evidence.

**START IMPLEMENTATION NOW.** Do not ask me to retype requirements already captured in this repository.
