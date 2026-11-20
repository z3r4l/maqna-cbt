# ONE SHOT CODEX PROMPT — GAP ANALYSIS + IMPLEMENT EXTENSION S8–S11

**TASK:** Lanjutkan repository **maqna-cbt** yang menurut pemilik sudah selesai S1–S7. Jangan membuat ulang proyek atau mengulang stage lama. Gunakan RyuExam CBT hanya sebagai referensi kebutuhan fitur, **bukan** untuk meniru brand/UI/kode. Kerjakan **audit gap implementasi nyata**, kemudian tambahkan fitur yang belum ada secara bertahap S8–S11, otomatis sesuai kontrol.

## 1. Baca sebelum mengedit

1. `AGENTS.md` (jika ada), `plan/README.md`, `plan/control.json`, `plan/DECISIONS.md`, `plan/FEATURE_MATRIX.csv`, `plan/STATUS.md`, `plan/PROGRESS.json` (jika ada).
2. Semua berkas di `plan/extensions/ryuexam/`: `README.md`, `GAP_OVERVIEW.md`, `GAP_MATRIX.csv`, `CONTROL.json`, `PERMISSIONS_DELTA.md`, `DATA_MODEL_DELTA.md`, `API_UI_DELTA.md`, `ACCEPTANCE_TESTS.md`, `stages/S8.md`–`S11.md`, dan `reference/AUDIT_RYUEXAM_2026-11-15.md`.
3. Source code backend/frontend real, database migrations, models, controllers, services, policies, React routes/pages, package manifests, tests, scripts, Git status. Pahami konvensi existing; jangan overwrite perubahan yang tidak berkaitan.

## 2. Aturan bisnis yang tidak boleh diubah

- Nama/brand: **maqna-cbt**, target satu sekolah/madrasah (struktur siap future multi-school).
- Stack: CodeIgniter 4 REST API + Shield untuk session auth, React + TypeScript + Vite + shadcn/ui, design tokens maqna-cbt. **Reuse kode existing**, jangan rewrite tanpa alasan teknis dan bukti tes.
- Semua pengguna termasuk siswa login **username atau email + password**. Tidak ada password plaintext di DB/list/kartu PDF/log/CSV.
- Guru publish ujian hanya pada mapel + semua kelas/rombel yang benar-benar diampunya. Admin sekolah publish di sekolah sendiri.
- Nilai/kunci/pembahasan siswa hanya terlihat setelah **manual result release** dari guru/admin authorized; essay pending menahan rilis sesuai keputusan asli.
- Snapshot soal pada publish, server-authoritative timer, stable shuffle/resume, autosave revision, idempotent submit, grading backend-only, RBAC + object/teaching/school scope, test negative permissions.
- Jangan menjadikan fitur 'wajib menjawab semua' sebagai alasan menolak finalisasi otomatis ketika deadline berlalu; catat jawaban kosong dan ikuti kebijakan yang terdefinisi.

## 3. WAJIB: baseline actual repository (sebelum perubahan apa pun)

- Catat `git status`, branch/commit, packages/versions, run existing non-destructive checks jika memungkinkan.
- Petakan route API dan halaman React existing, tabel/migrations, auth/roles, semua tipe soal, jadwal, printing, settings.
- Untuk **setiap RY-001..RY-048**, klasifikasikan berdasar bukti: `EXISTING_VERIFIED`, `PARTIAL`, `MISSING`, `UNVERIFIED` atau `SKIPPED_DISABLED`. Isi `plan/extensions/ryuexam/BASELINE_AUDIT.md` dengan path file, route, test, kekurangan, dan tindakan.
- **Jangan mengira tidak ada hanya karena nama file berbeda.** Cari seluruh repo sebelum membuat model/rute baru. Jika existing verified, jangan dibuat ulang. Jika partial, patch minimal. Jika missing dan enabled, implementasi.
- Jangan mengubah `plan/control.json`, status S0–S7 atau keputusan LOCKED. Jangan menghapus/rename API yang digunakan frontend atau data lama tanpa compatibility path.

