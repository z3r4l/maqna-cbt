# REST API Contract v1 — stable path families

Base `/api/v1`, JSON. Mutations require CSRF for Shield same-origin session. Responses: `{ "data": ..., "meta": ... }` success and `{ "error": { "code": "...", "message": "...", "fields": ... } }` failure. 401 unauthenticated; 403 authenticated forbidden by scope; 404 unavailable/unknown without leakage; 409 conflict/revision/state; 422 validation; 429 throttle. Keys are representative contracts; implement OpenAPI documentation with actual request/response schemas during S0–S5.

| Module | Method / route | Required scope | Stage |
|---|---|---|---|
| Auth | `GET /auth/csrf`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` | anonymous/authenticated | S0 |
| Auth | `POST /auth/password/forgot`, `POST /auth/password/reset`, `POST /auth/password/change` | verified token/current user | S0 |
| School | `GET/PUT /school`, `GET/POST /academic-years`, `GET/POST /semesters` | school_admin | S1 |
| School | `GET/POST /class-groups`, `PATCH /class-groups/{id}` | school_admin | S1 |
| School | `GET/POST /subjects`, `GET/POST /teacher-assignments` | school_admin | S1 |
| Users | `GET/POST /students`, `GET/POST /teachers`, `PATCH /students/{id}`, `PATCH /teachers/{id}` | school_admin | S1 |
| Bank | `GET/POST /question-categories`, `GET/POST /question-topics` | allowed admin/teacher | S2 |
| Bank | `GET/POST /questions`, `GET/PATCH /questions/{id}`, `POST /questions/{id}/assets` | authorized teacher/admin | S2 |
| Bank | `GET /assets/{id}` | ownership/active assigned attempt | S2 |
| Exams | `GET/POST /exams`, `GET/PATCH /exams/{id}`, `POST /exams/{id}/publish` | teacher (scope) / admin | S3 |
| Exams | `POST /exams/{id}/assignments`, `GET /exams/{id}/participants` | teacher (scope) / admin | S3 |
| Student | `GET /student/exams`, `GET /student/exams/{id}/instructions` | assigned student | S3 |
| Attempts | `POST /exams/{id}/start`, `GET /attempts/{attemptId}` | assigned student only | S4 |
| Attempts | `PUT /attempts/{attemptId}/answers/{questionId}`, `PATCH /attempts/{attemptId}/marks/{questionId}` | current attempt owner | S4 |
| Attempts | `POST /attempts/{attemptId}/submit` | attempt owner | S4 |
| Results | `GET /student/attempts`, `GET /student/results/{attemptId}` | own + released only for grade | S5 |
| Results | `GET /exams/{id}/results`, `POST /exams/{id}/results/release` | authorized admin/teacher | S5 |
| Reports | `GET /reports/exams`, `GET /reports/exams/{id}` | scoped admin/teacher | S5 |
| S6 | import/export/essay grading/access code/review/audit routes | per policy | S6 |
| S7 | review/version/package/proctor-monitor/item-analysis routes | per policy | S7 |

## Authentication and CSRF
- Login accepts `{identity: string, password: string}` where `identity` can be username or email; case-insensitive username, normalized email as appropriate.
- `GET /auth/me` returns safe profile + role + effective permissions, never secret hashes/tokens.
- Dev Vite proxy `/api` to backend; prod reverse-proxy same origin. No custom bearer+localStorage migration.

## Attempt start response (example)
```json
{ "data": { "attemptId": 58, "state": "active", "deadlineAt": "2026-11-15T03:30:00Z", "questionIds": [802, 805] } }
```

## Attempt get response (no grading secrets)
```json
{ "data": { "attemptId": 58, "state": "active", "deadlineAt": "2026-11-15T03:30:00Z", "serverNow": "2026-11-15T03:05:00Z", "questions": [{ "attemptQuestionId": 802, "stemHtml": "...", "options": [{"id": 9001, "html": "..."}], "marked": false, "answer": { "selectedOptionId": 9001, "revision": 3 } }] } }
```
Never return `is_correct`, correct choice ID, `snapshot_json` or internal grading fields in this endpoint.

## Autosave (compare-and-swap)
Request `PUT /attempts/58/answers/802`:
```json
{ "selectedOptionId": 9002, "expectedRevision": 3, "clientMutationId": "e6c2f1d5-23a8-4321-a8c0-1b236c4e3900" }
```
Success responds `{data:{selectedOptionId:9002,revision:4,savedAt:"..."}}`. Stale request returns 409 with current revision/answer (only for owner) and frontend resolves conflict rather than silently overwrite.

## Result release semantics
`POST /exams/{id}/results/release` checks teacher scope, no open attempts, no essay pending, DB transaction and audit. `GET /student/results/{attemptId}` before release returns status `awaiting_release` with **no grade, passStatus, key/explanation**; after release provides only permissions-dependent fields.

## Monitoring and security
- Rate limits with useful retry message and no user enumeration.
- All list endpoints paginated, filter/sort whitelisted, no raw SQL from parameters.
- Error responses must not leak detailed authorization differences that expose other students' results.
- Record API tests in `QA_REPORT.md`, contract definitions in `backend/docs/openapi.yaml` (or another documented repo location).
