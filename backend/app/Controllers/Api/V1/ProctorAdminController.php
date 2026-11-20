<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;

class ProctorAdminController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'users.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        return $profile === null ? $this->error('FORBIDDEN', 'Akses ditolak.', 403) : [(int) $profile['school_id'], (int) $user->id];
    }

    public function index(): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        $rows = db_connect()->table('user_profiles up')->select('up.user_id, up.display_name, up.active, u.username')
            ->join('users u', 'u.id=up.user_id')->join('auth_groups_users agu', "agu.user_id=up.user_id AND agu.group='proctor'")
            ->where('up.school_id', $context[0])->orderBy('up.display_name')->get()->getResultArray();
        return $this->response->setJSON(['data' => $rows, 'meta' => []]);
    }

    public function create(): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['username', 'email', 'displayName']) !== []) return $this->error('VALIDATION', 'Kolom tidak diizinkan.', 422);
        $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $name = trim((string) ($input['displayName'] ?? ''));
        if (! preg_match('/\A[a-z0-9.]{3,30}\z/', $username) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 2 || mb_strlen($name) > 120) return $this->error('VALIDATION', 'Identitas pengawas tidak valid.', 422);
        $db = db_connect();
        $model = new UserModel();
        if ($model->where('username', $username)->first() !== null) return $this->error('CONFLICT', 'Username sudah digunakan.', 409);
        $password = bin2hex(random_bytes(12));
        $db->transBegin();
        try {
            $entity = $model->createNewUser(['username' => $username, 'email' => $email, 'password' => $password]);
            $userId = (int) $model->insert($entity);
            $model->find($userId)->addGroup('proctor');
            $db->table('user_profiles')->insert(['user_id' => $userId, 'school_id' => $context[0], 'display_name' => $name, 'active' => 1, 'must_change_password' => 1]);
            (new AuditTrail($db))->record($context[0], $context[1], 'proctors.create', 'user', $userId);
            if (! $db->transStatus()) throw new \RuntimeException('Pengawas gagal dibuat.');
            $db->transCommit();
            return $this->response->setStatusCode(201)->setJSON(['data' => ['userId' => $userId, 'temporaryPassword' => $password], 'meta' => []]);
        } catch (\Throwable $error) {
            $db->transRollback();
            return $this->error('CONFLICT', 'Username atau email sudah digunakan.', 409);
        }
    }

    public function assignedExams(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('proctor') || ! \App\Services\PermissionPolicy::allows($user, 'monitor.view')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $rows = db_connect()->table('exam_proctors ep')->select('e.id, e.title, e.start_at, e.end_at')
            ->join('exams e', 'e.id=ep.exam_id AND e.school_id=ep.school_id')
            ->where('ep.school_id', $profile['school_id'])->where('ep.user_id', $user->id)->where('ep.active', 1)->where('e.status', 'published')->orderBy('e.start_at', 'DESC')->get()->getResultArray();
        return $this->response->setJSON(['data' => $rows, 'meta' => []]);
    }

    public function assign(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['userId']) !== [] || ! is_int($input['userId'] ?? null) || $input['userId'] < 1) return $this->error('VALIDATION', 'Pengawas tidak valid.', 422);
        $db = db_connect();
        $exam = $db->table('exams')->where('id', (int) $id)->where('school_id', $context[0])->where('status', 'published')->get()->getRowArray();
        $profile = $db->table('user_profiles')->where('user_id', $input['userId'])->where('school_id', $context[0])->where('active', 1)->get()->getRowArray();
        $group = $db->table('auth_groups_users')->where('user_id', $input['userId'])->where('group', 'proctor')->countAllResults();
        if ($exam === null || $profile === null || $group !== 1) return $this->error('VALIDATION', 'Ujian atau pengawas tidak sesuai sekolah.', 422);
        $old = $db->table('exam_proctors')->where('exam_id', (int) $id)->where('user_id', $input['userId'])->get()->getRowArray();
        $now = gmdate('Y-m-d H:i:s');
        if ($old === null) $db->table('exam_proctors')->insert(['school_id' => $context[0], 'exam_id' => (int) $id, 'user_id' => $input['userId'], 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        else $db->table('exam_proctors')->where('id', $old['id'])->update(['active' => 1, 'updated_at' => $now]);
        (new AuditTrail($db))->record($context[0], $context[1], 'proctors.assign', 'exam', (int) $id, ['user_id' => $input['userId']]);
        return $this->response->setJSON(['data' => ['assigned' => true], 'meta' => []]);
    }
}
