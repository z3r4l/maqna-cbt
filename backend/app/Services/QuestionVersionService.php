<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionVersionService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function source(int $school, int $questionId, int $actor, bool $admin): array
    {
        $question = $this->db->table('questions')->where('id', $questionId)->where('school_id', $school)->where('deleted_at', null)->get()->getRowArray();
        if ($question === null || (! $admin && (int) $question['author_user_id'] !== $actor)) throw new \OutOfBoundsException('Soal tidak ditemukan.');
        return $question;
    }

    private function snapshot(array $question): array
    {
        $choices = $this->db->table('question_matching_choices')->select('id, body')->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
        $choiceIndexes = array_flip(array_map('intval', array_column($choices, 'id')));
        $options = $this->db->table('question_options')->select('body, match_body, match_choice_id, is_correct')->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
        return ['body' => $question['body'], 'type' => $question['question_type'], 'rubric' => $question['rubric'],
            'explanation' => $question['explanation'], 'weight' => (float) $question['weight'],
            'matchingChoices' => array_map(static fn ($choice) => ['body' => $choice['body']], $choices),
            'options' => array_map(static function ($option) use ($choiceIndexes): array {
                $matchChoiceId = (int) ($option['match_choice_id'] ?? 0);
                return ['body' => $option['body'], 'matchBody' => $option['match_body'],
                    'matchChoiceIndex' => $choiceIndexes[$matchChoiceId] ?? null, 'isCorrect' => (bool) $option['is_correct']];
            }, $options)];
    }

    private function cleanText(mixed $value, int $max, bool $required = false): string
    {
        if (! is_string($value)) throw new \DomainException('Teks tidak valid.');
        $clean = trim(strip_tags($value));
        if (($required && $clean === '') || mb_strlen($clean) > $max) throw new \DomainException('Teks kosong atau terlalu panjang.');
        return $clean;
    }

    private function validate(array $input): array
    {
        if (array_diff(array_keys($input), ['body', 'type', 'rubric', 'explanation', 'weight', 'options', 'matchingChoices']) !== []) throw new \DomainException('Kolom revisi tidak valid.');
        $type = $input['type'] ?? null;
        if (! in_array($type, ['mcq', 'essay', 'multi_mcq', 'true_false', 'matching'], true)) throw new \DomainException('Jenis soal tidak valid.');
        if (! is_numeric($input['weight'] ?? null) || (float) $input['weight'] <= 0 || (float) $input['weight'] > 1000) throw new \DomainException('Bobot tidak valid.');
        if (! is_string($input['body'] ?? null)) throw new \DomainException('Teks soal tidak valid.');
        $body = QuestionContent::sanitize($input['body'], 20000);
        $rubric = $this->cleanText($input['rubric'] ?? '', 20000);
        $explanation = $this->cleanText($input['explanation'] ?? '', 20000);
        $matchingChoices = $input['matchingChoices'] ?? [];
        if ($type === 'matching' && $matchingChoices === [] && isset($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                if (! is_string($option['matchBody'] ?? null)) continue;
                $matchingChoices[] = ['body' => $option['matchBody']];
                $input['options'][$index]['matchChoiceIndex'] = count($matchingChoices) - 1;
            }
        }
        if ($type !== 'matching') $matchingChoices = [];
        if (! is_array($matchingChoices) || ! array_is_list($matchingChoices) || count($matchingChoices) > 12) throw new \DomainException('Pilihan pasangan tidak valid.');
        foreach ($matchingChoices as &$choice) {
            if (! is_array($choice) || array_diff(array_keys($choice), ['body']) !== [] || ! is_string($choice['body'] ?? null)) throw new \DomainException('Pilihan pasangan tidak valid.');
            $choice['body'] = QuestionContent::sanitize($choice['body'], 5000);
        }
        unset($choice);
        $options = $input['options'] ?? null;
        if (! is_array($options) || ! array_is_list($options) || count($options) > 12) throw new \DomainException('Opsi tidak valid.');
        $clean = [];
        foreach ($options as $option) {
            if (! is_array($option) || array_diff(array_keys($option), ['body', 'isCorrect', 'matchBody', 'matchChoiceIndex']) !== [] || ! is_bool($option['isCorrect'] ?? null)) throw new \DomainException('Opsi tidak valid.');
            if (! is_string($option['body'] ?? null)) throw new \DomainException('Teks opsi tidak valid.');
            $matchChoiceIndex = $option['matchChoiceIndex'] ?? null;
            if ($type === 'matching' && $matchChoiceIndex !== null && (! is_int($matchChoiceIndex) || $matchChoiceIndex < 0 || $matchChoiceIndex >= count($matchingChoices))) throw new \DomainException('Kunci pasangan tidak valid.');
            $clean[] = ['body' => QuestionContent::sanitize($option['body'], 5000), 'matchBody' => null, 'matchChoiceIndex' => $matchChoiceIndex, 'isCorrect' => $option['isCorrect']];
        }
        if ($type === 'mcq' && (count($clean) < 2 || count(array_filter($clean, static fn ($option) => $option['isCorrect'])) !== 1)) throw new \DomainException('Pilihan ganda memerlukan tepat satu kunci dan minimal dua opsi.');
        if ($type === 'multi_mcq' && (count($clean) < 3 || count(array_filter($clean, static fn ($option) => $option['isCorrect'])) < 2 || count(array_filter($clean, static fn ($option) => $option['isCorrect'])) === count($clean))) throw new \DomainException('Pilihan jamak memerlukan dua kunci dan satu pengecoh.');
        if (in_array($type, ['true_false', 'matching'], true) && count($clean) < 2) throw new \DomainException('Soal memerlukan minimal dua baris.');
        if ($type === 'matching') {
            $choiceBodies = array_map(static fn ($choice) => trim(strip_tags($choice['body'])), $matchingChoices);
            $indexes = array_column($clean, 'matchChoiceIndex');
            if (count($choiceBodies) < count($clean) || in_array('', $choiceBodies, true)
                || count(array_unique($choiceBodies)) !== count($choiceBodies)
                || in_array(null, $indexes, true) || count(array_unique($indexes)) !== count($clean)) throw new \DomainException('Setiap pernyataan harus memiliki pasangan unik.');
        }
        if ($type === 'essay' && ($rubric === '' || $clean !== [])) throw new \DomainException('Esai memerlukan rubrik tanpa opsi.');
        return ['body' => $body, 'type' => $type, 'rubric' => $rubric, 'explanation' => $explanation, 'weight' => (float) $input['weight'], 'matchingChoices' => $matchingChoices, 'options' => $clean];
    }

    public function create(int $school, int $questionId, int $actor, bool $admin): array
    {
        $this->db->transBegin();
        try {
            $question = $this->db->query('SELECT * FROM questions WHERE id=? AND school_id=? FOR UPDATE', [$questionId, $school])->getRowArray();
            if ($question === null || $question['status'] !== 'published' || (! $admin && (int) $question['author_user_id'] !== $actor)) throw new \OutOfBoundsException('Soal terbit tidak ditemukan.');
            $pending = $this->db->table('question_versions')->where('question_id', $questionId)->whereIn('status', ['draft', 'review', 'rejected'])->countAllResults();
            if ($pending > 0) throw new \DomainException('Selesaikan revisi yang masih terbuka.');
            $now = gmdate('Y-m-d H:i:s');
            $current = (int) $question['version_no'];
            if ($this->db->table('question_versions')->where('question_id', $questionId)->where('version_no', $current)->countAllResults() === 0) {
                $this->db->table('question_versions')->insert(['school_id' => $school, 'question_id' => $questionId, 'version_no' => $current,
                    'author_user_id' => $question['author_user_id'], 'status' => 'approved', 'snapshot_json' => json_encode($this->snapshot($question), JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now, 'decided_at' => $now]);
            }
            $this->db->table('question_versions')->insert(['school_id' => $school, 'question_id' => $questionId, 'version_no' => $current + 1,
                'author_user_id' => $actor, 'status' => 'draft', 'snapshot_json' => json_encode($this->snapshot($question), JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
            $this->db->transCommit();
            return $this->find($school, $id, $actor, $admin);
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }

    public function find(int $school, int $id, int $actor, bool $admin): array
    {
        $row = $this->db->table('question_versions')->where('id', $id)->where('school_id', $school)->get()->getRowArray();
        if ($row === null || (! $admin && (int) $row['author_user_id'] !== $actor)) throw new \OutOfBoundsException('Revisi tidak ditemukan.');
        $row['snapshot'] = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['snapshot_json']);
        return $row;
    }

    public function list(int $school, int $actor, bool $admin, ?int $questionId = null): array
    {
        $query = $this->db->table('question_versions qv')->select('qv.id, qv.question_id, qv.version_no, qv.status, qv.author_user_id, qv.reviewer_user_id, qv.review_note, qv.created_at, qv.decided_at, q.body AS current_body')
            ->join('questions q', 'q.id=qv.question_id')->where('qv.school_id', $school);
        if (! $admin) $query->where('qv.author_user_id', $actor);
        if ($questionId !== null) $query->where('qv.question_id', $questionId);
        return $query->orderBy('qv.id', 'DESC')->limit(100)->get()->getResultArray();
    }

    public function edit(int $school, int $id, int $actor, array $input): array
    {
        $version = $this->find($school, $id, $actor, false);
        if (! in_array($version['status'], ['draft', 'rejected'], true)) throw new \DomainException('Revisi tidak dapat diubah.');
        $snapshot = $this->validate($input);
        $contents = [$snapshot['body']];
        foreach ($snapshot['options'] as $option) $contents[] = $option['body'];
        foreach ($snapshot['matchingChoices'] as $choice) $contents[] = $choice['body'];
        $references = (new QuestionMedia($this->db))->bind($school, $actor, (int) $version['question_id'], $contents, $id);
        $contents = QuestionMedia::replace($contents, $references);
        $snapshot['body'] = QuestionContent::sanitize($contents[0], 20000);
        $contentIndex = 1;
        foreach ($snapshot['options'] as $index => $option) $snapshot['options'][$index]['body'] = QuestionContent::sanitize($contents[$contentIndex++], 5000);
        foreach ($snapshot['matchingChoices'] as $index => $choice) $snapshot['matchingChoices'][$index]['body'] = QuestionContent::sanitize($contents[$contentIndex++], 5000);
        $this->db->table('question_versions')->where('id', $id)->update(['snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'status' => 'draft', 'review_note' => null, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return $this->find($school, $id, $actor, false);
    }

    public function submit(int $school, int $id, int $actor): array
    {
        $version = $this->find($school, $id, $actor, false);
        if ($version['status'] !== 'draft') throw new \DomainException('Hanya draf yang dapat diajukan.');
        $this->validate($version['snapshot']);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('question_versions')->where('id', $id)->update(['status' => 'review', 'submitted_at' => $now, 'updated_at' => $now]);
        return $this->find($school, $id, $actor, false);
    }

    public function decide(int $school, int $id, int $reviewer, bool $approve, string $note): array
    {
        $this->db->transBegin();
        try {
            $version = $this->db->query('SELECT * FROM question_versions WHERE id=? AND school_id=? FOR UPDATE', [$id, $school])->getRowArray();
            if ($version === null) throw new \OutOfBoundsException('Revisi tidak ditemukan.');
            if ($version['status'] !== 'review' || (int) $version['author_user_id'] === $reviewer) throw new \DomainException('Revisi tidak dapat diputuskan oleh penulisnya.');
            $question = $this->source($school, (int) $version['question_id'], $reviewer, true);
            if ((int) $question['version_no'] + 1 !== (int) $version['version_no']) throw new \DomainException('Versi sumber telah berubah.');
            $now = gmdate('Y-m-d H:i:s');
            if ($approve) {
                $snapshot = $this->validate(json_decode($version['snapshot_json'], true, 512, JSON_THROW_ON_ERROR));
                $this->db->table('questions')->where('id', $question['id'])->update(['body' => $snapshot['body'], 'question_type' => $snapshot['type'], 'rubric' => $snapshot['rubric'],
                    'explanation' => $snapshot['explanation'], 'weight' => $snapshot['weight'], 'version_no' => $version['version_no'], 'updated_at' => $now]);
                $this->db->table('question_options')->where('question_id', $question['id'])->delete();
                $this->db->table('question_matching_choices')->where('question_id', $question['id'])->delete();
                $choiceIds = [];
                foreach ($snapshot['matchingChoices'] as $index => $choice) {
                    $this->db->table('question_matching_choices')->insert(['question_id' => $question['id'], 'sort_order' => $index + 1, 'body' => $choice['body'], 'created_at' => $now, 'updated_at' => $now]);
                    $choiceIds[] = (int) $this->db->insertID();
                }
                foreach ($snapshot['options'] as $index => $option) {
                    $choiceId = $option['matchChoiceIndex'] === null ? null : $choiceIds[$option['matchChoiceIndex']];
                    $this->db->table('question_options')->insert(['question_id' => $question['id'], 'sort_order' => $index + 1,
                        'body' => $option['body'], 'match_body' => $choiceId === null ? null : $snapshot['matchingChoices'][$option['matchChoiceIndex']]['body'],
                        'match_choice_id' => $choiceId, 'is_correct' => $option['isCorrect'] ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
                }
                $this->db->table('question_assets')->where('question_version_id', $version['id'])->update(['question_version_id' => null, 'updated_at' => $now]);
            } else {
                $this->db->table('question_assets')->where('question_version_id', $version['id'])->update(['archived_at' => $now, 'updated_at' => $now]);
            }
            $this->db->table('question_versions')->where('id', $id)->update(['status' => $approve ? 'approved' : 'rejected', 'reviewer_user_id' => $reviewer,
                'review_note' => $note, 'decided_at' => $now, 'updated_at' => $now]);
            (new AuditTrail($this->db))->record($school, $reviewer, $approve ? 'questions.revision_approved' : 'questions.revision_rejected', 'question_version', $id);
            if (! $this->db->transStatus()) throw new \RuntimeException('Keputusan revisi gagal.');
            $this->db->transCommit();
            return $this->find($school, $id, $reviewer, true);
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }

    public function archive(int $school, int $id, int $actor, bool $admin): array
    {
        $version = $this->find($school, $id, $actor, $admin);
        if ($version['status'] === 'review' || $version['status'] === 'archived') throw new \DomainException('Revisi tidak dapat diarsipkan.');
        $this->db->table('question_versions')->where('id', $id)->update(['status' => 'archived', 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return $this->find($school, $id, $actor, $admin);
    }
}
