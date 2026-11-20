<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ImportService;
use App\Services\QuestionKeyImportService;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionKeyImportController extends BaseController
{
    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionKeyImportService(db_connect())];
    }

    public function template(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-keys-template.csv"')->setBody((new ImportService(db_connect()))->template('keys'));
    }

    public function preview(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 2 * 1024 * 1024 || ! in_array(strtolower($file->getClientExtension()), ['csv', 'xlsx'], true)) return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Gunakan CSV/XLSX maksimal 2 MB.']]);
        try {
            return $this->response->setStatusCode(201)->setJSON(['data' => $context[3]->preview($context[0], $context[1], $context[2], $file->getTempName(), strtolower($file->getClientExtension()))]);
        } catch (\DomainException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => $error->getMessage()]]);
        } catch (\Throwable $error) {
            return $this->response->setStatusCode(500)->setJSON(['error' => ['code' => 'INTERNAL', 'message' => 'Pratinjau kunci gagal.']]);
        }
    }

    public function commit(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            return $this->response->setJSON(['data' => $context[3]->commit($context[0], $context[1], $context[2], (int) $id)]);
        } catch (\OutOfBoundsException $error) {
            return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Pratinjau tidak ditemukan.']]);
        } catch (\Throwable $error) {
            return $this->response->setStatusCode(409)->setJSON(['error' => ['code' => 'CONFLICT', 'message' => 'Soal berubah sejak pratinjau; impor kunci dibatalkan.']]);
        }
    }
}
