# Rencana API / UI tambahan — mengikuti konvensi existing

Semua path di bawah adalah **kandidat**, bukan perintah untuk menduplikasi endpoint yang sudah ada. Codex wajib mencari route/controller/page lama; *reuse* dan *extend* bila memungkinkan. Gunakan `/api/v1` hanya jika sesuai contract asli repo; response envelope, error codes, auth/CSRF, rate limit dan pagination mengikuti standar maqna-cbt sebelumnya.

## UI pages baru atau ditingkatkan

| Halaman / Komponen | Delta yang diperlukan | Stage |
|---|---|---|
| Dashboard Sekolah | token table & copy, status jadwal, statistik | S8 |
| Akademik / Jenis Ujian | master code/name/status | S8 |
| Akademik / Ruang | room list, assignment exam | S8 |
| Peserta | participant number, login status filter, safe revoke | S8 |
| Guru | import + mapping mata pelajaran/rombel | S8 |
| Bank Soal / Editor | tabs tipe soal, rich media, KaTeX, point total | S9 |
| Bank Soal / Import | wizard DOCX, JSON, Excel keys, ZIP with preview | S9 |
| Bank Soal / Preview | read-only student render, no answers exposed | S9 |
| Jadwal Ujian | token policy, optional rotating token, bulk operations | S10 |
| Kartu Peserta | preview F4 2x6 default, template, PDF | S10 |
| Administrasi | daftar hadir, berita acara, penandatangan | S10 |
| Status Ujian | filter by room; exports, essay review; safe admin actions | S10 |
| Analisis Soal | distractor distribution + student matrix; clear formulas | S11 |
| Pengaturan | branding, sidebar theme, access/security safe config | S11 |
| Bantuan / Tentang | offline-ready help text, local templates, app version | S11 |

**Responsivitas:** gunakan existing shadcn/ui, Card/Dialog/Sheet/Select/Table/Form, font, spacing & teal `#155451` existing; tabel lebar di desktop dapat berubah menjadi cards/filtered scroll pada mobile. **Jangan menyalin pixel-perfect atau brand RyuExam**.

## Kandidat endpoint

| Method | Path contoh | Permission / catatan |
|---|---|---|
| GET/POST | `/api/v1/exam-types` | school admin; archive instead of hard delete |
| GET/POST | `/api/v1/exam-rooms` | admin; scoped |
| POST | `/api/v1/exams/{id}/rooms/assign` | admin; unique roster |
| GET | `/api/v1/students?login_status=...` | pagination, scoped query |
| POST | `/api/v1/students/{id}/sessions/revoke` | admin/proctor scoped, reason/audit |
| POST | `/api/v1/teachers/import/preview` | dry-run, no mutation |
| POST | `/api/v1/teachers/import/commit` | idempotency key + audit |
| POST | `/api/v1/questions/import/docx/preview` | teacher authorized, media safe |
| POST | `/api/v1/questions/import/json/preview` | schema version |
| POST | `/api/v1/question-banks/{id}/archive/export` | signed download, scoped |
| POST | `/api/v1/question-banks/{id}/archive/preview` | ZIP safety guards |
| POST | `/api/v1/question-banks/{id}/preview` | teacher only (not student route) |
| GET | `/api/v1/exams/{id}/tokens/current` | scoped, no key in log/cache |
| GET | `/api/v1/exams/{id}/attendance/pdf` | proctor/admin; private document |
| GET | `/api/v1/exams/{id}/minutes/pdf` | proctor/admin; private document |
| GET | `/api/v1/exams/{id}/participant-cards/pdf` | school admin; no password |
| GET | `/api/v1/exams/{id}/answer-export` | teacher/admin only, hidden before release for students |
| GET | `/api/v1/exams/{id}/analysis/distractors` | teacher/admin; aggregate only |
| GET/PUT | `/api/v1/settings/branding` | admin only, school scoped |
| GET | `/api/v1/help/topics` | role filtered, local fallback |

## API safety contracts

- Semua perubahan destructive/bulk punya dry-run → konfirmasi eksplisit → action audit. *Disabled by default* sesuai `CONTROL.json`.
- Import file: multipart size cap, MIME/content verify, parse asynchronously hanya bila job runner tersedia, preview error per row, commit transaction/idempotent; no zip-slip/zip-bomb, no unsafe XML external entities.
- Export: auth check saat request dan saat download, short-lived signed link bila perlu; `Cache-Control: private, no-store` untuk credential/token/report personal.
- Exam scoring: tidak ada endpoint student yang mengembalikan kunci/hasil sebelum `released_at`.
- Token dynamic: additive to user login; simulate clock drift/timezone safe; never return tokens to unauthorized.
- Kartu peserta: jangan masukkan plaintext password; jika ingin onboarding, gunakan **temporary invite / reset token sekali pakai berumur pendek**, hanya untuk distribusi oleh petugas berwenang dan tidak disimpan permanen di PDF.
