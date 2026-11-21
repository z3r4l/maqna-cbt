<?php

namespace App\Database\Seeds;

use App\Services\QuestionBank;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Models\UserModel;

class SmkRplQuestionSeeder extends Seeder
{
    private const SCHOOL_CODE = 'MAQNA-SMK-DEMO';
    private const BANK_NAME = 'Dasar RPL Kelas X - 20 Soal';
    private const LEGACY_BANK_KEY = 'seed:smk-rpl-x-20-v1';
    private const TEACHER_USERNAME = 'teacher_smk_rpl_demo';

    private function ensure(string $table, array $where, array $attributes): int
    {
        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if ($row !== null) return (int) $row['id'];
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table($table)->insert($where + $attributes + ['created_at' => $now, 'updated_at' => $now]);
        return (int) $this->db->insertID();
    }

    private function teacherUser(int $school): int
    {
        $model = new UserModel();
        $user = $model->where('username', self::TEACHER_USERNAME)->first();
        if ($user === null) {
            $new = $model->createNewUser([
                'username' => self::TEACHER_USERNAME,
                'email' => 'teacher.smk.rpl@example.invalid',
                'password' => 'FixtureOnly123!',
            ]);
            $user = $model->find($model->insert($new));
        }
        if ($user === null) throw new \RuntimeException('Gagal membuat guru SMK demo.');
        $profile = $this->db->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray();
        if ($profile !== null && (int) $profile['school_id'] !== $school) {
            throw new \RuntimeException('Username guru SMK demo sudah digunakan di sekolah lain.');
        }
        if ($profile === null) {
            $this->db->table('user_profiles')->insert([
                'user_id' => $user->id, 'school_id' => $school,
                'display_name' => 'Guru Dasar RPL Contoh', 'active' => 1,
                'must_change_password' => 0,
            ]);
        }
        if (! $user->inGroup('teacher')) $user->addGroup('teacher');
        return (int) $user->id;
    }

    private static function objective(string $type, string $body, array $choices, array $correct, string $explanation): array
    {
        return [
            'questionType' => $type, 'body' => $body, 'explanation' => $explanation,
            'options' => array_map(
                static fn (int $index, string $text): array => ['body' => $text, 'isCorrect' => in_array($index, $correct, true)],
                array_keys($choices), $choices
            ),
        ];
    }

    private static function matching(string $body, array $statements, array $choices, array $keys, string $explanation): array
    {
        return [
            'questionType' => 'matching', 'body' => $body, 'explanation' => $explanation,
            'options' => array_map(
                static fn (int $index, string $text): array => ['body' => $text, 'matchChoiceIndex' => $keys[$index]],
                array_keys($statements), $statements
            ),
            'matchingChoices' => array_map(static fn (string $text): array => ['body' => $text], $choices),
        ];
    }

    private static function essay(string $body, string $rubric): array
    {
        return ['questionType' => 'essay', 'body' => $body, 'rubric' => $rubric, 'options' => []];
    }

