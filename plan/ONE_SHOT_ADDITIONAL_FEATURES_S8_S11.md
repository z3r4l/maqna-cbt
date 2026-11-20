# ONE-SHOT PROMPT CODEX — PENAMBAHAN FITUR MAQNA-CBT (S8–S11)

> **Instruksi tunggal untuk Codex.** Salin seluruh isi dokumen ini ke Codex di root repository `maqna-cbt`, ATAU letakkan dokumen di `plan/ONE_SHOT_ADDITIONAL_FEATURES_S8_S11.md` kemudian beri instruksi: **"Jalankan isi `plan/ONE_SHOT_ADDITIONAL_FEATURES_S8_S11.md` sepenuhnya."**
>
> **Tujuan:** audit versi maqna-cbt yang **sudah selesai S0–S7**, bandingkan dengan audit referensi RyuExam CBT, lalu implementasikan **hanya gap aktual** pada S8–S11. Jangan mengulang pembuatan project, menghapus fitur lama, atau menyalin brand/kode/desain pihak ketiga.

## A. Konteks dan aturan proyek yang WAJIB dipertahankan

- **Brand:** `maqna-cbt` saja. Target **sekolah/madrasah**, awalnya satu institusi; desain data tetap mendukung perluasan kelak.
- **Stack:** CodeIgniter 4 REST API (gunakan mekanisme autentikasi terpasang; CodeIgniter Shield jika memang dipakai proyek), React + TypeScript + Vite, shadcn/ui, Tailwind dan design tokens warna teal maqna-cbt. Gunakan pola architecture, lint, test framework, komponen, dan conventions **existing**.
- **Login siswa:** username atau email + password; jangan tampilkan, ekspor, log, atau cetak password plaintext.
- **Hak publikasi ujian:** admin sekolah; guru hanya untuk mapel dan **semua rombel tujuan** yang aktif di `teaching_assignments`. Permission harus melalui role **dan** policy akses objek di backend.
- **Hasil ujian:** dirilis **manual** oleh guru yang diotorisasi/admin, bukan otomatis ketika submit/akhir jadwal. Kunci jawaban/soal rahasia tidak bocor kepada siswa sebelum rilis.
- Pertahankan **server-side deadline**, stable question/option order pada attempt, snapshot soal terbit, autosave aman terhadap balapan request, resume setelah putus internet, submit idempotent, perhitungan nilai hanya di backend, essay menunggu koreksi.
- Jangan mengubah kode/DB yang sudah berhasil tanpa alasan. Jangan membuat migrasi ulang/tabel duplikat jika schema existing sudah dapat dipakai.
- Referensi `ryuexam-cbt-audit-untuk-kebutuhan-maqna-cbt.md` hanya inspeksi **UI admin demo** tanggal 15 November 2026; validasi backend, role, alur, dan hasil submit aslinya **tidak terverifikasi**. Gunakan sebagai daftar ide, bukan spesifikasi perilaku yang pasti.

## B. Perintah awal — pelajari repository, bukan langsung coding

