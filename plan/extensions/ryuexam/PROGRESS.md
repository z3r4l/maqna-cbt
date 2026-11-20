# Progress ekstensi S8-S11

| Tahap | Status | Temuan | Bukti | Langkah berikutnya |
|---|---|---|---|---|
| Baseline audit | DONE | 48 kandidat: 4 EXISTING_VERIFIED, 12 PARTIAL, 25 MISSING, 7 DISABLED. | `BASELINE_AUDIT.md`, `GAP_STATUS.csv`; migration status, PHPUnit, build, HTTP smoke parsial. | Mulai S8 pada gap aktif. |
| S8 | DONE | Semua 10 fitur aktif S8 selesai; RY-025 tetap parsial untuk S10. | Migration 000016-000019; 12 smoke S8, 14 smoke regresi, PHPUnit, build, dan e2e PASS. | S9. |
| S9 | DONE | RY-011 verified dan RY-012..022 selesai. | Migration 000020-000022; smoke S9, 14/14 S0-S7, 12/12 S8, PHPUnit, build, e2e PASS. | S10. |
| S10 | DONE | RY-023..030 dan RY-037/038 selesai atau verified; RY-002/031/032 tetap OFF. | Migration 000023-000025; 5 smoke S10, 14/14 S0-S7, 12/12 S8, smoke S9, PHPUnit 6/9, build, e2e PASS. | S11. |
| S11 | DONE | RY-033 tetap verified; RY-034/035/036/039/041/045/046/047 DONE; RY-040/042/043/044 tetap OFF. | Migration 000026-000027; 4 smoke S11, regresi S0-S10, PHPUnit 6/9, build, e2e 320/390/1280 PASS. | Pemeliharaan rutin. |

## Keputusan teknis

- Workspace bukan repository Git (`git status` exit 1). Tidak ada branch/worktree/commit untuk dipakai; edit hanya dalam workspace ini dan dokumentasikan path yang berubah.
- PHP ZIP tidak aktif pada server uji; fallback PharData untuk XLSX impor/ekspor telah diuji dengan 14 smoke regresi dan 12 smoke S8. Kegagalan awal serta perbaikannya dicatat di `QA_REPORT.md`.
- Prioritas ekstensi: reuse service dan schema S0-S7, migration tambahan, tidak menulis ulang alur auth/attempt/release yang lulus.

## Catatan perubahan per stage

### 2026-11-15 13:38 WIB - S8 master dan penempatan peserta

- Migration tambahan 000016 terpasang pada `maqna_cbt_dev`; menambah `exam_types`, `exam_rooms`, dan kolom assignment tanpa menghapus data.
- RY-010 `DONE`: enam jenis standar per sekolah, CRUD/arsip, validasi kode unik, referensi jenis pada draf ujian, UI admin.
- RY-003/004 `PARTIAL`: ruang scoped tahun ajaran dan sekolah, kapasitas, nomor peserta unik per ujian, API dan UI penempatan admin. Belum ada kartu/PDF cetak ulang.
- S8 tetap `IN_PROGRESS`; gate belum terpenuhi. Tes aktual di `QA_REPORT.md`.

### 2026-11-15 13:42 WIB - Filter riwayat login siswa

- RY-005 `PARTIAL`: admin bisa mencari dan memfilter siswa berdasarkan pernah/belum pernah login dari `auth_logins` sukses, dengan pagination server. `last_active` Shield ditampilkan sebagai informasi aktivitas terakhir. Akses pengawas dalam scope ujian belum diimplementasikan.
- `smoke_s8_login_status.py` dan build frontend PASS; status S8 tetap `IN_PROGRESS`.

### 2026-11-15 13:47 WIB - Cabut sesi siswa terpilih

- Migration 000017 menyimpan sesi siswa terpisah dari attempt. Guard memeriksa sesi pada permintaan API berikutnya; logout biasa juga menutup catatan sesi.
- RY-006 `PARTIAL`: admin dapat memilih satu sesi, memberi alasan dan konfirmasi, serta melihat audit. Pengawas dengan scope ujian belum diberi akses.
- `smoke_s8_revoke_session.py`, `smoke_auth.py`, dan build frontend PASS. S8 masih `IN_PROGRESS`.

