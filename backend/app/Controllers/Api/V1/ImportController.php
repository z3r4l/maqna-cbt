<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ImportService;
use CodeIgniter\HTTP\ResponseInterface;

class ImportController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(string $kind): array|ResponseInterface
    {
        if (! in_array($kind, ['students', 'teachers', 'subjects', 'questions'], true)) return $this->error('NOT_FOUND', 'Jenis impor tidak ditemukan.', 404);
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if (in_array($kind, ['students', 'teachers'], true) && (! $admin || ! \App\Services\PermissionPolicy::allows($user, 'users.manage'))) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        if ($kind === 'subjects' && (! $admin || ! \App\Services\PermissionPolicy::allows($user, 'subjects.manage'))) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        if ($kind === 'questions' && ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage'))) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new ImportService(db_connect())];
    }

    public function template(string $kind): ResponseInterface
    {
        $context = $this->context($kind);
        if ($context instanceof ResponseInterface) return $context;
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-' . $kind . '-template.csv"')
            ->setBody($context[3]->template($kind));
    }

    public function preview(string $kind): ResponseInterface
    {
        $context = $this->context($kind);
        if ($context instanceof ResponseInterface) return $context;
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 2 * 1024 * 1024) return $this->error('VALIDATION', 'Berkas CSV/XLSX wajib diisi dan maksimal 2 MB.', 422);
        $extension = mb_strtolower($file->getClientExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) return $this->error('VALIDATION', 'Gunakan CSV atau XLSX.', 422);
        try {
            $data = $context[3]->preview($context[0], $context[1], $context[2], $kind, (string) $this->request->getPost('mode'), $file->getTempName(), $extension);
            return $this->response->setStatusCode(201)->setJSON(['data' => $data, 'meta' => []]);
        } catch (\DomainException|\PhpOffice\PhpSpreadsheet\Reader\Exception $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\RuntimeException $error) {
            return $this->error('UNAVAILABLE', $error->getMessage(), 503);
        }
    }

    public function commit(string $kind, string $id): ResponseInterface
    {
        $context = $this->context($kind);
        if ($context instanceof ResponseInterface) return $context;
        $job = db_connect()->table('import_jobs')->where('id', (int) $id)->where('school_id', $context[0])->where('actor_user_id', $context[1])->where('kind', $kind)->get()->getRowArray();
        if ($job === null) return $this->error('NOT_FOUND', 'Pratinjau impor tidak ditemukan.', 404);
        try {
            $result = $context[3]->commit($context[0], $context[1], $context[2], (int) $id);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        } catch (\Throwable $error) {
            return $this->error('CONFLICT', 'Data berubah sejak pratinjau atau impor gagal; tidak ada baris yang disimpan.', 409);
        }
    }
}
