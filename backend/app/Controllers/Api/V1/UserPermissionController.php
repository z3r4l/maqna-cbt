<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\PermissionPolicy;
use CodeIgniter\HTTP\ResponseInterface;

final class UserPermissionController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function target(int $id): array|ResponseInterface
    {
        $actor = auth('session')->user();
        if ($actor === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $actor->inGroup('school_admin', 'superadmin') || ! PermissionPolicy::allows($actor, 'users.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $db = db_connect();
        $actorProfile = $db->table('user_profiles')->where('user_id', $actor->id)->where('active', 1)->get()->getRowArray();
        $profile = $db->table('user_profiles')->where('user_id', $id)->where('active', 1)->get()->getRowArray();
        if ($actorProfile === null || $profile === null || (int) $actorProfile['school_id'] !== (int) $profile['school_id']) return $this->error('NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        $target = auth('session')->getProvider()->findById($id);
        if ($target === null || (! $target->inGroup('teacher') && ! $target->inGroup('proctor')) || $target->inGroup('school_admin', 'superadmin')) return $this->error('VALIDATION', 'Izin hanya dapat diatur untuk guru dan pengawas.', 422);
        return [$db, $actor, $target, (int) $profile['school_id']];
    }

    public function index(int $id): ResponseInterface
    {
        $context = $this->target($id);
        if ($context instanceof ResponseInterface) return $context;
        [$db, , $target, $school] = $context;
        $rules = $db->table('user_permission_rules')->where('school_id', $school)->where('user_id', $id)->get()->getResultArray();
        $denied = array_column(array_filter($rules, static fn ($row) => (int) $row['denied'] === 1), 'permission');
        $rows = [];
        foreach (PermissionPolicy::STAFF_PERMISSIONS as $permission) {
            if ($target->can($permission)) $rows[] = ['permission' => $permission, 'allowed' => ! in_array($permission, $denied, true)];
        }
        return $this->response->setJSON(['data' => $rows, 'meta' => []]);
    }

    public function update(int $id): ResponseInterface
    {
        $context = $this->target($id);
        if ($context instanceof ResponseInterface) return $context;
        [$db, $actor, $target, $school] = $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['permission', 'allowed']) !== [] || ! is_string($input['permission'] ?? null) || ! is_bool($input['allowed'] ?? null) || ! in_array($input['permission'], PermissionPolicy::STAFF_PERMISSIONS, true) || ! $target->can($input['permission'])) return $this->error('VALIDATION', 'Izin tidak valid.', 422);
        $where = ['school_id' => $school, 'user_id' => $id, 'permission' => $input['permission']];
        $existing = $db->table('user_permission_rules')->where($where)->get()->getRowArray();
        if ($existing === null) $db->table('user_permission_rules')->insert($where + ['denied' => $input['allowed'] ? 0 : 1, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        else $db->table('user_permission_rules')->where('id', $existing['id'])->update(['denied' => $input['allowed'] ? 0 : 1, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        (new AuditTrail($db))->record($school, (int) $actor->id, 'users.permission.update', 'user', $id, ['permission' => $input['permission'], 'allowed' => $input['allowed']]);
        return $this->response->setJSON(['data' => ['permission' => $input['permission'], 'allowed' => $input['allowed']], 'meta' => []]);
    }
}
