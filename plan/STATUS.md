# IMPLEMENTATION STATUS — Codex updates this continuously

## Current task
- Task: Interactive student runner for true/false and matching answers DONE (2026-11-21 Asia/Jakarta).
- Changed: true/false now uses a desktop table and mobile answer cards; matching uses SVG connections with drag, tap, keyboard, and clear controls on desktop/mobile. Existing answer payload, autosave and backend are unchanged.
- Verification: `npm run test:runner-interactions` PASS (mock API, Edge headless; interaction, uniqueness, autosave, clear, resume, failed-save state and 320/390/768/1280 px overflow); screenshot review desktop/mobile PASS; `npm run build` PASS (existing >500 kB chunk warning); `python plan/scripts/validate_plan.py` PASS. Full CI4/MySQL runner smoke NOT RUN.

## Previous task
- Task: Fix stale CSRF token 403 responses DONE (2026-11-20 Asia/Jakarta).
- Changed: API CSRF failures now return `error.code=CSRF_INVALID`; the shared client invalidates its token after every mutation response or network failure and retries only marked CSRF failures once. Added focused backend and browser smoke coverage. No schema or migration changes.
- Verification: `npm run test:csrf`, `python backend/tests/smoke_csrf_api.py`, `python backend/tests/smoke_auth.py`, PHP syntax checks, `npm run build`, and `python plan/scripts/validate_plan.py` PASS. The existing full product browser flows were not run: port 5173 was occupied by an unresponsive process, so the app could not be served there.

## Previous maintenance
- Task: Remove the student runner “Kosongkan jawaban” link DONE (2026-11-20 Asia/Jakarta).
- Changed: removed only the link and clear-answer handler from the mobile status row in `frontend/src/pages/AttemptRunner.tsx`; autosave status stays in place. No API or migration changes.
- Verification: `npm run build` PASS (TypeScript and Vite; existing >500 kB chunk warning); `python plan/scripts/validate_plan.py` PASS. Runner browser smoke NOT RUN because no local app server was listening on ports 8080, 8081, 5173, or 5174. Build required process permission after sandbox `spawn EPERM`.

> Initial state created by design planning, **not** evidence of implementation. Do not mark `done` without running acceptance tests.

| Stage | Status | Features in stage | Verification | Notes |
|---|---|---|---|---|
| S0 | DONE | FR-01,02,04,05 | PASS | Shield session, CSRF, admin reset, profile, scoped policy; see QA report |
| S1 | DONE | FR-03,06 | PASS | Academic CRUD, rollover, school scopes and assignments; see QA report |
| S2 | DONE | FR-07,08 | PASS | Scoped question bank, validation and private images; see QA report |
| S3 | DONE | FR-13..18 | PASS | Exam wizard, publish policy, roster, immutable snapshot and stable attempt order |
| S4 | DONE | FR-22..27 | PASS | Server deadline, CAS autosave, stable resume, idempotent submit and responsive runner |
| S5 | DONE | FR-19,30..33 | PASS | Automatic MCQ grade, manual release, scoped history and reports |
| S6 | DONE | FR-09..11,20,34..36,38 | PASS | Mixed essay, CSV/XLSX imports, KaTeX, code, review, XLSX/PDF export, audit |
| S7 | DONE | FR-12,21,28,29,37 | PASS | Reviewed question versions, equivalent A/B packages, bounded telemetry, scoped proctor roster, private item analytics |

## Previous maintenance
- Stage: Post-S7 tampilan runner attempt sesuai referensi DONE (2026-11-20 Asia/Jakarta); historical S0-S7 entries unchanged.
- Changed files: full-screen responsive attempt runner, guarded route outside shared sidebar layout, attempt metadata response/type, API and browser smoke coverage.
- Verification: `python tests/smoke_attempts.py` PASS including metadata, scope, and answer-key privacy; PHP syntax check PASS; `npm run build` PASS; `node scripts/smoke-s9-runner.mjs` PASS at 320/390/940/1280 px including palette sheet, cancel/confirm, autosave, reload, completion navigation, and retry state; `python plan/scripts/validate_plan.py` PASS.
- Known limitations: Vite retains the existing main JS chunk >500 kB warning. Smoke tests use fake local users and create development exam/attempt records. No migration was needed.

