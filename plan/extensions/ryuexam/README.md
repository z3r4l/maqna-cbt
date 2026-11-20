# maqna-cbt — Ekstensi kebutuhan dari audit RyuExam CBT (S8–S11)

**Referensi:** Audit UI RyuExam CBT, 15 November 2026, 16 menu admin. **Status:** rancangan pengembangan delta, **bukan** hasil audit kode maqna-cbt. Hasil audit RyuExam sendiri tidak membuktikan perilaku backend/validasi/keamanan. Sumber utuh tersedia di `reference/AUDIT_RYUEXAM_2026-11-15.md`.

## Tujuan

Membandingkan aplikasi maqna-cbt **yang sudah selesai S0–S7** dengan referensi RyuExam, lalu **hanya menambahkan kekurangan** dan memperbaiki perilaku yang tidak sesuai kebutuhan sekolah/madrasah. JANGAN menjalankan ulang S0–S7 atau menghapus/mereset status lama. Nama dan visual tetap **maqna-cbt**, berbasis CodeIgniter 4 API + Shield, React TypeScript, shadcn/ui, dan design tokens maqna-cbt.

## Jalur instalasi

1. Ekstrak ZIP di root repo yang ada. Arsip berisi **hanya file baru** di `plan/ONE_SHOT_RYUEXAM_EXTENSION_PROMPT.md` dan `plan/extensions/ryuexam/`, tidak menimpa `plan/control.json`, `plan/stages/S0.md` ... `S7.md`, source code, atau `AGENTS.md`.
2. Pilih fitur di `plan/extensions/ryuexam/CONTROL.json` (default sebagian risiko tinggi nonaktif).
3. Buka Codex di root repository dan kirim **sekali**: `Laksanakan plan/ONE_SHOT_RYUEXAM_EXTENSION_PROMPT.md.`
4. Codex wajib memeriksa implementasi nyata lebih dulu dan mengisi `BASELINE_AUDIT.md`, lalu melaksanakan S8–S11 sesuai fitur yang diaktifkan.

## File kontrol

| Berkas | Kegunaan |
|---|---|
| `CONTROL.json` | Toggle fitur RY-001..RY-048, stage, kebijakan operasi berisiko |
| `GAP_MATRIX.csv` | Referensi 48 calon fitur/penyempurnaan dan hubungannya dengan S0–S7 |
| `BASELINE_AUDIT.md` | Bukti fitur EXISTING / PARTIAL / MISSING / UNVERIFIED dari repo nyata |
| `PERMISSIONS_DELTA.md` | Izin per peran dan aturan scope sekolah, mapel, rombel, ujian |
| `DATA_MODEL_DELTA.md` | Usulan perubahan schema yang harus dicocokkan dengan tabel ada |
| `API_UI_DELTA.md` | Rute dan halaman baru yang disarankan, tetap kompatibel dengan API saat ini |
| `ACCEPTANCE_TESTS.md` | Tes positif/negatif, keamanan, regression, dan dokumen cetak |
| `stages/S8.md` s.d. `S11.md` | Definisi selesai setiap tahap ekstensi |
| `PROGRESS.md` | Status baru S8–S11 tanpa menyentuh status S0–S7 |
| `reference/AUDIT_RYUEXAM_2026-11-15.md` | Isi file sumber dari pengguna, lokal dan tersedia offline |

## Urutan keputusan saat bertentangan

1. Pengamanan data dan izin yang sudah ditetapkan di `plan/DECISIONS.md` / `plan/control.json`.
2. Keputusan bisnis maqna-cbt: username/email + password, guru hanya sesuai penugasan, hasil ujian **manual release**.
3. `plan/extensions/ryuexam/CONTROL.json` untuk tambahan fitur dan risiko, tidak boleh melemahkan poin 1–2.
4. Spesifikasi ekstensi ini.
5. Audit RyuExam sebagai **referensi desain/fitur**, bukan kewajiban meniru semua perilakunya.

## Fitur berisiko yang nonaktif secara default

RY-002 (share WA), RY-031 (regrade), RY-032 (reset attempt), RY-040 (auto punish), RY-042 (backup SQL), RY-043 (restore/purge), RY-044 (maintenance). Pengaktifan flag **tidak mengizinkan** operasi destruktif atau rilis produksi otomatis; diperlukan persetujuan eksplisit di luar rencana ini.

## Kriteria selesai

Tidak cukup UI tampil: backend/API, otorisasi, validasi, migration, tests, dan perilaku mobile harus diverifikasi. Feature yang sudah benar-benar ada tidak dibangun ulang; tulis `EXISTING_VERIFIED` dengan bukti tes. Jika tidak dapat diuji, tulis `UNVERIFIED`, bukan `DONE`.
