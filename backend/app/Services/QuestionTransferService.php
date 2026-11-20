<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionTransferService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function ownedSubjects(int $school, int $actor, bool $admin): array
    {
        $subjects = $this->db->table('subjects')->select('id, code')->where('school_id', $school)->where('active', 1)->get()->getResultArray();
        if ($admin) return array_column($subjects, 'id', 'code');
        $bank = new QuestionBank($this->db);
        return array_column(array_filter($subjects, static fn ($subject) => $bank->teacherCanUseSubject($school, $actor, (int) $subject['id'])), 'id', 'code');
    }

    public function validate(int $school, int $actor, bool $admin, array $payload, bool $allowDuplicates = false): array
    {
        if (array_diff(array_keys($payload), ['schema', 'version', 'mode', 'questions']) !== []
            || $payload['schema'] !== 'maqna-cbt.questions' || ! in_array($payload['version'], [1, 2], true)
            || ! in_array($payload['mode'] ?? 'reject', ['reject', 'skip'], true)
            || ! is_array($payload['questions']) || ! array_is_list($payload['questions'])
            || count($payload['questions']) < 1 || count($payload['questions']) > 500) throw new \DomainException('Skema JSON soal tidak didukung.');
        $subjects = $this->ownedSubjects($school, $actor, $admin);
        $seen = []; $rows = [];
        foreach ($payload['questions'] as $index => $question) {
            if (! is_array($question) || array_diff(array_keys($question), ['subjectCode', 'body', 'type', 'weight', 'rubric', 'explanation', 'options', 'matchingChoices']) !== []
                || ! is_string($question['subjectCode'] ?? null) || ! isset($subjects[$question['subjectCode']]) || ! is_string($question['body'] ?? null)
                || ! in_array($question['type'] ?? null, ['mcq', 'essay', 'multi_mcq', 'true_false', 'matching'], true)
                || ! is_numeric($question['weight'] ?? null) || (float) $question['weight'] <= 0 || (float) $question['weight'] > 1000)
                throw new \DomainException('Baris ' . ($index + 1) . ': mata pelajaran atau soal tidak valid.');
            foreach (['rubric', 'explanation'] as $field) if (isset($question[$field]) && ! is_string($question[$field])) throw new \DomainException('Baris ' . ($index + 1) . ': teks tidak valid.');
            if (! is_array($question['options'] ?? null) || ! array_is_list($question['options']) || count($question['options']) > 12) throw new \DomainException('Baris ' . ($index + 1) . ': opsi tidak valid.');
            foreach ($question['options'] as $option) if (! is_array($option) || array_diff(array_keys($option), ['body', 'isCorrect', 'matchBody', 'matchChoiceIndex']) !== [] || ! is_string($option['body'] ?? null) || ! is_bool($option['isCorrect'] ?? null) || (isset($option['matchBody']) && ! is_string($option['matchBody'])) || (isset($option['matchChoiceIndex']) && ! is_int($option['matchChoiceIndex']))) throw new \DomainException('Baris ' . ($index + 1) . ': opsi tidak valid.');
            $options = $question['options'];
            $choices = $question['matchingChoices'] ?? [];
            if ($question['type'] === 'matching' && $choices === []) {
                $choices = [];
                foreach ($options as $optionIndex => $option) {
                    if (! is_string($option['matchBody'] ?? null)) continue;
                    $choices[] = ['body' => $option['matchBody']];
                    $options[$optionIndex]['matchChoiceIndex'] = count($choices) - 1;
                }
            }
            if (! is_array($choices) || ! array_is_list($choices) || count($choices) > 12) throw new \DomainException('Baris ' . ($index + 1) . ': pilihan pasangan tidak valid.');
            foreach ($choices as $choice) if (! is_array($choice) || array_diff(array_keys($choice), ['body']) !== [] || ! is_string($choice['body'] ?? null)) throw new \DomainException('Baris ' . ($index + 1) . ': pilihan pasangan tidak valid.');
            $correct = count(array_filter($options, static fn ($option) => $option['isCorrect']));
            if ($question['type'] === 'mcq' && (count($options) < 2 || $correct !== 1)
                || $question['type'] === 'multi_mcq' && (count($options) < 3 || $correct < 2 || $correct === count($options))
                || in_array($question['type'], ['true_false', 'matching'], true) && count($options) < 2
                || $question['type'] === 'essay' && ($options !== [] || trim((string) ($question['rubric'] ?? '')) === '')
                || $question['type'] === 'matching' && (count($choices) < count($options)
                    || in_array('', array_map(static fn ($choice) => trim(strip_tags($choice['body'])), $choices), true)
                    || count(array_unique(array_map(static fn ($choice) => trim(strip_tags($choice['body'])), $choices))) !== count($choices)
                    || in_array(null, array_column($options, 'matchChoiceIndex'), true)
                    || count(array_unique(array_column($options, 'matchChoiceIndex'))) !== count($options)
                    || array_diff(array_column($options, 'matchChoiceIndex'), array_keys($choices)) !== [])) throw new \DomainException('Baris ' . ($index + 1) . ': kunci atau pasangan tidak valid.');
            $body = QuestionContent::sanitize($question['body']);
            $key = $subjects[$question['subjectCode']] . ':' . mb_strtolower($body);
            $duplicate = isset($seen[$key]);
            if ($duplicate && ! $allowDuplicates) throw new \DomainException('Baris ' . ($index + 1) . ': duplikat dalam berkas.');
            $seen[$key] = true;
            $existing = $this->db->table('questions')->where('school_id', $school)->where('subject_id', $subjects[$question['subjectCode']])->where('body', $body)->where('deleted_at', null)->get()->getRowArray();
            if ($existing !== null && ($payload['mode'] ?? 'reject') === 'reject') throw new \DomainException('Baris ' . ($index + 1) . ': soal sudah ada.');
            $rows[] = ['rowNumber' => $index + 1, 'subjectCode' => $question['subjectCode'], 'type' => $question['type'], 'body' => mb_substr($body, 0, 160), 'status' => $existing === null ? 'ready' : 'skip'];
        }
        return $rows;
    }

    public function preview(int $school, int $actor, bool $admin, array $payload): array
    {
        $rows = $this->validate($school, $actor, $admin, $payload);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('question_transfer_jobs')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'status' => 'preview',
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR), 'row_count' => count($rows), 'created_at' => $now]);
        return ['id' => (int) $this->db->insertID(), 'rows' => $rows, 'ready' => count(array_filter($rows, static fn ($row) => $row['status'] === 'ready')),
            'skipped' => count(array_filter($rows, static fn ($row) => $row['status'] === 'skip'))];
    }

    public function commit(int $school, int $actor, bool $admin, int $id): array
    {
        $this->db->transBegin();
        try {
            $job = $this->db->query('SELECT * FROM question_transfer_jobs WHERE id=? AND school_id=? AND actor_user_id=? FOR UPDATE', [$id, $school, $actor])->getRowArray();
            if ($job === null) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            if ($job['status'] === 'committed') { $this->db->transCommit(); return ['id' => $id, 'status' => 'committed']; }
            $payload = json_decode($job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            $rows = $this->validate($school, $actor, $admin, $payload);
            $subjects = $this->ownedSubjects($school, $actor, $admin);
            $created = 0;
            foreach ($rows as $index => $row) {
                if ($row['status'] === 'skip') continue;
                $source = $payload['questions'][$index];
                (new QuestionBank($this->db))->save($school, $actor, ['subjectId' => (int) $subjects[$source['subjectCode']],
                    'body' => $source['body'], 'questionType' => $source['type'], 'weight' => (float) $source['weight'],
                    'rubric' => $source['rubric'] ?? '', 'explanation' => $source['explanation'] ?? '', 'matchingChoices' => $source['matchingChoices'] ?? [], 'options' => $source['options'], 'status' => 'draft'], null, $admin);
                $created++;
            }
            $this->db->table('question_transfer_jobs')->where('id', $id)->update(['status' => 'committed', 'committed_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($this->db))->record($school, $actor, 'questions.json_import', 'question_transfer_job', $id, ['created' => $created]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Impor JSON gagal.');
            $this->db->transCommit();
            return ['id' => $id, 'status' => 'committed', 'created' => $created];
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }

    public function export(int $school, int $actor, bool $admin): array
    {
        $builder = $this->db->table('questions q')->select('q.id, q.body, q.question_type, q.weight, q.rubric, q.explanation, s.code AS subject_code')
            ->join('subjects s', 's.id=q.subject_id')->where('q.school_id', $school)->where('q.deleted_at', null);
        if (! $admin) $builder->where('q.author_user_id', $actor);
        if ($builder->countAllResults(false) > 500) throw new \DomainException('Ekspor JSON dibatasi 500 soal. Gunakan arsip per mata pelajaran.');
        $questions = [];
        foreach ($builder->orderBy('q.id')->limit(500)->get()->getResultArray() as $row) {
            $options = $this->db->table('question_options')->select('body, match_body, is_correct, match_choice_id')->where('question_id', $row['id'])->orderBy('sort_order')->get()->getResultArray();
            $optionsPayload = array_map(static fn ($option) => ['body' => $option['body'], 'isCorrect' => (bool) $option['is_correct']], $options);
            $matchingChoices = [];
            if ($row['question_type'] === 'matching') {
                $matchingChoices = $this->db->table('question_matching_choices')->select('id, body')->where('question_id', $row['id'])->orderBy('sort_order')->get()->getResultArray();
                $indexes = array_flip(array_map('intval', array_column($matchingChoices, 'id')));
                foreach ($options as $index => $option) $optionsPayload[$index]['matchChoiceIndex'] = $indexes[(int) ($option['match_choice_id'] ?? 0)] ?? null;
                $matchingChoices = array_map(static fn ($choice) => ['body' => $choice['body']], $matchingChoices);
            }
            $questions[] = ['subjectCode' => $row['subject_code'], 'body' => $row['body'], 'type' => $row['question_type'],
                'weight' => (float) $row['weight'], 'rubric' => $row['rubric'] ?? '', 'explanation' => $row['explanation'] ?? '',
                'matchingChoices' => $matchingChoices, 'options' => $optionsPayload];
        }
        return ['schema' => 'maqna-cbt.questions', 'version' => 2, 'mode' => 'skip', 'questions' => $questions];
    }
}