## Previous task
- Stage: Post-S7 jadwal ujian berbasis bank soal DONE (2026-11-20 Asia/Jakarta); historical S0-S7 entries unchanged.
- Changed files: additive exam source/count/archive migration; question-bank and exam API/service/controller/routes; attempt subset selection; responsive `/exams` schedule list and bank-backed dialog; focused API/browser smoke and updated legacy UI smoke.
- Verification: migration run twice; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_exam_bank_schedules.py`, `smoke_exams.py`, `smoke_attempts.py`, `smoke_question_banks.py`, and `smoke_access_code.py` PASS; PHP syntax checks PASS; `npm run build` PASS; full `npm run test:e2e` PASS including `/exams` at 320/390/1280 px; `python plan/scripts/validate_plan.py` PASS.
- Known limitations: Vite reports the main JS bundle exceeds 500 kB; the default PHPUnit command warns/fails when coverage is requested because no coverage driver is installed, while `--no-coverage` passes. API smoke generated fake development records; test draft was archived and the published test exam remains in the local fixture database.

- Stage: Post-S7 formulir pembuatan soal dan editor baru DONE (2026-11-19 Asia/Jakarta); S0-S7 historical completion unchanged.
- Changed files: additive matching-choice, attempt choice-order and private media migrations; question/revision/exam/attempt/result/print/import/export services; private temporary image endpoint; Tiptap editor and responsive question form; student runner/review; API/browser smoke tests and frontend dependencies.
- Verification: migrations 000028–000030 applied and rerun with no new migrations; `php tests/structured_answer_test.php`, `php tests/question_content_test.php`, PHPUnit 6 tests/9 assertions, `python tests/smoke_s9_structured.py`, PHP lint, and plan validation PASS; `npm run build` PASS; final `npm run test:e2e` PASS; focused student matching and bank UI at 320/390/1280 px PASS.
- Known limitation: Vite reports the main JavaScript chunk exceeds 500 kB. Tiptap Mathematics peer metadata excludes the existing KaTeX 0.19 version, so install used npm's legacy peer resolver; formulas passed browser and build checks. Development-only fixture data was used.

### Previous maintenance
- Stage: Post-S1 full-width content layout DONE; S0-S7 historical completion unchanged
- Changed files: shared frontend layout and plan evidence. The right content area now fills the width beside the sidebar with a 16 px inset.
- Verification: `npm run build` PASS; Edge layout measurement PASS at 1900/390/320 px without horizontal overflow; `npm run test:students` PASS at 1280/390/320 px; populated desktop/mobile screenshot review PASS.
- Known limitation: focused student smoke creates a fake row in the dedicated development database; no production data changed.
- Source inventory: S0 has a runnable CI4 4.7.6 + Shield 1.4.1 backend, MySQL dev DB, React frontend, and fake local fixtures; no Git repository
- Files changed: version/package/proctor migrations, services, controllers and routes; frontend revision/proctor/analytics pages, runner heartbeat and serialized CSRF mutations; acceptance tests and operations guide
- Tests run: migration twice, purge command, PHPUnit, ADV-01..05 and S0–S7 regression, `npm run build`, `npm run test:e2e`, plan validation
- Last verified: S7 gate PASS 2026-11-14 23:05 Asia/Jakarta

### S0 – 2026-11-14  [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-01, FR-02, FR-04, FR-05
- Changed files: plan/STATUS.md, plan/PROGRESS.json, plan/QA_REPORT.md, plan/CHANGELOG.md (stage-start evidence)
- Tests planned: `php spark migrate` twice, `php spark test`, `npm run build`, AUTH-01..04
- Known issues: backend skeleton and frontend dependencies are absent; package installation not yet attempted
- Next action: scaffold CodeIgniter 4 and Shield, replace demo auth, run gates

### S0 – 2026-11-14 11:48 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-01, FR-02, FR-04, FR-05
- Changed files: backend/ CI4 scaffold, auth controllers/config/migrations/policy/seeder/tests; frontend auth/profile/reset pages, API client, router, shadcn components; retired backend-overlay demo; setup script and README
- Tests run: `php spark migrate --all` twice PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_auth.py` PASS (login, CSRF, reset, scope, disabled, throttle); `npm run build` PASS; `npm run test:e2e` PASS at 320px, 390px, 1280px
- Known issues: real email/SMS delivery intentionally disabled by `control.json`; reset uses authorized school admin; no production deployment performed
- Next action: implement S1 academic masters and assignment scopes