    private static function questions(): array
    {
        return [
            self::objective('mcq', 'Pada diagram alir, bentuk belah ketupat biasanya menunjukkan apa?',
                ['Awal atau akhir proses', 'Keputusan dengan percabangan', 'Masukan atau keluaran', 'Penyimpanan berkas'], [1],
                'Belah ketupat dipakai untuk keputusan yang menghasilkan cabang, misalnya ya atau tidak.'),
            self::objective('mcq', 'Nilai dari 7 + 3 * 2 menurut urutan operasi aritmetika adalah ...',
                ['20', '13', '17', '10'], [1], 'Perkalian dikerjakan lebih dahulu: 3 * 2 = 6, kemudian 7 + 6 = 13.'),
            self::objective('mcq', 'Dalam Python, tipe data yang sesuai untuk menyimpan bilangan bulat 17 adalah ...',
                ['str', 'float', 'int', 'bool'], [2], 'int menyimpan bilangan bulat; str untuk teks, float untuk desimal, dan bool untuk nilai logika.'),
            self::objective('mcq', 'Jika perulangan Python memakai range(3), nilai indeks yang dihasilkan adalah ...',
                ['1, 2, 3', '0, 1, 2', '0, 1, 2, 3', '3 saja'], [1], 'range(3) dimulai dari 0 dan berhenti sebelum 3.'),
            self::objective('mcq', 'Operator Python untuk membandingkan apakah dua nilai sama adalah ...',
                ['=', '==', '!=', '>='], [1], '== menguji kesamaan, sedangkan = memberi nilai ke variabel.'),
            self::objective('mcq', 'Perintah Git untuk melihat berkas yang berubah dan status staging adalah ...',
                ['git status', 'git clone', 'git merge', 'git init'], [0], 'git status menampilkan kondisi working tree dan staging area.'),
            self::objective('mcq', 'Fungsi utama CSS dalam halaman web adalah ...',
                ['Menyimpan data permanen', 'Mengatur tampilan dan tata letak', 'Mengganti sistem operasi', 'Membuat tabel basis data'], [1],
                'CSS mengatur presentasi halaman, seperti warna, jarak, dan tata letak.'),
            self::objective('mcq', 'Dalam tabel basis data, primary key dipakai untuk ...',
                ['Membedakan setiap baris secara unik', 'Menyamakan semua isi baris', 'Menghapus kolom kosong', 'Mengurutkan warna tampilan'], [0],
                'Primary key memberi identitas unik pada tiap baris tabel.'),

            self::objective('multi_mcq', 'Pilih ciri algoritma yang baik.',
                ['Langkahnya jelas', 'Dapat berakhir setelah sejumlah langkah', 'Menghasilkan keluaran yang ditentukan', 'Harus selalu ditulis dengan kode paling pendek'], [0, 1, 2],
                'Algoritma perlu langkah jelas, berhingga, dan memiliki hasil; panjang kode bukan syarat utamanya.'),
            self::objective('multi_mcq', 'Pilih tindakan yang membantu kerja tim memakai Git.',
                ['Menulis pesan commit yang menjelaskan perubahan', 'Meninjau perubahan sebelum menggabungkan branch', 'Membagikan password akun lewat repositori', 'Memperbarui branch sebelum menggabungkan perubahan'], [0, 1, 3],
                'Pesan commit, peninjauan, dan pembaruan branch membantu kolaborasi; password tidak boleh masuk repositori.'),
            self::objective('multi_mcq', 'Pilih contoh tipe data atau struktur data dasar dalam Python.',
                ['int untuk bilangan bulat', 'str untuk teks', 'bool untuk benar atau salah', 'CSS untuk daftar nilai'], [0, 1, 2],
                'int, str, dan bool adalah tipe data Python; CSS adalah bahasa gaya halaman web.'),
            self::objective('multi_mcq', 'Pilih praktik yang tepat saat aplikasi menyimpan password pengguna.',
                ['Menyimpan hash password, bukan teks aslinya', 'Memakai koneksi HTTPS saat mengirim password', 'Membatasi akses ke data autentikasi', 'Menampilkan password pengguna pada log kesalahan'], [0, 1, 2],
                'Hash, HTTPS, dan pembatasan akses mengurangi risiko kebocoran; password tidak boleh dicatat pada log.'),

            self::objective('true_false', 'Tentukan Benar atau Salah untuk pernyataan tentang alur program berikut.',
                ['Percabangan if memilih tindakan berdasarkan kondisi.', 'Perulangan for hanya dapat dijalankan satu kali.', 'Urutan instruksi dapat memengaruhi hasil program.'], [0, 2],
                'if dipakai untuk percabangan; for dapat mengulang beberapa kali; urutan instruksi berpengaruh pada hasil.'),
            self::objective('true_false', 'Tentukan Benar atau Salah untuk pernyataan tentang web berikut.',
                ['HTML menyusun struktur konten halaman.', 'CSS digunakan untuk mengatur gaya tampilan.', 'JavaScript hanya dapat digunakan untuk mengubah warna teks.'], [0, 1],
                'HTML menyusun konten, CSS mengatur tampilan, dan JavaScript mendukung berbagai perilaku interaktif.'),
            self::objective('true_false', 'Tentukan Benar atau Salah untuk pernyataan tentang basis data berikut.',
                ['Satu baris tabel dapat mewakili satu data siswa.', 'Primary key yang sama boleh dipakai oleh dua baris dalam satu tabel.', 'Data pribadi perlu dibatasi aksesnya sesuai peran pengguna.'], [0, 2],
                'Baris tabel mewakili satu rekaman; primary key harus unik; akses data pribadi perlu dibatasi.'),

            self::matching('Pasangkan istilah algoritma dengan artinya.',
                ['Sekuens', 'Seleksi', 'Iterasi'],
                ['Mengulang langkah berdasarkan aturan', 'Menjalankan langkah secara berurutan', 'Memilih cabang menurut kondisi', 'Menyimpan halaman web'],
                [1, 2, 0], 'Sekuens berarti urutan, seleksi berarti pilihan bercabang, dan iterasi berarti pengulangan.'),
            self::matching('Pasangkan teknologi web dengan fungsi utamanya.',
                ['HTML', 'CSS', 'JavaScript'],
                ['Perilaku interaktif', 'Struktur dan isi halaman', 'Tampilan dan tata letak', 'Penyimpanan fisik komputer'],
                [1, 2, 0], 'HTML untuk struktur, CSS untuk tampilan, dan JavaScript untuk perilaku halaman.'),
            self::matching('Pasangkan istilah Git dengan kegunaannya.',
                ['Commit', 'Branch', 'Merge'],
                ['Menggabungkan riwayat perubahan', 'Mencatat perubahan sebagai satu titik riwayat', 'Membuat jalur kerja terpisah', 'Menghapus semua akun pengguna'],
                [1, 2, 0], 'Commit mencatat perubahan, branch memisahkan jalur kerja, dan merge menggabungkannya.'),

            self::essay('Tuliskan pseudocode yang membaca satu bilangan bulat, lalu menampilkan apakah bilangan tersebut genap atau ganjil. Jelaskan alasan kondisi yang dipakai.',
                'Nilai maksimum 5 poin: membaca satu bilangan bulat (1); memakai sisa pembagian 2 dengan benar (1); menampilkan Genap saat sisa 0 (1); menampilkan Ganjil selain itu (1); menjelaskan bahwa bilangan genap habis dibagi 2 (1).'),
            self::essay('Sebuah tim kecil membuat situs katalog buku. Jelaskan langkah kerja yang aman saat dua anggota mengubah berkas yang sama memakai Git, mulai dari branch hingga penggabungan.',
                'Nilai maksimum 5 poin: membuat branch kerja terpisah (1); menyimpan perubahan dengan commit yang jelas (1); memperbarui branch dari sumber utama (1); menyelesaikan konflik dengan meninjau kedua perubahan (1); menguji hasil dan meminta peninjauan sebelum merge (1).'),
        ];
    }

