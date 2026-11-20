# QA ekstensi RyuExam

| Waktu WIB | Gate | Perintah | Exit | Hasil |
|---|---|---|---:|---|
| 2026-11-15 13:26 | Baseline schema | `php spark migrate:status` | 0 | PASS: 15 migration aplikasi terpasang, Shield dan Settings ada. |
| 2026-11-15 13:26 | Baseline backend | `php vendor/bin/phpunit --no-coverage` | 0 | PASS: 6 tests, 9 assertions. |
| 2026-11-15 13:26 | Baseline frontend | `npm run build` | 0 | PASS: TypeScript/Vite; warning ukuran bundle KaTeX nonfatal. |
| 2026-11-15 13:26 | Validasi paket | `python plan/extensions/ryuexam/scripts/validate_extension.py` | 0 | PASS: 48 fitur, 41 ON, 7 OFF. |
| 2026-11-15 13:26 | Baseline HTTP | `python tests/run_all_smokes.py` | 1 | FAIL: `smoke_auth`, `academic`, `questions`, `exams`, `attempts`, `results`, `essay`, `access_code` lulus; `smoke_imports` gagal 503 preview XLSX karena PHP ZIP tidak aktif pada server 8081. Skrip berikutnya pada runner NOT_RUN. |
| 2026-11-15 13:26 | Baseline HTTP lanjutan | `smoke_audit.py`, `smoke_versions.py`, `smoke_packages.py`, `smoke_proctor_analytics.py` dengan cache dev dibersihkan per skrip | 0 masing-masing | PASS. |

Environment: backend `development`, MySQL `maqna_cbt_dev`, PHP 8.5.10, CodeIgniter 4.7.6; frontend Node 22.23.3 dan Vite 6.4.6. Semua fixture sintetis. S8 lulus setelah perbaikan XLSX dan gate akhir di bawah.

## S8 sementara, 2026-11-15 13:38 WIB

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration 000016 | `php spark migrate --all` | 0 | PASS; `exam_types`, `exam_rooms`, dan kolom assignment terpasang di DB dev. |
| Master jenis/ruang | `python tests/smoke_s8_masters.py` | 0 | PASS; scope admin/teacher, kode standar, duplikat 409, arsip, draf dengan jenis dan jenis nonaktif ditolak. Awalnya gagal 500 untuk duplikat; validasi konflik diperbaiki lalu lulus. |
| Penempatan | `python tests/smoke_s8_assignments.py` | 0 | PASS; teacher 403, admin dapat menyimpan ruang dan nomor, baca ulang tetap sama, nomor duplikat 409. |
| Regresi backend | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite. Percobaan pertama dalam sandbox gagal `spawn EPERM`; run dengan eskalasi lokal lulus. Warning bundle KaTeX tetap nonfatal. |



