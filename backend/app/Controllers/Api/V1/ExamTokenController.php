<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\ExamService;
use App\Services\DynamicExamToken;
use CodeIgniter\HTTP\ResponseInterface;

class ExamTokenController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'exams.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $admin];
    }

    public function index(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin] = $context;
        $status = (string) ($this->request->getGet('status') ?? 'all');
        $subject = (string) ($this->request->getGet('subjectId') ?? '');
        $class = (string) ($this->request->getGet('classGroupId') ?? '');
        if (! in_array($status, ['all', 'draft', 'published'], true) || ($subject !== '' && (! ctype_digit($subject) || (int) $subject < 1)) || ($class !== '' && (! ctype_digit($class) || (int) $class < 1))) {
            return $this->error('VALIDATION', 'Filter tidak valid.', 422);
        }
        $db = db_connect();
        $query = $db->table('exams e')
            ->select('e.id, e.title, e.status, e.subject_id, e.start_at, e.end_at, e.token_mode, s.name AS subject_name')
            ->select('(e.access_code_hash IS NOT NULL) AS token_enabled', false)
            ->select('(SELECT GROUP_CONCAT(cg.name ORDER BY cg.name SEPARATOR ", ") FROM exam_classes ec JOIN class_groups cg ON cg.id=ec.class_group_id WHERE ec.exam_id=e.id) AS class_names', false)
            ->join('subjects s', 's.id=e.subject_id')->where('e.school_id', $school)->where('e.archived_at', null);
        if (! $admin) $query->where('e.author_user_id', $user);
        if ($status !== 'all') $query->where('e.status', $status);
        if ($subject !== '') $query->where('e.subject_id', (int) $subject);
        if ($class !== '') $query->where('EXISTS(SELECT 1 FROM exam_classes ec WHERE ec.exam_id=e.id AND ec.class_group_id=' . (int) $class . ')', null, false);
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $total = $query->countAllResults(false);
        $rows = $query->orderBy('e.id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();
        return $this->response->setJSON(['data' => $rows, 'meta' => ['page' => $page, 'perPage' => 20, 'total' => $total]]);
    }

    public function rotate(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        if (($this->request->getJSON(true)['confirmed'] ?? false) !== true) return $this->error('VALIDATION', 'Konfirmasi diperlukan.', 422);
        [$school, $user, $admin] = $context;
        $db = db_connect();
        $db->transBegin();
        try {
            $exam = $db->query('SELECT * FROM exams WHERE id=? AND school_id=? FOR UPDATE', [(int) $id, $school])->getRowArray();
            $service = new ExamService($db);
            if ($exam === null || $exam['status'] !== 'published' || ! $service->isAllowed($school, $user, $admin, $exam)) {
                $db->transRollback();
                return $this->error('NOT_FOUND', 'Ujian terbit tidak ditemukan.', 404);
            }
            if (! $admin && ! $service->canTeachAll($school, $user, $exam)) {
                $db->transRollback();
                return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
            }
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($index = 0; $index < 8; $index++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $mode = $exam['token_mode'] ?? 'static';
            $seed = password_hash($code, PASSWORD_DEFAULT);
            $db->table('exams')->where('id', (int) $id)->update(['access_code_hash' => $seed, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($db))->record($school, $user, 'exams.access_code.rotate', 'exam', (int) $id);
            if (! $db->transStatus()) throw new \RuntimeException('Token gagal diganti.');
            $db->transCommit();
            return $this->response->setJSON(['data' => $mode === 'dynamic' ? DynamicExamToken::at($seed, time()) : ['accessCode' => $code], 'meta' => []]);
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function current(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin] = $context;
        $db = db_connect();
        $exam = $db->table('exams')->where('id', (int) $id)->where('school_id', $school)->where('status', 'published')->get()->getRowArray();
        $service = new ExamService($db);
        if ($exam === null || ! $service->isAllowed($school, $user, $admin, $exam)) return $this->error('NOT_FOUND', 'Ujian terbit tidak ditemukan.', 404);
        if (! $admin && ! $service->canTeachAll($school, $user, $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        if (($exam['token_mode'] ?? 'static') !== 'dynamic' || $exam['access_code_hash'] === null) return $this->error('VALIDATION', 'Token dinamis belum aktif.', 422);
        return $this->response->setJSON(['data' => DynamicExamToken::at($exam['access_code_hash'], time()), 'meta' => []]);
    }

    public function policy(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        $mode = $input['mode'] ?? null;
        if (($input['confirmed'] ?? false) !== true || ! in_array($mode, ['none', 'static', 'dynamic'], true)) return $this->error('VALIDATION', 'Mode token dan konfirmasi diperlukan.', 422);
        [$school, $user, $admin] = $context;
        $db = db_connect();
        $db->transBegin();
        try {
            $exam = $db->query('SELECT * FROM exams WHERE id=? AND school_id=? FOR UPDATE', [(int) $id, $school])->getRowArray();
            $service = new ExamService($db);
            if ($exam === null || $exam['status'] !== 'published' || ! $service->isAllowed($school, $user, $admin, $exam)) {
                $db->transRollback();
                return $this->error('NOT_FOUND', 'Ujian terbit tidak ditemukan.', 404);
            }
            if (! $admin && ! $service->canTeachAll($school, $user, $exam)) {
                $db->transRollback();
                return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
            }
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($index = 0; $index < 8; $index++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $seed = $mode === 'none' ? null : password_hash($code, PASSWORD_DEFAULT);
            $db->table('exams')->where('id', (int) $id)->update(['token_mode' => $mode === 'dynamic' ? 'dynamic' : 'static', 'access_code_hash' => $seed, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($db))->record($school, $user, 'exams.access_code.policy', 'exam', (int) $id, ['mode' => $mode]);
            if (! $db->transStatus()) throw new \RuntimeException('Kebijakan token gagal disimpan.');
            $db->transCommit();
            $data = ['mode' => $mode];
            if ($mode === 'dynamic') $data += DynamicExamToken::at($seed, time());
            elseif ($mode === 'static') $data['accessCode'] = $code;
            return $this->response->setJSON(['data' => $data, 'meta' => []]);
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
