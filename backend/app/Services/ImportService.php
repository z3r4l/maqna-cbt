<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PharData;

final class ImportService
{
    private const HEADERS = [
        'students' => ['nis', 'name', 'username', 'email', 'class_group_code'],
        'teachers' => ['employee_no', 'name', 'username', 'email', 'subject_code', 'class_group_code', 'semester_name'],
        'subjects' => ['code', 'name'],
        'questions' => ['subject_code', 'body', 'type', 'weight', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'rubric', 'explanation'],
        'keys' => ['question_id', 'type', 'key'],
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    public function template(string $kind): string
    {
        if (! isset(self::HEADERS[$kind])) throw new \DomainException('Jenis impor tidak valid.');
        return implode(',', self::HEADERS[$kind]) . "\r\n";
    }

    private function readXlsxWithPhar(string $path): array
    {
        try {
            $archive = new PharData($path);
            $entries = new \RecursiveIteratorIterator($archive);
            $count = 0; $size = 0;
            foreach ($entries as $entry) {
                $count++;
                $size += $entry->getSize();
                if ($count > 100 || $size > 20 * 1024 * 1024 || $entry->getSize() > 8 * 1024 * 1024 || $entry->isLink()) throw new \DomainException('Arsip XLSX terlalu besar atau tidak aman.');
                $name = str_replace('\\', '/', $entry->getPathName());
                if (str_contains($name, '/../') || str_contains($name, '/./')) throw new \DomainException('Path arsip XLSX tidak aman.');
            }
            if (! isset($archive['xl/workbook.xml'], $archive['xl/worksheets/sheet1.xml'])) throw new \DomainException('Struktur XLSX tidak lengkap.');
            $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $workbook = simplexml_load_string($archive['xl/workbook.xml']->getContent(), \SimpleXMLElement::class, LIBXML_NONET);
            if ($workbook === false) throw new \DomainException('Workbook XLSX rusak.');
            $workbook->registerXPathNamespace('m', $namespace);
            if (count($workbook->xpath('//m:sheets/m:sheet')) !== 1) throw new \DomainException('Gunakan satu lembar XLSX.');
            $strings = [];
            if (isset($archive['xl/sharedStrings.xml'])) {
                $shared = simplexml_load_string($archive['xl/sharedStrings.xml']->getContent(), \SimpleXMLElement::class, LIBXML_NONET);
                if ($shared === false) throw new \DomainException('Teks XLSX rusak.');
                $shared->registerXPathNamespace('m', $namespace);
                foreach ($shared->xpath('//m:si') as $item) {
                    $item->registerXPathNamespace('m', $namespace);
                    $strings[] = implode('', array_map('strval', $item->xpath('.//m:t')));
                }
            }
            $sheet = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent(), \SimpleXMLElement::class, LIBXML_NONET);
            if ($sheet === false) throw new \DomainException('Lembar XLSX rusak.');
            $sheet->registerXPathNamespace('m', $namespace);
            $result = []; $highest = 0;
            foreach ($sheet->xpath('//m:sheetData/m:row') as $row) {
                $number = (int) $row['r'];
                if ($number < 1 || $number > 501) throw new \DomainException('Maksimal 500 baris data.');
                $highest = max($highest, $number);
                $cells = [];
                foreach ($row->children($namespace)->c as $cell) {
                    if (! preg_match('/\A([A-Z]{1,3})([1-9][0-9]{0,3})\z/', (string) $cell->attributes()['r'], $matches) || (int) $matches[2] !== $number) throw new \DomainException('Alamat sel XLSX tidak valid.');
                    $column = 0;
                    foreach (str_split($matches[1]) as $letter) $column = $column * 26 + ord($letter) - 64;
                    if ($column > 30) throw new \DomainException('Terlalu banyak kolom XLSX.');
                    $cell->registerXPathNamespace('m', $namespace);
                    if ($cell->xpath('./m:f') !== []) throw new \DomainException('Rumus XLSX tidak diizinkan dalam impor.');
                    $type = (string) $cell->attributes()['t'];
                    if ($type === 'inlineStr') $value = implode('', array_map('strval', $cell->xpath('.//m:is//m:t')));
                    elseif ($type === 's') $value = $strings[(int) ($cell->children($namespace)->v ?? -1)] ?? throw new \DomainException('Indeks teks XLSX tidak valid.');
                    else $value = (string) ($cell->children($namespace)->v ?? '');
                    $cells[$column - 1] = $value;
                }
                if ($cells !== []) {
                    $width = max(array_keys($cells)) + 1;
                    $result[$number] = array_replace(array_fill(0, $width, ''), $cells);
                }
            }
            $rows = [];
            for ($number = 1; $number <= $highest; $number++) $rows[] = $result[$number] ?? [];
            return $rows;
        } catch (\PharException $error) {
            throw new \DomainException('Arsip XLSX rusak atau tidak didukung.', 0, $error);
        }
    }