1. Baca `AGENTS.md` dan seluruh `plan/` yang ada: `DECISIONS.md`, `ARCHITECTURE_RULES.md`, `DATA_MODEL.md`, `API_CONTRACT.md`, `FEATURE_MATRIX.csv`, `STATUS.md`, `PROGRESS.json`, `QA_REPORT.md`, `CHANGELOG.md`, stage S0–S7, jika tersedia. Prioritaskan keputusan pengguna terbaru di prompt ini jika bertentangan dengan contoh lama.
2. Jika tersedia, baca `plan/extensions/ryuexam/` dan referensi audit pada path `plan/extensions/ryuexam/reference/AUDIT_RYUEXAM_2026-11-15.md` atau berkas audit yang ada pada repository. Jika tidak tersedia, gunakan daftar 48 item di bawah sebagai spesifikasi minimum; **jangan berhenti hanya karena audit eksternal tidak ada**.
3. Identifikasi branch dan `git status`, daftar migrasi/tabel nyata, API routes, services, policies, role/permissions, React route/pages, test suite, import/export handlers, dan asset handling. **Jangan overwrite uncommitted user changes.**
4. Jalankan baseline tests **yang aman/non-destruktif** jika environment memungkinkan. Catat perintah yang dijalankan dan hasil sebenarnya; jangan menyebut `PASS` untuk test yang tidak dijalankan.
5. Buat atau perbarui **hanya** folder `plan/extensions/ryuexam/` sebagai catatan ekstensi; **jangan reset atau timpa file status S0–S7**. Buat `BASELINE_AUDIT.md`, `GAP_STATUS.csv`, `PROGRESS.md`, `QA_REPORT.md`, `CHANGELOG.md`, `BLOCKERS.md` jika belum tersedia. Jangan overwrite progress dari sesi sebelumnya; lanjutkan dari bukti existing.
6. Untuk setiap `RY-001`–`RY-048`, klasifikasi salah satu: `EXISTING_VERIFIED`, `PARTIAL`, `MISSING`, `UNVERIFIED`, `DISABLED`, `BLOCKED`. Sertakan minimal lokasi file/rute dan bukti test/observasi. **Label `EXISTING_VERIFIED` perlu bukti, bukan dugaan dari nama file**.
7. Pada fitur `PARTIAL`, tambahkan perilaku yang kurang dengan perubahan sekecil mungkin. Pada `MISSING` yang aktif, implementasikan frontend + backend + validation + migration + policy + tests sesuai kebutuhan. Pada yang `EXISTING_VERIFIED`, jangan tulis ulang. Catat hasil akhir per item.

## C. Scope fitur yang harus diaudit (48 item)

**Aturan aktivasi:** daftar ini adalah kandidat fitur, bukan perintah untuk membuat duplikat. Kolom `Default` merupakan default yang aman ketika belum ada file kontrol. `OFF` berarti dokumentasikan gap tetapi **JANGAN implementasikan aksi berisiko atau mengeksekusinya** tanpa permintaan persetujuan eksplisit. Jika sudah ada `plan/extensions/ryuexam/CONTROL.json`, patuhi nilai user yang ada dengan tetap tunduk pada larangan tindakan destruktif dan pengiriman eksternal.

