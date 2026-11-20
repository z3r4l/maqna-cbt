# Analisis kesenjangan terhadap rencana S0–S7

**Metode:** membandingkan `plan/FEATURE_MATRIX.csv` asli (FR-01..FR-38) dengan audit antarmuka 16 menu RyuExam CBT. **Bukan** pemeriksaan kode maqna-cbt versi sekarang. Status akhir fitur aktual wajib diisi setelah baseline scan oleh Codex.

| Menu referensi | Yang sudah direncanakan pada S0–S7 | Kandidat tambahan / validasi S8–S11 | ID |
|---|---|---|---|
| 1. Dashboard | Dashboard laporan, jadwal, monitoring | tabel token + filter, copy, opsi berbagi eksplisit | RY-001..002 |
| 2. Data Kelas | tahun ajaran, rombel/kelas | ruang ujian terpisah dari kelas | RY-003 |
| 3. Data Siswa | CRUD & import peserta, login | nomor peserta, filter login, revoke sesi, mass actions | RY-004..007,048 |
| 4. Data Guru | CRUD guru, teaching assignments | import guru dan mapel/rombel terstruktur | RY-008 |
| 5. Mata Pelajaran | master mata pelajaran | import/export massal terverifikasi | RY-009 |
| 6. Jenis Ujian | jenis belum eksplisit pada 38 FR | master PH/PTS/PAS/PAT/US/TO | RY-010 |
| 7. Bank Soal | single MCQ/essay, gambar, math, Excel, version | multi-MCQ, benar/salah, matching, DOCX/JSON/ZIP, preview, key Excel, poin | RY-011..022 |
| 8. Jadwal Ujian | CRUD exam/jadwal, token, shuffle, assignments | token dinamis, status massal, mandatory answer, toggle token | RY-023..024,037..038 |
| 9. Cetak Kartu Peserta | belum eksplisit | kartu peserta PDF dengan header/ruang/F4 | RY-025 |
| 10. Administrasi | laporan hasil/attendance statistik | daftar hadir dan berita acara yang bisa dicetak | RY-026..027 |
| 11. Status Ujian | monitoring, essay grade, export nilai | export jawaban, regrade/reset berisiko (nonaktif) | RY-028..032 |
| 12. Analisis Soal | analisis butir P, DP, distraktor | validasi rumus, matriks, pilihan kosong/export | RY-033..034 |
| 13. Pengaturan | RBAC, security, tab detection, basic settings | branding, PWA, role granular, session rules, maintenance/backup (gated) | RY-035..036,039..044 |
| 14. Log Pembaruan | `plan/CHANGELOG.md` internal | UI release notes read-only | RY-045 |
| 15. Petunjuk | docs plan dan API | panduan dalam aplikasi / template import lokal | RY-046 |
| 16. Tentang | branding project | halaman info versi maqna-cbt | RY-047 |

## Prioritas penambahan untuk sekolah/madrasah

**P1 / paling berguna:** master jenis ujian; ruang + nomor peserta; tiga tipe soal baru; import DOCX/JSON/ZIP; preview siswa; kartu peserta; daftar hadir + berita acara; scoped monitoring, branding. Validasi dahulu yang ternyata sudah tersedia.

**P2 / setelah alur inti stabil:** token dinamis, import kunci Excel, export naskah, distribusi token manual, distribusi jawaban/matriks, role granular lebih lanjut, PWA, help/about, pemeliharaan. Operasi yang dapat menghapus/mengubah skor/akses ujian **disabled** hingga disetujui.

## Tidak boleh meniru mentah-mentah

- Audit antarmuka demo menampilkan password pada beberapa tabel/kartu. **Maqna-cbt tidak boleh menampilkan password plaintext**, bahkan untuk admin. Gunakan alur reset/invite sementara.
- Control RyuExam untuk reset ujian, hapus massal, restore SQL dan anti-cheat auto-submit bukan bukti aman. Implementasi harus melewati gating, audit, validasi kebijakan, dan persetujuan.
- Hasil ujian tetap **dirilis manual oleh guru yang berwenang/admin**; setting 'tampilkan nilai' tidak boleh menimpa keputusan bisnis ini.
- Hak akses guru berdasarkan `teaching_assignments` dan sekolah, bukan hanya label role.
- Jangan menyalin desain, logo, identitas, screenshot atau source code pihak ketiga; gunakan gaya maqna-cbt.

## Ketidakpastian sumber

Audit pada tanggal 15 November 2026 hanya memeriksa halaman admin dan modal tanpa submit; tidak menguji backend, role non-admin, mobile, performa dan kebijakan validasi aktual. Perlakukan semua perilaku server sebagai hipotesis kebutuhan, bukan fakta RyuExam.
