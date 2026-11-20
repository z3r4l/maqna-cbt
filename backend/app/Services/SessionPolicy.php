<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class SessionPolicy
{
    public const ROLES = ['student', 'teacher', 'proctor', 'school_admin', 'superadmin'];
    public function __construct(private BaseConnection $db) {}

    public function role($user): string
    {
        foreach (['superadmin', 'school_admin', 'teacher', 'proctor', 'student'] as $role) if ($user->inGroup($role)) return $role;
        return 'student';
    }

    public function limit(int $school, string $role): int
    {
        $row = $this->db->table('school_session_policies')->where('school_id', $school)->where('role', $role)->get()->getRowArray();
        return $row === null ? 0 : (int) $row['max_sessions'];
    }

    public function register(int $school, int $user, string $role, string $key): void
    {
        $limit = $this->limit($school, $role);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if ($limit > 0) {
                $existing = $this->db->query('SELECT * FROM account_sessions WHERE school_id=? AND user_id=? AND revoked_at IS NULL ORDER BY last_seen_at ASC FOR UPDATE', [$school, $user])->getResultArray();
                if (count($existing) >= $limit) {
                    if ($role === 'student') {
                        $student = $this->db->table('students')->where('school_id', $school)->where('user_id', $user)->get()->getRowArray();
                        $active = $student !== null && $this->db->table('attempts')->where('school_id', $school)->where('student_id', $student['id'])->where('status', 'active')->where('deadline_at >', $now)->countAllResults() > 0;
                        if ($active) throw new \DomainException('Ujian masih aktif pada sesi lain. Lanjutkan pada perangkat semula atau minta bantuan pengawas.');
                    }
                    foreach (array_slice($existing, 0, count($existing) - $limit + 1) as $session) {
                        $this->db->table('account_sessions')->where('id', $session['id'])->update(['revoked_at' => $now, 'revoke_reason' => 'Batas sesi baru']);
                    }
                    (new AuditTrail($this->db))->record($school, $user, 'sessions.limit_applied', 'user', $user, ['role' => $role, 'limit' => $limit]);
                }
            }
            $this->db->table('account_sessions')->insert(['school_id' => $school, 'user_id' => $user, 'role' => $role, 'session_key_hash' => hash('sha256', $key), 'created_at' => $now, 'last_seen_at' => $now]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Sesi gagal didaftarkan.');
            $this->db->transCommit();
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }
}
