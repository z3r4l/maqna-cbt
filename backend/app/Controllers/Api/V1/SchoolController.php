<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class SchoolController extends BaseController
{
    public function logo(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $row = db_connect()->table('school_assets')->where('school_id', $context[1])->where('kind', 'logo')->get()->getRowArray();
        if ($row === null) return $this->error('NOT_FOUND', 'Logo belum diunggah.', 404);
        return $this->response->setHeader('Content-Type', $row['mime_type'])->setHeader('Cache-Control', 'private, max-age=300')->setHeader('X-Content-Type-Options', 'nosniff')->setBody($row['content']);
    }

    public function uploadLogo(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'school.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $file = $this->request->getFile('logo');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 2 * 1024 * 1024) return $this->error('VALIDATION', 'Logo PNG/JPEG maksimal 2 MB.', 422);
        $bytes = file_get_contents($file->getTempName());
        $image = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($image === false || ! in_array($image['mime'], ['image/png', 'image/jpeg'], true) || $image[0] < 1 || $image[1] < 1 || $image[0] > 3000 || $image[1] > 3000) return $this->error('VALIDATION', 'Logo PNG/JPEG tidak valid atau terlalu besar.', 422);
        $db = db_connect();
        $now = gmdate('Y-m-d H:i:s');
        $row = $db->table('school_assets')->where('school_id', $school)->where('kind', 'logo')->get()->getRowArray();
        $data = ['school_id' => $school, 'kind' => 'logo', 'mime_type' => $image['mime'], 'content' => $bytes, 'updated_at' => $now];
        if ($row === null) $db->table('school_assets')->insert($data);
        else $db->table('school_assets')->where('id', $row['id'])->update($data);
        (new \App\Services\AuditTrail($db))->record($school, (int) $user->id, 'school.logo.update', 'school', $school);
        return $this->response->setJSON(['data' => ['updatedAt' => $now], 'meta' => []]);
    }
    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [$user, (int) $profile['school_id']];
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    public function show(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [, $schoolId] = $context;
        $row = db_connect()->table('schools')->where('id', $schoolId)->get()->getRowArray();
        if ($row === null) return $this->error('NOT_FOUND', 'Sekolah tidak ditemukan.', 404);
        return $this->response->setJSON(['data' => $row, 'meta' => []]);
    }

    public function update(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $schoolId] = $context;
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'school.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['name', 'timezone']) !== []) return $this->error('VALIDATION', 'Kolom tidak diizinkan.', 422);
        $update = [];
        if (isset($input['name'])) {
            $name = trim((string) $input['name']);
            if (mb_strlen($name) < 3 || mb_strlen($name) > 160) return $this->error('VALIDATION', 'Nama sekolah harus 3–160 karakter.', 422);
            $update['name'] = $name;
        }
        if (isset($input['timezone'])) {
            if (! in_array($input['timezone'], \DateTimeZone::listIdentifiers(), true)) return $this->error('VALIDATION', 'Zona waktu tidak valid.', 422);
            $update['timezone'] = $input['timezone'];
        }
        if ($update === []) return $this->error('VALIDATION', 'Tidak ada perubahan.', 422);
        db_connect()->table('schools')->where('id', $schoolId)->update($update + ['updated_at' => gmdate('Y-m-d H:i:s')]);
        return $this->show();
    }
}