### 2026-11-15 13:52 WIB - Impor guru/mapel dan ekspor CSV

- RY-007/008/009 `PARTIAL`: CSV siswa/guru/mapel diekspor dengan pembatasan sekolah dan pelindung formula. Impor guru dan mapel memakai pipeline preview/commit existing; penugasan guru memetakan kode mapel, rombel dan semester ke ID valid. Rollback dua baris guru pada konflik diuji.
- Ekspor penugasan guru multi-baris belum lengkap. PHP ZIP masih tidak aktif, sehingga jalur XLSX belum terverifikasi.
- `smoke_s8_staff_imports.py`, `smoke_s8_exports.py`, PHPUnit, dan build frontend PASS.

### 2026-11-15 13:59 WIB - Kartu F4 dan token dashboard

- RY-003/004 `DONE`: penempatan ruang dan nomor peserta muncul pada kartu PDF F4; filter kelas/ruang dan cetak ulang nomor yang sama lulus. RY-025 S10 `PARTIAL` karena logo sekolah belum tersedia.
- RY-001 `DONE`: tabel token scoped dengan filter status/mapel/rombel. Token hanya disimpan sebagai hash; tombol membuat token baru dengan konfirmasi dan menampilkannya sekali untuk disalin. Rotasi tidak memodifikasi attempt yang berjalan.
- Impor guru mendukung beberapa baris penugasan untuk satu guru; ekspor guru menyertakan kode mapel, rombel, dan semester.
- `smoke_s8_cards.py`, `smoke_s8_tokens.py`, `smoke_s8_staff_imports.py`, dan build frontend PASS. S8 tetap `IN_PROGRESS`.

### 2026-11-15 14:05 WIB - Aksi massal aman

- Migration 000018 menambah pratinjau aksi massal. RY-048 `DONE`: pilihan siswa/guru lintas halaman, batas 100 ID, pratinjau jumlah dan penghalang, alasan, teks konfirmasi, checkbox persetujuan, audit, serta nonaktif tanpa penghapusan fisik.
- `smoke_s8_bulk_people.py` dan build frontend PASS. S8 tetap `IN_PROGRESS` karena akses pengawas dan XLSX belum selesai.

### 2026-11-15 13:26 WIB - Baseline audit

- File baru: `BASELINE_AUDIT.md`, `GAP_STATUS.csv`, `PROGRESS.md`, `QA_REPORT.md`, `CHANGELOG.md`, `BLOCKERS.md`.
- Route API: belum berubah. Migration: belum dibuat. Permission: belum berubah.
- Tes: lihat `QA_REPORT.md`.


### 2026-11-15 - S9 jawaban terstruktur (berjalan)

- Migration 000020 menambah kolom pasangan dan jawaban JSON tanpa menghapus data lama. Tiga tipe baru dapat dibuat, diterbitkan, dikerjakan, disimpan dengan CAS, dan dinilai server; soal lama tetap memakai format sebelumnya.
- smoke_s9_structured.py PASS, structured_answer_test.php PASS, 14 smoke S0-S7 PASS, PHPUnit 6/9 PASS, build frontend PASS. S9 tetap IN_PROGRESS karena RY-015..019 dan RY-022 belum lulus gate.


### 2026-11-15 - S9 JSON dan rich text (berjalan)

- Migration 000021 memasang pratinjau transfer soal. JSON versi 1 diimpor/ekspor dengan izin sekolah/guru, validasi skema dan kunci, mode duplikat, commit atomik, serta UI. RY-017 selesai menurut smoke API dan browser.
- Rich text subset HTML disanitasi server dan dibatasi lagi di renderer klien; KaTeX tetap memakai trust=false. Test sanitizer PASS; RY-015 masih parsial sampai media dan UI lengkap diuji.
- E2e penuh setelah tambahan smoke S9 PASS. S9 tetap IN_PROGRESS; RY-016/018/019/022 belum selesai.


### 2026-11-15 - Gate akhir S9

