# Audit RyuExam CBT untuk Referensi Kebutuhan maqna-cbt

**Tanggal audit:** 15 November 2026  
**Sumber:** tab admin RyuExam CBT yang sudah login, URL demo `demo.ryuexamcbt.web.id`  
**Metode:** inspeksi halaman, daftar kontrol, isi form/modal yang tersedia, kolom tabel, dan opsi dropdown. Tidak ada form dikirim, data diubah/dihapus, impor/restore dijalankan, atau cetakan diunduh.  
**Cakupan:** 16 menu admin yang tampil pada sidebar.

> Catatan demo: halaman menyatakan data bawaan dikunci, sedangkan data tambahan dapat dikelola. Menu Kelola Admin, Database, Maintenance dan Pembaruan juga disebut dikunci pada mode demo. Antarmuka pengaturan tetap menampilkan kontrol untuk sejumlah fitur ini. Audit ini mencatat tampilan dan tidak menyatakan fungsi backend telah teruji.

## Ringkasan navigasi

| # | Menu | Kelompok | Tujuan utama yang terlihat |
|---:|---|---|---|
| 1 | Dashboard | Ringkasan | Statistik, token aktif, pintasan pemantauan ujian, salin token |
| 2 | Data Kelas | Data master | Daftar, tambah, edit kelas |
| 3 | Data Siswa | Data master | Kelola akun siswa, impor/ekspor, filter, reset login, cetak kartu |
| 4 | Data Guru | Data master | Kelola akun guru, impor/ekspor akun |
| 5 | Mata Pelajaran | Data master | Kelola kode dan nama mapel, impor/ekspor |
| 6 | Jenis Ujian | Data master | Kelola kode dan nama jenis ujian |
| 7 | Bank Soal | Manajemen ujian | Kelola bank, butir soal, kunci, impor/ekspor, pratinjau |
| 8 | Jadwal Ujian | Manajemen ujian | Buat jadwal, sasaran kelas, token, durasi, pengacakan, status |
| 9 | Cetak Kartu Peserta | Manajemen ujian | Filter peserta dan tata letak kartu, pratinjau/cetak PDF |
| 10 | Administrasi | Manajemen ujian | Daftar hadir dan berita acara |
| 11 | Status Ujian | Pelaksanaan | Pantau peserta, skor/status, koreksi esai, ekspor/reset per ujian |
| 12 | Analisis Soal | Pelaksanaan | Analisis psikometri dan ekspor Excel |
| 13 | Pengaturan | Sistem | Identitas, keamanan, anti-cheat, token, role admin, backup/maintenance |
| 14 | Log Pembaruan | Sistem | Riwayat versi, fitur, perbaikan dan bug |
| 15 | Petunjuk | Bantuan | Panduan bank soal, Word/JSON, KaTeX, Excel, FAQ |
| 16 | Tentang | Bantuan | Versi aplikasi, ringkasan kemampuan, tautan pengembang |

## Detail per menu

### 1. Dashboard

- Ringkasan jumlah siswa, guru, bank soal dan konfigurasi token dinamis.
- Tabel token ujian per mata pelajaran: nama ujian/mapel, kelas, token, waktu mulai/selesai, durasi, status dan aksi.
- Pencarian ujian berdasarkan mapel/token; checkbox untuk memilih ujian satu per satu atau semua.
- Tombol salin info token untuk dibagikan, aksi bagikan token terpilih melalui WhatsApp, dan pintasan “Lihat Status Peserta”.
- Alur: pantau ujian aktif → cari jadwal → salin token atau buka pemantauan peserta.
- Token ujian adalah kredensial operasional; dokumentasi/seed maqna-cbt sebaiknya tidak memuat token aktual.

### 2. Data Kelas

- Tabel: ID, nama kelas, aksi.
- Tambah membuka modal dengan `Nama Kelas` wajib (contoh `IX.6`) dan tombol Simpan/Batal.
- Edit memakai ID tersembunyi dan nama kelas wajib.
- Data bawaan demo memiliki aksi terkunci; data tambahan dapat memiliki kontrol edit/hapus.
- Validasi terlihat: nama kelas wajib diisi. Keunikan nama belum terverifikasi.
- Alur: tambah kelas → kelas menjadi pilihan pada siswa, bank soal dan jadwal.

