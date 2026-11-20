# Baseline audit S0-S7 untuk ekstensi RyuExam

Waktu: 2026-11-15 13:26 WIB. Referensi RyuExam adalah audit UI demo, bukan bukti perilaku backend. Repository ini bukan checkout Git (`git status` exit 1), sehingga tidak ada branch/commit atau diff yang dapat diverifikasi. Pemeriksaan dilakukan pada workspace saat ini tanpa reset status S0-S7.

## Inventaris nyata

- Backend: CodeIgniter 4.7.6, PHP 8.5.10, Shield session auth, MySQL dev `maqna_cbt_dev`. `php spark migrate:status` mencatat 15 migration aplikasi, Shield dan Settings telah terpasang. `backend/app/Config/Routes.php` memetakan API `/api/v1` ke controller auth, akademik, orang, soal, ujian, attempt, hasil, impor, pengawas dan audit. `SchoolScopePolicy.php` serta penugasan guru membatasi objek di service/controller.
- Frontend: React 18, TypeScript, Vite 6.4.6. `frontend/src/main.tsx` memetakan login/reset, dashboard, sekolah, akademik, siswa/guru, penugasan, soal, ujian, attempt, hasil, laporan, impor, pengawas, analisis dan audit. `Layout.tsx` memakai navigasi role; `index.css` memakai token teal.
- Schema: `students`, `teachers`, `subjects`, `class_groups`, `teaching_assignments`, `questions`, `question_options`, `exams`, `exam_items`, `attempts`, `attempt_items`, `attempt_results`, `import_jobs`, `question_versions`, `proctor_events` berada pada migration 000003-000015. Belum ada `exam_rooms`, `exam_types`, atau nomor peserta.
- Alur keamanan yang ditemukan: Shield/CSRF, login username/email, policy sekolah dan penugasan mapel+rombel, snapshot item saat publish, deadline server, revisi autosave, submit idempoten, hasil manual release, grade esai dan audit. Ini didukung smoke API yang berhasil sampai `smoke_access_code.py`; `smoke_audit.py`, `smoke_versions.py`, `smoke_packages.py`, dan `smoke_proctor_analytics.py` lulus terpisah.
- Ekspor yang ada: hasil XLSX/PDF pada `ResultController::export`; impor siswa/soal CSV/XLSX dua tahap pada `ImportService`. Belum ada ekspor siswa/guru/mapel atau dokumen kartu/absensi. Aset soal lokal dan KaTeX ada; DOCX/JSON/ZIP bank belum ada.

## Baseline tests

| Perintah | Hasil | Keterangan |
|---|---|---|
| `php spark migrate:status` | PASS, exit 0 | 15 migration aplikasi telah terpasang; read-only. |
| `php vendor/bin/phpunit --no-coverage` | PASS, exit 0 | 6 tests, 9 assertions. |
| `npm run build` | PASS, exit 0 | TypeScript/Vite; warning bundle KaTeX >500 kB nonfatal. |
| `python plan/extensions/ryuexam/scripts/validate_extension.py` | PASS, exit 0 | 48 kandidat, 41 ON, 7 OFF. |
| `python tests/run_all_smokes.py` | FAIL, exit 1 | Delapan awal lulus sampai `smoke_access_code.py`; `smoke_imports.py` gagal pada preview XLSX karena server port 8081 tidak memuat PHP ZIP (503). Sisa skrip tidak dijalankan oleh runner. |
| `smoke_audit.py`, `smoke_versions.py`, `smoke_packages.py`, `smoke_proctor_analytics.py` | PASS, exit 0 masing-masing | Dijalankan terpisah setelah cache dev dibersihkan. |

`EXISTING_VERIFIED` hanya diberikan bila jalur fitur dan tes spesifik cocok. `PARTIAL` berarti implementasi pendukung ada, namun seluruh acceptance ekstensi belum terpenuhi. `MISSING` berarti route/schema/UI fitur tidak ditemukan pada inventaris. Status akhir sementara pada `GAP_STATUS.csv` tidak mengklaim gap aktif selesai.

## Audit 48 kandidat

