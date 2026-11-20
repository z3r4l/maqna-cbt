# Acceptance & regression tests — S8 sampai S11

## Gerbang awal (wajib)

- [ ] Inventaris route/frontend/model/migrations/test lengkap dan `BASELINE_AUDIT.md` untuk RY-001..RY-048 diisi berdasarkan bukti actual.
- [ ] Tidak ada perubahan tak disengaja pada `plan/STATUS.md`, `plan/PROGRESS.json`, `plan/control.json` atau stage S0–S7.
- [ ] `git diff` ter-review; tidak menimpa perubahan pengguna lain.

## Akademik, dashboard, pengguna (S8)

- [ ] Master jenis ujian dapat dibuat/ubah/arsip; duplicate kode+school memberi 422/409.
- [ ] Kelas tetap terpisah dari ruang ujian; student room assignment unik per exam.
- [ ] Nomor peserta tetap konsisten ketika PDF dicetak ulang; NIS/username tidak terpengaruh.
- [ ] Import guru/siswa/mapel: preview + per-row errors, commit konsisten; failed import tidak meninggalkan data separuh.
- [ ] Filter status login bisa dipakai admin/proctor dengan scope benar; user tak berhak ditolak.
- [ ] Revoke sesi meminta alasan dan konfirmasi; tidak menghapus attempt/jawaban siswa.

## Bank dan jenis soal (S9)

- [ ] PG satu jawaban yang lama tetap berfungsi dan nilainya tetap sama.
- [ ] PG kompleks: benar bila set pilihan tepat; duplicate/unrecognized option ditolak.
- [ ] Benar/salah: setiap statement wajib dikenali; item count dan jawaban kosong terdefinisi.
- [ ] Menjodohkan: semua pair termasuk shuffle tetap koheren dan autosave/resume stabil.
- [ ] Essay manual grading tetap menahan rilis hingga lengkap.
- [ ] Snapshot soal ujian yang sudah dipublikasikan tidak berubah setelah bank diedit.
- [ ] DOCX/JSON/ZIP dapat diimport dengan preview dan akurasi jumlah soal/aset; file rusak dan payload berbahaya ditolak tanpa perubahan bank.
- [ ] ZIP traversal `../`, symlink/absolute paths, decompression bombs, duplicate manifest, oversize reject.
- [ ] Formula KaTeX dan gambar responsif, safe rich HTML (XSS blocked), video embeds allowlisted.
- [ ] Backend student DTO tidak mengandung answer key, bobot rahasia, atau rubrik sebelum rilis.

## Operasional ujian (S10)

- [ ] Token awal/dinamis tervalidasi pada server saat **start attempt**; login/penugasan/jadwal tetap wajib.
- [ ] Auto-save menjelang expiry tetap mengikuti aturan server; token tidak mem-pause timer.
- [ ] Export kartu peserta F4/folio pagination benar, filter kelas/ruang, tanpa password plaintext.
- [ ] Daftar hadir/berita acara PDF dapat dicetak dan mencatat pengawas, kelas, ruangan, jumlah hadir/absen.
- [ ] Status pengawas tidak mengekspos kunci maupun memungkinkan edit nilai; monitoring scoped.
- [ ] Koreksi essay dan release result manual tetap bekerja, termasuk rejeksinya jika essay masih pending.
- [ ] Mandatory-all-answered hanya menghalangi **manual submit**; server auto-expiry tetap menyelesaikan dengan sesuai kebijakan yang didefinisikan.
- [ ] Bulk status edit menolak perubahan saat ujian berjalan kecuali transisi yang didukung dan diotorisasi.
- [ ] Fitur reset/regrade disabled tidak memiliki UI active ataupun API mutasi yang dapat dijalankan.

## Settings, analytics, docs (S11)

- [ ] Branding logo, favicon, PWA manifest aman dan fallback bekerja pada mobile.
- [ ] Tab switching di mobile bukan bukti pelanggaran mutlak; safe default hanya log.
- [ ] Analisis P/DP memiliki rumus dan peringatan ukuran sampel; nilai expected pada fixture terhitung benar.
- [ ] Distractor counts termasuk jawaban kosong benar; ekspor xlsx aman dari formula injection.
- [ ] Role granular: teacher kelas A tidak dapat publish atau export kelas B (403), proctor tidak dapat grade.
- [ ] Tema sidebar tidak merusak kontras/aksesibilitas tombol, mobile nav dan form.
- [ ] Help dan about menampilkan branding maqna-cbt dan template lokal.
- [ ] Backup/restore/maintenance disabled tidak memunculkan endpoint terbuka.

## Regression wajib atas S0–S7

- [ ] Login username/email, logout, reset password via Shield.
- [ ] Teacher assignment → exam publish only for authorized mapel/rombel.
- [ ] Create exam, randomisasi soal/opsi, max attempts, start/resume idempotent.
- [ ] Server-side timer, autosave dengan revision, resume dari putus koneksi.
- [ ] Scoring MCQ lama deterministik; submit idempotent; essay grading sesuai aturan.
- [ ] **Nilai tidak muncul pada siswa sebelum `released_at` manual**, meskipun lewat API, export, refresh, atau cache.
- [ ] Mobile exam (390px) dan desktop dashboard/sidebar tanpa interaksi terpotong.
- [ ] Keamanan endpoint: 401 unauthenticated, 403 unauthorized, 422 invalid input; CSRF/session.

## Cara melaporkan hasil

Setiap tes dicatat sebagai `PASS`, `FAIL`, `NOT_RUN`, atau `BLOCKED`, dengan perintah, file test, exit code, dan konteks DB/env. Jangan membuat tes hijau palsu atau menandai `DONE` kalau hanya `NOT_RUN`.
