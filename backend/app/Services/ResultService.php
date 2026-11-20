<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ResultService
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function gradeAttempt(array $attempt, bool $recalculate = false): array
    {
        $existing = $this->db->table('attempt_results')->where('attempt_id', $attempt['id'])->get()->getRowArray();
        if ($existing !== null && ! $recalculate) return $existing;
        if (! in_array($attempt['status'], ['submitted', 'expired'], true)) throw new \DomainException('Percobaan belum selesai.');
        $exam = $this->db->table('exams')->where('id', $attempt['exam_id'])->get()->getRowArray();
        if ($exam === null) throw new \RuntimeException('Ujian tidak ditemukan.');
        $items = $this->db->table('attempt_items ai')->select('ai.selected_option_id, ai.answer_json, ai.essay_points, ei.weight, ei.snapshot_json')
            ->join('exam_items ei', 'ei.id=ai.exam_item_id')->where('ai.attempt_id', $attempt['id'])->get()->getResultArray();
        $earned = 0.0; $max = 0.0; $correctCount = 0; $pending = 0;
        foreach ($items as $item) {
            $weight = (float) $item['weight'];
            $max += $weight;
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['type'] ?? 'mcq') === 'essay') {
                if ($item['essay_points'] === null) $pending++;
                else $earned += (float) $item['essay_points'];
                continue;
            }
            if (in_array($snapshot['type'] ?? 'mcq', ['multi_mcq', 'true_false', 'matching'], true)) {
                [$points, $allCorrect] = StructuredAnswer::points($snapshot['type'], $item['answer_json'] === null ? null : json_decode($item['answer_json'], true, 512, JSON_THROW_ON_ERROR), $snapshot['options'], $weight, $snapshot['matching_choices'] ?? []);
                $earned += $points;
                if ($allCorrect) $correctCount++;
                continue;
            }
            $correct = null;
            foreach ($snapshot['options'] as $option) if ((int) $option['is_correct'] === 1) $correct = (int) $option['id'];
            if ($item['selected_option_id'] !== null && (int) $item['selected_option_id'] === $correct) {
                $earned += $weight;
                $correctCount++;
            }
        }
        $score = $max > 0 ? round($earned / $max * 100, 2) : 0.0;
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'school_id' => $attempt['school_id'], 'exam_id' => $attempt['exam_id'], 'student_id' => $attempt['student_id'], 'attempt_id' => $attempt['id'],
            'earned_points' => round($earned, 2), 'max_points' => round($max, 2), 'score' => $score,
            'passed' => $score >= (float) $exam['passing_grade'] ? 1 : 0,
            'correct_count' => $correctCount, 'question_count' => count($items), 'pending_review' => $pending > 0 ? 1 : 0,
            'graded_at' => $pending > 0 ? null : $now, 'updated_at' => $now,
        ];
        if ($existing === null) $this->db->table('attempt_results')->insert($row + ['released_at' => null, 'created_at' => $now]);
        else $this->db->table('attempt_results')->where('id', $existing['id'])->update($row);
        return $this->db->table('attempt_results')->where('attempt_id', $attempt['id'])->get()->getRowArray();
    }

    public function scoreEssay(int $school, int $examId, int $itemId, int $actor, float $points, string $feedback): array
    {
        $this->db->transBegin();
        try {
            $item = $this->db->query('SELECT ai.*, a.school_id, a.exam_id, a.status AS attempt_status, ei.weight, ei.snapshot_json FROM attempt_items ai JOIN attempts a ON a.id=ai.attempt_id JOIN exam_items ei ON ei.id=ai.exam_item_id WHERE ai.id=? AND a.school_id=? AND a.exam_id=? FOR UPDATE', [$itemId, $school, $examId])->getRowArray();
            if ($item === null) throw new \OutOfBoundsException('Jawaban tidak ditemukan.');
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['type'] ?? 'mcq') !== 'essay' || $item['attempt_status'] === 'active') throw new \DomainException('Jawaban esai belum siap dinilai.');
            $release = $this->db->table('result_release_batches')->where('school_id', $school)->where('exam_id', $examId)->get()->getRowArray();
            if ($release !== null) throw new \DomainException('Nilai sudah dirilis.');
            if (! is_finite($points) || $points < 0 || $points > (float) $item['weight']) throw new \DomainException('Poin di luar bobot soal.');
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('attempt_items')->where('id', $itemId)->update(['essay_points' => $points, 'essay_feedback' => $feedback, 'essay_graded_by' => $actor, 'essay_graded_at' => $now, 'updated_at' => $now]);
            $attempt = $this->db->table('attempts')->where('id', $item['attempt_id'])->get()->getRowArray();
            $result = $this->gradeAttempt($attempt, true);
            $this->db->table('audit_events')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'action' => 'essay.grade', 'target_type' => 'attempt_item', 'target_id' => $itemId, 'metadata_json' => json_encode(['points' => $points]), 'created_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penilaian gagal.');
            $this->db->transCommit();
            return $result;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function release(int $school, int $examId, int $actor): array
    {
        $this->db->transBegin();
        try {
            $exam = $this->db->query('SELECT * FROM exams WHERE id = ? AND school_id = ? FOR UPDATE', [$examId, $school])->getRowArray();
            if ($exam === null) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
            $prior = $this->db->table('result_release_batches')->where('school_id', $school)->where('exam_id', $examId)->get()->getRowArray();
            if ($prior !== null) { $this->db->transCommit(); return $prior; }
            if ($exam['status'] !== 'published' || $exam['end_at'] > gmdate('Y-m-d H:i:s')) throw new \DomainException('Ujian belum berakhir.');
            $attempts = $this->db->table('attempts')->where('school_id', $school)->where('exam_id', $examId)->get()->getResultArray();
            if ($attempts === []) throw new \DomainException('Belum ada percobaan untuk dirilis.');
            foreach ($attempts as $attempt) {
                if ($attempt['status'] === 'active') throw new \DomainException('Masih ada percobaan aktif.');
                $this->gradeAttempt($attempt);
            }
            $pending = $this->db->table('attempt_results')->where('school_id', $school)->where('exam_id', $examId)->where('pending_review', 1)->countAllResults();
            if ($pending > 0) throw new \DomainException('Masih ada jawaban esai yang belum dinilai.');
            $count = $this->db->table('attempt_results')->where('school_id', $school)->where('exam_id', $examId)->countAllResults();
            if ($count !== count($attempts)) throw new \DomainException('Penilaian belum lengkap.');
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('result_release_batches')->insert(['school_id' => $school, 'exam_id' => $examId, 'released_by_user_id' => $actor, 'released_at' => $now, 'result_count' => $count, 'created_at' => $now, 'updated_at' => $now]);
            $batchId = (int) $this->db->insertID();
            $this->db->table('attempt_results')->where('school_id', $school)->where('exam_id', $examId)->update(['released_at' => $now, 'updated_at' => $now]);
            $this->db->table('audit_events')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'action' => 'results.release', 'target_type' => 'exam', 'target_id' => $examId, 'metadata_json' => json_encode(['resultCount' => $count]), 'created_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Rilis hasil gagal.');
            $this->db->transCommit();
            return $this->db->table('result_release_batches')->where('id', $batchId)->get()->getRowArray();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function history(int $school, int $student): array
    {
        $assignments = $this->db->table('exam_assignments ea')->select('e.id, e.title, e.subject_id, e.result_policy, s.name AS subject_name')
            ->join('exams e', 'e.id=ea.exam_id')->join('subjects s', 's.id=e.subject_id')
            ->where('ea.school_id', $school)->where('ea.student_id', $student)->where('e.status', 'published')
            ->orderBy('e.id', 'DESC')->get()->getResultArray();
        $history = [];
        foreach ($assignments as $exam) {
            $attempts = $this->db->table('attempts')->where('exam_id', $exam['id'])->where('student_id', $student)->orderBy('attempt_number')->get()->getResultArray();
            $item = ['examId' => (int) $exam['id'], 'title' => $exam['title'], 'subjectName' => $exam['subject_name'],
                'attemptCount' => count($attempts), 'status' => 'not_started'];
            if ($attempts !== []) {
                $latest = $attempts[count($attempts) - 1];
                $item['attemptId'] = (int) $latest['id'];
                $item['status'] = $latest['status'] === 'active' ? 'in_progress' : 'awaiting_release';
                $released = $this->db->table('result_release_batches')->where('exam_id', $exam['id'])->where('school_id', $school)->get()->getRowArray();
                if ($released !== null) {
                    $results = $this->db->table('attempt_results')->where('exam_id', $exam['id'])->where('student_id', $student)->where('released_at !=', null)->get()->getResultArray();
                    if ($results !== []) {
                        if ($exam['result_policy'] === 'highest') usort($results, static fn ($a, $b) => (float) $b['score'] <=> (float) $a['score']);
                        $chosen = $results[0];
                        $item['attemptId'] = (int) $chosen['attempt_id'];
                        $item['status'] = 'released';
                        $item['score'] = (float) $chosen['score'];
                        $item['passed'] = (bool) $chosen['passed'];
                        $item['releasedAt'] = $chosen['released_at'];
                    }
                }
            }
            $history[] = $item;
        }
        return $history;
    }

    public function studentResult(int $school, int $student, int $attemptId): array
    {
        $attempt = $this->db->table('attempts')->where('id', $attemptId)->where('school_id', $school)->where('student_id', $student)->get()->getRowArray();
        if ($attempt === null) throw new \OutOfBoundsException('Hasil tidak ditemukan.');
        if ($attempt['status'] === 'active') return ['status' => 'in_progress'];
        $result = $this->db->table('attempt_results')->where('attempt_id', $attemptId)->get()->getRowArray();
        if ($result === null || $result['released_at'] === null) return ['status' => 'awaiting_release'];
        $exam = $this->db->table('exams')->where('id', $attempt['exam_id'])->get()->getRowArray();
        return ['status' => 'released', 'attemptId' => $attemptId, 'score' => (float) $result['score'], 'passed' => (bool) $result['passed'],
            'correctCount' => (int) $result['correct_count'], 'questionCount' => (int) $result['question_count'], 'releasedAt' => $result['released_at'],
            'reviewAvailable' => (int) $exam['review_enabled'] === 1 && $exam['end_at'] <= gmdate('Y-m-d H:i:s')];
    }

    public function answerReview(int $school, int $student, int $attemptId): array
    {
        $attempt = $this->db->table('attempts')->where('id', $attemptId)->where('school_id', $school)->where('student_id', $student)->get()->getRowArray();
        if ($attempt === null) throw new \OutOfBoundsException('Percobaan tidak ditemukan.');
        $exam = $this->db->table('exams')->where('id', $attempt['exam_id'])->where('school_id', $school)->get()->getRowArray();
        $release = $this->db->table('attempt_results')->where('attempt_id', $attemptId)->where('released_at !=', null)->get()->getRowArray();
        if ($exam === null || (int) $exam['review_enabled'] !== 1 || $exam['end_at'] > gmdate('Y-m-d H:i:s') || $release === null) throw new \DomainException('Tinjauan jawaban belum diizinkan.');
        $items = $this->db->table('attempt_items ai')->select('ai.display_order, ai.selected_option_id, ai.answer_json, ai.essay_text, ai.essay_points, ai.essay_feedback, ei.weight, ei.snapshot_json')
            ->join('exam_items ei', 'ei.id=ai.exam_item_id')->where('ai.attempt_id', $attemptId)->orderBy('ai.display_order')->get()->getResultArray();
        $review = [];
        foreach ($items as $item) {
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $choices = $snapshot['matching_choices'] ?? [];
            if ($choices === [] && ($snapshot['type'] ?? '') === 'matching') {
                foreach ($snapshot['options'] as $option) $choices[] = ['id' => (int) $option['id'], 'body' => $option['match_body'] ?? ''];
            }
            $review[] = ['order' => (int) $item['display_order'], 'body' => $snapshot['body'], 'type' => $snapshot['type'] ?? 'mcq',
                'options' => $snapshot['options'], 'choices' => $choices, 'answer' => $item['answer_json'] === null ? null : json_decode($item['answer_json'], true, 512, JSON_THROW_ON_ERROR),
                'selectedOptionId' => $item['selected_option_id'] === null ? null : (int) $item['selected_option_id'],
                'essayText' => $item['essay_text'], 'essayPoints' => $item['essay_points'] === null ? null : (float) $item['essay_points'],
                'essayFeedback' => $item['essay_feedback'], 'maxPoints' => (float) $item['weight'], 'explanation' => $snapshot['explanation']];
        }
        return $review;
    }

    public function report(int $school, int $examId, ?int $classId): array
    {
        $exam = (new ExamService($this->db))->find($school, $examId);
        if ($exam === null) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        $query = $this->db->table('exam_assignments ea')->select('ea.student_id, ea.class_group_id, up.display_name, u.username, cg.name AS class_name, a.id AS attempt_id, a.status AS attempt_status, ar.score, ar.passed, ar.pending_review, ar.released_at')
            ->join('students s', 's.id=ea.student_id')->join('users u', 'u.id=s.user_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('class_groups cg', 'cg.id=ea.class_group_id')
            ->join('attempts a', 'a.student_id=ea.student_id AND a.exam_id=ea.exam_id', 'left')
            ->join('attempt_results ar', 'ar.attempt_id=a.id', 'left')
            ->where('ea.school_id', $school)->where('ea.exam_id', $examId);
        if ($classId !== null) $query->where('ea.class_group_id', $classId);
        $rows = $query->orderBy('up.display_name')->get()->getResultArray();
        $scores = array_map('floatval', array_column(array_filter($rows, static fn ($row) => $row['score'] !== null), 'score'));
        return ['exam' => ['id' => (int) $exam['id'], 'title' => $exam['title'], 'status' => $exam['status'], 'endAt' => $exam['end_at']],
            'statistics' => ['assigned' => count(array_unique(array_column($rows, 'student_id'))), 'graded' => count($scores),
                'average' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
                'passed' => count(array_filter($rows, static fn ($row) => $row['passed'] !== null && (int) $row['passed'] === 1))],
            'rows' => $rows];
    }
}
