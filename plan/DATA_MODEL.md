# Data Model & relationships — implementation directives

The detailed dictionary and diagrams are in `references/architecture/MAQNA_CBT_ERD_PERMISSION_MODULES_v1.2.md` and `references/architecture/diagrams/`. The following requirements MUST be enforced in actual migrations, models, and tests.

## Academic & auth
- Shield-owned `users`, `auth_identities`, `auth_groups_users`, etc. (inspect installed Shield migration; do not reimplement password storage).
- `schools(id,name,code,timezone,status)`; v1 seed one school; scoped domain tables attach `school_id`.
- `students(id,user_id,school_id,nis,nisn,status)`, `teachers(id,user_id,school_id,employee_no,status)`; unique user profile; unique NIS within school.
- `academic_years`, `semesters`, `grade_levels`, `class_groups`, `student_class_memberships`, `subjects`, `teaching_assignments`. Membership history retained; one active primary rombel per academic year; teacher assignment subject/class/semester consistent.

## Questions / exams
- `question_categories`, `question_topics`, `questions`, `question_options`, `question_assets`; essay-specific rubric later; do not allow hard delete if used.
- `exams` has `school_id`, `subject_id`, `semester_id`, owner, `state`, schedule UTC, duration, attempts policy, publish config, passing grade, release policy manual.
- `exam_classes`, `exam_assignments`; class targeting snapshots students at time of publish to explicit assignments.
- `exam_items` stores **immutable** `snapshot_json` with frozen question text/options/key for grading; use internal entity, never serialize raw to student.

## Attempts, results, events
- `exam_attempts` includes assignment FK, attempt ordinal, state, started/deadline/submitted timestamps, actor/session metadata as needed. Prevent >1 ACTIVE per assignment with transaction/locking (cross-DB compatible; not only fragile unique nullable trick).
- `attempt_questions`, `attempt_options`, `attempt_answers` persist stable permutation, selected option, mark-for-review, revision/updated_at. `attempt_answers` should not use a foreign key that assumes an answer row for every option; for single choice store one selected option FK or nullable; verify schema against snapshot.
- `attempt_results` stores score fields, `graded_at`, `released_at`, `released_by`, publish batch FK. `essay_grades` tracks grader and point value; `result_release_batches` for atomic visibility switch.
- `attempt_events` for time/visibility/network logs (P2). `audit_logs` for security mutations (full P1). Avoid storing PII-heavy request bodies, answer keys, session tokens.

## Optional S6/S7 extensions
- `question_versions`, `review_events` and approval flags; exam `packages`/`package_items`, `exam_access_codes`, `import_jobs`+`import_rows`, `proctor_events`, item analysis views or cached stats.

## Constraints and indices
- Unique teacher+subject+rombel+semester assignment; unique exam+student assignment; unique attempt assignment+attempt_no; unique attempt+exam_item; unique attempt_question selected option at most one for single choice.
- Foreign keys on school/academic dimensions; index `exam.state, start_at, end_at`, `exam_attempt.state, deadline_at`, `exam_assign.student_id`, `teaching_assignment(teacher_id, semester_id, class_group_id,subject_id)`.
- Number/scores DECIMAL and min/max validated; all mutable domain entities have created_at/updated_at, soft delete when required.
- Never trust `school_id`, teacher_id, `student_id` from client when they should derive from authenticated principal.

## Critical migrations edge cases
- Non-destructive `php spark migrate` works twice, rollback/down methods intentional, tests use isolated DB. Never overwrite production schema based on demo migration without auditing it.
- Existing `backend-overlay/app/Database/Migrations/2026-11-14-000001_CreateCbtTables.php` is only a starting demo; before migration, reconcile it with the full data model instead of keeping wrong tables and adding parallel ones.