| ID | Status awal | Bukti file utama | Bukti tes | Tindakan |
|---|---|---|---|---|
| RY-001 | PARTIAL | ExamService.php, Exams.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-002 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-003 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-004 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-005 | PARTIAL | PeopleService.php, StudentAdmin.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-006 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-007 | PARTIAL | ImportService.php, ImportController.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-008 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-009 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-010 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-011 | EXISTING_VERIFIED | ExamService.php, QuestionBank.php | smoke_questions.py;smoke_exams.py | SKIP |
| RY-012 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-013 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-014 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-015 | PARTIAL | QuestionBank.php, MathText.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-016 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-017 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-018 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-019 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-020 | PARTIAL | QuestionBank.php, ResultService.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-021 | PARTIAL | ExamController.php, Exams.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-022 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-023 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-024 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-025 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-026 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-027 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-028 | EXISTING_VERIFIED | ProctorService.php, Proctoring.tsx | smoke_proctor_analytics.py | SKIP |
| RY-029 | PARTIAL | ResultController.php, ResultController.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-030 | PARTIAL | ResultController.php, Results.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-031 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-032 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-033 | EXISTING_VERIFIED | QuestionAnalyticsService.php, Proctoring.tsx | smoke_proctor_analytics.py | SKIP |
| RY-034 | PARTIAL | QuestionAnalyticsService.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-035 | PARTIAL | SchoolController.php, SchoolSettings.tsx | baseline code inspection; full acceptance pending | EXTEND |
| RY-036 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-037 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-038 | EXISTING_VERIFIED | AttemptService.php, 2026-11-14-000010_AddExamAccessCode.php | smoke_access_code.py | SKIP |
| RY-039 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-040 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-041 | PARTIAL | AuthGroups.php, SchoolScopePolicy.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-042 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-043 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-044 | DISABLED | CONTROL.json | NOT_RUN (disabled) | SKIP |
| RY-045 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-046 | PARTIAL | ImportController.php | baseline code inspection; full acceptance pending | EXTEND |
| RY-047 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |
| RY-048 | MISSING | Routes.php, main.tsx | baseline route/schema/UI inspection | IMPLEMENT |

## Batas keputusan

- Tujuh item OFF tetap tidak diimplementasikan: RY-002, RY-031, RY-032, RY-040, RY-042, RY-043, RY-044.
- Tidak ada cetak/ekspor password plaintext atau reset attempt otomatis. Token ujian tidak mengganti autentikasi dan penugasan. Nilai siswa tetap disembunyikan sampai rilis manual.
- Karena repo tidak memiliki `.git`, perubahan pengguna tidak dapat dipisahkan dengan `git diff`. Hanya file yang diperlukan untuk gap akan diedit; status S0-S7 tetap.

## Hasil delta setelah S11 (2026-11-16)

- Baseline tetap 48 kandidat: 4 fitur sudah terverifikasi dan dipakai ulang, 7 fitur OFF tidak diimplementasikan. Seluruh gap aktif S8-S11 di `GAP_STATUS.csv` berstatus DONE.
- Perubahan S11: migration 000026-000027; layanan/pengendali matriks jawaban, kebijakan sesi dan izin pengguna; komponen matriks, pengaturan sesi/izin, halaman informasi; manifest, service worker aset shell, ikon maqna-cbt, tema sidebar. Endpoint lama, izin dasar Shield, scope sekolah/mapel/rombel, dan rilis hasil manual tetap dipakai.
- Bukti diff operasional: `QuestionAnalyticsService.php`, `QuestionMatrixController.php`, `SessionPolicy.php`, `AccountSessionGuard.php`, `UserPermissionController.php`, `PermissionPolicy.php`, `Layout.tsx`, `PeopleAdmin.tsx`, `ProctorAdmin.tsx`, `SchoolSettings.tsx`, `InfoPages.tsx`, `AnswerMatrix.tsx`, `StaffPermissions.tsx`, `SessionPolicySettings.tsx`, `main.tsx`, `index.css`, serta empat smoke S11. Git diff tidak tersedia karena workspace bukan repository Git; daftar ini disusun dari file implementasi dan gate yang diperiksa.
