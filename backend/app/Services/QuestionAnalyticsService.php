<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionAnalyticsService
{
    public const MIN_SAMPLE = 5;

    public function __construct(private BaseConnection $db) {}

    public function forExam(int $school, int $examId): array
    {
        $attempts = $this->db->table('attempts a')->select('a.id, a.student_id, ar.score')
            ->join('attempt_results ar', 'ar.attempt_id=a.id')
            ->where('a.school_id', $school)->where('a.exam_id', $examId)
            ->whereIn('a.status', ['submitted', 'expired'])->where('ar.pending_review', 0)
            ->orderBy('a.id', 'DESC')->get()->getResultArray();
        $byStudent = [];
        foreach ($attempts as $attempt) $byStudent[$attempt['student_id']] ??= $attempt;
        $sample = count($byStudent);
        if ($sample < self::MIN_SAMPLE) return ['sampleSize' => $sample, 'minSample' => self::MIN_SAMPLE, 'items' => [], 'suppressed' => true];

        $ranked = array_values($byStudent);
        usort($ranked, static fn ($a, $b) => (float) $b['score'] <=> (float) $a['score']);
        $groupSize = max(1, (int) ceil($sample * 0.27));
        $top = array_map('intval', array_column(array_slice($ranked, 0, $groupSize), 'id'));
        $bottom = array_map('intval', array_column(array_slice($ranked, -$groupSize), 'id'));
        $ids = array_map('intval', array_column($ranked, 'id'));
        $items = $this->db->table('exam_items')->select('id, sort_order, snapshot_json')->where('exam_id', $examId)->orderBy('sort_order')->get()->getResultArray();
        $output = [];
        foreach ($items as $item) {
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['type'] ?? 'mcq') !== 'mcq') continue;
            $correctId = null;
            $options = [];
            foreach ($snapshot['options'] as $option) {
                $id = (int) $option['id'];
                $options[$id] = ['optionId' => $id, 'body' => $option['body'], 'count' => 0, 'correct' => (int) $option['is_correct'] === 1];
                if ((int) $option['is_correct'] === 1) $correctId = $id;
            }
            $answers = $this->db->table('attempt_items ai')->select('ai.attempt_id, ai.selected_option_id')
                ->join('attempts a', 'a.id=ai.attempt_id')
                ->where('ai.exam_item_id', $item['id'])->where('a.school_id', $school)->whereIn('ai.attempt_id', $ids)->get()->getResultArray();
            $correct = 0; $topCorrect = 0; $bottomCorrect = 0; $unanswered = 0;
            foreach ($answers as $answer) {
                $selected = $answer['selected_option_id'] === null ? null : (int) $answer['selected_option_id'];
                if ($selected === null) $unanswered++;
                elseif (isset($options[$selected])) $options[$selected]['count']++;
                if ($selected === $correctId) {
                    $correct++;
                    if (in_array((int) $answer['attempt_id'], $top, true)) $topCorrect++;
                    if (in_array((int) $answer['attempt_id'], $bottom, true)) $bottomCorrect++;
                }
            }
            $output[] = ['itemId' => (int) $item['id'], 'order' => (int) $item['sort_order'], 'body' => $snapshot['body'],
                'sampleSize' => $sample, 'difficulty' => round($correct / $sample, 3),
                'discrimination' => round(($topCorrect - $bottomCorrect) / $groupSize, 3),
                'unanswered' => $unanswered, 'distractors' => array_values($options)];
        }
        return ['sampleSize' => $sample, 'minSample' => self::MIN_SAMPLE, 'items' => $output, 'suppressed' => false];
    }

    public function matrix(int $school, int $examId): array
    {
        $exam = $this->db->table('exams')->where('school_id', $school)->where('id', $examId)->get()->getRowArray();
        if ($exam === null) throw new \OutOfBoundsException('Ujian tidak ditemukan.');
        $items = $this->db->table('exam_items')->select('id, sort_order, snapshot_json')->where('exam_id', $examId)->orderBy('sort_order')->get()->getResultArray();
        $attempts = $this->db->table('attempts a')->select('a.id, a.student_id, a.status, ar.score, up.display_name, cg.name AS class_name')
            ->join('attempt_results ar', 'ar.attempt_id=a.id')->join('students s', 's.id=a.student_id')
            ->join('user_profiles up', 'up.user_id=s.user_id')->join('exam_assignments ea', 'ea.exam_id=a.exam_id AND ea.student_id=a.student_id')
            ->join('class_groups cg', 'cg.id=ea.class_group_id')->where('a.school_id', $school)->where('a.exam_id', $examId)
            ->whereIn('a.status', ['submitted', 'expired'])->where('ar.pending_review', 0)->orderBy('a.id', 'DESC')->get()->getResultArray();
        $latest = [];
        foreach ($attempts as $attempt) $latest[$attempt['student_id']] ??= $attempt;
        $rows = [];
        foreach ($latest as $attempt) {
            $answers = $this->db->table('attempt_items')->select('exam_item_id, selected_option_id, answer_json, essay_text')->where('attempt_id', $attempt['id'])->get()->getResultArray();
            $byItem = array_column($answers, null, 'exam_item_id');
            $cells = [];
            foreach ($items as $item) {
                $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                $answer = $byItem[$item['id']] ?? null;
                if ($answer === null || ($answer['selected_option_id'] === null && $answer['answer_json'] === null && trim((string) $answer['essay_text']) === '')) $value = 'Kosong';
                elseif ($answer['essay_text'] !== null) $value = 'Esai';
                elseif ($answer['answer_json'] !== null) {
                    $structured = json_decode($answer['answer_json'], true, 512, JSON_THROW_ON_ERROR);
                    $value = is_array($structured) ? json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : 'Terisi';
                } else {
                    $index = array_search((int) $answer['selected_option_id'], array_map('intval', array_column($snapshot['options'] ?? [], 'id')), true);
                    $value = $index === false ? 'Terisi' : chr(65 + $index);
                }
                $cells[] = $value;
            }
            $rows[] = ['studentId' => (int) $attempt['student_id'], 'name' => $attempt['display_name'], 'className' => $attempt['class_name'], 'attemptId' => (int) $attempt['id'], 'score' => (float) $attempt['score'], 'answers' => $cells];
        }
        usort($rows, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return ['examId' => $examId, 'title' => $exam['title'], 'questions' => array_map(static fn ($item) => ['id' => (int) $item['id'], 'order' => (int) $item['sort_order']], $items), 'rows' => $rows];
    }
}