- RY-012..022 DONE; RY-011 tetap EXISTING_VERIFIED. Siklus tiga tipe soal sampai rilis manual, impor DOCX/JSON/ZIP dan kunci XLSX, rich text aman, naskah/kunci PDF-DOCX, serta preview siswa lulus acceptance.
- Migration 000020-000022 terpasang. python tests/smoke_s9_structured.py, php tests/structured_answer_test.php, php tests/question_content_test.php, 14 smoke regresi, 12 smoke S8, PHPUnit, 
- `npm run build` dan `npm run test:e2e` PASS.
- S10 dimulai otomatis sesuai CONTROL.json. S0-S7 tidak diubah.

### 2026-11-15 - S10 token dan kebijakan jawaban (berjalan)

- Migration 000023 terpasang pada DB dev: mode token dan kewajiban menjawab semua soal. Token dinamis memakai jendela 300 detik, tenggang 30 detik saat pergantian, dan pembatasan percobaan kode yang sudah ada. Guru/admin dapat memilih mode serta mengambil kode saat ini dalam scope ujian; kebijakan jawab semua ditegakkan pada submit manual, sedangkan expiry tetap otomatis.
- Pemeriksaan sintaks empat file PHP PASS, `php tests/dynamic_exam_token_test.php` PASS, dan `npm run build` PASS. Acceptance HTTP/browser S10 belum dijalankan; S10 tetap IN_PROGRESS. RY-023 dan RY-037 belum ditandai selesai.
- Smoke HTTP token dinamis PASS. PDF daftar hadir dan berita acara F4, versi terisi/kosong dengan filter rombel/ruang, tersedia untuk staf berwenang; smoke PDF dan build PASS. RY-026/027 menunggu uji penerimaan lengkap dan metadata penandatangan. S10 tetap IN_PROGRESS.
- Ekspor jawaban XLSX per ujian/rombel ditambahkan ke laporan, termasuk jawaban esai, nilai, dan status tanpa kunci soal. Sintaks PHP dan permintaan HTTP admin dengan berkas XLSX valid PASS; cakupan izin negatif dan browser belum diverifikasi.

### 2026-11-15 - Gate akhir S10

- Token dinamis, status massal jeda/aktifkan dengan pratinjau dan guard percobaan, kartu berlogo, daftar hadir dan berita acara, ekspor jawaban, pintasan penilaian esai, serta wajib jawab semua selesai. Migration 000023-000025 terpasang pada DB dev. Fallback XLSX diperbaiki agar rentang kolom mengikuti header, setelah uji ekspor menemukan kolom H-L tersembunyi.
- Lima smoke HTTP S10 PASS, unit token PASS, 14/14 regresi S0-S7 PASS, 12/12 S8 PASS, smoke S9 PASS, PHPUnit 6 tests/9 assertions PASS, validator kontrol PASS, `npm run build` PASS, `npm run test:e2e` PASS termasuk S10 390/1280 px. Build dan smoke S10 diulang setelah pintasan esai, PASS.
- MariaDB lokal sempat mati antar giliran; layanan lokal dijalankan kembali, lalu migration dan gate lulus. S10 DONE; S11 dimulai otomatis.


### 2026-11-16 - Gate akhir S11

- Matriks jawaban siswa dan XLSX dalam scope sekolah/penugasan, logo sekolah, tema sidebar tersimpan, PWA shell tanpa cache API, bantuan/FAQ/template lokal, pembaruan kurasi, dan halaman Tentang selesai. Izin guru/pengawas dapat dibatasi per pengguna oleh admin sekolah; perubahan diaudit dan berlaku pada permintaan berikutnya. Batas sesi per peran tidak mencabut sesi siswa dengan percobaan aktif.
- Migration 000026-000027 terpasang. Empat smoke S11 PASS, 14/14 S0-S7, 12/12 S8, smoke S9 dan lima smoke S10 PASS; PHPUnit 6 tests/9 assertions, validator 48/41/7, `npm run build`, dan `npm run test:e2e` PASS.
- Regresi S0-S7 awal gagal karena smoke mapel membaca hanya halaman pertama setelah fixture bertambah; tiga smoke diperbaiki memakai `perPage=100`, lalu 14/14 lulus. Uji izin setelah suite panjang sempat menerima HTTP 429 dari rate limit fixture; cache development dibersihkan, lalu uji ulang PASS.
