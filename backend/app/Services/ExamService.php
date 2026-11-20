<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ExamService
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function find(int $school, int $id): ?array
    {
        return $this->db->table('exams')->where('school_id', $school)->where('id', $id)->get()->getRowArray();
    }

    public function isAllowed(int $school, int $userId, bool $admin, array $exam): bool
    {
        return $admin || (int) $exam['author_user_id'] === $userId;
    }

    private function listQuery(int $school, ?int $author, string $search, string $subjectId, string $classId, string $status, string $now)
    {
        $query = $this->db->table('exams e')->join('subjects s', 's.id=e.subject_id')
            ->join('question_banks b', 'b.id=e.source_bank_id', 'left')->where('e.school_id', $school)->where('e.archived_at', null);
        if ($author !== null) $query->where('e.author_user_id', $author);
        if ($subjectId !== '') $query->where('e.subject_id', (int) $subjectId);
        if ($classId !== '') $query->where('EXISTS (SELECT 1 FROM exam_classes ec WHERE ec.exam_id=e.id AND ec.class_group_id=' . (int) $classId . ')', null, false);
        if ($search !== '') {
            $term = mb_substr($search, 0, 80);
            $query->groupStart()->like('e.title', $term)->orLike('s.name', $term)->orLike('b.name', $term)->groupEnd();
        }
        if ($status === 'draft' || $status === 'paused') $query->where('e.status', $status);
        elseif ($status === 'active') $query->where('e.status', 'published')->where('e.end_at >=', $now);
        elseif ($status === 'finished') $query->where('e.status', 'published')->where('e.end_at <', $now);
        return $query;
    }

    public function list(int $school, ?int $author, int $page, int $perPage, string $search, string $subjectId = '', string $classId = '', string $status = 'all'): array
    {
        $page = max(1, $page); $perPage = max(1, min(100, $perPage)); $now = gmdate('Y-m-d H:i:s');
        if (! in_array($status, ['all', 'active', 'finished', 'draft', 'paused'], true)) throw new \DomainException('Filter status tidak valid.');
        $query = $this->listQuery($school, $author, $search, $subjectId, $classId, $status, $now)
            ->select('e.id, e.title, e.subject_id, e.semester_id, e.exam_type_id, e.start_at, e.end_at, e.duration_minutes, e.status, e.published_at, e.shuffle_questions, e.shuffle_options, e.source_bank_id, e.display_question_count, s.name AS subject_name, b.name AS bank_name')
            ->select('(e.access_code_hash IS NOT NULL) AS token_enabled', false)
            ->select('COALESCE(e.display_question_count, (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id)) AS question_count', false)
            ->select('(SELECT GROUP_CONCAT(cg.name ORDER BY cg.name SEPARATOR ", ") FROM exam_classes ec JOIN class_groups cg ON cg.id=ec.class_group_id WHERE ec.exam_id=e.id) AS class_names', false)
            ->select('COALESCE((SELECT SUM(ei.weight) FROM exam_items ei WHERE ei.exam_id=e.id), (SELECT SUM(q.weight) FROM questions q WHERE q.bank_id=e.source_bank_id AND q.status="published" AND q.deleted_at IS NULL), 0) AS total_points', false)
            ->select('(SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id) AS attempt_count', false);
        $total = $query->countAllResults(false);
        $rows = $query->orderBy('e.start_at', 'DESC')->orderBy('e.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $counts = ['all' => 0, 'active' => 0, 'finished' => 0, 'draft' => 0, 'paused' => 0];
        foreach (array_keys($counts) as $key) $counts[$key] = $this->listQuery($school, $author, $search, $subjectId, $classId, $key, $now)->countAllResults();
        return ['rows' => $rows, 'page' => $page, 'perPage' => $perPage, 'total' => $total, 'counts' => $counts, 'serverNow' => $now];
    }

    public function detail(array $exam): array
    {
        $exam['requiresAccessCode'] = ! empty($exam['access_code_hash']);
        unset($exam['access_code_hash']);
        $exam['classIds'] = array_map('intval', array_column($this->db->table('exam_classes')->select('class_group_id')->where('exam_id', $exam['id'])->get()->getResultArray(), 'class_group_id'));
        $exam['questionIds'] = array_map('intval', array_column($this->db->table('exam_questions')->select('question_id')->where('exam_id', $exam['id'])->orderBy('sort_order')->get()->getResultArray(), 'question_id'));
        $exam['participantCount'] = $this->db->table('exam_assignments')->where('exam_id', $exam['id'])->countAllResults();
        $exam['attemptCount'] = $this->db->table('attempts')->where('exam_id', $exam['id'])->countAllResults();
        if (! empty($exam['source_bank_id'])) {
            $exam['source_bank_name'] = $this->db->table('question_banks')->select('name')->where('id', $exam['source_bank_id'])->where('school_id', $exam['school_id'])->get()->getRowArray()['name'] ?? null;
            $exam['published_question_count'] = $exam['status'] === 'published'
                ? $this->db->table('exam_items')->where('exam_id', $exam['id'])->countAllResults()
                : $this->db->table('questions')->where('bank_id', $exam['source_bank_id'])->where('school_id', $exam['school_id'])->where('status', 'published')->where('deleted_at', null)->countAllResults();
        }
        return $exam;
    }

    private function utc(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,6})?(?:Z|[+-]\d\d:\d\d)$/', $value)) throw new \DomainException('Waktu harus ISO 8601 dengan zona waktu.');
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new \DomainException('Waktu tidak valid.');
        }
    }

    private function ids(mixed $value, string $name): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > 200 || count($value) !== count(array_unique($value, SORT_REGULAR))) throw new \DomainException($name . ' harus daftar ID unik maksimal 200.');
        foreach ($value as $id) if (! is_int($id) || $id < 1) throw new \DomainException($name . ' berisi ID tidak valid.');
        return $value;
    }

    public function save(int $school, int $user, bool $admin, array $input, ?int $id): array
    {
        $allowed = ['title', 'instructions', 'subjectId', 'semesterId', 'examTypeId', 'startAt', 'endAt', 'durationMinutes', 'passingGrade', 'maxAttempts', 'resultPolicy', 'shuffleQuestions', 'shuffleOptions', 'classIds', 'questionIds', 'accessCode', 'reviewEnabled', 'packageMode', 'requireAllAnswers'];
        if (array_diff(array_keys($input), $allowed) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $old = $id === null ? null : $this->find($school, $id);
        if ($id !== null && $old === null) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        if ($old !== null && ! $this->isAllowed($school, $user, $admin, $old)) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        if ($old !== null && $old['status'] !== 'draft') throw new \DomainException('Ujian terbit tidak dapat diubah.');
        $title = trim(strip_tags((string) ($input['title'] ?? $old['title'] ?? '')));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) throw new \DomainException('Judul harus 3–160 karakter.');
        $instructions = trim(strip_tags((string) ($input['instructions'] ?? $old['instructions'] ?? '')));
        if (mb_strlen($instructions) > 10000) throw new \DomainException('Instruksi terlalu panjang.');
        $subjectId = (int) ($input['subjectId'] ?? $old['subject_id'] ?? 0);
        $semesterId = (int) ($input['semesterId'] ?? $old['semester_id'] ?? 0);
        $examTypeId = (int) ($input['examTypeId'] ?? $old['exam_type_id'] ?? 0);
        $subject = $this->db->table('subjects')->where('id', $subjectId)->where('school_id', $school)->where('active', 1)->get()->getRowArray();
        $semester = $this->db->table('semesters')->where('id', $semesterId)->where('school_id', $school)->get()->getRowArray();
        if ($subject === null || $semester === null) throw new \DomainException('Mapel atau semester tidak ditemukan di sekolah.');
        if ($examTypeId > 0 && $this->db->table('exam_types')->where('id', $examTypeId)->where('school_id', $school)->where('active', 1)->countAllResults() !== 1) throw new \DomainException('Jenis ujian tidak aktif atau di luar sekolah.');
        if (! $admin && ! (new QuestionBank($this->db))->teacherCanUseSubject($school, $user, $subjectId)) throw new \DomainException('Mapel di luar penugasan guru.');
        $start = isset($input['startAt']) ? $this->utc($input['startAt']) : ($old['start_at'] ?? null);
        $end = isset($input['endAt']) ? $this->utc($input['endAt']) : ($old['end_at'] ?? null);
        if ($start === null || $end === null || $start >= $end) throw new \DomainException('Jadwal mulai harus sebelum selesai.');
        $duration = (int) ($input['durationMinutes'] ?? $old['duration_minutes'] ?? 0);
        $passing = $input['passingGrade'] ?? $old['passing_grade'] ?? null;
        $max = (int) ($input['maxAttempts'] ?? $old['max_attempts'] ?? 1);
        $policy = $input['resultPolicy'] ?? $old['result_policy'] ?? 'first';
        $packageMode = $input['packageMode'] ?? $old['package_mode'] ?? 'single';
        if (! in_array($packageMode, ['single', 'ab'], true)) throw new \DomainException('Mode paket tidak valid.');
        if ($packageMode === 'ab' && ! empty($input['shuffleQuestions'] ?? $old['shuffle_questions'] ?? false)) throw new \DomainException('Paket A/B tidak dapat digabung dengan acak urutan soal.');
        $codeHash = $old['access_code_hash'] ?? null;
        if (array_key_exists('accessCode', $input)) {
            if (! is_string($input['accessCode']) || ($input['accessCode'] !== '' && ! preg_match('/\A[A-Za-z0-9-]{6,32}\z/', $input['accessCode']))) throw new \DomainException('Kode akses harus 6–32 huruf, angka, atau tanda hubung.');
            $codeHash = $input['accessCode'] === '' ? null : password_hash($input['accessCode'], PASSWORD_DEFAULT);
        }
        if ($duration < 1 || $duration > 720 || ! is_numeric($passing) || (float) $passing < 0 || (float) $passing > 100 || $max < 1 || $max > 5 || ! in_array($policy, ['first', 'highest'], true) || ($max === 1 && $policy !== 'first')) throw new \DomainException('Durasi, batas percobaan, ambang lulus, atau kebijakan hasil tidak valid.');
        $classes = array_key_exists('classIds', $input) ? $this->ids($input['classIds'], 'Rombel') : ($old === null ? [] : $this->detail($old)['classIds']);
        $questions = array_key_exists('questionIds', $input) ? $this->ids($input['questionIds'], 'Soal') : ($old === null ? [] : $this->detail($old)['questionIds']);
        foreach ($classes as $classId) {
            $class = $this->db->table('class_groups')->where('id', $classId)->where('school_id', $school)->where('academic_year_id', $semester['academic_year_id'])->where('active', 1)->get()->getRowArray();
            if ($class === null) throw new \DomainException('Rombel tidak sesuai semester atau sekolah.');
        }
        foreach ($questions as $questionId) {
            $question = $this->db->table('questions')->where('id', $questionId)->where('school_id', $school)->where('subject_id', $subjectId)->where('deleted_at', null)->get()->getRowArray();
            if ($question === null) throw new \DomainException('Soal tidak sesuai mata pelajaran atau sekolah.');
            if (! $admin && (int) $question['author_user_id'] !== $user) throw new \DomainException('Guru hanya dapat memakai soal miliknya.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $row = ['school_id' => $school, 'subject_id' => $subjectId, 'semester_id' => $semesterId, 'exam_type_id' => $examTypeId ?: null,
                'title' => $title, 'instructions' => $instructions, 'start_at' => $start, 'end_at' => $end,
                'duration_minutes' => $duration, 'passing_grade' => $passing, 'max_attempts' => $max,
                'result_policy' => $policy, 'shuffle_questions' => ! empty($input['shuffleQuestions'] ?? $old['shuffle_questions'] ?? false) ? 1 : 0,
                'shuffle_options' => ! empty($input['shuffleOptions'] ?? $old['shuffle_options'] ?? false) ? 1 : 0, 'access_code_hash' => $codeHash,
                'review_enabled' => ! empty($input['reviewEnabled'] ?? $old['review_enabled'] ?? false) ? 1 : 0,
                'package_mode' => $packageMode, 'require_all_answers' => ! empty($input['requireAllAnswers'] ?? $old['require_all_answers'] ?? false) ? 1 : 0, 'updated_at' => $now];
            if ($id === null) {
                $this->db->table('exams')->insert($row + ['author_user_id' => $user, 'status' => 'draft', 'created_at' => $now]);
                $id = (int) $this->db->insertID();
            } else $this->db->table('exams')->where('id', $id)->update($row);
            $this->db->table('exam_classes')->where('exam_id', $id)->delete();
            foreach ($classes as $classId) $this->db->table('exam_classes')->insert(['exam_id' => $id, 'class_group_id' => $classId, 'created_at' => $now, 'updated_at' => $now]);
            $this->db->table('exam_questions')->where('exam_id', $id)->delete();
            foreach ($questions as $index => $questionId) $this->db->table('exam_questions')->insert(['exam_id' => $id, 'question_id' => $questionId, 'sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan ujian gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->detail($this->find($school, $id));
    }

    public function saveFromBank(int $school, int $user, bool $admin, array $input, ?int $id = null): array
    {
        $allowed = ['bankId', 'title', 'semesterId', 'classIds', 'displayQuestionCount', 'startAt', 'endAt', 'durationMinutes', 'accessCode', 'shuffleQuestions', 'shuffleOptions', 'status'];
        if (array_diff(array_keys($input), $allowed) !== []) throw new \DomainException('Kolom jadwal tidak diizinkan.');
        $old = $id === null ? null : $this->find($school, $id);
        if ($id !== null && ($old === null || ! $this->isAllowed($school, $user, $admin, $old))) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        if ($old !== null && $old['status'] !== 'draft') throw new \DomainException('Hanya draf yang dapat diubah melalui formulir bank soal.');

        $bankId = (int) ($input['bankId'] ?? $old['source_bank_id'] ?? 0);
        $bank = (new QuestionBankContainer($this->db))->find($school, $bankId, $admin ? null : $user);
        if ($bank === null) throw new \OutOfBoundsException('Bank soal tidak ditemukan.');
        $status = (string) ($input['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'published'], true)) throw new \DomainException('Status jadwal tidak valid.');
        $title = trim(strip_tags((string) ($input['title'] ?? $old['title'] ?? '')));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) throw new \DomainException('Nama ujian harus 3–160 karakter.');

        $semesterId = (int) ($input['semesterId'] ?? $old['semester_id'] ?? 0);
        $semester = $this->db->table('semesters')->where('id', $semesterId)->where('school_id', $school)->where('active', 1)->get()->getRowArray();
        if ($semester === null) throw new \DomainException('Semester aktif tidak ditemukan di sekolah.');
        $bankClasses = (new QuestionBankContainer($this->db))->detail($bank)['classes'];
        $bankClassIds = array_map('intval', array_column($bankClasses, 'id'));
        $classes = array_key_exists('classIds', $input) ? $this->ids($input['classIds'], 'Rombel') : ($old === null ? [] : $this->detail($old)['classIds']);
        if (count($classes) > 50 || array_diff($classes, $bankClassIds) !== []) throw new \DomainException('Kelas harus dipilih dari kelas bank soal.');
        foreach ($classes as $classId) {
            $class = $this->db->table('class_groups')->where('id', $classId)->where('school_id', $school)->where('academic_year_id', $semester['academic_year_id'])->where('active', 1)->get()->getRowArray();
            if ($class === null) throw new \DomainException('Kelas tidak aktif atau tidak sesuai dengan semester bank soal.');
            if (! $admin) {
                $assigned = $this->db->table('teaching_assignments ta')->join('teachers t', 't.id=ta.teacher_id')
                    ->where('ta.school_id', $school)->where('t.user_id', $user)->where('ta.semester_id', $semesterId)
                    ->where('ta.subject_id', $bank['subject_id'])->where('ta.class_group_id', $classId)->where('ta.active', 1)->countAllResults();
                if ($assigned < 1) throw new \DomainException('Kelas di luar penugasan guru untuk semester ini.');
            }
        }
        $published = $this->db->table('questions')->select('id')->where('bank_id', $bankId)->where('school_id', $school)
            ->where('status', 'published')->where('deleted_at', null)->orderBy('id')->get()->getResultArray();
        if (count($published) > 200) throw new \DomainException('Bank ini memiliki lebih dari batas 200 soal per ujian.');
        $questionIds = array_map('intval', array_column($published, 'id'));
        $displayCount = $input['displayQuestionCount'] ?? $old['display_question_count'] ?? count($questionIds);
        if (! is_int($displayCount) || $displayCount < 0 || $displayCount > count($questionIds) || ($status === 'published' && $displayCount < 1)) {
            throw new \DomainException('Jumlah soal harus 1 sampai jumlah soal terbit di bank. Draf boleh bernilai 0.');
        }
        $start = isset($input['startAt']) ? $this->utc($input['startAt']) : ($old['start_at'] ?? null);
        $end = isset($input['endAt']) ? $this->utc($input['endAt']) : ($old['end_at'] ?? null);
        $duration = (int) ($input['durationMinutes'] ?? $old['duration_minutes'] ?? 0);
        if ($start === null || $end === null || $start >= $end || $duration < 1 || $duration > 720) throw new \DomainException('Jadwal atau durasi ujian tidak valid.');
        if (array_key_exists('accessCode', $input)) {
            if (! is_string($input['accessCode']) || ! preg_match('/\A[A-Za-z0-9-]{6,32}\z/', $input['accessCode'])) throw new \DomainException('Token harus 6–32 huruf, angka, atau tanda hubung.');
        } elseif ($old === null || empty($old['access_code_hash'])) {
            throw new \DomainException('Token ujian wajib diisi.');
        }
        foreach (['shuffleQuestions', 'shuffleOptions'] as $flag) {
            if (array_key_exists($flag, $input) && ! is_bool($input[$flag])) throw new \DomainException('Pengaturan acak tidak valid.');
        }

        $this->db->transBegin();
        try {
            $saveInput = [
                'title' => $title, 'instructions' => $old['instructions'] ?? '', 'subjectId' => (int) $bank['subject_id'],
                'semesterId' => $semesterId, 'examTypeId' => (int) ($bank['exam_type_id'] ?? 0),
                'startAt' => $input['startAt'] ?? (new \DateTimeImmutable($start, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endAt' => $input['endAt'] ?? (new \DateTimeImmutable($end, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'durationMinutes' => $duration, 'passingGrade' => 70, 'maxAttempts' => 1, 'resultPolicy' => 'first',
                'shuffleQuestions' => $input['shuffleQuestions'] ?? false, 'shuffleOptions' => $input['shuffleOptions'] ?? false,
                'classIds' => $classes, 'questionIds' => $questionIds,
                'reviewEnabled' => false, 'packageMode' => 'single', 'requireAllAnswers' => false,
            ];
            if (array_key_exists('accessCode', $input)) $saveInput['accessCode'] = $input['accessCode'];
            $saved = $this->save($school, $user, $admin, $saveInput, $id);
            $examId = (int) $saved['id'];
            $this->db->table('exams')->where('id', $examId)->update([
                'source_bank_id' => $bankId, 'display_question_count' => $displayCount,
                'status' => 'draft', 'archived_at' => null, 'token_mode' => 'static', 'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($status === 'published') $saved = $this->publish($school, $user, $admin, $examId);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan jadwal bank soal gagal.');
            $this->db->transCommit();
            return $this->detail($this->find($school, $examId));
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function archiveDraft(int $school, int $user, bool $admin, int $id): void
    {
        $exam = $this->find($school, $id);
        if ($exam === null || ! $this->isAllowed($school, $user, $admin, $exam)) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        if ($exam['status'] !== 'draft' || $this->db->table('attempts')->where('exam_id', $id)->countAllResults() > 0) {
            throw new \DomainException('Hanya draf yang belum memiliki percobaan dapat diarsipkan.');
        }
        $this->db->table('exams')->where('id', $id)->where('school_id', $school)->where('status', 'draft')
            ->update(['archived_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function updatePublishedSchedule(int $school, int $user, bool $admin, int $id, array $input): array
    {
        if (array_diff(array_keys($input), ['startAt', 'endAt', 'durationMinutes']) !== []) throw new \DomainException('Kolom jadwal tidak diizinkan.');
        $exam = $this->find($school, $id);
        if ($exam === null || ! $this->isAllowed($school, $user, $admin, $exam)) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        if ($exam['status'] !== 'published') throw new \DomainException('Jadwal ini bukan ujian terbit.');
        if ($this->db->table('attempts')->where('exam_id', $id)->countAllResults() > 0) throw new \DomainException('Waktu tidak dapat diubah setelah siswa mulai mengerjakan.');
        if (! $admin && ! $this->canTeachAll($school, $user, $exam)) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        $start = $this->utc($input['startAt'] ?? null); $end = $this->utc($input['endAt'] ?? null);
        $duration = $input['durationMinutes'] ?? null;
        if (! is_int($duration) || $duration < 1 || $duration > 720 || $start >= $end) throw new \DomainException('Jadwal atau durasi ujian tidak valid.');
        $this->db->table('exams')->where('id', $id)->update(['start_at' => $start, 'end_at' => $end, 'duration_minutes' => $duration, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return $this->detail($this->find($school, $id));
    }

    public function rosterPreview(int $school, int $id): array
    {
        $exam = $this->find($school, $id);
        if ($exam === null) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        $classes = $this->detail($exam)['classIds'];
        if ($classes === []) return [];
        return $this->db->table('student_class_memberships m')->select('s.id AS student_id, up.display_name, cg.id AS class_group_id, cg.name AS class_name')
            ->join('students s', 's.id=m.student_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('class_groups cg', 'cg.id=m.class_group_id')
            ->where('m.school_id', $school)->where('m.active', 1)->where('s.status', 'active')
            ->whereIn('m.class_group_id', $classes)->groupBy('s.id, up.display_name, cg.id, cg.name')->orderBy('up.display_name')->get()->getResultArray();
    }

    public function assignmentRoster(int $school, int $id): array
    {
        $exam = $this->find($school, $id);
        if ($exam === null || $exam['status'] !== 'published') throw new \OutOfBoundsException('Daftar peserta belum tersedia.');
        return $this->db->table('exam_assignments ea')
            ->select('ea.student_id, ea.class_group_id, ea.exam_room_id, ea.participant_number, up.display_name, cg.name AS class_name, er.name AS room_name')
            ->join('students s', 's.id=ea.student_id')
            ->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('class_groups cg', 'cg.id=ea.class_group_id')
            ->join('exam_rooms er', 'er.id=ea.exam_room_id', 'left')
            ->where('ea.school_id', $school)->where('ea.exam_id', $id)
            ->orderBy('up.display_name')->get()->getResultArray();
    }

    public function assignParticipant(int $school, int $examId, int $studentId, array $input): array
    {
        if (array_diff(array_keys($input), ['examRoomId', 'participantNumber']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $roomId = $input['examRoomId'] ?? null;
        $number = $input['participantNumber'] ?? null;
        if ($roomId !== null && (! is_int($roomId) || $roomId < 1)) throw new \DomainException('Ruang tidak valid.');
        if ($number !== null && (! is_string($number) || ! preg_match('/\A[A-Za-z0-9-]{1,40}\z/', $number))) throw new \DomainException('Nomor peserta harus 1-40 huruf, angka, atau tanda hubung.');
        $this->db->transBegin();
        try {
            $exam = $this->db->query('SELECT * FROM exams WHERE id = ? AND school_id = ? FOR UPDATE', [$examId, $school])->getRowArray();
            if ($exam === null || $exam['status'] !== 'published') throw new \OutOfBoundsException('Ujian terbit tidak ditemukan.');
            $assignment = $this->db->query('SELECT * FROM exam_assignments WHERE exam_id = ? AND student_id = ? AND school_id = ? FOR UPDATE', [$examId, $studentId, $school])->getRowArray();
            if ($assignment === null) throw new \OutOfBoundsException('Peserta tidak ditugaskan.');
            if ($this->db->table('attempts')->where('exam_id', $examId)->where('student_id', $studentId)->countAllResults() > 0) throw new \DomainException('Penempatan peserta yang sudah mulai ujian tidak dapat diubah.');
            if ($roomId !== null) {
                $semester = $this->db->table('semesters')->where('id', $exam['semester_id'])->get()->getRowArray();
                $room = $this->db->table('exam_rooms')->where('id', $roomId)->where('school_id', $school)->where('academic_year_id', $semester['academic_year_id'])->where('active', 1)->get()->getRowArray();
                if ($room === null) throw new \DomainException('Ruang tidak sesuai sekolah atau tahun ajaran.');
                $occupied = $this->db->table('exam_assignments')->where('exam_id', $examId)->where('exam_room_id', $roomId)->where('student_id !=', $studentId)->countAllResults();
                if ($occupied >= (int) $room['capacity']) throw new \DomainException('Kapasitas ruang penuh.');
            }
            if ($number !== null) {
                $duplicate = $this->db->table('exam_assignments')->where('exam_id', $examId)->where('participant_number', $number)->where('student_id !=', $studentId)->countAllResults();
                if ($duplicate > 0) throw new \LogicException('Nomor peserta sudah digunakan.');
            }
            $this->db->table('exam_assignments')->where('exam_id', $examId)->where('student_id', $studentId)->where('school_id', $school)
                ->update(['exam_room_id' => $roomId, 'participant_number' => $number, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penempatan peserta gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->db->table('exam_assignments')->where('exam_id', $examId)->where('student_id', $studentId)->get()->getRowArray();
    }

    public function canTeachAll(int $school, int $user, array $exam): bool
    {
        foreach ($this->detail($exam)['classIds'] as $classId) {
            $assigned = $this->db->table('teaching_assignments ta')->join('teachers t', 't.id=ta.teacher_id')
                ->where('ta.school_id', $school)->where('t.user_id', $user)->where('ta.semester_id', $exam['semester_id'])
                ->where('ta.subject_id', $exam['subject_id'])->where('ta.class_group_id', $classId)->where('ta.active', 1)->countAllResults();
            if ($assigned < 1) return false;
        }
        return true;
    }

    public function publish(int $school, int $user, bool $admin, int $id): array
    {
        $this->db->transBegin();
        try {
            $exam = $this->db->query('SELECT * FROM exams WHERE id = ? AND school_id = ? FOR UPDATE', [$id, $school])->getRowArray();
            if ($exam === null || ! $this->isAllowed($school, $user, $admin, $exam)) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
            if ($exam['status'] !== 'draft') throw new \DomainException('Ujian sudah terbit.');
            if (! empty($exam['source_bank_id'])) {
                $bank = $this->db->table('question_banks')->where('id', $exam['source_bank_id'])->where('school_id', $school)->get()->getRowArray();
                if ($bank === null || (int) $bank['subject_id'] !== (int) $exam['subject_id'] || (! $admin && (int) $bank['author_user_id'] !== $user)) throw new \DomainException('Bank soal tidak lagi dapat digunakan.');
                $bankQuestionIds = array_map('intval', array_column($this->db->table('questions')->select('id')->where('bank_id', $bank['id'])->where('school_id', $school)
                    ->where('status', 'published')->where('deleted_at', null)->orderBy('id')->get()->getResultArray(), 'id'));
                if (count($bankQuestionIds) > 200) throw new \DomainException('Bank ini memiliki lebih dari batas 200 soal per ujian.');
                $this->db->table('exam_questions')->where('exam_id', $id)->delete();
                foreach ($bankQuestionIds as $index => $questionId) $this->db->table('exam_questions')->insert([
                    'exam_id' => $id, 'question_id' => $questionId, 'sort_order' => $index + 1,
                    'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ((int) $exam['display_question_count'] < 1 || (int) $exam['display_question_count'] > count($bankQuestionIds)) throw new \DomainException('Jumlah soal tidak sesuai dengan soal terbit di bank.');
            }
            $detail = $this->detail($exam);
            $classes = $detail['classIds']; $questionIds = $detail['questionIds'];
            if ($classes === [] || $questionIds === []) throw new \DomainException('Pilih rombel dan sedikitnya satu soal.');
            if ($exam['package_mode'] === 'ab' && count($questionIds) < 2) throw new \DomainException('Paket A/B memerlukan minimal dua soal.');
            if ((int) $exam['duration_minutes'] < 1 || $exam['start_at'] >= $exam['end_at']) throw new \DomainException('Jadwal tidak valid.');
            $semester = $this->db->table('semesters')->where('id', $exam['semester_id'])->where('school_id', $school)->where('active', 1)->get()->getRowArray();
            if ($semester === null) throw new \DomainException('Semester harus aktif.');
            foreach ($classes as $classId) {
                $class = $this->db->table('class_groups')->where('id', $classId)->where('school_id', $school)->where('academic_year_id', $semester['academic_year_id'])->where('active', 1)->get()->getRowArray();
                if ($class === null) throw new \DomainException('Rombel tidak aktif atau tidak sesuai semester.');
            }
            if (! $admin && ! $this->canTeachAll($school, $user, $exam)) throw new \DomainException('Guru tidak mengampu semua rombel target.');
            $now = gmdate('Y-m-d H:i:s');
            $itemOrder = [];
            foreach ($questionIds as $index => $questionId) {
                $question = $this->db->table('questions')->where('id', $questionId)->where('school_id', $school)->where('subject_id', $exam['subject_id'])->where('status', 'published')->where('deleted_at', null)->get()->getRowArray();
                if ($question === null) throw new \DomainException('Semua soal harus terbit dan sesuai mata pelajaran.');
                if (! $admin && (int) $question['author_user_id'] !== $user) throw new \DomainException('Guru hanya dapat memakai soal miliknya.');
                $options = $this->db->table('question_options')->select('id, body, match_body, match_choice_id, is_correct')->where('question_id', $questionId)->orderBy('sort_order')->get()->getResultArray();
                $matchingChoices = $this->db->table('question_matching_choices')->select('id, body')->where('question_id', $questionId)->orderBy('sort_order')->get()->getResultArray();
                if ($question['question_type'] === 'mcq' && (count($options) < 2 || array_sum(array_column($options, 'is_correct')) !== 1)) throw new \DomainException('Opsi soal tidak valid.');
                if (in_array($question['question_type'], ['multi_mcq', 'true_false', 'matching'], true) && count($options) < 2) throw new \DomainException('Baris soal tidak valid.');
                if ($question['question_type'] === 'matching') {
                    $choiceIds = array_map('intval', array_column($matchingChoices, 'id'));
                    $keys = array_map(static fn ($option) => (int) ($option['match_choice_id'] ?? 0), $options);
                    if (count($choiceIds) < count($options) || in_array(0, $keys, true) || count(array_unique($keys)) !== count($options)
                        || array_diff($keys, $choiceIds) !== []) throw new \DomainException('Pasangan soal menjodohkan tidak lengkap.');
                }
                if ($question['question_type'] === 'essay' && trim((string) $question['rubric']) === '') throw new \DomainException('Rubrik esai belum diisi.');
                $assets = $this->db->table('question_assets')->select('id, original_name, mime_type')->where('question_id', $questionId)->where('archived_at', null)->get()->getResultArray();
                $snapshot = json_encode(['body' => $question['body'], 'type' => $question['question_type'], 'rubric' => $question['rubric'], 'explanation' => $question['explanation'], 'options' => $options, 'matching_choices' => $matchingChoices, 'assets' => $assets], JSON_THROW_ON_ERROR);
                $this->db->table('exam_items')->insert(['exam_id' => $id, 'source_question_id' => $questionId, 'sort_order' => $index + 1, 'snapshot_json' => $snapshot, 'weight' => $question['weight'], 'created_at' => $now, 'updated_at' => $now]);
                $itemOrder[] = (int) $this->db->insertID();
            }
            $this->db->table('exam_packages')->insert(['exam_id' => $id, 'code' => 'A', 'item_order_json' => json_encode($itemOrder, JSON_THROW_ON_ERROR), 'created_at' => $now]);
            if ($exam['package_mode'] === 'ab') $this->db->table('exam_packages')->insert(['exam_id' => $id, 'code' => 'B', 'item_order_json' => json_encode(array_reverse($itemOrder), JSON_THROW_ON_ERROR), 'created_at' => $now]);
            $roster = $this->rosterPreview($school, $id);
            if ($roster === []) throw new \DomainException('Rombel target belum memiliki siswa aktif.');
            foreach ($roster as $person) $this->db->table('exam_assignments')->insert(['school_id' => $school, 'exam_id' => $id, 'student_id' => $person['student_id'], 'class_group_id' => $person['class_group_id'], 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $this->db->table('exams')->where('id', $id)->update(['status' => 'published', 'published_at' => $now, 'updated_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penerbitan ujian gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->detail($this->find($school, $id));
    }

    public function studentList(int $school, int $studentId): array
    {
        return $this->db->table('exam_assignments ea')->select('e.id, e.title, e.instructions, e.start_at, e.end_at, e.duration_minutes, e.max_attempts, (e.access_code_hash IS NOT NULL) AS requires_access_code, s.name AS subject_name', false)
            ->join('exams e', 'e.id=ea.exam_id')->join('subjects s', 's.id=e.subject_id')
            ->where('ea.school_id', $school)->where('ea.student_id', $studentId)->where('e.status', 'published')
            ->orderBy('e.start_at', 'DESC')->get()->getResultArray();
    }
}
