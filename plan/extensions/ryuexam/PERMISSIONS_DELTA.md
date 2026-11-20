# Hak akses tambahan — least privilege / record scope

Sumber audit memperlihatkan kontrol per-menu, tetapi belum membuktikan enforcement backend. Seluruh rute yang ditambah **harus** memakai policy real CodeIgniter 4 dan izin yang sesuai permission registry saat ini (nama di bawah hanya usulan). `superadmin` tidak berhak mengeksekusi tindakan destruktif otomatis hanya karena mempunyai role.

| Fitur | Permission contoh | school_admin | teacher | proctor | student | Pembatasan objek |
|---|---|---|---|---|---|---|
| Jenis ujian, ruang, roster | `academics.manage` | ✓ | — | baca ruang* | — | `school_id` |
| Nomer peserta / kartu | `participants.issue_cards` | ✓ | — | — | sendiri tanpa kredensial* | sekolah/ujian |
| Reset login siswa | `student_sessions.revoke` | ✓ | — | ✓* | — | hanya siswa ujian terawasi; reason log |
| Import siswa/guru/mapel | `academics.import` | ✓ | — | — | — | sekolah dan rekonsiliasi existing |
| Bank soal kelola | `question_banks.manage` | ✓ | ✓* | — | — | pengampu, kepemilikan bank, sekolah |
| Import/eksport bank/key | `question_banks.import` | ✓ | ✓* | — | — | bank yang diperbolehkan |
| Preview soal | `question_banks.preview` | ✓ | ✓* | — | — | tanpa public key exposure |
| Token lihat/salin | `exams.tokens.view` | ✓ | ✓* | ✓* | — | jadwal ditugaskan; jangan ke export public |
| Token konfigurasi | `exams.tokens.configure` | ✓ | ✓* | — | — | seluruh kelas/mapel authorized |
| Jadwal publish/status | existing `exams.publish` | ✓ | ✓* | — | — | assignment seluruh rombel target |
| Monitoring ujian | existing `attempts.monitor` | ✓ | ✓* | ✓* | — | ujian dalam scope |
| Daftar hadir/berita acara | `exam_documents.manage` | ✓ | ✓* | ✓* | — | jadwal/ruang terawasi |
| Kartu peserta ekspor | `participants.cards.export` | ✓ | — | — | — | jangan tampilkan password |
| Koreksi essay | existing `grading.essay` | ✓ | ✓* | — | — | grader authorized |
| Recalculate grade | `grades.recalculate` | opt-in | — | — | — | preview impact, audit, release blocked |
| Reset attempt | `attempts.reset` | opt-in | — | — | — | explicit approval, never silent |
| Lihat analisis butir | existing `analytics.view` | ✓ | ✓* | — | — | mapel/kelas/ujian authorized |
| Branding sekolah | `settings.branding` | ✓ | — | — | — | only own school |
| Admin custom roles | `roles.manage` | — | — | — | — | superadmin only; no elevation self |
| Anti-cheat policy | `settings.exam_security` | opt-in | — | — | — | safe default log-only |
| Maintenance | `maintenance.configure` | — | — | — | — | superadmin explicit deployment approval |
| Backup/restore database | `system.backup`/`system.restore` | — | — | — | — | superadmin + out-of-band approval + disabled default |

`✓*` artinya hanya sesuai penugasan dan permission eksplisit; bukan seluruh sekolah. Role `superadmin` mengelola kebijakan global melalui akses berlapis dan audit.

## Invariant lintas fitur

1. Siswa login dengan **username atau email + password**, tidak lewat nomor peserta saja.
2. Guru hanya boleh membuat/mempublikasikan ujian pada **mapel dan semua rombel target** yang diampu. Bank soal dari guru lain memerlukan izin khusus.
3. Peserta hanya melihat hasil **setelah dirilis manual** oleh guru/admin; fitur laporan, kartu, monitoring dan analisis tidak membuka hasil sebelum rilis kepada siswa.
4. Admin/proctor tidak boleh mendapatkan password plaintext, token aktivasi lama, atau akses ke kunci jawaban tanpa permission.
5. Semua ekspor diberi filter sekolah/kelas/ujian dan mencegah spreadsheet formula injection.
6. Perlakuan kelompok demo RyuExam bukan role model resmi maqna-cbt; gunakan roles existing, permission yang dapat diuji, dan record-level policies.

## Tes negatif minimum

- User tak login → 401 untuk semua rute sensitif.
- Teacher kelas VII-A coba publish jadwal VII-B → 403.
- Proctor unduh kunci soal atau ubah nilai → 403.
- Student akses `attempt_id` siswa lain, token admin, PDF kartu siswa lain → 403/404 yang tidak membocorkan data.
- School admin sekolah A coba membaca atau ekspor sekolah B bila future multi-school enabled → 403/404.
- Non-superadmin mengaktifkan backup/restore/maintenance atau edit role → 403.
- User berhak tetapi operation disabled by control/security policy → 403/409, tidak ada side effect.
