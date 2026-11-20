# Acceptance and test scenarios — mandatory evidence

The project is **not done** if only mock UI works. Run on a development database with dummy data. Record command, exit code, environment, and short evidence in `QA_REPORT.md`. If Browser/E2E infrastructure is unavailable, run available tests and mark UI E2E `UNVERIFIED`; do not claim passed.

## S0 auth & security
- [ ] AUTH-01 valid username/password and email/password, wrong credential, disabled account; duplicate username case-insensitive.
- [ ] AUTH-02 login rate limit, logout revokes session, CSRF blocks unsafe request without token.
- [ ] AUTH-03 reset token single use + expiry, no user enumeration; student without email has admin reset flow.
- [ ] AUTH-04 anonymous 401; student on admin endpoint 403; correct school scope enforced.

## S1 school data
- [ ] ACD-01 one active academic year/semester, year rollover, rombel assignment history.
- [ ] ACD-02 student can't self-edit NIS/rombel; teacher cannot see unrelated roster.
- [ ] ACD-03 teacher's assignment for one class cannot grant access to another.

## S2 bank soal
- [ ] QST-01 MCQ <2 options fails, >1 correct fails; saving draft allowed while incomplete.
- [ ] QST-02 malicious HTML/script sanitized; media unauthorized 403; image size/MIME rejected.
- [ ] QST-03 teacher cannot edit other teacher's protected bank questions.

## S3 exam management
- [ ] EXM-01 teacher with mapel VII-A but not VII-B cannot publish exam for both; school_admin can within school.
- [ ] EXM-02 malformed schedule/passing grade/empty questions/empty participant roster fails publish.
- [ ] EXM-03 snapshot frozen at publish; modifying question bank doesn't change published question.
- [ ] EXM-04 attempted double-start race doesn't create 2 active attempts or bypass max_attempts.
- [ ] EXM-05 random order persists on refresh and between reconnects.

## S4 attempt
- [ ] ATT-01 timer authoritative server; device clock changed cannot increase deadline.
- [ ] ATT-02 autosave successive revisions; stale request rejected 409, latest wins.
- [ ] ATT-03 reload and reconnect preserve answers/order/marks/deadline.
- [ ] ATT-04 submit twice is idempotent; expired request rejects save and finalizes attempt; background command finalizes offline attempt.
- [ ] ATT-05 student cannot access another student's attempt nor hidden correct answer keys.
- [ ] ATT-06 mobile 320px/390px and desktop ≥1280px exam page navigation/submit works.

## S5 scoring and release
- [ ] RES-01 correct/wrong/unanswered MCQ exact score and pass threshold.
- [ ] RES-02 student submit gets **no score or passStatus**; before manual release results/history show only awaiting_release.
- [ ] RES-03 unauthorized teacher cannot release; authorized teacher or admin can release only when all pending essays/attempts are resolved.
- [ ] RES-04 release repeated is idempotent; score visible only after release and only for own attempt.
- [ ] RES-05 report filters apply teacher+school scopes; cross student/cross school negative tests.

## S6 feature expansions
- [ ] EXT-01 CSV/Excel import rejects duplicate/invalid records with row-level report; no partial silent successes.
- [ ] EXT-02 essay grading cannot exceed max points; release blocked pending essay grade.
- [ ] EXT-03 LaTeX render and sanitized image/formula mobile preview; XSS blocked.
- [ ] EXT-04 access code required for configured exam, throttle guesses.
- [ ] EXT-05 answer review key/explanation only after release **and** review permission enabled.
- [ ] EXT-06 Excel export escapes formula injection; PDF contains only authorized scoped data.
- [ ] EXT-07 audit log captures sensitive actions without raw credentials/tokens.

## S7 advanced
- [ ] ADV-01 question version review does not mutate existing exam attempt snapshots.
- [ ] ADV-02 package A/B scores remain comparable, attempt package persists.
- [ ] ADV-03 tab event throttling and proctor visibility do not expose other student answers or infer fraud automatically.
- [ ] ADV-04 monitoring disallows cross-class unauthorized viewers; offline heartbeat shows disconnected appropriately.
- [ ] ADV-05 quality analysis handles small samples and no data safely.

## Minimum build gates
- PHP syntax + PHPUnit integration/features tests pass. `php spark migrate --all` succeeds on test DB, second migration run safe.
- `npm run build` passes; tests/lint scripts if introduced pass; no runtime errors on smoke routes.
- API 401/403/409/422 and student unreleased results regression tests.
- Record exact failures and remediation; never mark stage `done` if required tests are absent or failing.
