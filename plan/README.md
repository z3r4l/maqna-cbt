# maqna-cbt / plan — kendali implementasi S0–S7

**Tujuan:** satu master prompt untuk meminta Codex membangun seluruh aplikasi CBT Sekolah/Madrasah bertahap, sambil memberi pemilik proyek kendali eksplisit atas scope, prioritas, pengujian, dan perubahan. Semua dokumen/diagram di folder ini tersedia offline.

## Jalankan dalam satu prompt

1. Ekstrak paket ZIP ke **root repo `maqna-cbt/`**; jangan menaruh `plan/` di dalam `frontend/` atau `backend/`.
2. Tinjau dan ubah `plan/control.json` (semua tahap aktif secara default). Tulis pengecualian di `plan/CHANGE_REQUESTS.md` sebelum eksekusi jika perlu.
3. Buka repo root pada Codex (VS Code/Codex CLI). Pastikan mode implementasi tersedia dan tools memiliki izin akses workspace; jangan gunakan **Plan-only mode** untuk menjalankan kode.
4. Salin isi `plan/ONE_SHOT_CODEX_PROMPT.md` sekali saja ke Codex (atau gunakan pesan ringkas `Implementasikan plan/ONE_SHOT_CODEX_PROMPT.md sesuai plan/control.json secara end-to-end.`).
5. Codex harus membaca seluruh plan, mengimplementasikan stage secara berurutan, memeriksa pengujian, memperbarui `plan/STATUS.md` dan `plan/PROGRESS.json`.

**Satu prompt bukan jaminan semuanya selesai dalam satu eksekusi.** Eksekusi panjang mungkin dibatasi context window, waktu, akses internet, izin, atau error dependensi. File status memungkinkan dilanjutkan tanpa mengulang pekerjaan.

## File kendali utama

| File | Fungsi | Diedit oleh |
|---|---|---|
| `control.json` | Enable/disable stage atau fitur, stop checkpoint, aturan keamanan | **Pemilik proyek** |
| `DECISIONS.md` | Keputusan bisnis yang mengalahkan asumsi dokumen lama | Pemilik; Codex usul perubahan |
| `CHANGE_REQUESTS.md` | Permintaan tambahan/larangan, harus sudah disetujui sebelum scope berubah | **Pemilik proyek** |
| `ONE_SHOT_CODEX_PROMPT.md` | Instruksi eksekusi satu kali untuk Codex | Pemilik proyek |
| `stages/S0.md` ... `S7.md` | Definition of Done per tahap | Pemilik; Codex menandai evidensi di status |
| `FEATURE_MATRIX.csv` | Inventaris 38 fitur, prioritas, stage dan test | Pemilik; Codex isi bukti terpisah |
| `STATUS.md`, `PROGRESS.json` | Progres nyata per stage/fitur | Codex |
| `QA_REPORT.md`, `CHANGELOG.md`, `BLOCKERS.md` | Hasil uji, perubahan, masalah | Codex |
| `references/` | Spesifikasi asli, ERD, desain dan aset lokal | Referensi read-only |

## Cara mengontrol Codex

- **Bangun semuanya:** `execution.enabled_stages` = S0..S7, `stop_after_stage: null`, semua `features.FR-xx: true`.
- **Hanya MVP:** `execution.enabled_stages` = S0..S5; ubah FR P1/P2 ke `false`.
- **Berhenti setelah S3:** `execution.stop_after_stage: "S3"` (batas kontrol, bukan persetujuan untuk skip testing).
- **Mematikan fitur:** ubah misalnya `features.FR-28` menjadi `false`; Codex harus skip deteksi pindah tab.
- **Mewajibkan review manual:** `execution.require_review_after_each_stage: true` (proses menjadi bertahap dan memerlukan turn lanjutan).
- **Larangan tambahan:** tulis di `CHANGE_REQUESTS.md` dan tandai statusnya APPROVED.

**Prioritas aturan:** `control.json` > `DECISIONS.md` > `CHANGE_REQUESTS.md` (approved only, tidak boleh menimpa kontrol keamanan) > `ARCHITECTURE_RULES.md` > `stages/` > `references/` > boilerplate lama. Jika konflik, Catat `BLOCKERS.md` dan pilih tindakan paling aman.

## File dalam paket

- Root `AGENTS.md`: panduan repo untuk Codex.
- `plan/` mandiri: seluruh referensi inti sudah disalin, tidak tergantung internet untuk dibaca.
- Sebagian dependensi CodeIgniter/React tentu tetap perlu di-install dari package registry saat pengembangan, bukan untuk membaca plan.
- `python3 plan/scripts/validate_plan.py` mengecek format kontrol, jumlah fitur, stage files, dan referensi lokal.

## Status awal

Semua status S0–S7 masih **NOT_STARTED**. Boilerplate lama adalah demo dan tidak dapat dianggap lulus production acceptance tanpa implementasi dan pengujian ulang.
