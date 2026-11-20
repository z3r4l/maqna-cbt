# Usulan perubahan data model S8–S11 — diferensial, bukan schema pengganti

> PENTING: sebelum membuat tabel, Codex harus memetakan nama tabel/migrations yang **benar-benar ada** pada repo S0–S7. Usulan ini adalah konsep, bukan instruksi untuk menciptakan duplikat. Jangan rename, truncate atau drop tabel existing tanpa proses migrasi/backup yang disetujui.

| Konsep tambahan | Relasi yang diharapkan | Stage | Catatan |
|---|---|---|---|
| `exam_types` | school 1:N exam_type; exam atau bank merujuk jenis | S8 | `code` unik per sekolah: PH, PTS, PAS, PAT, US, TO; tipe bisa inactive |
| `exam_rooms` | school 1:N room | S8 | ruangan bukan rombel; ruangan boleh dipakai beberapa jadwal pada waktu lain |
| `exam_room_assignments` | exam 1:N room_assignment; student 1:N | S8 | unique(exam, student); optional seat number |
| `participant_numbers` / field terindeks | exam/school/year + student | S8 | scope/uniqueness eksplisit, tidak ganti username/NIS |
| `auth_session_events` | user/session 1:N events | S8 | login/revoke/status aman, tidak menyimpan token plaintext |
| `question_banks` | school/subject/teacher/exam_type 1:N | S9 | reuse models jika sudah ada; berbeda dengan `exams` |
| `question_items` dengan discriminator `type` | bank 1:N, question version N | S9 | type: single_mcq, multi_mcq, true_false, matching, essay |
| `question_structures` / JSON tervalidasi | question 1:1 struktur khusus versi | S9 | hindari memaksakan semua jenis ke `options` tunggal; gunakan schema version |
| `question_assets` | question 1:N images/video refs | S9 | stored path private, MIME, hash, referential cleanup |
| `question_import_runs` | actor, bank, status, validation report | S9 | idempotent preview/commit, row errors and media map |
| `question_bank_archives` | bank 1:N export artifacts | S9 | ZIP JSON manifest version + asset hashes; no arbitrary executable files |
| `exam_tokens` (opsional) | exam 1:N tokens / rotating secret ref | S10 | hash token at rest when possible, TTL, rotate, non-reuse |
| `exam_documents` | exam, room, type, generated_by | S10 | attendance/official minutes/PDF metadata, PII safe |
| `exam_attendance` | exam + student + room | S10 | present/absent/excused; from attempt or manual with audit |
| `grading_revisions` | attempt 1:N recalculation runs | S10 | only if explicitly enabled; freeze previous published evidence |
| `exam_incidents` | exam, student optional, proctor | S10 | notes, timestamps, events; explicit scope |
| `school_branding` / `school_settings` | school 1:1 | S11 | logos, favicon, header; retain old data |
| `question_analysis_runs` | exam N analysis runs, aggregate rows | S11 | version based on submitted snapshot, n sample, formula |
| `app_release_notes` | version 1:N notes | S11 | read-only UI; never publish secrets or internals |

## Kunci hubungan yang tak boleh rusak

- `exam_attempts` tetap menjadi pemilik satu sesi siswa; **jangan reset** urutan soal/jawaban/timer hanya untuk mendukung tipe soal baru.
- `exam_items` atau padanan **snapshot saat publish**, termasuk versi konten, opsi, rubrik, bobot, dan kunci. Paket A/B atau pengacakan disimpan pada instance attempt.
- `attempt_answers` dapat memakai `answer_json` tervalidasi **berdasarkan jenis soal**; adapter compatible untuk single MCQ & essay yang sudah ada. Pertahankan pembacaan data lama.
- `attempt_results.released_at` atau padanan adalah gerbang rilis **manual** yang sama bagi setiap tipe soal.
- Tahun ajaran/semester, rombel, `teaching_assignments`, `school_id`, dan policy tetap otoritatif untuk scope.

## Format jawaban konseptual (server memvalidasi schema)

```json
{
  "single_mcq": {"choice_id": "opt-uuid"},
  "multi_mcq": {"choice_ids": ["opt-a", "opt-c"]},
  "true_false": {"statement_answers": {"stmt-1": true, "stmt-2": false}},
  "matching": {"pairs": {"left-1": "right-2", "left-2": "right-1"}},
  "essay": {"text": "Jawaban siswa"}
}
```

`correct_answer` **jangan** pernah masuk ke DTO student sebelum manual result release.

## Aturan scoring kandidat (perlu dicatat di settings, tidak boleh diam-diam berubah)

- PG biasa: benar = poin penuh, selain itu 0.
- PG kompleks: default *exact-set match* (semua tepat, tidak lebih/kurang) agar deterministik.
- Benar/salah dan matching: default all-or-nothing per soal; jika nilai parsial diperlukan, implementasi eksplisit dengan bobot per pernyataan dan versi skor.
- Essay: menunggu koreksi manual sebelum rilis.
- Hasil normalisasi 0–100, rounding konsisten dan tidak mengubah skor attempt lama.

## Migrasi aman

1. Backup schema dan tahu record count dev/test.
2. Additive migrations (nullable field + backfill + constraints), tidak drop/rename langsung.
3. Backfill tanpa kehilangan `school_id`/owner.
4. `up/down` hanya jika aman; reversal yang bisa kehilangan data dinyatakan tidak didukung.
5. Idempotensi dan regression dengan database test berisi fixtures hasil S0–S7.
6. Tidak pernah menjalankan `db:seed` reset, `db:wipe`, truncate, migrate:fresh, restore, atau delete massal di data riil tanpa persetujuan eksplisit.
