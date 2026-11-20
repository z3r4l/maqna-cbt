<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionKeyImportService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function inspect(int $school, int $actor, bool $admin, array $row): array
    {
        if (! ctype_digit((string) $row['question_id']) || (int) $row['question_id'] < 1) throw new \DomainException('ID soal tidak valid.');
        $question = $this->db->table('questions')->where('id', (int) $row['question_id'])->where('school_id', $school)->where('deleted_at', null)->get()->getRowArray();
        if ($question === null || (! $admin && (int) $question['author_user_id'] !== $actor)) throw new \DomainException('Soal di luar izin.');
        if ($question['status'] !== 'draft' || $this->db->table('exam_items')->where('source_question_id', $question['id'])->countAllResults() > 0) throw new \DomainException('Kunci hanya dapat diubah pada draf yang belum masuk snapshot ujian.');
        if ($question['question_type'] !== $row['type'] || ! in_array($row['type'], ['mcq', 'multi_mcq', 'true_false', 'matching'], true)) throw new \DomainException('Jenis soal tidak sesuai.');
        $options = $this->db->table('question_options')->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
        $count = count($options);
        if ($count < 2 || $count > 12) throw new \DomainException('Jumlah opsi tidak valid.');
        $choices = $row['type'] === 'matching' ? $this->db->table('question_matching_choices')->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray() : [];
        $choiceCount = count($choices);
        if ($row['type'] === 'matching' && ($choiceCount < $count || $choiceCount > 12)) throw new \DomainException('Jumlah pilihan pasangan tidak valid.');
        $key = strtoupper(trim($row['key']));
        $correct = []; $matches = [];
        if ($row['type'] === 'true_false') {
            $values = explode(',', str_replace(' ', '', $key));
            if (count($values) !== $count || array_diff($values, ['T', 'F']) !== []) throw new \DomainException('Kunci benar/salah harus T,F per baris.');
            $correct = array_map(static fn ($value) => $value === 'T', $values);
        } elseif ($row['type'] === 'matching') {
            $parts = explode(',', str_replace(' ', '', $key));
            if (count($parts) !== $count) throw new \DomainException('Kunci pasangan harus lengkap.');
            foreach ($parts as $part) {
                if (! preg_match('/^([A-L])=([A-L])$/', $part, $m)) throw new \DomainException('Format pasangan tidak valid.');
                $left = ord($m[1]) - 65; $right = ord($m[2]) - 65;
                if ($left >= $count || $right >= $choiceCount || isset($matches[$left]) || in_array($right, $matches, true)) throw new \DomainException('Pasangan duplikat atau di luar soal.');
                $matches[$left] = $right;
            }
            ksort($matches);
        } else {
            $letters = explode(',', str_replace(' ', '', $key));
            if (count($letters) !== count(array_unique($letters))) throw new \DomainException('Kunci duplikat.');
            foreach ($letters as $letter) if (! preg_match('/^[A-L]$/', $letter) || ord($letter) - 65 >= $count) throw new \DomainException('Kunci di luar opsi.');
            if ($row['type'] === 'mcq' && count($letters) !== 1 || $row['type'] === 'multi_mcq' && (count($letters) < 2 || count($letters) >= $count)) throw new \DomainException('Jumlah kunci tidak sesuai jenis soal.');
            foreach ($options as $index => $_) $correct[] = in_array(chr(65 + $index), $letters, true);
        }
        $current = $row['type'] === 'matching' ? implode(',', array_map(static function ($index, $option) use ($choices): string {
            $choiceIds = array_map('intval', array_column($choices, 'id'));
            $choiceIndex = array_search((int) ($option['match_choice_id'] ?? $option['id']), $choiceIds, true);
            return chr(65 + $index) . '=' . chr(65 + ($choiceIndex === false ? $index : $choiceIndex));
        }, array_keys($options), $options))
            : ($row['type'] === 'true_false' ? implode(',', array_map(static fn ($option) => $option['is_correct'] ? 'T' : 'F', $options))
                : implode(',', array_map(static fn ($index) => chr(65 + $index), array_keys(array_filter($options, static fn ($option) => $option['is_correct'])))));
        return ['questionId' => (int) $question['id'], 'type' => $row['type'], 'currentKey' => $current, 'newKey' => $key,
            'correct' => $correct, 'matches' => $matches, 'optionIds' => array_map('intval', array_column($options, 'id')),
            'choiceIds' => array_map('intval', array_column($choices, 'id')), 'choiceBodies' => array_column($choices, 'body')];
    }

    public function preview(int $school, int $actor, bool $admin, string $path, string $extension): array
    {
        $source = (new ImportService($this->db))->read('keys', $path, $extension);
        $seen = []; $rows = []; $resolved = [];
        foreach ($source as $entry) {
            $value = $entry['value'];
            if (isset($seen[$value['question_id']])) throw new \DomainException('ID soal duplikat dalam berkas.');
            $seen[$value['question_id']] = true;
            $item = $this->inspect($school, $actor, $admin, $value);
            $rows[] = ['rowNumber' => $entry['number'], 'questionId' => $item['questionId'], 'type' => $item['type'], 'currentKey' => $item['currentKey'], 'newKey' => $item['newKey']];
            $resolved[] = $value;
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('question_transfer_jobs')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'status' => 'preview',
            'payload_json' => json_encode(['kind' => 'keys', 'rows' => $resolved], JSON_THROW_ON_ERROR), 'row_count' => count($rows), 'created_at' => $now]);
        return ['id' => (int) $this->db->insertID(), 'rows' => $rows, 'count' => count($rows)];
    }

    public function commit(int $school, int $actor, bool $admin, int $id): array
    {
        $this->db->transBegin();
        try {
            $job = $this->db->query('SELECT * FROM question_transfer_jobs WHERE id=? AND school_id=? AND actor_user_id=? FOR UPDATE', [$id, $school, $actor])->getRowArray();
            if ($job === null) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            $payload = json_decode($job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== 'keys') throw new \OutOfBoundsException('Pratinjau kunci tidak ditemukan.');
            if ($job['status'] === 'committed') { $this->db->transCommit(); return ['id' => $id, 'status' => 'committed']; }
            foreach ($payload['rows'] as $row) {
                $item = $this->inspect($school, $actor, $admin, $row);
                foreach ($item['optionIds'] as $index => $optionId) {
                    $update = $item['type'] === 'matching' ? ['match_choice_id' => $item['choiceIds'][$item['matches'][$index]], 'match_body' => $item['choiceBodies'][$item['matches'][$index]]]
                        : ['is_correct' => $item['correct'][$index] ? 1 : 0];
                    $this->db->table('question_options')->where('id', $optionId)->update($update + ['updated_at' => gmdate('Y-m-d H:i:s')]);
                }
            }
            $this->db->table('question_transfer_jobs')->where('id', $id)->update(['status' => 'committed', 'committed_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($this->db))->record($school, $actor, 'questions.keys_import', 'question_transfer_job', $id, ['count' => count($payload['rows'])]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Impor kunci gagal.');
            $this->db->transCommit();
            return ['id' => $id, 'status' => 'committed', 'updated' => count($payload['rows'])];
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }
}