## 4. Laksanakan empat tahap ekstensi

- **S8:** dashboard/admin, exam types, room assignment, nomor peserta, login-status filter, import guru/siswa/mapel, safe session revoke.
- **S9:** bank-soal reuse, PG kompleks, benar/salah, matching, rich text/media/KaTeX, DOCX/JSON/ZIP imports (preview/commit), key Excel, per-question points, student preview, exports.
- **S10:** jadwal/token dinamis optional, print kartu peserta F4, daftar hadir, berita acara, monitoring, answer exports, essay entry point, mandatory-answer policy, bulk schedule guards.
- **S11:** analisis distraktor/matriks, branding/logo/favicon/PWA/theme, session policy, RBAC granular, local help, change log UI, about page, dan fitur high-risk **hanya jika diaktifkan**.

Untuk tiap stage: baca DoD; identifikasi fitur `CONTROL.json` enabled, implement real backend+frontend+migrations+policies+tests, jalankan QA dan regression, catat evidence di `PROGRESS.md`. Jika `stop_after_stage` ditentukan, berhenti setelah stage tersebut tanpa mengklaim sisa selesai.

## 5. Safety/Approval (lebih tinggi daripada flag fitur)

- **DILARANG** mereset/menghapus data riil, menjalankan DB restore, truncate, full reseed, deploy produksi, mengirim pesan WhatsApp/email nyata, menampilkan credential, membocorkan jawaban, atau mengubah skor hasil terpublikasi secara diam-diam.
- Respect flags dalam `plan/extensions/ryuexam/CONTROL.json`; RY-002, RY-031, RY-032, RY-040, RY-042, RY-043, RY-044 default **false**.
- Jika user nantinya mengaktifkan high-risk feature, implement gated workflow hanya setelah membaca safety controls; aksi irreversible/external memerlukan **persetujuan eksplisit** sebelum dijalankan. Jika tidak ada, tulis BLOCKED tanpa mengeksekusi operasi.
- Import ZIP/DOCX/JSON harus melindungi dari XSS, XXE, path traversal, decompression bombs, malicious file, oversized uploads dan formula injection pada spreadsheet.
- Anti-cheat tab blur/focus hanya indikator, bukan bukti pelanggaran; default log-only. Jaga pengalaman siswa saat koneksi tidak stabil.
- Maintain responsive desktop/mobile; style shadcn/ui dan color maqna-cbt, tidak perlu pixel-copy UI RyuExam.

## 6. Auditability/Quality

- **Before edit:** update baseline file dengan bukti nyata.
- **During implementation:** update `plan/extensions/ryuexam/PROGRESS.md` per stage, PR-size change list/paths, migrations, API schema, permissions, tests.
- **After each stage:** jalankan relevant unit/integration/API/E2E/build tests dengan exit code asli. Update QA failures; jika belum dapat diuji, tandai `UNVERIFIED`/`BLOCKED`, jangan `DONE`.
- Re-run regressions dari S0–S7 terutama login, teacher scope, attempt timer/autosave/resume, scoring, **manual result release** dan result privacy.
- Ensure all enabled RY IDs have a final state + evidence and no disabled/unsafe mutations were executed.
- **Finish:** ringkas tabel (existing / patched / newly added / disabled / blocked), daftar files & migrations, test commands/results, remaining risks, dan cara menjalankan. Jangan hanya membuat dokumen atau mockup. Jika lingkungan membatasi pengerjaan, capai hasil nyata sebanyak mungkin sambil jujur mencatat blocker untuk dilanjutkan, tanpa mengklaim semua selesai.

**START NOW**: inspect source & dependencies, complete baseline audit, then implement enabled gaps in S8→S11 without waiting for another routine prompt.
