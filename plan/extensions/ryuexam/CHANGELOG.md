# Changelog ekstensi RyuExam

| Waktu WIB | Tahap | Perubahan | Bukti |
|---|---|---|---|
| 2026-11-15 13:26 | Baseline | Mengisi audit 48 kandidat dan status gap tanpa mengubah source, status S0-S7, atau kontrol fitur. | Route/schema/UI inspection dan baseline tests pada `QA_REPORT.md`. |
| 2026-11-15 13:38 | S8 | Menambah migration master jenis/ruang ujian; menghubungkan jenis ke draf, serta API/UI penempatan ruang dan nomor peserta yang diaudit. | `smoke_s8_masters.py`, `smoke_s8_assignments.py`, PHPUnit, build PASS. |
| 2026-11-15 13:42 | S8 | Menambah filter riwayat login siswa dari Shield, status di daftar, dan validasi parameter pada API. | `smoke_s8_login_status.py`, build PASS. |
| 2026-11-15 13:47 | S8 | Menambah registry sesi siswa, guard revokasi, API/UI pemilihan sesi dengan alasan dan konfirmasi, serta audit. | `smoke_s8_revoke_session.py`, `smoke_auth.py`, build PASS. |
| 2026-11-15 13:52 | S8 | Memperluas pipeline impor guru/mapel, membuat ekspor CSV siswa/guru/mapel yang aman untuk spreadsheet, serta tautan UI. | `smoke_s8_staff_imports.py`, `smoke_s8_exports.py`, PHPUnit, build PASS. |
| 2026-11-15 13:59 | S8 | Membuat kartu peserta PDF F4, filter kelas/ruang, nomor tersimpan; menambah tabel token dashboard dan rotasi sekali tampil; ekspor multi penugasan guru. | `smoke_s8_cards.py`, `smoke_s8_tokens.py`, `smoke_s8_staff_imports.py`, build PASS. |
| 2026-11-15 14:05 | S8 | Menambah pratinjau dan nonaktif massal siswa/guru dengan guard aktivitas, alasan, konfirmasi ganda, dan audit. | `smoke_s8_bulk_people.py`, build PASS. |
| 2026-11-15 | S8 | Menuntaskan akses pengawas terbatas, filter login, pencabutan sesi, dan fallback XLSX aman; menutup seluruh gate S8. | `run_s8_smokes.py` 12/12, `run_all_smokes.py` 14/14, PHPUnit 6/6, build dan e2e PASS. |
| 2026-11-15 | S9 parsial | Menambah tipe pilihan jamak, benar/salah, menjodohkan; jawaban terstruktur, evaluator server, editor dan runner. | Migration 000020; smoke S9, aturan nilai, 14 smoke regresi, PHPUnit dan build PASS. |
| 2026-11-15 | S9 parsial | Menambah impor/ekspor JSON berversi dengan pratinjau, commit atomik dan UI; rich text sanitasi server serta renderer KaTeX/HTML aman. | Migration 000021; smoke S9 JSON dan sanitasi, e2e 390/1280 PASS. |
| 2026-11-15 | S9 selesai | Menutup seluruh RY-012..022: tipe soal, rich text, impor DOCX/JSON/ZIP, kunci XLSX, bobot, preview siswa, naskah/kunci PDF-DOCX. | Migration 000020-000022; smoke S9, 14 regresi, 12 S8, PHPUnit, build dan e2e PASS. |
| 2026-11-15 | S10 berjalan | Menambah mode token dinamis terjadwal dan kebijakan wajib jawab semua soal; kontrol mode pada dashboard token. | Migration 000023; sintaks PHP, uji unit token, dan build frontend PASS; acceptance S10 belum dijalankan. |
| 2026-11-15 | S10 berjalan | Menambah PDF daftar hadir dan berita acara F4 terisi/kosong dengan filter rombel/ruang dan tautan cetak. | Smoke token dan dokumen PDF PASS, build frontend PASS; gate tahap belum selesai. |
| 2026-11-15 | S10 berjalan | Menambah ekspor jawaban siswa XLSX dari laporan berizin dengan cakupan ujian/rombel dan tanpa kunci soal. | Sintaks PHP dan permintaan HTTP admin awal PASS; acceptance penuh belum dijalankan. |
| 2026-11-15 | S10 selesai | Menambah status massal aman, catatan berita acara, logo kartu, wajib jawab, token dinamis, ekspor jawaban, pintasan nilai esai; memperbaiki rentang kolom XLSX cadangan. | Migration 000023-000025; 5 smoke S10, 14/14 regresi, 12/12 S8, smoke S9, PHPUnit 6/9, build, e2e PASS. |

| 2026-11-16 | S11 selesai | Menambah matriks/XLSX jawaban, logo sekolah, tema sidebar dan PWA shell, batas sesi aman, izin per guru/pengawas, bantuan lokal, pembaruan dan Tentang. Memperbaiki smoke lama agar membaca mapel dari halaman yang tepat. | Migration 000026-000027; 4 smoke S11, 14 regresi, 12 S8, smoke S9 dan S10, PHPUnit 6/9, build dan e2e PASS. |