| ID | Stage | Prioritas | Default | Kandidat fitur | Kriteria garis besar |
|---|---|---|---|---|---|
| RY-001 | S8 | P1 | ON | Tabel token ujian + filter status/mapel/kelas + salin token | Filter scoped; token hanya untuk role yang diizinkan; copy satu tindakan pengguna. |
| RY-002 | S10 | P2 | OFF | Bagikan token terpilih via WhatsApp dengan aksi eksplisit | Hanya compose link/pesan atas aksi user; tanpa otomatis kirim; tanpa password. |
| RY-003 | S8 | P1 | ON | Ruang ujian dan penempatan siswa per jadwal | Ruang terpisah dari rombel; assignment scoped sekolah dan tahun. |
| RY-004 | S8 | P1 | ON | Nomor peserta ujian per siswa/per ujian | Nomor peserta unik dalam scope kegiatan; tidak menimpa NIS/username. |
| RY-005 | S8 | P1 | ON | Filter status login, pencarian, pagination | Status last_active akurat dan aman; pencarian terotorisasi; pagination server. |
| RY-006 | S8 | P1 | ON | Reset login/sesi siswa dengan alasan dan audit | Revoke sesi terpilih setelah konfirmasi; alasan tercatat; tidak hapus attempt. |
| RY-007 | S8 | P1 | ON | Import/export siswa plus template dan validasi pratinjau | Preview sebelum commit; error per baris; CSV formula injection aman. |
| RY-008 | S8 | P1 | ON | Import/export guru dengan assignment mapel/rombel | Preview dan mapping; assignment tidak berbasis teks bebas; deduplikasi. |
| RY-009 | S8 | P2 | ON | Import/export mata pelajaran | Kode mapel unik per sekolah; preview duplikat sebelum commit. |
| RY-010 | S8 | P1 | ON | Master jenis ujian PH, PTS, PAS, PAT, US, TO | CRUD kode unik, archive aman; referensial ke bank/jadwal. |
| RY-011 | S9 | P0 | ON | Bank soal sebagai kontainer reuse lintas jadwal | Bank terpisah dari jadwal; draft/edit tidak mengubah snapshot ujian aktif. |
| RY-012 | S9 | P1 | ON | Pilihan ganda kompleks (jawaban jamak) | Scoring backend exact-set default; semua opsi snapshot; negative tests. |
| RY-013 | S9 | P1 | ON | Benar/salah per pernyataan | Setiap baris dinilai deterministik; jawaban kosong jelas. |
| RY-014 | S9 | P1 | ON | Menjodohkan pasangan | Pasangan stabil dengan randomisasi display; scoring server. |
| RY-015 | S9 | P1 | ON | Rich text, gambar, video, LaTeX/KaTeX | Sanitasi HTML, URL/embed allowlist; media mobile dan accessible. |
| RY-016 | S9 | P1 | ON | Import soal Word DOCX dengan aset | Parse template; preview; retain inline assets; error soal bukan silently discard. |
| RY-017 | S9 | P1 | ON | Import/ekspor JSON tervalidasi | Schema versioned; rejects unknown/malicious payload; atomic preview+commit. |
| RY-018 | S9 | P1 | ON | Import/backup ZIP bank soal + media | Block zip-slip/bombs; mode append/replace; snapshot dan backup verified. |
| RY-019 | S9 | P2 | ON | Import kunci jawaban Excel dengan pratinjau | Validasi tipe/key; preview changes; locked if active snapshot; audit. |
| RY-020 | S9 | P1 | ON | Set poin per item dan normalisasi total | Skor tetap 0-100; bobot nol/negatif dicegah; hasil lama immutable. |
| RY-021 | S9 | P1 | ON | Preview siswa sebelum publish | Preview tanpa membocorkan kunci di student route; responsif. |
| RY-022 | S9 | P2 | ON | Unduh naskah/template bank soal tanpa kunci opsional | PDF/Word soal scoped; kunci terpisah hanya bagi authorized. |
| RY-023 | S10 | P2 | ON | Token dinamis rotasi terjadwal | TTL, clock server, offline grace defined; token only extra gate; rate limit. |
| RY-024 | S10 | P2 | ON | Ubah status jadwal massal dengan guard | Preview affected attempts; no mutation running/completed; confirmation+audit. |
| RY-025 | S10 | P1 | ON | Kartu peserta PDF filter kelas/ruang/tata letak F4 | PDF paginated, logo/no participant; no plaintext password; printable. |
| RY-026 | S10 | P1 | ON | Daftar hadir terisi/kosong cetak PDF | Attendance by exam/class/room; signer metadata; print and PDF. |
| RY-027 | S10 | P1 | ON | Berita acara ujian terisi/kosong dan catatan | Capture time, attendance, supervisor, incident notes; export PDF. |
| RY-028 | S10 | P1 | ON | Tampilan progres/status peserta per ujian + refresh | Scoped polling, status started/submitted/expired, heartbeat TTL. |
| RY-029 | S10 | P1 | ON | Export jawaban siswa dan nilai per ujian | No leak to student; essay text safe; privacy and permission applied. |
| RY-030 | S10 | P1 | ON | Koreksi esai dari panel status ujian | Only authorized grader; grading before manual result release; audited. |
| RY-031 | S10 | P2 | OFF | Hitung ulang nilai dengan snapshot/versi revisi | Dry-run impact preview; versioned correction; no silent overwrite released results. |
| RY-032 | S10 | P2 | OFF | Reset attempt individual atau massal | Strict explicit approval; backup, reason, reversible policy; never auto-reset. |
| RY-033 | S11 | P1 | ON | Kesukaran P & daya pembeda DP | Document formulas, sample size warning, scoped analysis. |
| RY-034 | S11 | P2 | ON | Sebaran pengecoh, matriks siswa dan export Excel | Aggregates correct, blank, choices; avoid leaking individual public answers. |
| RY-035 | S11 | P1 | ON | Identitas sekolah, logo, favicon, header soal | Sanitize uploads; assets school scoped; preview; fallback defaults. |
| RY-036 | S11 | P2 | ON | PWA manifest/icon 192,512 + tema sidebar | Responsive theme tokens; PWA no offline exam claim; no copyrighted assets. |
| RY-037 | S10 | P1 | ON | Kebijakan wajib jawab semua soal per ujian | Server confirms completeness; expiry auto-submit still allowed per policy. |
| RY-038 | S10 | P1 | ON | Pengaturan token diperlukan/tidak per ujian | Does not bypass login/assignment and exam availability. |
| RY-039 | S11 | P2 | ON | Cegah multi-login dan remote revoke sesi | No lost valid attempt on reconnection; configurable per role; audited. |
| RY-040 | S11 | P2 | OFF | Kebijakan anti-cheat: thresholds/auto-logout/auto-submit | Signals not cheating proof; safe default log-only; penalties gated. |
| RY-041 | S11 | P1 | ON | Preset role admin dan granular per-menu permissions | No teacher cross-class access; permission edits audited and cached safely. |
| RY-042 | S11 | P2 | OFF | Backup database SQL | Offline encryption/retention/secure access; never credentials exposed. |
| RY-043 | S11 | P2 | OFF | Restore database/empty selected datasets | Design-only unless explicit manual approval; never executed automatically. |
| RY-044 | S11 | P2 | OFF | Maintenance mode for student/teacher with message/end | Whitelist admins, failsafe expiry, preserve active exam if possible. |
| RY-045 | S11 | P2 | ON | In-app changelog/version release notes | Read-only sanitized release history, no secret technical logs. |
| RY-046 | S11 | P2 | ON | Bantuan dalam aplikasi dan unduh template impor | Help pages per role, local templates and FAQ, no external dependency. |
| RY-047 | S11 | P2 | ON | Halaman tentang, versi, dukungan | Brand only maqna-cbt; build version, docs links; no RyuExam branding. |
| RY-048 | S8 | P2 | ON | Aksi bulk aman untuk data siswa/guru dengan pratinjau | Selection scoped/paginated; soft archive; no bulk physical delete by default. |