### 3. Data Siswa

- Daftar dengan kolom pilihan massal, ID, nomor peserta, username, nama, kelas, ruang, password dan aksi.
- Fitur: impor Excel, ekspor, pengelolaan password, cetak kartu, tambah siswa, filter kelas, pencarian/paginasi, filter status login (semua/sudah/belum), reset login dan hapus massal.
- Tambah/edit: kelas wajib; username opsional saat tambah dan wajib saat edit; nama wajib; nomor peserta opsional; ruang opsional; password opsional. Ada kontrol bantuan untuk membuat username/password otomatis.
- Impor: file Excel `.xlsx` wajib; tersedia unduh template. Form edit memakai ID tersembunyi.
- Ukuran halaman yang terlihat: 10/50/100. Ringkasan login memisahkan semua/sudah/belum login.
- Validasi terlihat: kelas dan nama wajib; username wajib saat edit; berkas wajib untuk impor. Keunikan username/nomor peserta belum terverifikasi.
- Alur: buat kelas → tambah satuan atau impor template → verifikasi kelas/ruang/nomor peserta → terbitkan kartu atau kelola login.
- **Privasi:** tabel demo memuat identitas dan password siswa. Isi baris/credential tidak disalin ke dokumen ini.

### 4. Data Guru

- Tabel: nomor, nama guru, username, password (disamarkan/enkripsi di daftar), mata pelajaran dan aksi.
- Tambah/edit: nama, username dan password wajib; mapel berupa isian teks opsional. Ada kontrol tampil/sembunyikan password.
- Impor Excel `.xlsx`/`.xls` dengan berkas wajib dan template; ekspor serta hapus massal juga tampak.
- Validasi terlihat: nama, username dan password wajib pada tambah; pada form edit password juga ditandai wajib. Perilaku jika kosong belum dipastikan.
- Alur: tambah/impor akun → isi mapel → guru mengakses modul sesuai role/hak akses.

### 5. Mata Pelajaran

- Daftar kode/nama mapel dengan aksi; tersedia impor Excel, tambah, ekspor dan hapus massal.
- Form tambah/edit: kode mapel wajib (contoh MAT/BIN/IPA) dan nama mapel wajib (contoh Matematika/Bahasa Indonesia).
- Impor file Excel wajib, menerima `.xlsx`/`.xls`; tersedia template.
- Pilihan mapel digunakan pada bank soal, dashboard, jadwal dan analisis.
- Validasi unik kode/nama belum terverifikasi.

### 6. Jenis Ujian

- Tabel: nomor, kode jenis, nama jenis ujian dan aksi.
- Tambah/edit: kode wajib (contoh PTS/PAS/PAT/US/PH) dan nama wajib (contoh Penilaian Tengah Semester).
- Jenis bawaan yang terlihat: PH, PTS, PAS, PAT, US, TO.
- Alur: definisikan jenis → pilih saat membuat bank soal → gunakan bank pada jadwal.

### 7. Bank Soal

- Daftar dapat dicari dan difilter menurut kelas/mapel. Kolom: nama, jenis ujian, mapel, kelas, komposisi tipe soal, pengunggah, jumlah/nilai dan aksi.
- Aksi terlihat: buat/edit, impor soal, impor ZIP, backup ZIP, ekspor, pilih/hapus massal, unduh naskah/template, pengelolaan kunci Excel, set poin dan preview siswa.
- Metadata bank: nama wajib, jenis ujian, mapel dan pilihan multi-kelas. Jenis/mapel memakai dropdown; kelas checkbox.
- Impor soal: target bank dan berkas wajib. Petunjuk menyebut Word `.docx` atau JSON. Impor ZIP mendukung bank baru atau yang sudah ada, nama kustom opsional, serta mode “Tambahkan” atau “Timpa Semua”.
- Lima bentuk soal: pilihan ganda satu jawaban benar, pilihan ganda kompleks, benar/salah berupa tabel pernyataan, menjodohkan, dan esai/uraian.
- Editor soal menyediakan rich text, pilihan, kunci dan poin. PG biasa mendukung 4 opsi A–D atau 5 opsi A–E; PG kompleks memilih beberapa jawaban; benar/salah menggunakan pernyataan dengan nilai Benar/Salah; menjodohkan memiliki baris pernyataan/pasangan; esai berupa uraian.
- Editor mendukung tautan, gambar, video dan rumus LaTeX/KaTeX dengan mode inline/display dan pratinjau. Kontrol “Tambah Pernyataan”, “Tambah Pilihan”, “Tambah Baris” menambah struktur soal.
- Kunci Excel memiliki sumber template saat ini/kustom, tipe kustom (PG, PG kompleks, benar-salah, menjodohkan), jumlah soal, poin, file Excel wajib dan opsi hitung ulang otomatis.
- Validasi rinci tiap tipe soal belum dapat dipastikan tanpa submit; kelengkapan minimum pertanyaan/opsi tidak diasumsikan.
- Alur: buat bank → tambah soal/impor → periksa kunci → set poin → preview siswa → jadwalkan.