### S1 – 2026-11-14 11:48 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-03, FR-06
- Changed files: plan/STATUS.md, plan/PROGRESS.json, plan/QA_REPORT.md, plan/CHANGELOG.md (stage-start evidence)
- Tests planned: `php spark migrate --all` twice, PHPUnit, ACD-01..03, `npm run build`, `npm run test:e2e`
- Known issues: no S1 code or test evidence yet
- Next action: add school/year/semester/rombel/subject/teaching/student membership schema, APIs, UI, and tests

### S1 – 2026-11-14 12:08 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-03, FR-06
- Changed files: academic migration/seeder, AcademicCatalog/PeopleService/AssignmentService, academic/people/school/assignment controllers and routes, React admin CRUD/assignment/school pages, dashboard, UI smoke and API smoke
- Tests run: `php spark migrate --all` twice PASS; MySQL schema check 31 foreign keys and 29 indexed columns; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_academic.py` PASS (ACD-01..03, rollover, 403, membership history, no-email student); `npm run build` PASS; `npm run test:e2e` PASS (CRUD pages 390/1280px)
- Known issues: fake smoke rows remain in dedicated development database as inactive/history records; no destructive cleanup
- Next action: implement S2 question bank and private media

### S2 – 2026-11-14 12:08 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-07, FR-08
- Changed files: plan status/progress/QA/changelog
- Tests run: NOT_RUN
- Known issues: question bank not yet implemented
- Next action: add question/option/asset migration, secure API and editor, then QST-01..03

### S2 – 2026-11-14 12:14 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-07, FR-08
- Changed files: question migration, QuestionBank service/controller/routes, private image storage, React question list/editor/preview, API client, QST smoke and Edge smoke
- Tests run: `php spark migrate --all` twice PASS; PHP syntax PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_questions.py` PASS (QST-01..03); `npm run build` PASS; `npm run test:e2e` PASS including teacher create/publish at 390px
- Known issues: question image serving to students during attempts will be added with S4 scope; fake test questions remain in development database
- Next action: implement S3 exam builder and immutable publish snapshot

### S3 – 2026-11-14 12:14 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-13..18
- Changed files: plan status/progress/QA/changelog
- Tests run: NOT_RUN
- Known issues: exam schema and endpoints not yet implemented
- Next action: add exam config, target classes and roster, publish policy, immutable snapshot and student listing