## D. Implementasi berurutan dan definition of done

### S8 — Administrasi sekolah dan pengelolaan akun (RY-001, RY-003..010, RY-048)

- Dashboard token/status dengan filter mapel, kelas, jadwal, salin informasi **hanya kepada role authorized**; token sensitif dipisahkan dari data publik. Berbagi via WhatsApp (**RY-002 OFF**) jangan mengirim pesan nyata.
- Bedakan **rombel** dan **ruang ujian**. Ruang dapat dikaitkan ke jadwal dan penempatan siswa; nomor peserta boleh per sesi/jadwal bila dibutuhkan. Tambahkan unique constraints sesuai lingkup dan cek konflik penempatan.
- Manajemen siswa: pencarian, filter rombel dan status login, pagination; reset session oleh admin dengan alasan dan audit. `status login` hanya berdasar state sesi yang benar-benar bisa ditentukan server, bukan menebak online dari last login.
- Import/export siswa, guru, dan mapel memakai template versi resmi aplikasi; preview, validasi baris, deteksi duplikat, error downloadable, transaksi/partial rules eksplisit. Import guru harus menggunakan FK penugasan mengajar, bukan string mapel bebas.
- Master `exam_types` (PH, PTS, PAS, PAT, US, TO), CRUD, unik kode; referensi pada bank soal atau ujian tanpa merusak data existing.
- Tindakan bulk harus punya preview jumlah record, filter scope dan double confirmation, termasuk larangan hapus data riil tanpa otorisasi. Lebih baik soft delete/deactivate daripada hard delete untuk akun bersejarah.
- **DoD S8:** migration non-destruktif; scoped CRUD; template import/export; UI desktop/mobile; positive/negative role tests; original S0–S7 user login dan teaching permissions tetap lulus.

### S9 — Bank soal lebih kaya dan interoperabilitas (RY-011..022)