### 8. Jadwal Ujian

- Ringkasan jumlah semua ujian, aktif, selesai dan draf; filter mapel/kelas dan ukuran tabel.
- Kolom: nama ujian, kelas, bank soal, waktu, jumlah soal, pengacakan soal/opsi, token, status dan aksi.
- Form tambah/edit: pilih bank wajib, nama ujian wajib, satu/lebih kelas, jumlah soal opsional (kosong berarti semua menurut placeholder), token wajib, status Aktif/Draft/Selesai, mulai/selesai wajib, durasi menit wajib, acak soal dan acak opsi.
- Ada pencarian bank soal pada dropdown, aksi poin per bank, ubah status massal dan hapus terpilih.
- Validasi terlihat: bank, nama, token, waktu mulai/selesai dan durasi wajib. Rentang waktu, token unik dan batas jumlah soal belum terverifikasi.
- Alur: siapkan bank → pilih kelas/waktu/durasi/pengacakan → tentukan status/token → siswa masuk saat sesi tersedia → pantau di Status Ujian.

### 9. Cetak Kartu Peserta

- Header kartu: nama ujian, nama sekolah, tahun pelajaran. Nama sekolah berasal dari Pengaturan namun dapat diganti untuk cetakan; logo dapat ditampilkan/diganti.
- Filter kelas, ruang dan cari siswa.
- Urutan: nomor peserta naik, nama A–Z, kelas lalu nama, ruang lalu nomor peserta, ID siswa naik.
- Kertas F4/Folio 215×330 mm; pratinjau menunjukkan 2×6 = 12 kartu per lembar dan jumlah lembar sesuai peserta.
- Kartu berisi nomor peserta, nama, kelas, ruang, username dan password. Ada pintasan Data Siswa serta “Cetak / Simpan PDF”.
- Alur: atur kop/logo → filter dan urutkan → periksa pratinjau/jumlah lembar → cetak/simpan PDF.
- Kartu memuat password; batasi hak akses, distribusi dan penyimpanan hasil cetak.

### 10. Administrasi

- Pilihan dokumen Daftar Hadir/Berita Acara, format “Terisi Data Ujian” atau “Kosongan (isi manual)”, tombol Simpan Isian dan Cetak.
- Pilih jadwal; filter kelas/ruang/sesi; urut peserta berdasarkan nama A–Z atau nomor peserta/username.
- Kop/waktu: nama kegiatan, tahun pelajaran, tanggal dan pukul.
- Penandatangan: tampilkan kepala sekolah/proktor; label NIP; nama/NIP kepala sekolah, proktor, pengawas 1 dan pengawas 2.
- Tata letak: semua baris satu halaman atau 15/20/25 per halaman. Opsi tandai peserta absen dari data ujian.
- Berita acara memiliki pilihan isi otomatis dan area catatan manual. Pratinjau menampilkan jumlah peserta dan status hadir/mengerjakan, tabel tanda tangan/keterangan serta blok tanda tangan.
- Validasi: kontrol sebagian besar opsional; syarat simpan belum diuji. Tombol simpan/cetak tidak ditekan.

### 11. Status Ujian