### S3 – 2026-11-14 12:32 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-13..18
- Changed files: exam and attempt-order migrations, ExamService/ExamController, attempt start/resume foundation, React exam wizard/student list, EXM smoke and browser smoke
- Tests run: `php spark migrate --all` twice PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_exams.py` PASS EXM-01..05 including teacher all-class policy, invalid publish, immutable snapshot, persisted stable shuffle, no student keys; `npm run build` PASS; `npm run test:e2e` PASS teacher wizard/publish at 390px
- Known issues: S4 still needs versioned saves, submit, expiry command and responsive runner
- Next action: implement S4 attempt engine

### S4 – 2026-11-14 12:32 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-22..27
- Changed files: plan status/progress/QA/changelog
- Tests run: NOT_RUN
- Known issues: attempt start/resume exists; answer saves, submit, expiry and UI runner are not yet implemented
- Next action: add CAS answer saves, idempotent submit, expiry command and runner

### S4 – 2026-11-14 12:50 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-22..27
- Changed files: attempt mutation migration, AttemptService/AttemptController, expiry Spark command, mobile Sheet runner, API client, ATT smoke and browser smoke
- Tests run: `php spark migrate --all` twice PASS; `php spark attempts:finalize-expired` PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_attempts.py` PASS ATT-01..06 including CAS, retry, device scope, concurrent start/submit, expired cleanup, scoped images; `npm run build` PASS; `npm run test:e2e` PASS runner at 320/390/1280, keyboard, autosave/reload/submit
- Known issues: automatic grading and result release are S5; pending results show no score
- Next action: implement S5 grading and manual release

### S5 – 2026-11-14 12:50 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-19, FR-30..33
- Changed files: plan status/progress/QA/changelog
- Tests run: NOT_RUN
- Known issues: grade/result/report endpoints not yet implemented
- Next action: add deterministic scoring, release transaction, scoped history and reports

### S5 – 2026-11-14 18:19 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-19, FR-30..33
- Changed files: result/release/audit migration, ResultService/ResultController/routes, submit and expiry grading integration, React result history/detail and staff report/release views, RES HTTP smoke and Edge smoke
- Tests run: `php spark migrate --all` twice PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_results.py` PASS; `python tests/smoke_attempts.py` PASS regression; `npm run build` PASS; `npm run test:e2e` PASS
- Known issues: S6 essay grading will add pending-review release gate; fake smoke records stay in dedicated dev DB
- Next action: implement S6 feature expansions

### S6 – 2026-11-14 18:19 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-09..11, FR-20, FR-34..36, FR-38
- Changed files: plan status/progress/QA/changelog stage-start evidence
- Tests run: NOT_RUN
- Known issues: S6 functionality is not implemented yet
- Next action: implement essay review and release gate, imports, access codes, answer review, exports, audit and math display

### S6 – 2026-11-14 19:13 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-09..11, FR-20, FR-34..36, FR-38
- Changed files: additive essay/access code/review/import migrations, QuestionBank/ExamService/AttemptService/ResultService changes, ImportService/ImportController/AuditTrail/AuditController, XLSX/PDF export, KaTeX renderer, React essay grading/import/audit/review views, smoke scripts, README and setup script
- Tests run: `php spark migrate --all` twice PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/run_all_smokes.py` PASS (11 scripts S0–S6 including EXT-01..07); `npm run build` PASS; `npm run test:e2e` PASS (CSV preview/commit, KaTeX with XSS check, runner and prior flows)
- Known issues: Laragon PHP ZIP extension is present but disabled in the default php.ini; verified local server runs with `php -d extension=zip` on port 8081; XLSX endpoints return 503 without it. Vite reports a bundle size warning due to KaTeX fonts. Development fixtures remain in the dedicated dev database.
- Next action: implement S7 advanced features

### S7 – 2026-11-14 19:13 [Asia/Jakarta]
- Status: IN_PROGRESS
- FR IDs: FR-12, FR-21, FR-28, FR-29, FR-37
- Changed files: plan status/progress/QA/changelog stage-start evidence
- Tests run: NOT_RUN
- Known issues: S7 functionality not implemented yet
- Next action: implement question review/versioning, stable exam packages, limited proctor telemetry/monitor, and item analytics