| S8 filter login | `python tests/smoke_s8_login_status.py` | 0 | PASS; teacher 403, filter tidak valid 422, hitungan yes+no sama dengan total sekolah, status per baris konsisten. |
| S8 pencabutan sesi | `python tests/smoke_s8_revoke_session.py` | 0 | PASS; teacher 403, konfirmasi wajib 422, cabut satu sesi, sesi lama 401, attempt tidak berubah, login ulang 200. |
| Regresi auth setelah guard | `python tests/smoke_auth.py` | 0 | PASS; username/email login, logout, reset, CSRF dan peran. |
| S8 build setelah UI sesi | `npm run build` | 0 | PASS; TypeScript/Vite dengan warning ukuran bundle nonfatal. |
| S8 impor guru/mapel | `python tests/smoke_s8_staff_imports.py` | 0 | PASS; preview per baris, duplikat, commit, mapping penugasan, rollback seluruh transaksi saat data berubah. |
| S8 ekspor CSV | `python tests/smoke_s8_exports.py` | 0 | PASS; teacher 403, tiga jenis ekspor admin, tanpa sandi/alias internal, formula CSV diawali apostrof. |
| Regresi backend setelah impor | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| S8 build setelah impor | `npm run build` | 0 | PASS; TypeScript/Vite dengan warning ukuran bundle nonfatal. |
| S8 kartu peserta | `python tests/smoke_s8_cards.py` | 0 | PASS; PDF 612Ã—936 pt (F4), filter kelas/ruang, nomor sama pada dua unduhan, teacher 403, tanpa password. Tes awal gagal karena PyPDF2 lama tidak bisa ekstraksi Dompdf; diperbaiki memakai `pdftotext`. |
| S8 penempatan kapasitas | `python tests/smoke_s8_assignments.py` | 0 | PASS; kapasitas ruang 1 menolak peserta kedua 422. |
| S8 token dashboard | `python tests/smoke_s8_tokens.py` | 0 | PASS; scope role, filter status/mapel/kelas, konfirmasi wajib, token sekali tampil, tidak bocor ke list/student API. |
| S8 impor multi penugasan | `python tests/smoke_s8_staff_imports.py` | 0 | PASS; dua penugasan untuk satu guru dan ekspor kode mapel/rombel berhasil. |
| S8 build dashboard/kartu | `npm run build` | 0 | PASS; TypeScript/Vite, warning bundle nonfatal. |
| S8 aksi massal | `python tests/smoke_s8_bulk_people.py` | 0 | PASS; teacher 403, ID ganda 422, pratinjau, double confirm, nonaktif tanpa hapus, ulang commit 409, guru dengan penugasan aktif ditolak. |
| S8 build aksi massal | `npm run build` | 0 | PASS; TypeScript/Vite, warning bundle nonfatal. |

## Gate akhir S8, 2026-11-15

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate --all` dan `php spark migrate:status` | 0 | PASS; migration aplikasi 000016-000019 terpasang di DB dev. |
| Validasi kontrol | `python plan/extensions/ryuexam/scripts/validate_extension.py` | 0 | PASS; 48 kandidat, 41 ON, 7 OFF. |
| Backend S8 | `python tests/run_s8_smokes.py` | 0 | PASS; 12 HTTP smoke, termasuk XLSX, formula/multi-sheet/bomb, role negatif, kartu F4, token, sesi, bulk, pengawas. |
| Regresi S0-S7 | `python tests/run_all_smokes.py` | 0 | PASS; 14 HTTP smoke, termasuk impor XLSX dan ekspor XLSX dengan fallback PharData pada PHP tanpa ZIP. Kegagalan awal 503 dan ekspor 500 telah diperbaiki. |
| PHPUnit | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite, warning chunk >500 kB nonfatal. |
| Browser | `npm run test:e2e` | 0 | PASS; loader, navigasi, dan S8 di viewport 390/1280. Locator Semester yang ambigu diperbaiki dengan label eksplisit. |


## S9 sementara, 2026-11-15

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate --all` | 0 | PASS; 000020 pada DB dev. |
| Tiga tipe soal | `python tests/smoke_s9_structured.py` | 0 | PASS; create, publish, snapshot tanpa kunci, autosave, resume, submit, skor 100. |
| Aturan nilai | `php tests/structured_answer_test.php` | 0 | PASS; exact-set, parsial per baris, jawaban invalid. |
| Regresi backend | `python tests/run_all_smokes.py` | 0 | PASS; 14 skrip HTTP S0-S7. |
| PHPUnit | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite. Percobaan sandbox awal gagal spawn EPERM; eskalasi lokal lulus. |


| S9 JSON/versi | `python tests/smoke_s9_structured.py` | 0 | PASS; versi salah, field tak dikenal, batch rusak, scope siswa, duplikat, mode skip, commit idempotent, manual release, revisi snapshot. |
| S9 sanitasi | `php tests/question_content_test.php` | 0 | PASS; script/URL/embed terlarang dibuang, embed youtube-nocookie tetap aman. |
| S9 browser lengkap | `npm run test:e2e` | 0 | PASS; smoke S0-S8 dan editor S9 390/1280, tanpa overflow. |



