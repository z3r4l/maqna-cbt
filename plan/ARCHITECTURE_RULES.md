# Architecture, security, and operational constraints

## Backend
- CodeIgniter 4 on PHP supported runtime; Shield authentication; MySQL/MariaDB. Verify exact package versions and install commands against official docs in agent environment.
- Existing `backend-overlay/` is a *demo overlay*, not a ready production backend. If `backend/` absent, scaffold official CI4 skeleton via Composer and import application code **selectively**; replace old bearer token/seeder demo auth, do not keep conflicting auth systems.
- Organize: `app/Controllers/Api/V1/{Auth,Academic,Questions,Exams,Attempts,Results,Reports,Monitoring}`, `app/Services/*`, `app/Policies/*`, `app/Models/*`, `app/Database/{Migrations,Seeds}`, `app/Commands/*`, `app/Validation/*`, `app/Config/{Routes,Filters,AuthGroups}`.
- No business-critical logic in controller method bodies; services transaction boundaries; policies gate every row-level sensitive operation.
- SQL constraints and indexes as well as application validation. Use DB transactions and row locking for start/submit/grading/release to avoid concurrency flaws. Never depend on javascript-only enforcement.
- Use `school_id` in relevant entities; teacher scopes are on subject + class_group + semester, not simple author alone; regular student scope is assignment/attempt owner.
- API version `/api/v1`; standard JSON errors and explicit HTTP status. No exposing stack traces in production.
- Shield session cookie same-origin; CSRF token endpoint/meta handling; browser fetch `credentials:'same-origin'`, a CSRF header on mutating requests, and JSON 401/403. Avoid token storage in localStorage. Proper logout/login/rotation and credential checks.
- Rate limit login, reset, exam access-code, uploads, and autosave endpoints appropriately (avoid self-DOS). All changes auditable without storing password/token/PII dumps.
- Private image/document assets with authorized access; sanitize rendered rich-text, reject executable files, limits on size/MIME, protect SVG if accepted (prefer raster images).

## Data integrity
- Store `users`/auth identities using Shield conventions; no copied password columns in profiles.
- Snapshot question text/options/key at exam publication; immutable for published version. For student response strip keys, `is_correct`, hidden rubric, raw snapshot JSON.
- At attempt start, persist stable question and option order, `deadline_at`, `attempt_no`, device session identifier (only as needed), and all target constraints atomically.
- Autosave revision monotonically increases per answer and is compared on server; when stale, return 409 with latest server revision/data. Frontend saves sequentially per question and resolves 409 safely.
- Attempts state machine with explicit transitions; submit/expire idempotent. A background `spark` command finalizes expired attempts even when client offline.
- Scoring from frozen data. Essay requires server-side manual grading and audit. Final results tracked per attempt, separate `graded_at` and `released_at`.
- Manual release transactional, refuses active/pending attempts/essays; no leakage via reports or alternative endpoints to student.
- Distinct access rules for view own result vs review answers/key vs teacher report.

## Frontend
- React + TypeScript + Vite. Reuse existing `frontend/`; migrate demo adapters rather than duplicate entire app.
- Actual shadcn/ui primitives installed/configured correctly; Radix where suitable; Tailwind v4. `src/components/ui` are local source components.
- Centralized API client/query hooks, Zod (or equivalent) runtime validation where appropriate, React Router guards in addition to backend authorization. No direct production data mocked.
- Teal palette `#155451`, hover `#0D403D`, soft `#E5F3EF`, bg `#F5F6F7`, success `#27B87A` as **inspired estimates**; no external Testora screenshots as bundled assets.
- Desktop sidebar and persistent exam palette; mobile responsive topbar and Sheet/Drawer; 320px width supported; keyboard access, visible focus, semantic labels, tap targets ~44px.
- Network progress (saving/saved/error), unsynced answer indication, graceful retry, and confirmed submit dialog. Don't mark 'saved' before server ack.

## Security + privacy negative tests
- Cross-school, cross-class, wrong teacher, student impersonating teacher, student accessing unreleased results, published exam editing, forged answer key/score, randomization leakage, time bypass, duplicate attempt race, stale autosave race, XSS from question HTML, unauthorized media, malicious CSV/Excel content.
- Client tab switch detection is telemetry, not 'fraud proof'. Do not automatically punish students solely based on visibilitychange.
- No SMS/email to real recipients, no deployment, no prod database modification without user approval and explicit credentials. Fake data for seeds.

## Dependencies
Use supported stable versions available to environment; avoid unreviewed major upgrades. Document exact versions in README/lock files. If internet unavailable do not fake installation/build success; record blocker.