### S7 – 2026-11-14 19:42 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-12, FR-21, FR-28, FR-29, FR-37
- Changed files: question version and exam package migrations/services/routes; proctor event migration/service/retention command; analytics service; React revision, proctor and analytics pages; runner heartbeat; serialized frontend CSRF mutations; HTTP/browser tests; README
- Tests run: `php spark migrate --all` twice PASS; `php spark proctor:purge` PASS; `php vendor/bin/phpunit --no-coverage` PASS (6 tests, 9 assertions); `python tests/run_all_smokes.py` PASS (14 scripts); `npm run build` PASS; `npm run test:e2e` PASS; `python plan/scripts/validate_plan.py` PASS
- Known issues: Vite reports a nonfatal bundle-size warning from KaTeX; PHP coverage driver unavailable, so PHPUnit ran with `--no-coverage`. Telemetry purge requires a daily scheduler in deployment.
- Next action: optional deployment preparation and operational scheduling, subject to separate authorization

### S7 – 2026-11-14 23:05 [Asia/Jakarta]
- Status: DONE
- FR IDs: FR-12, FR-21, FR-28, FR-29, FR-37
- Changed files: transaction lock for proctor heartbeat and final evidence updates
- Tests run: `python tests/run_all_smokes.py` PASS (14 scripts); `npm run test:e2e` PASS (full Edge regression including runner and reviewer); `php vendor/bin/phpunit --no-coverage` PASS (6/9); `python plan/scripts/validate_plan.py` PASS
- Known issues: nonfatal KaTeX bundle warning and missing optional PHP coverage driver remain; schedule daily telemetry purge in deployment
- Next action: none for local implementation

## Required stage update format
At each stage start/end, update the table above and append:

```
### Sx – YYYY-MM-DD HH:MM [timezone]
- Status: IN_PROGRESS | DONE | BLOCKED | UNVERIFIED
- FR IDs: ...
- Changed files: ...
- Tests run (commands + PASS/FAIL/NOT_RUN): ...
- Known issues: ...
- Next action: ...
```

**Allowed status values:** NOT_STARTED, IN_PROGRESS, DONE, BLOCKED, UNVERIFIED. DONE requires positive proof; untested is UNVERIFIED, not DONE.

### Post-S7 ? 2026-11-20 00:44 [Asia/Jakarta]
- Status: DONE (fitur detail ujian bank dan simulasi pratinjau; S0?S7 historis tidak berubah)
- FR IDs: UI detail ujian, privasi pratinjau, aset soal snapshot
- Changed files: `backend/app/Controllers/Api/V1/ExamController.php`, `backend/app/Services/ExamService.php`, `backend/app/Config/Routes.php`, `frontend/src/lib/api.ts`, `frontend/src/components/MathText.tsx`, `frontend/src/components/ExamStudentPreview.tsx`, `frontend/src/pages/Exams.tsx`, `frontend/scripts/smoke-exam-preview.mjs`, `backend/tests/smoke_s9_structured.py`
- Tests run (commands + PASS/FAIL/NOT_RUN): `php -l` PASS; `php spark routes` confirms preview asset route; `composer test -- --no-coverage` PASS (6 tests, 9 assertions); `python tests/smoke_exam_bank_schedules.py` PASS; `python tests/smoke_s9_structured.py` PASS before added focused assertions; targeted API check for `/exams/301/preview` schema/key privacy PASS; `npx tsc --noEmit` PASS; approved `npm run build` PASS; `node scripts/smoke-exam-preview.mjs` PASS at 320/390/1280px. Full `npm run test:e2e` ran through existing UI suites, then exited at `smoke-exam-schedules.mjs` because `127.0.0.1:5174` was not running. A later structured API smoke rerun stopped on unrelated ZIP import fixture HTTP 422 before completing.
- Known issues: direct scoped image route was added and route-registered; `/exams/301` currently has no preview image, so image bytes/archived-image behavior were not exercised in this turn. E2E schedule smoke needs its expected Vite server on port 5174. Build has existing >500 kB bundle warning.
- Next action: rerun the schedule E2E with its configured server and add archived-image fixture coverage when available.