- Tabel ujian: nama, mapel, kelas, token, waktu, durasi, jumlah soal, status dan aksi.
- Pemilihan ujian membuka daftar peserta: nama, kelas, progres, status, mulai/selesai, lama, skor dan aksi.
- Kontrol terlihat: refresh, unduh jawaban, unduh nilai terpilih dalam ZIP, hitung ulang nilai, reset ujian. Ada modal detail ujian dan modal koreksi esai.
- Detail ujian mengelompokkan mapel/jenis/kelas, bank/pembuat/jumlah, waktu/durasi dan pengacakan.
- Koreksi esai menampilkan siswa, skor sementara, jawaban esai, tombol tutup dan simpan penilaian.
- Alur: pilih jadwal → monitor → unduh jawaban/koreksi esai → hitung ulang jika kunci berubah → ekspor hasil.
- Reset/koreksi/simpan tidak dijalankan; konfirmasi dan prasyarat tindakan belum diverifikasi.

### 12. Analisis Soal

- Dropdown jadwal ujian; tombol Analisis dan Ekspor Excel `.xlsx`.
- Deskripsi menyebut tingkat kesukaran P, daya pembeda DP, rekomendasi butir, sebaran pengecoh dan matriks siswa.
- Tabel hasil: nomor, soal, bentuk, kunci, benar/menjawab, kesukaran, daya pembeda, rekomendasi. Tabel lain menampilkan matriks dan hitungan pilihan A–E/kosong/total peserta.
- Alur: pilih jadwal → analisis → tinjau kualitas/sebaran → ekspor Excel.
- Hasil statistik untuk ujian pilihan tidak dibuat selama audit.

### 13. Pengaturan

- Tab/anchor internal: Umum, Metode Login Siswa, Pengaturan Pelanggaran, Kelola Admin, Token Dinamis, Database, Maintenance, Pembaruan.
- Umum: nama sekolah/lembaga, teks aplikasi, logo, ikon PWA (dibuat 192/512), favicon dan kop soal; kontrol hapus/reset aset terpisah.
- Tema sidebar: Putih atau Biru Toska, dengan pratinjau.
- Aturan ujian: wajib menjawab semua soal; tampilkan nilai ke siswa.
- Login siswa: nonaktifkan token, wajib password, cegah multi-login, tampilkan tombol keluar ujian.
- Anti-cheat: batas pelanggaran; tindakan “Keluarkan Siswa (Logout)” atau “Kumpulkan Otomatis (Auto Submit)”; penalti logout dalam menit.
- Admin: daftar nama, username, role, hak akses dan password. Form tambah/edit admin; preset Super Admin, Proktor, Teknisi, Pengawas, Admin Kurikulum, Kustom. Tambah meminta nama, username, password, label role dan checkbox akses per menu. Edit mendukung password baru opsional.
- Token dinamis: nonaktifkan tab token, aktifkan token dinamis, interval, berlaku untuk semua ujian.
- Database: unduh backup SQL, restore file SQL, kosongkan data terpilih/pilih semua. Operasi ini berisiko tinggi dan tidak dicoba.
- Maintenance: mode maintenance, cakupan siswa/guru, judul, pesan dan waktu sampai.
- Ada tombol simpan untuk beberapa bagian serta pemeriksaan patch. Pembaruan/restore tidak dieksekusi.
- Validasi pengaturan kebanyakan tidak ditandai wajib di HTML; batas angka dan validasi server belum diketahui.

### 14. Log Pembaruan

- Riwayat versi kronologis dengan label Update/Fix/Bug, tanggal dan uraian perubahan.
- Versi terbaru yang terlihat 1.9.1 (perbaikan reset demo), 1.9.0 (pembaruan internal), 1.8.8 (font Arab), dan riwayat sampai versi 1.0.0.
- Tidak tampak form/aksi mutasi.
- Berguna untuk transparansi rilis, pemeriksaan perubahan dan dukungan operasional.

### 15. Petunjuk