## Gate akhir S9, 2026-11-15

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate --all` dan `php spark migrate:status` | 0 | PASS; migration aplikasi 000020-000022 terpasang pada DB dev. |
| Kontrol | `python plan/extensions/ryuexam/scripts/validate_extension.py` | 0 | PASS; 48 kandidat, 41 ON, 7 OFF. |
| Acceptance S9 | `python tests/smoke_s9_structured.py` | 0 | PASS; tiga tipe soal sampai rilis manual, revisi snapshot, JSON, DOCX gambar, ZIP media append/replace, zip-slip/bomb, kunci XLSX, naskah/kunci PDF-DOCX, preview aman dan izin negatif. Kegagalan awal ZIP duplikat dan zip-slip diperbaiki lalu lulus. |
| Penilaian/sanitasi | `php tests/structured_answer_test.php` dan `php tests/question_content_test.php` | 0 | PASS; exact-set, poin parsial, validasi jawaban, HTML/URL/embed allowlist. |
| Regresi S0-S7 | `python tests/run_all_smokes.py` | 0 | PASS; 14/14 skrip HTTP. |
| Regresi S8 | `python tests/run_s8_smokes.py` | 0 | PASS; 12/12 skrip HTTP. |
| PHPUnit | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite; warning chunk >500 kB nonfatal. |
| Browser | `npm run test:e2e` | 0 | PASS; semua smoke S0-S9, editor rich HTML/KaTeX 390/1280, runner tiga tipe autosave/reload/submit 390. Kegagalan awal label field Pertanyaan diperbaiki lalu lulus. |

## S10 berjalan, 2026-11-15

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate:status` | 0 | PASS; 000023 terpasang pada DB dev. |
| Sintaks | `php -l` pada ExamTokenController, AttemptService, ExamService, DynamicExamToken | 0 | PASS. |
| Token unit | `php tests/dynamic_exam_token_test.php` | 0 | PASS; rotasi, tenggang 30 detik, dan penolakan kode kedaluwarsa/salah. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite, warning chunk >500 kB nonfatal. |
| Acceptance S10 | Belum dijalankan | - | UNVERIFIED; tahap berjalan. |
| Token HTTP | `python tests/smoke_s10_tokens.py` | 0 | PASS; scope, konfirmasi, mode dinamis, kode saat ini, rotasi, tidak bocor ke siswa. |
| Dokumen HTTP | `python tests/smoke_s10_documents.py` | 0 | PASS; daftar hadir/berita acara terisi dan kosong F4, siswa 403, tanpa rahasia. |
| Frontend setelah dokumen | `npm run build` | 0 | PASS; TypeScript/Vite, warning chunk >500 kB nonfatal. |
| Ekspor jawaban awal | `php -l app/Controllers/Api/V1/ResultController.php`; HTTP admin ke `answers.xlsx` | 0 | PASS; berkas ZIP/XLSX 6859 byte dari fixture. Uji negatif dan browser belum dijalankan. |