    public function read(string $kind, string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if ($handle === false) throw new \DomainException('Berkas tidak dapat dibaca.');
            $rows = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($rows) > 500) break;
                $rows[] = $row;
            }
            fclose($handle);
        } elseif ($extension === 'xlsx') {
            if (! extension_loaded('zip')) {
                $rows = $this->readXlsxWithPhar($path);
            } else {
                $reader = new Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($path);
                if ($spreadsheet->getSheetCount() !== 1 || $spreadsheet->getActiveSheet()->getHighestRow() > 501) throw new \DomainException('Gunakan satu lembar dan maksimal 500 baris data.');
                $rows = $spreadsheet->getActiveSheet()->rangeToArray('A1:' . $spreadsheet->getActiveSheet()->getHighestColumn() . $spreadsheet->getActiveSheet()->getHighestRow(), null, false, false);
                $spreadsheet->disconnectWorksheets();
            }
        } else throw new \DomainException('Gunakan berkas CSV atau XLSX.');
        if ($rows === [] || count($rows) > 501) throw new \DomainException('Berkas kosong atau melebihi 500 baris.');
        $headers = array_map(static fn ($cell) => mb_strtolower(trim((string) $cell)), $rows[0]);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        if ($headers !== self::HEADERS[$kind]) throw new \DomainException('Kolom tidak cocok dengan template ' . $kind . '.');
        $data = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            if (count($row) > count($headers)) throw new \DomainException('Baris ' . ($index + 2) . ' memiliki terlalu banyak kolom.');
            $row = array_pad($row, count($headers), '');
            $cells = array_map(static fn ($cell) => trim((string) ($cell ?? '')), $row);
            if (implode('', $cells) === '') continue;
            if (array_filter($cells, static fn ($cell) => mb_strlen($cell) > 20000 || ! mb_check_encoding($cell, 'UTF-8'))) throw new \DomainException('Berkas berisi teks tidak valid.');
            $data[] = ['number' => $index + 2, 'value' => array_combine($headers, $cells)];
        }
        if ($data === []) throw new \DomainException('Tidak ada baris data.');
        return $data;
    }

    private function check(int $school, int $actor, bool $admin, string $kind, string $mode, array $row, array &$seen): array
    {
        if ($kind === 'subjects') {
            $code = mb_strtoupper(trim($row['code']));
            $name = trim($row['name']);
            if (! preg_match('/\A[A-Z0-9_-]{2,24}\z/', $code) || mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new \DomainException('Kode atau nama mapel tidak valid.');
            if (isset($seen[$code])) throw new \DomainException('Kode mapel duplikat dalam berkas.');
            $seen[$code] = true;
            $existing = $this->db->table('subjects')->where('school_id', $school)->where('code', $code)->get()->getRowArray();
            if ($existing !== null && $mode === 'reject') throw new \DomainException('Kode mapel sudah digunakan.');
            return ['status' => $existing !== null && $mode === 'skip' ? 'skip' : 'ready', 'code' => $code, 'existingId' => $existing === null ? null : (int) $existing['id']];
        }
        if ($kind === 'teachers') {
            $username = mb_strtolower(trim($row['username']));
            $employee = trim($row['employee_no']);
            if (! preg_match('/\A[a-z0-9.]{3,30}\z/', $username) || mb_strlen($employee) < 1 || mb_strlen($employee) > 40 || mb_strlen($row['name']) < 2 || mb_strlen($row['name']) > 120 || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) throw new \DomainException('Identitas guru tidak valid.');
            $identity = $employee . '|' . mb_strtolower(trim($row['email']));
            if (isset($seen[$username]) && $seen[$username] !== $identity) throw new \DomainException('Identitas guru berbeda untuk username yang sama.');
            if (isset($seen['employee:' . $employee]) && $seen['employee:' . $employee] !== $username) throw new \DomainException('Nomor pegawai duplikat dalam berkas.');
            $seen[$username] = $identity; $seen['employee:' . $employee] = $username;
            $subject = $this->db->table('subjects')->where('school_id', $school)->where('code', $row['subject_code'])->where('active', 1)->get()->getRowArray();
            $semester = $this->db->table('semesters')->where('school_id', $school)->where('name', $row['semester_name'])->where('active', 1)->get()->getRowArray();
            $class = $semester === null ? null : $this->db->table('class_groups')->where('school_id', $school)->where('academic_year_id', $semester['academic_year_id'])->where('code', $row['class_group_code'])->where('active', 1)->get()->getRowArray();
            if ($subject === null || $semester === null || $class === null) throw new \DomainException('Mapel, semester, atau rombel aktif tidak sesuai sekolah.');
            $assignmentKey = 'assignment:' . $username . ':' . $subject['id'] . ':' . $class['id'] . ':' . $semester['id'];
            if (isset($seen[$assignmentKey])) throw new \DomainException('Penugasan guru duplikat dalam berkas.');
            $seen[$assignmentKey] = true;
            $user = $this->db->table('users')->where('username', $username)->get()->getRowArray();
            $existing = $user === null ? null : $this->db->table('teachers')->where('school_id', $school)->where('user_id', $user['id'])->get()->getRowArray();
            if ($user !== null && $existing === null) throw new \DomainException('Username sudah dipakai akun lain.');
            $employeeOwner = $this->db->table('teachers')->where('school_id', $school)->where('employee_no', $employee)->get()->getRowArray();
            if ($employeeOwner !== null && ($existing === null || $employeeOwner['id'] !== $existing['id'])) throw new \DomainException('Nomor pegawai sudah digunakan.');
            if ($existing !== null && $mode === 'reject' && ! isset($seen['created:' . $username])) throw new \DomainException('Guru sudah ada.');
            if ($existing === null) $seen['created:' . $username] = true;
            $assigned = $existing === null ? false : $this->db->table('teaching_assignments')->where('teacher_id', $existing['id'])->where('subject_id', $subject['id'])->where('class_group_id', $class['id'])->where('semester_id', $semester['id'])->countAllResults() > 0;
            return ['status' => ($existing !== null && $mode === 'skip') || $assigned ? 'skip' : 'ready', 'existingId' => $existing === null ? null : (int) $existing['id'], 'subjectId' => (int) $subject['id'], 'semesterId' => (int) $semester['id'], 'classId' => (int) $class['id']];
        }
        if ($kind === 'students') {
            $username = mb_strtolower($row['username']);
            $nis = $row['nis'];
            if (! preg_match('/\A[a-z0-9.]{3,30}\z/', $username) || mb_strlen($nis) < 2 || mb_strlen($nis) > 40 || mb_strlen($row['name']) < 2 || mb_strlen($row['name']) > 120 || ($row['email'] !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL))) throw new \DomainException('NIS, nama, username, atau email tidak valid.');
            if (isset($seen[$username]) || isset($seen['nis:' . $nis])) throw new \DomainException('Duplikat dalam berkas.');
            $seen[$username] = true; $seen['nis:' . $nis] = true;
            $class = $this->db->table('class_groups cg')->select('cg.id')->join('academic_years ay', 'ay.id=cg.academic_year_id')->where('cg.school_id', $school)->where('cg.code', $row['class_group_code'])->where('cg.active', 1)->where('ay.active', 1)->get()->getRowArray();
            if ($class === null) throw new \DomainException('Rombel aktif tidak ditemukan.');
            $user = $this->db->table('users')->where('username', $username)->get()->getRowArray();
            $existing = $user === null ? null : $this->db->table('students')->where('school_id', $school)->where('user_id', $user['id'])->get()->getRowArray();
            if ($user !== null && $existing === null) throw new \DomainException('Username sudah dipakai akun lain.');
            $nisOwner = $this->db->table('students')->where('school_id', $school)->where('nis', $nis)->get()->getRowArray();
            if ($nisOwner !== null && ($existing === null || $nisOwner['id'] !== $existing['id'])) throw new \DomainException('NIS sudah digunakan.');
            if ($existing !== null && $mode === 'reject') throw new \DomainException('Siswa sudah ada.');
            return ['status' => $existing !== null && $mode === 'skip' ? 'skip' : 'ready', 'classId' => (int) $class['id'], 'existingId' => $existing === null ? null : (int) $existing['id']];
        }
        $subject = $this->db->table('subjects')->where('school_id', $school)->where('code', $row['subject_code'])->where('active', 1)->get()->getRowArray();
        if ($subject === null || (! $admin && ! (new QuestionBank($this->db))->teacherCanUseSubject($school, $actor, (int) $subject['id']))) throw new \DomainException('Mapel di luar penugasan.');
        if ($row['body'] === '' || mb_strlen($row['body']) > 20000 || ! is_numeric($row['weight']) || (float) $row['weight'] <= 0 || (float) $row['weight'] > 1000 || ! in_array($row['type'], ['mcq', 'essay'], true)) throw new \DomainException('Soal, jenis, atau bobot tidak valid.');
        if ($row['type'] === 'mcq' && ($row['option_a'] === '' || $row['option_b'] === '' || ! in_array($row['correct_option'], ['A', 'B', 'C', 'D'], true) || $row['option_' . mb_strtolower($row['correct_option'])] === '')) throw new \DomainException('Opsi atau kunci tidak valid.');
        if ($row['type'] === 'essay' && $row['rubric'] === '') throw new \DomainException('Rubrik esai wajib diisi.');
        $key = (int) $subject['id'] . ':' . mb_strtolower($row['body']);
        if (isset($seen[$key])) throw new \DomainException('Duplikat dalam berkas.');
        $seen[$key] = true;
        $existing = $this->db->table('questions')->where('school_id', $school)->where('subject_id', $subject['id'])->where('body', $row['body'])->where('deleted_at', null)->get()->getRowArray();
        if ($existing !== null && $mode !== 'skip') throw new \DomainException('Soal sudah ada. Gunakan mode lewati duplikat.');
        return ['status' => $existing === null ? 'ready' : 'skip', 'subjectId' => (int) $subject['id']];
    }

    public function preview(int $school, int $actor, bool $admin, string $kind, string $mode, string $path, string $extension): array
    {
        if (! isset(self::HEADERS[$kind]) || ! in_array($mode, ['reject', 'skip', 'update'], true) || ($kind === 'questions' && $mode === 'update')) throw new \DomainException('Jenis atau mode impor tidak valid.');
        if (in_array($kind, ['students', 'teachers', 'subjects'], true) && ! $admin) throw new \DomainException('Hanya admin dapat mengimpor data ini.');
        $source = $this->read($kind, $path, $extension);
        $seen = []; $rows = []; $counts = ['ready' => 0, 'skip' => 0, 'error' => 0];
        foreach ($source as $entry) {
            try {
                $check = $this->check($school, $actor, $admin, $kind, $mode, $entry['value'], $seen);
                $status = $check['status']; $error = null;
            } catch (\DomainException $exception) {
                $check = []; $status = 'error'; $error = $exception->getMessage();
            }
            $counts[$status]++;
            $rows[] = ['rowNumber' => $entry['number'], 'status' => $status, 'error' => $error, 'value' => $entry['value'], 'resolved' => $check];
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('import_jobs')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'kind' => $kind, 'mode' => $mode, 'status' => 'preview', 'row_count' => count($rows), 'ready_count' => $counts['ready'], 'skip_count' => $counts['skip'], 'error_count' => $counts['error'], 'created_at' => $now]);
            $jobId = (int) $this->db->insertID();
            foreach ($rows as $row) $this->db->table('import_rows')->insert(['job_id' => $jobId, 'row_number' => $row['rowNumber'], 'status' => $row['status'], 'payload_json' => json_encode(['value' => $row['value'], 'resolved' => $row['resolved']], JSON_THROW_ON_ERROR), 'error_message' => $row['error']]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Pratinjau gagal disimpan.');
            $this->db->transCommit();
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['id' => $jobId, 'kind' => $kind, 'mode' => $mode, 'counts' => $counts, 'rows' => $rows];
    }

    public function commit(int $school, int $actor, bool $admin, int $jobId): array
    {
        $this->db->transBegin();
        try {
            $job = $this->db->query('SELECT * FROM import_jobs WHERE id=? AND school_id=? AND actor_user_id=? FOR UPDATE', [$jobId, $school, $actor])->getRowArray();
            if ($job === null) throw new \OutOfBoundsException('Pratinjau impor tidak ditemukan.');
            if ($job['status'] === 'committed') { $this->db->transCommit(); return ['id' => $jobId, 'status' => 'committed', 'created' => (int) $job['ready_count'], 'skipped' => (int) $job['skip_count'], 'credentials' => []]; }
            if ((int) $job['error_count'] > 0) throw new \DomainException('Perbaiki semua baris bermasalah dan unggah ulang.');
            if (in_array($job['kind'], ['students', 'teachers', 'subjects'], true) && ! $admin) throw new \DomainException('Hanya admin dapat mengimpor data ini.');
            $records = $this->db->table('import_rows')->where('job_id', $jobId)->orderBy('row_number')->get()->getResultArray();
            $seen = []; $credentials = [];
            foreach ($records as $record) {
                if ($record['status'] === 'skip') continue;
                $payload = json_decode($record['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                $row = $payload['value'];
                $current = $this->check($school, $actor, $admin, $job['kind'], $job['mode'], $row, $seen);
                if ($current['status'] !== 'ready') throw new \DomainException('Data berubah sejak pratinjau. Unggah ulang.');
                if ($job['kind'] === 'subjects') {
                    (new AcademicCatalog($this->db))->save('subjects', $school, ['code' => $current['code'], 'name' => $row['name']], $current['existingId']);
                } elseif ($job['kind'] === 'teachers') {
                    if ($current['existingId'] === null) {
                        $created = (new PeopleService($this->db))->create('teachers', $school, ['username' => $row['username'], 'displayName' => $row['name'], 'employeeNo' => $row['employee_no'], 'email' => $row['email']]);
                        $teacherId = (int) $created['person']['id'];
                        $credentials[] = ['rowNumber' => (int) $record['row_number'], 'username' => $row['username'], 'temporaryPassword' => $created['temporaryPassword']];
                    } else {
                        $teacherId = $current['existingId'];
                        (new PeopleService($this->db))->update('teachers', $school, $teacherId, ['displayName' => $row['name'], 'employeeNo' => $row['employee_no']]);
                    }
                    (new AssignmentService($this->db))->createTeaching($school, ['teacherId' => $teacherId, 'subjectId' => $current['subjectId'], 'classGroupId' => $current['classId'], 'semesterId' => $current['semesterId']]);
                } elseif ($job['kind'] === 'students') {
                    if ($current['existingId'] === null) {
                        $created = (new PeopleService($this->db))->create('students', $school, ['username' => $row['username'], 'displayName' => $row['name'], 'nis' => $row['nis'], 'email' => $row['email']]);
                        $studentId = (int) $created['person']['id'];
                        $credentials[] = ['rowNumber' => (int) $record['row_number'], 'username' => $row['username'], 'temporaryPassword' => $created['temporaryPassword']];
                    } else {
                        $studentId = $current['existingId'];
                        (new PeopleService($this->db))->update('students', $school, $studentId, ['displayName' => $row['name'], 'nis' => $row['nis']]);
                    }
                    (new AssignmentService($this->db))->moveStudent($school, $studentId, $current['classId']);
                } else {
                    $options = [];
                    if ($row['type'] === 'mcq') foreach (['A', 'B', 'C', 'D'] as $letter) if ($row['option_' . mb_strtolower($letter)] !== '') $options[] = ['body' => $row['option_' . mb_strtolower($letter)], 'isCorrect' => $row['correct_option'] === $letter];
                    (new QuestionBank($this->db))->save($school, $actor, ['subjectId' => $current['subjectId'], 'body' => $row['body'], 'questionType' => $row['type'], 'rubric' => $row['rubric'], 'explanation' => $row['explanation'], 'weight' => (float) $row['weight'], 'options' => $options, 'status' => 'draft'], null, $admin);
                }
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('import_jobs')->where('id', $jobId)->update(['status' => 'committed', 'committed_at' => $now]);
            (new AuditTrail($this->db))->record($school, $actor, 'imports.commit', 'import_job', $jobId, ['kind' => $job['kind'], 'ready' => (int) $job['ready_count'], 'skipped' => (int) $job['skip_count']]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Impor gagal.');
            $this->db->transCommit();
            return ['id' => $jobId, 'status' => 'committed', 'created' => (int) $job['ready_count'], 'skipped' => (int) $job['skip_count'], 'credentials' => $credentials];
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }
}