- Tujuh bagian: alur bank soal, AI/JSON, LaTeX/KaTeX, format Word, kunci/Excel, praktik guru, FAQ.
- Pintasan: buat via AI, buka bank soal, generator AI, template PG biasa/kompleks/kunci 40 soal, unduh template Word.
- Panduan Word menetapkan satu tabel per soal dan dokumen `.docx`; membahas gambar sebaris, kunci dan pilihan jawaban.
- Panduan menyebut impor Word/JSON, Set Poin agar total 100, Preview Siswa, Hitung Nilai, backup ZIP dan optimasi gambar WebP.
- Sebagian tombol menuju bank soal atau sumber eksternal (Gemini/CodeCogs); tautan tersebut tidak dibuka.

### 16. Tentang

- Identitas: RyuExam CBT, aplikasi ujian berbasis komputer untuk sekolah, versi 1.9.1; diperbarui 14 November 2026.
- Kemampuan yang disebut: lima bentuk soal, impor Word/JSON, token/jadwal, pengawasan, analisis butir dan kartu peserta.
- Tautan selengkapnya, WhatsApp/Telegram pengembang dan situs pengembang.
- Tidak ada form data bisnis.

## Alur lintas-menu yang tersirat

1. Siapkan data master: kelas, siswa, guru, mapel dan jenis ujian.
2. Buat bank soal lewat editor atau impor Word/JSON; periksa tipe dan kunci; atur poin.
3. Buat jadwal dari bank; pilih kelas, waktu, durasi, token, pengacakan dan status.
4. Terbitkan informasi ujian/kartu; siswa masuk dengan akun dan token.
5. Pantau peserta/status/jawaban; koreksi esai; hitung ulang bila diperlukan.
6. Analisis butir dan ekspor nilai/analisis; cetak administrasi dan arsip hasil.

## Kandidat kebutuhan untuk maqna-cbt

- Model data inti: tahun ajaran/semester, kelas/rombel, siswa, guru, mata pelajaran, jenis ujian, bank soal, soal, opsi/kunci, jadwal, sasaran kelas, sesi/peserta, jawaban, nilai, audit log, konfigurasi institusi dan role/permission.
- Pisahkan **bank soal** (konten/metadata) dari **jadwal ujian** (waktu, kelas, token, durasi, status); satu bank dapat dipakai beberapa jadwal.
- Model soal perlu struktur khusus untuk pilihan tunggal, jawaban jamak, benar/salah, menjodohkan dan esai. Rich text, gambar, video dan rumus perlu aman serta dapat dipratinjau.
- Impor sebaiknya memiliki pratinjau validasi sebelum komit: format, baris/soal gagal, duplikasi, aset terlepas dan kunci tidak valid. Mode tambah/ganti harus eksplisit dan diberi konfirmasi.
- Status jadwal/sesi perlu transisi terdefinisi. Reset, hapus massal, restore, kumpulkan otomatis dan publikasi token perlu otorisasi, konfirmasi, audit trail dan proteksi dari salah klik.
- Jangan menampilkan password siswa secara terbuka. Gunakan reset/generate credential, hash password dan alur distribusi terbatas. Kartu cetak dapat dibatasi/diwatermark dan tidak menyimpan password permanen.
- Role preset + izin per modul praktis; implementasikan least privilege, pembatasan per record dan audit perubahan penting.
- Dashboard, status real-time, ekspor, analisis dan administrasi perlu filter konsisten (jadwal, mapel, kelas, ruang, status) serta pagination.
- Analisis butir perlu menjelaskan rumus, minimum responden dan penanganan jawaban kosong agar rekomendasi dapat ditafsirkan.

## Batasan dan hal yang belum terverifikasi

- Audit mencakup tampilan admin dan kontrol yang tersedia; tidak menguji hasil submit, backend, responsivitas mobile, performa, keamanan endpoint, role selain administrator demo, atau pengalaman siswa/guru.
- Tombol yang terlihat bukan bukti fungsi backend; validasi klien/server yang tidak tampak tidak dapat disimpulkan.
- Modal form dibaca tanpa diisi. Koreksi, restore, reset, hapus, simpan dan cetak tidak dijalankan.
- Beberapa daftar memuat informasi pribadi/credential demo; informasi tersebut tidak disertakan dalam dokumen.
- Perlakukan temuan sebagai referensi fitur UI/alur. Kebijakan, skema, role dan aturan maqna-cbt tetap perlu disesuaikan dengan institusi sasaran.
