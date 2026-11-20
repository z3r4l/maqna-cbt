<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class AuditController extends BaseController
{
    public function index(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if (! $admin && ! $user->inGroup('teacher')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $page = max(1, (int) $this->request->getGet('page'));
        $query = db_connect()->table('audit_events ae')->select('ae.id, ae.actor_user_id, u.username, ae.action, ae.target_type, ae.target_id, ae.metadata_json, ae.created_at')
            ->join('users u', 'u.id=ae.actor_user_id')->where('ae.school_id', $profile['school_id']);
        if (! $admin) $query->where('ae.actor_user_id', $user->id);
        $total = $query->countAllResults(false);
        $rows = $query->orderBy('ae.id', 'DESC')->limit(50, ($page - 1) * 50)->get()->getResultArray();
        foreach ($rows as &$row) $row['metadata'] = json_decode($row['metadata_json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        unset($row);
        foreach ($rows as &$row) unset($row['metadata_json']);
        return $this->response->setJSON(['data' => $rows, 'meta' => ['page' => $page, 'perPage' => 50, 'total' => $total]]);
    }
}
