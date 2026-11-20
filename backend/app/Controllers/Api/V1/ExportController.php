<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class ExportController extends BaseController
{
    public function people(string $kind): ResponseInterface
    {
        if (! in_array($kind, ['students', 'teachers', 'subjects'], true)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Ekspor tidak ditemukan.']]);
        }
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $permission = $kind === 'subjects' ? 'subjects.manage' : 'users.manage';
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, $permission)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        }
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $school = (int) $profile['school_id'];
        if ($kind === 'students') {
            $headers = ['nis', 'nisn', 'name', 'username', 'email', 'class_group_code', 'status'];
            $rows = $db->table('students p')->select('p.nis, p.nisn, up.display_name AS name, u.username, ai.secret AS email, p.status')
                ->select('(SELECT cg.code FROM student_class_memberships m JOIN academic_years ay ON ay.id=m.academic_year_id AND ay.school_id=p.school_id JOIN class_groups cg ON cg.id=m.class_group_id AND cg.school_id=p.school_id WHERE m.student_id=p.id AND m.school_id=p.school_id AND m.active=1 AND ay.active=1 ORDER BY m.id DESC LIMIT 1) AS class_group_code', false)
                ->join('users u', 'u.id=p.user_id')->join('user_profiles up', 'up.user_id=p.user_id')
                ->join('auth_identities ai', "ai.user_id=p.user_id AND ai.type='email_password'", 'left')
                ->where('p.school_id', $school)->orderBy('p.id')->get()->getResultArray();
            foreach ($rows as &$row) if (is_string($row['email']) && str_ends_with($row['email'], '@internal.maqna.invalid')) $row['email'] = '';
            unset($row);
        } elseif ($kind === 'teachers') {
            $headers = ['employee_no', 'name', 'username', 'email', 'status', 'subject_code', 'class_group_code', 'semester_name'];
            $rows = $db->table('teachers p')->select('p.employee_no, up.display_name AS name, u.username, ai.secret AS email, p.status, sub.code AS subject_code, cg.code AS class_group_code, se.name AS semester_name')
                ->join('users u', 'u.id=p.user_id')->join('user_profiles up', 'up.user_id=p.user_id')
                ->join('auth_identities ai', "ai.user_id=p.user_id AND ai.type='email_password'", 'left')
                ->join('teaching_assignments ta', 'ta.teacher_id=p.id AND ta.school_id=p.school_id AND ta.active=1', 'left')
                ->join('subjects sub', 'sub.id=ta.subject_id AND sub.school_id=p.school_id', 'left')
                ->join('class_groups cg', 'cg.id=ta.class_group_id AND cg.school_id=p.school_id', 'left')
                ->join('semesters se', 'se.id=ta.semester_id AND se.school_id=p.school_id', 'left')
                ->where('p.school_id', $school)->orderBy('p.id')->get()->getResultArray();
        } else {
            $headers = ['code', 'name', 'active'];
            $rows = $db->table('subjects')->select('code, name, active')->where('school_id', $school)->orderBy('code')->get()->getResultArray();
        }
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cell = (string) ($row[$header] ?? '');
                if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $cell)) $cell = "'" . $cell;
                $cells[] = $cell;
            }
            fputcsv($stream, $cells);
        }
        rewind($stream);
        $body = stream_get_contents($stream);
        fclose($stream);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-' . $kind . '.csv"')
            ->setHeader('Cache-Control', 'no-store')->setBody($body);
    }
}