    public function run(): void
    {
        if (ENVIRONMENT === 'production') throw new \RuntimeException('Seeder SMK demo tidak boleh dijalankan di produksi.');

        $questions = self::questions();
        if (count($questions) !== 20) throw new \RuntimeException('Seeder SMK harus berisi tepat 20 soal.');

        $db = $this->db;
        $db->transBegin();
        try {
            $school = $this->ensure('schools', ['code' => self::SCHOOL_CODE], [
                'name' => 'SMK Contoh', 'timezone' => 'Asia/Jakarta', 'status' => 'active',
            ]);
            $year = $this->ensure('academic_years', ['school_id' => $school, 'label' => '2026/2027'], [
                'start_on' => '2026-07-01', 'end_on' => '2027-06-30', 'active' => 1,
            ]);
            $semester = $this->ensure('semesters', ['school_id' => $school, 'academic_year_id' => $year, 'name' => 'Ganjil'], [
                'start_on' => '2026-07-01', 'end_on' => '2026-12-31', 'active' => 1,
            ]);
            $grade = $this->ensure('grade_levels', ['school_id' => $school, 'code' => 'X'], [
                'name' => 'Kelas X', 'sort_order' => 10, 'active' => 1,
            ]);
            $class = $this->ensure('class_groups', ['school_id' => $school, 'academic_year_id' => $year, 'code' => 'X-RPL-1'], [
                'grade_level_id' => $grade, 'name' => 'X RPL 1', 'active' => 1,
            ]);
            $subject = $this->ensure('subjects', ['school_id' => $school, 'code' => 'DRPL'], [
                'name' => 'Dasar RPL', 'active' => 1,
            ]);
            $examType = $this->ensure('exam_types', ['school_id' => $school, 'code' => 'PH'], [
                'name' => 'Penilaian Harian', 'active' => 1,
            ]);
            $teacherUser = $this->teacherUser($school);
            $teacher = $this->ensure('teachers', ['school_id' => $school, 'user_id' => $teacherUser], [
                'employee_no' => 'EMP-SMK-RPL-DEMO', 'status' => 'active',
            ]);
            $this->ensure('teaching_assignments', [
                'school_id' => $school, 'teacher_id' => $teacher, 'semester_id' => $semester,
                'class_group_id' => $class, 'subject_id' => $subject,
            ], ['active' => 1]);

            $banks = $db->table('question_banks')->where('school_id', $school)->where('subject_id', $subject)
                ->where('author_user_id', $teacherUser)->get()->getResultArray();
            $matches = array_values(array_filter($banks, static fn (array $row): bool => $row['name'] === self::BANK_NAME));
            if (count($matches) > 1 || ($matches === [] && $banks !== [])) {
                throw new \RuntimeException('Bank SMK demo sudah diubah atau ada bank lain; periksa data manual sebelum menjalankan ulang seeder.');
            }
            $bank = $matches[0] ?? null;
            if ($bank !== null && $bank['default_key'] !== null) {
                if ($bank['default_key'] !== self::LEGACY_BANK_KEY) throw new \RuntimeException('Identitas bank SMK demo berbenturan dengan bank bawaan.');
                $db->table('question_banks')->where('id', $bank['id'])->update(['default_key' => null]);
            }
            if ($bank === null) {
                $now = gmdate('Y-m-d H:i:s');
                $db->table('question_banks')->insert([
                    'school_id' => $school, 'subject_id' => $subject, 'exam_type_id' => $examType,
                    'author_user_id' => $teacherUser, 'name' => self::BANK_NAME,
                    'default_key' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $bank = ['id' => (int) $db->insertID()];
            }
            $bankId = (int) $bank['id'];
            if (! $db->table('question_bank_classes')->where('bank_id', $bankId)->where('class_group_id', $class)->countAllResults()) {
                $db->table('question_bank_classes')->insert(['bank_id' => $bankId, 'class_group_id' => $class]);
            }

            $existing = $db->table('questions')->where('bank_id', $bankId)->where('deleted_at', null)->countAllResults();
            if ($existing !== 0 && $existing !== 20) {
                throw new \RuntimeException("Bank SMK demo berisi {$existing} soal; periksa data manual sebelum menjalankan ulang seeder.");
            }
            if ($existing === 0) {
                $service = new QuestionBank($db);
                foreach ($questions as $question) {
                    $service->save($school, $teacherUser, $question + [
                        'bankId' => $bankId, 'subjectId' => $subject, 'weight' => 5, 'status' => 'draft',
                    ], null, false);
                }
            }
            if (! $db->transStatus()) throw new \RuntimeException('Penyimpanan bank soal SMK demo gagal.');
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
