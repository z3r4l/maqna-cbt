<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class StudentSessionGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth('session')->user();
        if ($user === null || ! $user->inGroup('student')) return null;
        $session = service('session');
        $key = $session->get('maqna_student_session_key');
        if (! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            $key = bin2hex(random_bytes(32));
            $session->set('maqna_student_session_key', $key);
        }
        $hash = hash('sha256', $key);
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        $student = $profile === null ? null : $db->table('students')->where('user_id', $user->id)->where('school_id', $profile['school_id'])->get()->getRowArray();
        if ($student === null) return null;
        $record = $db->table('student_sessions')->where('session_key_hash', $hash)->get()->getRowArray();
        if ($record !== null && ($record['revoked_at'] !== null || (int) $record['student_id'] !== (int) $student['id'])) {
            auth('session')->logout();
            return service('response')->setStatusCode(401)->setJSON(['error' => ['code' => 'SESSION_REVOKED', 'message' => 'Sesi berakhir. Silakan masuk kembali.']]);
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($record === null) {
            $db->table('student_sessions')->insert(['school_id' => $profile['school_id'], 'student_id' => $student['id'], 'session_key_hash' => $hash, 'created_at' => $now, 'last_seen_at' => $now]);
        } else {
            $db->table('student_sessions')->where('id', $record['id'])->update(['last_seen_at' => $now]);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