- Pertahankan bank soal reusable lintas jadwal dan snapshot per publish. Tambah `multiple_choice_multiple`, `true_false_matrix`, dan `matching`, selain `multiple_choice_single` serta `essay` existing.
- **Schema per jenis soal** tervalidasi ketat (opsi, daftar pernyataan/baris/pasangan, kunci, poin). Skema respons attempt, save, snapshot, rendering React, dan grading harus terhubung konsisten. Untuk PG kompleks dan matching gunakan **exact-match grading** default; jika fractional scoring ditambahkan harus per-ujian, terdokumentasi, dan teruji. Untuk benar/salah tabel, dokumentasikan apakah exact-match seluruh baris atau skor per baris; gunakan exact-match default agar konsisten.
- Rich text dan aset lokal (gambar, video/link tepercaya, inline/block KaTeX) dengan sanitasi XSS; upload file dibatasi jenis, MIME, ukuran, penyimpanan aman, akses sesuai role.
- Import `.docx` dan JSON, backup/restore **bank soal dalam ZIP** (bukan database); ZIP harus dilindungi dari zip-slip, oversized payload, zip bomb, dan file berbahaya; DOCX dari XXE, path traversal, link remote, macro/active content; normalisasi media.
- **Semua import dua tahap**: upload/parse+preview tanpa mutasi, lalu `confirm/commit` validasi ulang di server. Default `append`, replace/overwrite butuh eksplisit konfirmasi, izin tinggi, backup/log dan dilarang menyentuh attempt aktif/historis. Error per baris/per soal bisa diunduh.
- Import kunci Excel harus cocok dengan ID/snapshot versi item yang dimaksud; jangan diam-diam mengubah hasil ujian terpublikasi. Set poin per soal, normalisasi total opsional dengan aturan pembulatan eksplisit; preview siswa **tidak** memperlihatkan kunci kecuali pada mode guru yang aman. Unduh naskah tanpa kunci default.
- **DoD S9:** tiap jenis soal render, simpan, resume, submit, auto-score/essay pending dengan tes; parse/import/cancel invalid file tanpa mutasi; media/KaTeX aman; regression existing bank & attempts lulus.

### S10 — Jadwal, kartu peserta, administrasi dan status ujian (RY-023..032, RY-037..038)

- Token statis existing tetap berjalan. Token dinamis optional per ujian dengan masa berlaku server-side, rotasi dan izin akses; jangan memutus sesi siswa yang sudah terautentikasi dan sedang mengerjakan tanpa alasan. Toggle wajib token harus per ujian dan default mengikuti policy yang berlaku.
- Bulk status jadwal harus menolak perubahan yang merusak attempts aktif atau mengubah snapshot/room/assignment historis. Implement safe preview + dry-run.
- Cetak **kartu peserta PDF** filter kelas/ruang/nomor, urutan, kop/logo, pratinjau, kertas F4/Folio 215×330 mm, opsi 12 kartu/lembar sesuai audit; layout harus benar saat print dan hasil PDF. **Dilarang mencantumkan plaintext password**. Pakai username dan, bila diminta oleh kebijakan, kode aktivasi/reset sekali pakai yang aman dan tidak disimpan sebagai plaintext permanen.
- Cetak **daftar hadir** dan **berita acara** per jadwal, kelas, ruang, sesi; format terisi atau kosong, kop, tanggal/jam, tanda tangan kepala sekolah/proktor/pengawas, catatan manual; data absen berasal dari status yang dibuktikan server, bukan sekadar logout browser.
- Status ujian menampilkan progress attempt, mulai/selesai, durasi, jawaban tersimpan, nilai khusus authorized, filter serta refresh. Ekspor jawaban/hasil terverifikasi per jadwal dengan scope dan proteksi dari formula injection di CSV/XLSX; audit pengunduhan data sensitif.
- Koreksi essay dari layar status ujian gunakan workflow scoring yang sudah ada. Opsi “wajib jawab semua” hanya memvalidasi **submit manual**; timer habis tetap auto-submit agar attempt tidak menggantung.
- **RY-031 hitung ulang nilai dan RY-032 reset attempt tetap OFF**; jangan menjalankan pengubahan nilai historis ataupun reset. Jika nanti disetujui, harus ada versioning, audit before/after, impact preview, approval, dan menjaga hasil yang sudah dirilis.
- **DoD S10:** PDF bisa dibuka dan dicek ukuran/layout, laporan sesuai filter, token schedule benar, permissions ketat, regression timer-autosave-resume-idempotent submit + manual result release lulus.

### S11 — Analisis, branding, keamanan, bantuan (RY-033..047)