## Gate akhir S10, 2026-11-15

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate --all`; `php spark migrate:status` | 0 | PASS; 000023-000025 terpasang. Percobaan awal status gagal karena MariaDB lokal berhenti; layanan dijalankan kembali lalu lulus. |
| Kontrol | `python plan/extensions/ryuexam/scripts/validate_extension.py` | 0 | PASS; 48 kandidat, 41 ON, 7 OFF. |
| S10 token | `php tests/dynamic_exam_token_test.php`; `python tests/smoke_s10_tokens.py` | 0 | PASS; TTL, grace, scope, rotasi, tanpa bocor ke siswa. |
| S10 administrasi | `python tests/smoke_s10_documents.py`; `python tests/smoke_s10_bulk_export.py`; `python tests/smoke_s10_logo.py` | 0 | PASS; PDF F4, catatan dan penandatangan, status massal aman, ekspor XLSX, logo pada kartu, izin negatif. Bug rentang fallback XLSX ditemukan dan diperbaiki. |
| S10 jawaban wajib | `python tests/smoke_s10_required_answers.py` | 0 | PASS; submit kosong ditolak, submit terisi diterima. |
| Regresi S0-S7 | `python tests/run_all_smokes.py` | 0 | PASS; 14/14 skrip HTTP. |
| Regresi S8 | `python tests/run_s8_smokes.py` | 0 | PASS; 12/12 skrip HTTP. |
| Regresi S9 | `python tests/smoke_s9_structured.py` | 0 | PASS; siklus soal terstruktur, transfer, cetak, media, dan rilis. |
| PHPUnit | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite, warning chunk >500 kB nonfatal. |
| Browser | `npm run test:e2e`; `node scripts/smoke-s10.mjs` setelah pintasan esai | 0 | PASS; seluruh smoke S0-S10, S10 pada 390/1280 px. |


## Gate akhir S11, 2026-11-16

| Gate | Perintah | Exit | Hasil |
|---|---|---:|---|
| Migration | `php spark migrate --all`; `php spark migrate:status` | 0 | PASS; 000026-000027 terpasang pada DB dev. |
| Kontrol | `python plan/extensions/ryuexam/scripts/validate_extension.py` | 0 | PASS; 48 kandidat, 41 ON, 7 OFF. |
| S11 matriks | `python tests/smoke_s11_matrix.py` | 0 | PASS; matriks terbaru, XLSX kolom dinamis, siswa 403. |
| S11 sesi | `python tests/smoke_s11_sessions.py`; `python tests/smoke_s11_staff_sessions.py` | 0 | PASS; percobaan siswa aktif dipertahankan, batas sesi staf mencabut yang terlama, konfigurasi fixture dipulihkan. |
| S11 izin | `python tests/smoke_s11_permissions.py` | 0 | PASS; edit admin saja, izin tak boleh melampaui Shield, 403 segera berlaku dan izin dipulihkan. Percobaan gabungan awal terkena HTTP 429; setelah `php spark cache:clear` development, uji ulang PASS. |
| Regresi S0-S7 | `python tests/run_all_smokes.py` | 0 | PASS 14/14. Percobaan awal gagal saat cache clear berjalan serentak; ulangan gagal karena daftar mapel fixture melewati halaman pertama. Tiga smoke diubah ke `perPage=100`; ulangan PASS. |
| Regresi S8 | `python tests/run_s8_smokes.py` | 0 | PASS 12/12. |
| Regresi S9 | `python tests/smoke_s9_structured.py` | 0 | PASS. |
| Regresi S10 | Lima `smoke_s10_*.py` | 0 | PASS; token, dokumen, status/ekspor, wajib jawab, logo. |
| PHPUnit | `php vendor/bin/phpunit --no-coverage` | 0 | PASS; 6 tests, 9 assertions. |
| Frontend | `npm run build` | 0 | PASS; TypeScript/Vite. Percobaan awal gagal karena tipe `import.meta.env`; diperbaiki dengan `vite-env.d.ts`. Build berikutnya terkena `spawn EPERM` sandbox; dijalankan dengan izin proses lokal dan PASS. Warning chunk >500 kB nonfatal. |
| Browser | `npm run test:e2e` | 0 | PASS seluruh smoke S0-S11, termasuk S11 320/390/1280 px. Smoke S11 awal memakai nama tombol yang tidak sesuai aria-label; selector diperbaiki lalu PASS. |
| Kontras | Perhitungan WCAG sRGB | 0 | Teks sidebar gelap 13,64:1; tautan aktif 6,43:1; tema terang 7,61:1. |

Tidak ada deployment atau perubahan layanan eksternal. Git diff tidak tersedia karena workspace ini bukan repository Git.
