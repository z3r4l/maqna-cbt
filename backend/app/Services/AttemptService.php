<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AttemptService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function shuffle(array $items): array
    {
        for ($index = count($items) - 1; $index > 0; $index--) {
            $random = random_int(0, $index);
            [$items[$index], $items[$random]] = [$items[$random], $items[$index]];
        }
        return $items;
    }

    public function deviceAllowed(array $attempt, string $device): bool
    {
        return $attempt['device_token_hash'] === null || hash_equals((string) $attempt['device_token_hash'], hash('sha256', $device));
    }

    public function start(int $school, int $student, int $examId, string $device, string $accessCode = ''): array
    {
        $this->db->transBegin();
        $closed = false;
        try {
            $assignment = $this->db->query('SELECT * FROM exam_assignments WHERE school_id = ? AND student_id = ? AND exam_id = ? FOR UPDATE', [$school, $student, $examId])->getRowArray();
            if ($assignment === null) throw new \OutOfBoundsException('Ujian tidak ditugaskan.');
            $exam = $this->db->table('exams')->where('id', $examId)->where('school_id', $school)->where('status', 'published')->get()->getRowArray();
            if ($exam === null) throw new \OutOfBoundsException('Ujian tidak tersedia.');
            $now = gmdate('Y-m-d H:i:s');
            if ($now < $exam['start_at'] || $now >= $exam['end_at']) throw new \DomainException('Ujian di luar jadwal.');
            $existing = $this->db->table('attempts')->where('exam_id', $examId)->where('student_id', $student)->where('status', 'active')->orderBy('id', 'DESC')->get()->getRowArray();
            if ($existing !== null && $existing['deadline_at'] > $now) {
                if (! $this->deviceAllowed($existing, $device)) throw new \DomainException('Percobaan aktif pada perangkat lain.');
                if ($existing['device_token_hash'] === null) {
                    $this->db->table('attempts')->where('id', $existing['id'])->update(['device_token_hash' => hash('sha256', $device)]);
                    $existing['device_token_hash'] = hash('sha256', $device);
                }
                $this->db->transCommit();
                $closed = true;
                return $this->detail($existing);
            }
            if ($exam['access_code_hash'] !== null) {
                $codeAttempt = $this->db->table('exam_code_attempts')->where('exam_id', $examId)->where('student_id', $student)->get()->getRowArray();
                if ($codeAttempt !== null && $codeAttempt['locked_until'] !== null && $codeAttempt['locked_until'] > $now) {
                    $this->db->transCommit(); $closed = true;
                    throw new \OverflowException('Terlalu banyak percobaan kode. Coba lagi nanti.');
                }
                $validCode = ($exam['token_mode'] ?? 'static') === 'dynamic'
                    ? DynamicExamToken::verify($exam['access_code_hash'], $accessCode, time())
                    : password_verify($accessCode, $exam['access_code_hash']);
                if (! $validCode) {
                    $count = $codeAttempt === null || $codeAttempt['window_started_at'] < gmdate('Y-m-d H:i:s', time() - 900) ? 1 : (int) $codeAttempt['failed_count'] + 1;
                    $row = ['failed_count' => $count, 'window_started_at' => $count === 1 ? $now : $codeAttempt['window_started_at'], 'locked_until' => $count >= 5 ? gmdate('Y-m-d H:i:s', time() + 900) : null];
                    if ($codeAttempt === null) $this->db->table('exam_code_attempts')->insert($row + ['school_id' => $school, 'exam_id' => $examId, 'student_id' => $student]);
                    else $this->db->table('exam_code_attempts')->where('id', $codeAttempt['id'])->update($row);
                    $this->db->transCommit(); $closed = true;
                    if ($count >= 5) throw new \OverflowException('Terlalu banyak percobaan kode. Coba lagi nanti.');
                    throw new \DomainException('Kode akses ujian tidak valid.');
                }
                if ($codeAttempt !== null) $this->db->table('exam_code_attempts')->where('id', $codeAttempt['id'])->update(['failed_count' => 0, 'locked_until' => null, 'window_started_at' => $now]);
            }
            if ($existing !== null) $this->db->table('attempts')->where('id', $existing['id'])->update(['status' => 'expired', 'submitted_at' => $now, 'updated_at' => $now]);
            if ($existing !== null) (new ResultService($this->db))->gradeAttempt($this->db->table('attempts')->where('id', $existing['id'])->get()->getRowArray());
            $count = $this->db->table('attempts')->where('exam_id', $examId)->where('student_id', $student)->countAllResults();
            if ($count >= (int) $exam['max_attempts']) {
                $this->db->transCommit();
                $closed = true;
                throw new \DomainException('Batas percobaan tercapai.');
            }
            $packageCode = 'A';
            if ($exam['package_mode'] === 'ab') {
                $roster = array_map('intval', array_column($this->db->table('exam_assignments')->select('student_id')->where('exam_id', $examId)->orderBy('student_id')->get()->getResultArray(), 'student_id'));
                $position = array_search($student, $roster, true);
                $packageCode = (($position === false ? 0 : $position) + $count) % 2 === 0 ? 'A' : 'B';
            }
            $deadline = min(gmdate('Y-m-d H:i:s', time() + (int) $exam['duration_minutes'] * 60), $exam['end_at']);
            $this->db->table('attempts')->insert(['school_id' => $school, 'exam_id' => $examId, 'student_id' => $student, 'attempt_number' => $count + 1, 'package_code' => $packageCode, 'status' => 'active', 'started_at' => $now, 'deadline_at' => $deadline, 'revision' => 0, 'last_seen_at' => $now, 'device_token_hash' => hash('sha256', $device), 'created_at' => $now, 'updated_at' => $now]);
            $attemptId = (int) $this->db->insertID();
            $items = $this->db->table('exam_items')->where('exam_id', $examId)->orderBy('sort_order')->get()->getResultArray();
            $package = $this->db->table('exam_packages')->where('exam_id', $examId)->where('code', $packageCode)->get()->getRowArray();
            if ($package !== null) {
                $byId = []; foreach ($items as $item) $byId[(int) $item['id']] = $item;
                $items = array_map(static fn ($id) => $byId[(int) $id], json_decode($package['item_order_json'], true, 512, JSON_THROW_ON_ERROR));
            }
            if ((int) $exam['shuffle_questions'] === 1) $items = $this->shuffle($items);
            if (isset($exam['display_question_count'])) $items = array_slice($items, 0, (int) $exam['display_question_count']);
            foreach ($items as $index => $item) {
                $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                $optionIds = array_map('intval', array_column($snapshot['options'], 'id'));
                if ((int) $exam['shuffle_options'] === 1) $optionIds = $this->shuffle($optionIds);
                $choiceIds = array_map('intval', array_column($snapshot['matching_choices'] ?? [], 'id'));
                if ((int) $exam['shuffle_options'] === 1) $choiceIds = $this->shuffle($choiceIds);
                $this->db->table('attempt_items')->insert(['attempt_id' => $attemptId, 'exam_item_id' => $item['id'], 'display_order' => $index + 1, 'option_order_json' => json_encode($optionIds, JSON_THROW_ON_ERROR),
                    'matching_choice_order_json' => $choiceIds === [] ? null : json_encode($choiceIds, JSON_THROW_ON_ERROR), 'marked' => 0, 'revision' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }
            if (! $this->db->transStatus()) throw new \RuntimeException('Percobaan gagal dibuat.');
            $this->db->transCommit();
            $closed = true;
            return $this->detail($this->db->table('attempts')->where('id', $attemptId)->get()->getRowArray());
        } catch (\Throwable $error) {
            if (! $closed) $this->db->transRollback();
            throw $error;
        }
    }

    public function find(int $school, int $student, int $id): ?array
    {
        return $this->db->table('attempts')->where('id', $id)->where('school_id', $school)->where('student_id', $student)->get()->getRowArray();
    }

    public function expireDue(): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $ids = array_column($this->db->table('attempts')->select('id')->where('status', 'active')->where('deadline_at <=', $now)->get()->getResultArray(), 'id');
        if ($ids !== []) $this->db->table('attempts')->whereIn('id', $ids)->where('status', 'active')->update(['status' => 'expired', 'submitted_at' => $now, 'updated_at' => $now]);
        foreach ($ids as $id) (new ResultService($this->db))->gradeAttempt($this->db->table('attempts')->where('id', $id)->get()->getRowArray());
        return count($ids);
    }

    public function expireOne(array $attempt): array
    {
        if ($attempt['status'] === 'active' && $attempt['deadline_at'] <= gmdate('Y-m-d H:i:s')) {
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('attempts')->where('id', $attempt['id'])->where('status', 'active')->update(['status' => 'expired', 'submitted_at' => $now, 'updated_at' => $now]);
            $expired = $this->db->table('attempts')->where('id', $attempt['id'])->get()->getRowArray();
            (new ResultService($this->db))->gradeAttempt($expired);
            return $expired;
        }
        return $attempt;
    }

    public function saveAnswer(int $school, int $student, int $attemptId, int $itemId, string $device, array $input): array
    {
        if (array_diff(array_keys($input), ['expectedRevision', 'selectedOptionId', 'essayText', 'answer', 'marked', 'mutationId']) !== []
            || ! is_int($input['expectedRevision'] ?? null) || $input['expectedRevision'] < 0
            || ! is_bool($input['marked'] ?? null)
            || ! is_string($input['mutationId'] ?? null)
            || ! preg_match('/^[a-f0-9-]{36}$/i', $input['mutationId'])
            || (array_key_exists('selectedOptionId', $input) && $input['selectedOptionId'] !== null && (! is_int($input['selectedOptionId']) || $input['selectedOptionId'] < 1))
            || (array_key_exists('essayText', $input) && $input['essayText'] !== null && (! is_string($input['essayText']) || mb_strlen($input['essayText']) > 20000))) {
            throw new \InvalidArgumentException('Revisi, pilihan, penanda, atau ID mutasi tidak valid.');
        }
        if (! array_key_exists('selectedOptionId', $input)) throw new \InvalidArgumentException('Pilihan wajib dinyatakan, boleh kosong.');
        $this->db->transBegin();
        $closed = false;
        try {
            $attempt = $this->db->query('SELECT * FROM attempts WHERE id = ? AND school_id = ? AND student_id = ? FOR UPDATE', [$attemptId, $school, $student])->getRowArray();
            if ($attempt === null) throw new \OutOfBoundsException('Percobaan tidak ditemukan.');
            if (! $this->deviceAllowed($attempt, $device)) throw new \DomainException('Percobaan aktif pada perangkat lain.');
            $prior = $this->db->table('attempt_mutations')->where('attempt_id', $attemptId)->where('mutation_id', $input['mutationId'])->get()->getRowArray();
            if ($prior !== null) {
                $this->db->transCommit();
                $closed = true;
                return json_decode($prior['response_json'], true, 512, JSON_THROW_ON_ERROR);
            }
            $now = gmdate('Y-m-d H:i:s');
            if ($attempt['status'] !== 'active') throw new \DomainException('Percobaan sudah selesai.');
            if ($attempt['deadline_at'] <= $now) {
                $this->db->table('attempts')->where('id', $attemptId)->update(['status' => 'expired', 'submitted_at' => $now, 'updated_at' => $now]);
                (new ResultService($this->db))->gradeAttempt($this->db->table('attempts')->where('id', $attemptId)->get()->getRowArray());
                $this->db->transCommit();
                $closed = true;
                throw new \DomainException('Waktu ujian habis.');
            }
            $item = $this->db->query('SELECT ai.*, ei.snapshot_json FROM attempt_items ai JOIN exam_items ei ON ei.id=ai.exam_item_id WHERE ai.id = ? AND ai.attempt_id = ? FOR UPDATE', [$itemId, $attemptId])->getRowArray();
            if ($item === null) throw new \OutOfBoundsException('Soal tidak ditemukan.');
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $type = $snapshot['type'] ?? 'mcq';
            if ($type === 'essay' && ($input['selectedOptionId'] !== null || ! array_key_exists('essayText', $input))) throw new \InvalidArgumentException('Jawaban esai tidak valid.');
            if ($type === 'mcq' && ! empty($input['essayText'])) throw new \InvalidArgumentException('Jawaban pilihan ganda tidak valid.');
            $structured = in_array($type, ['multi_mcq', 'true_false', 'matching'], true);
            if ($structured && ($input['selectedOptionId'] !== null || ! empty($input['essayText']) || ! array_key_exists('answer', $input))) throw new \InvalidArgumentException('Jawaban terstruktur tidak valid.');
            if (! $structured && array_key_exists('answer', $input)) throw new \InvalidArgumentException('Jenis jawaban tidak sesuai soal.');
            $answer = $structured ? StructuredAnswer::normalize($type, $input['answer'], $snapshot['options'], $snapshot['matching_choices'] ?? []) : null;
            $essayText = $type === 'essay' ? trim(strip_tags((string) $input['essayText'])) : null;
            $validIds = array_map('intval', array_column($snapshot['options'], 'id'));
            if ($input['selectedOptionId'] !== null && ! in_array($input['selectedOptionId'], $validIds, true)) throw new \InvalidArgumentException('Pilihan tidak termasuk soal.');
            if ((int) $item['revision'] !== $input['expectedRevision']) throw new \DomainException('Jawaban berubah pada permintaan lain. Muat ulang untuk mendapatkan revisi terbaru.');
            $newRevision = (int) $item['revision'] + 1;
            $this->db->table('attempt_items')->where('id', $itemId)->update(['selected_option_id' => $input['selectedOptionId'], 'essay_text' => $essayText, 'answer_json' => $answer === null ? null : json_encode($answer, JSON_THROW_ON_ERROR), 'marked' => $input['marked'] ? 1 : 0, 'revision' => $newRevision, 'saved_at' => $now, 'updated_at' => $now]);
            $attemptRevision = (int) $attempt['revision'] + 1;
            $this->db->table('attempts')->where('id', $attemptId)->update(['revision' => $attemptRevision, 'last_seen_at' => $now, 'updated_at' => $now]);
            $result = ['itemId' => $itemId, 'revision' => $newRevision, 'attemptRevision' => $attemptRevision, 'selectedOptionId' => $input['selectedOptionId'], 'essayText' => $essayText, 'answer' => $answer, 'marked' => $input['marked'], 'savedAt' => $now];
            $this->db->table('attempt_mutations')->insert(['attempt_id' => $attemptId, 'attempt_item_id' => $itemId, 'mutation_id' => $input['mutationId'], 'response_json' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Jawaban gagal disimpan.');
            $this->db->transCommit();
            $closed = true;
            return $result;
        } catch (\Throwable $error) {
            if (! $closed) $this->db->transRollback();
            throw $error;
        }
    }

    public function submit(int $school, int $student, int $id, string $device): array
    {
        $this->db->transBegin();
        try {
            $attempt = $this->db->query('SELECT * FROM attempts WHERE id = ? AND school_id = ? AND student_id = ? FOR UPDATE', [$id, $school, $student])->getRowArray();
            if ($attempt === null) throw new \OutOfBoundsException('Percobaan tidak ditemukan.');
            if (! $this->deviceAllowed($attempt, $device)) throw new \DomainException('Percobaan aktif pada perangkat lain.');
            if ($attempt['status'] === 'active') {
                $now = gmdate('Y-m-d H:i:s');
                $status = $attempt['deadline_at'] <= $now ? 'expired' : 'submitted';
                if ($status === 'submitted') {
                    $required = $this->db->table('exams')->select('require_all_answers')->where('id', $attempt['exam_id'])->where('school_id', $school)->get()->getRowArray();
                    if ($required !== null && (int) $required['require_all_answers'] === 1) {
                        $missing = $this->db->table('attempt_items')->where('attempt_id', $id)->where('selected_option_id', null)->where('answer_json', null)->groupStart()->where('essay_text', null)->orWhere('essay_text', '')->groupEnd()->countAllResults();
                        if ($missing > 0) throw new \DomainException('Jawab semua soal sebelum mengumpulkan ujian. Masih ada ' . $missing . ' soal kosong.');
                    }
                }
                $this->db->table('attempts')->where('id', $id)->update(['status' => $status, 'submitted_at' => $now, 'updated_at' => $now]);
                $attempt['status'] = $status; $attempt['submitted_at'] = $now;
            }
            (new ResultService($this->db))->gradeAttempt($attempt);
            $unanswered = $this->db->table('attempt_items')->where('attempt_id', $id)->where('selected_option_id', null)->where('answer_json', null)->groupStart()->where('essay_text', null)->orWhere('essay_text', '')->groupEnd()->countAllResults();
            if (! $this->db->transStatus()) throw new \RuntimeException('Pengumpulan gagal.');
            $this->db->transCommit();
            return ['id' => (int) $id, 'status' => $attempt['status'], 'submittedAt' => $attempt['submitted_at'], 'unansweredCount' => $unanswered];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function detail(array $attempt): array
    {
        $exam = $this->db->table('exams e')->select('e.title AS examTitle, s.name AS subjectName')
            ->join('subjects s', 's.id=e.subject_id')->where('e.id', $attempt['exam_id'])->get()->getRowArray();
        $categories = [];
        foreach ($this->db->table('exam_items ei')->select('ei.id, qc.name AS category_name')
            ->join('questions q', 'q.id=ei.source_question_id', 'left')
            ->join('question_topics qt', 'qt.id=q.topic_id', 'left')
            ->join('question_categories qc', 'qc.id=qt.category_id', 'left')
            ->where('ei.exam_id', $attempt['exam_id'])->get()->getResultArray() as $row) {
            $categories[(int) $row['id']] = $row['category_name'];
        }
        $items = $this->db->table('attempt_items ai')->select('ai.id, ai.display_order, ai.option_order_json, ai.matching_choice_order_json, ai.selected_option_id, ai.essay_text, ai.answer_json, ai.marked, ai.revision, ei.id AS exam_item_id, ei.snapshot_json')
            ->join('exam_items ei', 'ei.id=ai.exam_item_id')->where('ai.attempt_id', $attempt['id'])->orderBy('ai.display_order')->get()->getResultArray();
        $safe = [];
        foreach ($items as $item) {
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $byId = [];
            foreach ($snapshot['options'] as $option) $byId[(int) $option['id']] = ['id' => (int) $option['id'], 'body' => $option['body']];
            $ordered = [];
            foreach (json_decode($item['option_order_json'], true, 512, JSON_THROW_ON_ERROR) as $optionId) $ordered[] = $byId[(int) $optionId];
            $choices = $snapshot['matching_choices'] ?? [];
            if ($choices === [] && ($snapshot['type'] ?? '') === 'matching') {
                foreach ($snapshot['options'] as $option) $choices[] = ['id' => (int) $option['id'], 'body' => $option['match_body'] ?? ''];
            }
            if ($item['matching_choice_order_json'] !== null && $item['matching_choice_order_json'] !== '') {
                $choiceById = [];
                foreach ($choices as $choice) $choiceById[(int) $choice['id']] = ['id' => (int) $choice['id'], 'body' => $choice['body']];
                $choices = array_map(static fn ($id) => $choiceById[(int) $id], json_decode($item['matching_choice_order_json'], true, 512, JSON_THROW_ON_ERROR));
            }
            $safe[] = ['id' => (int) $item['id'], 'order' => (int) $item['display_order'], 'body' => $snapshot['body'], 'type' => $snapshot['type'] ?? 'mcq', 'categoryName' => $categories[(int) $item['exam_item_id']] ?? null, 'options' => $ordered,
                'choices' => $choices,
                'assets' => $snapshot['assets'], 'selectedOptionId' => $item['selected_option_id'] === null ? null : (int) $item['selected_option_id'],
                'essayText' => $item['essay_text'], 'answer' => $item['answer_json'] === null ? null : json_decode($item['answer_json'], true, 512, JSON_THROW_ON_ERROR),
                'marked' => (bool) $item['marked'], 'revision' => (int) $item['revision']];
        }
        return ['id' => (int) $attempt['id'], 'examId' => (int) $attempt['exam_id'], 'examTitle' => $exam['examTitle'] ?? 'Ujian', 'subjectName' => $exam['subjectName'] ?? '', 'packageCode' => $attempt['package_code'] ?? 'A', 'status' => $attempt['status'],
            'startedAt' => $attempt['started_at'], 'deadlineAt' => $attempt['deadline_at'], 'submittedAt' => $attempt['submitted_at'],
            'revision' => (int) $attempt['revision'], 'serverNow' => gmdate('Y-m-d H:i:s'), 'items' => $safe];
    }
}
