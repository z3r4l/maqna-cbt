<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ProctorService
{
    public function __construct(private BaseConnection $db) {}

    public function heartbeat(int $school, int $student, int $attemptId, string $device, ?string $event): array
    {
        if ($event !== null && ! in_array($event, ['visibility_hidden', 'visibility_visible', 'focus', 'blur'], true)) {
            throw new \InvalidArgumentException('Jenis peristiwa tidak valid.');
        }
        $this->db->transBegin();
        $closed = false;
        try {
            $attempt = $this->db->query('SELECT * FROM attempts WHERE id=? AND school_id=? AND student_id=? FOR UPDATE', [$attemptId, $school, $student])->getRowArray();
            if ($attempt === null) throw new \OutOfBoundsException('Percobaan tidak ditemukan.');
            if (! (new AttemptService($this->db))->deviceAllowed($attempt, $device)) throw new \DomainException('Percobaan aktif pada perangkat lain.');
            if ($attempt['status'] !== 'active') throw new \DomainException('Percobaan sudah selesai.');
            if ($attempt['deadline_at'] <= gmdate('Y-m-d H:i:s')) {
                $this->db->transCommit();
                $closed = true;
                (new AttemptService($this->db))->expireOne($attempt);
                throw new \DomainException('Percobaan sudah selesai.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('attempts')->where('id', $attemptId)->update(['last_seen_at' => $now]);
            $recorded = false;
            if ($event !== null) {
                $recent = $this->db->table('proctor_events')->where('attempt_id', $attemptId)
                    ->where('created_at >=', gmdate('Y-m-d H:i:s', time() - 10))->countAllResults();
                if ($recent < 2) {
                    $this->db->table('proctor_events')->insert(['school_id' => $school, 'attempt_id' => $attemptId, 'event_type' => $event, 'created_at' => $now]);
                    $recorded = true;
                }
            }
            if (! $this->db->transStatus()) throw new \RuntimeException('Heartbeat gagal dicatat.');
            $this->db->transCommit();
            $closed = true;
            return ['serverNow' => $now, 'deadlineAt' => $attempt['deadline_at'], 'eventRecorded' => $recorded];
        } catch (\Throwable $error) {
            if (! $closed) $this->db->transRollback();
            throw $error;
        }
    }

    public function roster(int $school, int $examId, string $loginStatus = 'all'): array
    {
        $rows = $this->db->table('exam_assignments ea')
            ->select('ea.student_id, ea.class_group_id, up.display_name, cg.name AS class_name, u.last_active')
            ->select('(EXISTS(SELECT 1 FROM auth_logins al WHERE al.user_id=s.user_id AND al.success=1)) AS has_logged_in', false)
            ->join('students s', 's.id=ea.student_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('users u', 'u.id=s.user_id')
            ->join('class_groups cg', 'cg.id=ea.class_group_id')
            ->where('ea.school_id', $school)->where('ea.exam_id', $examId)->orderBy('up.display_name')->get()->getResultArray();
        $now = time();
        foreach ($rows as &$row) {
            $attempt = $this->db->table('attempts')->where('school_id', $school)->where('exam_id', $examId)
                ->where('student_id', $row['student_id'])->orderBy('id', 'DESC')->get()->getRowArray();
            $row['attemptId'] = $attempt === null ? null : (int) $attempt['id'];
            $row['lastSeenAt'] = $attempt['last_seen_at'] ?? null;
            $row['remainingSeconds'] = $attempt === null ? null : max(0, strtotime($attempt['deadline_at'] . ' UTC') - $now);
            $row['eventCount'] = $attempt === null ? 0 : $this->db->table('proctor_events')->where('attempt_id', $attempt['id'])->countAllResults();
            $row['status'] = 'not_started';
            $row['sessions'] = $this->db->table('student_sessions')->select('id, last_seen_at')->where('school_id', $school)->where('student_id', $row['student_id'])->where('revoked_at', null)->orderBy('last_seen_at', 'DESC')->limit(5)->get()->getResultArray();
            if ($attempt !== null) {
                if ($attempt['status'] !== 'active') $row['status'] = $attempt['status'];
                elseif ($row['remainingSeconds'] === 0) $row['status'] = 'expired';
                elseif ($attempt['last_seen_at'] === null || strtotime($attempt['last_seen_at'] . ' UTC') < $now - 60) $row['status'] = 'disconnected';
                else $row['status'] = 'active';
            }
        }
        unset($row);
        if ($loginStatus === 'yes') $rows = array_values(array_filter($rows, static fn ($row) => (int) $row['has_logged_in'] === 1));
        if ($loginStatus === 'no') $rows = array_values(array_filter($rows, static fn ($row) => (int) $row['has_logged_in'] === 0));
        return ['serverNow' => gmdate('Y-m-d H:i:s'), 'rows' => $rows];
    }
}
