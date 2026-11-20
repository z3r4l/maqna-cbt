<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionBank
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function teacherCanUseSubject(int $school, int $userId, int $subjectId): bool
    {
        return $this->db->table('teaching_assignments ta')->join('teachers t', 't.id=ta.teacher_id')
            ->join('semesters se', 'se.id=ta.semester_id')->join('academic_years ay', 'ay.id=se.academic_year_id')
            ->where('ta.school_id', $school)->where('t.user_id', $userId)->where('ta.subject_id', $subjectId)
            ->where('ta.active', 1)->where('se.active', 1)->where('ay.active', 1)->countAllResults() > 0;
    }

    public function find(int $school, int $id): ?array
    {
        return $this->db->table('questions')->where('school_id', $school)->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
    }

    public function full(array $question): array
    {
        $question['options'] = $this->db->table('question_options')->select('id, sort_order, body, match_body, match_choice_id, is_correct')
            ->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
        $question['matching_choices'] = $this->db->table('question_matching_choices')->select('id, sort_order, body')
            ->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
        $question['assets'] = $this->db->table('question_assets')->select('id, original_name, mime_type, size_bytes')
            ->where('question_id', $question['id'])->where('archived_at', null)->orderBy('id')->get()->getResultArray();
        return $question;
    }

    public function list(int $school, ?int $author, int $page, int $perPage, string $search, ?int $subject, ?int $bankId = null): array
    {
        $page = max(1, $page); $perPage = max(1, min(100, $perPage));
        $builder = $this->db->table('questions q')->select('q.id, q.school_id, q.subject_id, q.topic_id, q.author_user_id, q.body, q.question_type, q.weight, q.status, q.created_at, s.name AS subject_name')
            ->join('subjects s', 's.id=q.subject_id')->where('q.school_id', $school)->where('q.deleted_at', null);
        if ($author !== null) $builder->where('q.author_user_id', $author);
        if ($subject !== null) $builder->where('q.subject_id', $subject);
        if ($bankId !== null) $builder->where('q.bank_id', $bankId);
        if ($search !== '') $builder->like('q.body', mb_substr($search, 0, 80));
        $total = $builder->countAllResults(false);
        return ['rows' => $builder->orderBy('q.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray(), 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }

    private function plain(mixed $value, int $max, bool $required = true): ?string
    {
        if ($value === null && ! $required) return null;
        if (! is_string($value)) throw new \DomainException('Teks harus berupa string.');
        $clean = trim(strip_tags($value));
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $clean);
        if ($clean === '' && $required || mb_strlen($clean) > $max) throw new \DomainException('Teks kosong atau terlalu panjang.');
        return $clean;
    }

    public function save(int $school, int $author, array $input, ?int $id, bool $admin): array
    {
        if (array_diff(array_keys($input), ['bankId', 'subjectId', 'topicId', 'body', 'explanation', 'rubric', 'questionType', 'weight', 'options', 'matchingChoices', 'status']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $old = $id === null ? null : $this->find($school, $id);
        if ($id !== null && $old === null) throw new \OutOfBoundsException('Soal tidak ditemukan.');
        if ($old !== null && ! $admin && (int) $old['author_user_id'] !== $author) throw new \OutOfBoundsException('Soal tidak ditemukan.');
        if ($old !== null && $old['status'] === 'published') throw new \DomainException('Soal terbit tidak dapat diubah; revisi tersedia pada S7.');
        $subjectId = (int) ($input['subjectId'] ?? $old['subject_id'] ?? 0);
        $subject = $this->db->table('subjects')->where('id', $subjectId)->where('school_id', $school)->where('active', 1)->get()->getRowArray();
        if ($subject === null || (! $admin && ! $this->teacherCanUseSubject($school, $author, $subjectId))) throw new \DomainException('Mata pelajaran di luar penugasan.');
        if (array_key_exists('bankId', $input) && filter_var($input['bankId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new \DomainException('Bank soal tidak valid.');
        $bankId = array_key_exists('bankId', $input) ? (int) $input['bankId'] : (int) ($old['bank_id'] ?? 0);
        if ($bankId > 0) {
            $bank = (new QuestionBankContainer($this->db))->find($school, $bankId, $admin ? null : $author);
            if ($bank === null || (int) $bank['subject_id'] !== $subjectId) throw new \DomainException('Bank soal tidak sesuai mata pelajaran atau di luar akses.');
        }
        if ($old !== null && $old['status'] === 'published' && $bankId !== (int) $old['bank_id']) throw new \DomainException('Soal terbit tidak dapat dipindah bank.');
        $topicId = array_key_exists('topicId', $input) ? ($input['topicId'] === null ? null : (int) $input['topicId']) : ($old['topic_id'] ?? null);
        if ($topicId !== null) {
            $topic = $this->db->table('question_topics qt')->join('question_categories qc', 'qc.id=qt.category_id')
                ->where('qt.id', $topicId)->where('qt.school_id', $school)->where('qc.subject_id', $subjectId)->get()->getRowArray();
            if ($topic === null) throw new \DomainException('Topik tidak sesuai mata pelajaran.');
        }
        $bodyInput = $input['body'] ?? $old['body'] ?? '';
        if (! is_string($bodyInput)) throw new \DomainException('Teks soal tidak valid.');
        $body = QuestionContent::sanitize($bodyInput, 20000);
        $explanation = $this->plain($input['explanation'] ?? $old['explanation'] ?? null, 20000, false);
        $rubric = $this->plain($input['rubric'] ?? $old['rubric'] ?? null, 20000, false);
        $type = $input['questionType'] ?? $old['question_type'] ?? 'mcq';
        if (! in_array($type, ['mcq', 'essay', 'multi_mcq', 'true_false', 'matching'], true)) throw new \DomainException('Jenis soal tidak valid.');
        if ($type === 'essay' && ($rubric === null || $rubric === '')) throw new \DomainException('Soal esai memerlukan rubrik penilaian.');
        $weight = $input['weight'] ?? $old['weight'] ?? 1;
        if (! is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 1000) throw new \DomainException('Bobot harus lebih dari nol dan paling banyak 1000.');
        $status = $input['status'] ?? $old['status'] ?? 'draft';
        if (! in_array($status, ['draft', 'published'], true)) throw new \DomainException('Status tidak valid.');
        $matchingChoices = $input['matchingChoices'] ?? null;
        if ($type === 'matching' && $matchingChoices === null && isset($input['options']) && is_array($input['options'])) {
            $matchingChoices = [];
            foreach ($input['options'] as $index => $option) {
                if (! is_string($option['matchBody'] ?? null)) continue;
                $matchingChoices[] = ['body' => $option['matchBody']];
                $input['options'][$index]['matchChoiceIndex'] = count($matchingChoices) - 1;
            }
        }
        if ($type !== 'matching') $matchingChoices = [];
        if ($matchingChoices !== null) {
            if (! is_array($matchingChoices) || ! array_is_list($matchingChoices) || count($matchingChoices) > 12) throw new \DomainException('Pilihan pasangan harus berupa daftar maksimal 12.');
            foreach ($matchingChoices as &$choice) {
                if (! is_array($choice) || array_diff(array_keys($choice), ['body']) !== [] || ! is_string($choice['body'] ?? null)) throw new \DomainException('Pilihan pasangan tidak valid.');
                $value = trim($choice['body']);
                $choice['body'] = $value === '' && $status === 'draft' ? '' : QuestionContent::sanitize($value, 5000);
            }
            unset($choice);
        }
        $options = $input['options'] ?? null;
        if ($id === null && $options === null) $options = [];
        if ($options !== null) {
            if (! is_array($options) || count($options) > 12 || ! array_is_list($options)) throw new \DomainException('Opsi harus berupa daftar maksimal 12.');
            $clean = [];
            foreach ($options as $option) {
                if (! is_array($option) || array_diff(array_keys($option), ['body', 'isCorrect', 'matchBody', 'matchChoiceIndex']) !== []) throw new \DomainException('Opsi tidak valid.');
                if (! is_string($option['body'] ?? null)) throw new \DomainException('Teks opsi tidak valid.');
                $bodyText = trim($option['body']);
                $matchIndex = $type === 'matching' ? ($option['matchChoiceIndex'] ?? null) : null;
                if ($type === 'matching' && $matchIndex !== null && (! is_int($matchIndex) || $matchIndex < 0 || $matchIndex >= count($matchingChoices ?? []))) throw new \DomainException('Kunci pasangan tidak valid.');
                $clean[] = [
                    'body' => $bodyText === '' && $status === 'draft' ? '' : QuestionContent::sanitize($option['body'], 5000),
                    'match_choice_index' => $matchIndex,
                    'is_correct' => ! empty($option['isCorrect']) ? 1 : 0,
                ];
            }
            $options = $clean;
        }
        if ($status === 'published' && $type === 'mcq') {
            $candidate = $options ?? $this->db->table('question_options')->where('question_id', $id)->get()->getResultArray();
            if (in_array('', array_map(static fn ($option) => trim(strip_tags($option['body'])), $candidate), true)) throw new \DomainException('Semua opsi soal terbit harus diisi.');
            if (count($candidate) < 2 || array_sum(array_column($candidate, 'is_correct')) !== 1) throw new \DomainException('Soal terbit memerlukan sedikitnya dua opsi dan tepat satu jawaban benar.');
        }
        if ($status === 'published' && in_array($type, ['multi_mcq', 'true_false', 'matching'], true)) {
            $candidate = $options ?? $this->db->table('question_options')->where('question_id', $id)->get()->getResultArray();
            if (in_array('', array_map(static fn ($option) => trim(strip_tags($option['body'])), $candidate), true)) throw new \DomainException('Semua baris soal terbit harus diisi.');
            if (count($candidate) < 2) throw new \DomainException('Soal terbit memerlukan sedikitnya dua baris.');
            if ($type === 'multi_mcq' && (array_sum(array_column($candidate, 'is_correct')) < 2 || array_sum(array_column($candidate, 'is_correct')) >= count($candidate))) throw new \DomainException('Pilihan jamak memerlukan sedikitnya dua kunci dan satu pengecoh.');
            if ($type === 'matching') {
                $choiceBodies = array_map(static fn ($choice) => trim(strip_tags($choice['body'])), $matchingChoices ?? []);
                $indexes = array_column($candidate, 'match_choice_index');
                if (count($choiceBodies) < count($candidate) || count($choiceBodies) > 12 || in_array('', $choiceBodies, true)
                    || count(array_unique($choiceBodies)) !== count($choiceBodies)
                    || in_array(null, $indexes, true) || count(array_unique($indexes)) !== count($candidate)) {
                    throw new \DomainException('Setiap pernyataan harus memiliki pasangan unik dan pilihan kanan harus lengkap.');
                }
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if ($bankId < 1) $bankId = (new QuestionBankContainer($this->db))->defaultFor($school, $author, $subjectId);
            $row = ['school_id' => $school, 'subject_id' => $subjectId, 'bank_id' => $bankId, 'topic_id' => $topicId, 'body' => $body, 'question_type' => $type, 'explanation' => $explanation, 'rubric' => $rubric, 'weight' => $weight, 'status' => $status, 'updated_at' => $now];
            if ($id === null) {
                $this->db->table('questions')->insert($row + ['author_user_id' => $author, 'created_at' => $now]);
                $id = (int) $this->db->insertID();
            } else $this->db->table('questions')->where('id', $id)->update($row);
            $contentRows = [$body];
            if ($options !== null) foreach ($options as $option) $contentRows[] = $option['body'];
            foreach ($matchingChoices ?? [] as $choice) $contentRows[] = $choice['body'];
            $references = (new QuestionMedia($this->db))->bind($school, $author, $id, $contentRows);
            $contentRows = QuestionMedia::replace($contentRows, $references);
            $body = QuestionContent::sanitize($contentRows[0], 20000);
            $this->db->table('questions')->where('id', $id)->update(['body' => $body, 'updated_at' => $now]);
            $contentIndex = 1;
            if ($options !== null) foreach ($options as $index => $option) {
                $value = $contentRows[$contentIndex++];
                $options[$index]['body'] = $value === '' && $status === 'draft' ? '' : QuestionContent::sanitize($value, 5000);
            }
            foreach ($matchingChoices ?? [] as $index => $_) {
                $value = $contentRows[$contentIndex++];
                $matchingChoices[$index]['body'] = $value === '' && $status === 'draft' ? '' : QuestionContent::sanitize($value, 5000);
            }
            if ($options !== null) {
                $this->db->table('question_options')->where('question_id', $id)->delete();
                $this->db->table('question_matching_choices')->where('question_id', $id)->delete();
                $choiceIds = [];
                foreach ($matchingChoices ?? [] as $index => $choice) {
                    $this->db->table('question_matching_choices')->insert(['question_id' => $id, 'sort_order' => $index + 1, 'body' => $choice['body'], 'created_at' => $now, 'updated_at' => $now]);
                    $choiceIds[] = (int) $this->db->insertID();
                }
                foreach ($options as $index => $option) {
                    $choiceId = $option['match_choice_index'] === null ? null : $choiceIds[$option['match_choice_index']];
                    $this->db->table('question_options')->insert([
                        'question_id' => $id, 'sort_order' => $index + 1, 'body' => $option['body'],
                        'match_body' => $choiceId === null ? null : $matchingChoices[$option['match_choice_index']]['body'],
                        'match_choice_id' => $choiceId, 'is_correct' => $option['is_correct'], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan soal gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->full($this->find($school, $id));
    }

    public function archive(int $school, int $id): void
    {
        $this->db->table('questions')->where('school_id', $school)->where('id', $id)
            ->update(['status' => 'archived', 'deleted_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }
}