- Validasi analisis butir: tingkat kesukaran P, daya pembeda DP (definisikan kelompok atas/bawah dan kebijakan nilai kosong), matriks jawaban, distribusi opsi/distraktor, peserta efektif, batas minimum sampel dan ekspor Excel. Jangan memberi rekomendasi statistik pasti bila sampel tak cukup.
- Pengaturan sekolah: nama, logo, kop soal, favicon, aset PWA 192/512, warna/tema navigasi; semua harus memakai branding **maqna-cbt** dan komponen shadcn/ui responsif; jangan mengganti warna per halaman dengan hardcode tersebar.
- Pencegahan multi-login berbasis sesi/refresh token/revoke yang sesuai mekanisme auth existing; jangan memutus attempt tanpa alur resume. Role preset dan per-menu permission perlu least privilege dan backend policies, bukan sekadar menyembunyikan tombol React.
- Anti-cheat tab blur/focus hanya sinyal (indikasi), **bukan bukti kecurangan**. Default catat event saja. Auto-logout/auto-submit karena pelanggaran (**RY-040 OFF**) harus dilarang kecuali approval dan aturan sekolah teruji.
- UI **Log Pembaruan**, **Petunjuk/FAQ**, dan **Tentang maqna-cbt**. Gunakan template import milik proyek serta info versi dari metadata aplikasi; tidak ada link wajib ke layanan AI pihak ketiga.
- Backup SQL, restore/empty DB, dan maintenance (**RY-042/043/044 OFF**) tidak dibuat/diaktifkan sebagai aksi sistem yang bisa dijalankan otomatis. Jika suatu saat diaktifkan, spesifikasi tambahan diperlukan meliputi approval, backup teruji, akses operator, lingkungan, dan prosedur pemulihan. **Jangan menjalankan operasi backup/restore nyata sebagai eksperimen.**
- **DoD S11:** tests formula analisis + empty/small sample, settings upload dan scoped access, role abuse, UI/help navigation, regression end-to-end S0–S10; disabled risks tetap tidak dieksekusi.

## E. Kontrol pelaksanaan dan file wajib dalam `plan/`

1. Jika `plan/extensions/ryuexam/CONTROL.json` tidak ada, buat dengan nilai default ON/OFF yang tercantum pada tabel C, `enabled_stages: [S8,S9,S10,S11]`, `stop_after_stage: null`, `require_review_after_each_stage: false`, `allow_destructive_data_ops: false`, `allow_external_messages: false`, `auto_deploy: false`. Jika ada, **JANGAN reset perubahan user**.
2. Dalam repository, simpan audit awal di `plan/extensions/ryuexam/BASELINE_AUDIT.md` dan status setiap RY-ID di `GAP_STATUS.csv` dengan kolom `id,existing_status,evidence_files,evidence_tests,action,final_status,notes`.
3. Gunakan `plan/extensions/ryuexam/PROGRESS.md` untuk catatan per stage: file perubahan, route, migration, policy, test command, hasil, blocked issue, dan keputusan teknis. Tambahkan `QA_REPORT.md`, `CHANGELOG.md`, `BLOCKERS.md` bila tidak ada.
4. Saat melanjutkan sesi yang terputus, baca progress yang sudah ada, pastikan state Git/DB, dan **lanjutkan hanya item yang belum verified**. Jangan menghapus riwayat.
5. Bila ada `require_review_after_each_stage: true`, jeda untuk persetujuan sebelum stage berikutnya. Bila `stop_after_stage` terisi, hentikan sesuai batas. Jika disabled, tetap catat di gap matrix, jangan implement.
6. Migrations harus **idempotent dalam lifecycle framework**, forward-only, add-only jika memungkinkan, backup dan uji rollback di lingkungan disposable; tidak boleh menjalankan destructive migration pada database produksi.
7. Perbarui contract API, OpenAPI/docs yang sudah ada, permission matrix dan struktur UI dalam plan jika benar-benar ada perubahan perilaku. Jangan mengedit dokumen baseline untuk menyamarkan perubahan keputusan.

## F. Acceptance tests minimum / regression gates

Uji dengan backend API real & komponen frontend real (unit/integration/E2E sesuai tooling tersedia), bukan hanya mockup:

- Akun siswa login via **username maupun email**; password tersimpan hash; siswa tidak bisa memperoleh daftar token/kunci/nilai sebelum rilis.
- Guru A tidak dapat mengedit/terbitkan ujian mapel atau kelas Guru B; pengawas tidak dapat mengubah skor; guru tidak bisa merilis hasil sebelum seluruh essay grading selesai.
- Bank soal dipakai beberapa jadwal, mengubah bank asli tidak mengubah snapshot ujian terpublikasi; kombinasi lima tipe soal dinilai dan ditampilkan benar.
- Submit ulang idempotent; timer dari backend; reconnect memulihkan urutan soal, jawaban, dan deadline; autosave request lama tidak overwrite jawaban baru.
- Import invalid `.docx/.json/.xlsx/.zip` gagal secara terarah **tanpa mutasi**; preview/commit terpisah; media file asing/path traversal ditolak.
- Token dinamis valid hanya pada window server; kartu/daftar hadir/berita acara PDF sesuai filter dan **tidak** memuat password plaintext.
- Admin, guru, pengawas, dan siswa mendapat response 403/404 yang benar pada unauthorized object; kebocoran data antar kelas ditolak server.
- Export answer/grade menghindari spreadsheet formula injection; statistik analisis soal mencantumkan metode dan minimum sample.
- Mobile layout responsif; navigasi, dialog, tabel, editor, print action dapat digunakan; browser refresh tidak menghilangkan data tersimpan.
- High-risk **OFF** tetap OFF; tidak ada reset/delete/restore nyata dan tidak ada WhatsApp/email terkirim dari test.
- Per stage catat `PASS/FAIL/NOT_RUN`, perintah serta exit code/output yang relevan. Jangan klaim semua selesai jika tes tidak dijalankan atau dependensi tidak tersedia.

## G. Kebijakan keamanan dan batasan operasional

- **JANGAN** menjalankan `db:seed` yang mereset data nyata, `DROP/TRUNCATE`, bulk delete, restore SQL, forced score update pada hasil published, real deploy, atau kirim pesan nyata. Untuk semua aksi nyata eksternal/destruktif, butuh persetujuan eksplisit pengguna; tidak cukup karena ada UI tombol atau feature flag ON.
- Jangan mencetak/menyimpan password plaintext, private keys, token login siswa atau token ujian aktif pada log/README/screenshot/test fixtures. Seed test hanya data sintetis.
- Aset upload: validasi ekstensi dan MIME, pembatasan ukuran, scan opsional sesuai environment, sanitasi rich text, storage aman dan authorization file retrieval.
- Jaga compatibility API/DB serta data historis; buat adaptasi non-breaking atau migration tersendiri bila diperlukan.
- Saat terhambat oleh kekurangan library, masalah akses atau runner, kerjakan bagian yang feasible. Tandai hasil `BLOCKED` atau `UNVERIFIED` dan tinggalkan instruksi reproduksi, bukan membuat placeholder yang diklaim selesai.

## H. Format laporan akhir wajib dari Codex

Tampilkan tabel ringkas **48 item**: `ID | Kategori (existing/partial/missing/disabled) | Hasil | Evidence file/test`, lalu:

1. Tahap S8/S9/S10/S11: `DONE / PARTIAL / BLOCKED / NOT_STARTED` plus alasan.
2. Files yang berubah, migrasi yang dibuat, pages/routes/API baru, perubahan permission.
3. Test yang **benar-benar** dieksekusi beserta hasilnya; daftar test yang tidak bisa dijalankan.
4. Risiko kompatibilitas, keamanan, pekerjaan tersisa, dan cara menjalankan setup lokal.
5. Lokasi file monitoring `plan/extensions/ryuexam/BASELINE_AUDIT.md`, `GAP_STATUS.csv`, `PROGRESS.md`, `QA_REPORT.md`, `BLOCKERS.md`.

**MULAI SEKARANG**: lakukan audit implementasi nyata repository maqna-cbt, bandingkan 48 kandidat fitur dengan S0–S7, kemudian kerjakan gap aktif secara berurutan S8 sampai S11 tanpa meminta prompt rutin berikutnya. Jangan berhenti pada analisis/roadmap saja; implementasikan, uji, dan dokumentasikan semaksimal mungkin dalam batas akses dan sesi Codex.
