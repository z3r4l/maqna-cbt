<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\SessionPolicy;
use CodeIgniter\HTTP\ResponseInterface;

final class SessionPolicyController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'school.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $user->inGroup('superadmin')];
    }

    public function index(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $service = new SessionPolicy(db_connect());
        $rows = [];
        foreach (SessionPolicy::ROLES as $role) {
            if ($role === 'superadmin' && ! $context[2]) continue;
            $rows[] = ['role' => $role, 'maxSessions' => $service->limit($context[0], $role)];
        }
        return $this->response->setJSON(['data' => $rows, 'meta' => []]);
    }

    public function update(string $role): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (! in_array($role, SessionPolicy::ROLES, true) || ($role === 'superadmin' && ! $context[2]) || array_diff(array_keys($input), ['maxSessions']) !== [] || ! isset($input['maxSessions']) || ! is_int($input['maxSessions']) || $input['maxSessions'] < 0 || $input['maxSessions'] > 5) return $this->error('VALIDATION', 'Batas sesi harus 0 sampai 5.', 422);
        $db = db_connect();
        $row = $db->table('school_session_policies')->where('school_id', $context[0])->where('role', $role)->get()->getRowArray();
        $data = ['school_id' => $context[0], 'role' => $role, 'max_sessions' => $input['maxSessions'], 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($row === null) $db->table('school_session_policies')->insert($data);
        else $db->table('school_session_policies')->where('id', $row['id'])->update($data);
        (new AuditTrail($db))->record($context[0], $context[1], 'sessions.policy.update', 'school', $context[0], ['role' => $role, 'maxSessions' => $input['maxSessions']]);
        return $this->response->setJSON(['data' => ['role' => $role, 'maxSessions' => $input['maxSessions']], 'meta' => []]);
    }
}
