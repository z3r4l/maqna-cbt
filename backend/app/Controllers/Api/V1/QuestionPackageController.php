<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\QuestionPackageService;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionPackageController extends BaseController
{
    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionPackageService(db_connect())];
    }

    public function export(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            return $this->response->setHeader('Content-Type', 'application/zip')->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-question-package.zip"')
                ->setHeader('Cache-Control', 'no-store')->setBody($context[3]->exportZip($context[0], $context[1], $context[2]));
        } catch (\DomainException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => $error->getMessage()]]);
        }
    }

    public function template(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-question-template.docx"')->setHeader('Cache-Control', 'no-store')->setBody($context[3]->templateDocx());
    }

    public function preview(string $kind): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        if (! in_array($kind, ['zip', 'docx'], true)) return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Jenis impor tidak ditemukan.']]);
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 10 * 1024 * 1024 || strtolower($file->getClientExtension()) !== $kind) return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Berkas ZIP/DOCX maksimal 10 MB.']]);
        try {
            $mode = (string) ($this->request->getPost('mode') ?? 'append');
            $result = $kind === 'zip' ? $context[3]->previewZip($context[0], $context[1], $context[2], $file->getTempName(), $mode)
                : $context[3]->previewDocx($context[0], $context[1], $context[2], $file->getTempName());
            return $this->response->setStatusCode(201)->setJSON(['data' => $result]);
        } catch (\DomainException|\JsonException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => $error->getMessage()]]);
        } catch (\Throwable $error) {
            return $this->response->setStatusCode(500)->setJSON(['error' => ['code' => 'INTERNAL', 'message' => 'Pratinjau arsip gagal.']]);
        }
    }

    public function commit(string $kind, string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        if (! in_array($kind, ['zip', 'docx'], true)) return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Jenis impor tidak ditemukan.']]);
        try {
            return $this->response->setJSON(['data' => $context[3]->commit($context[0], $context[1], $context[2], (int) $id, $kind)]);
        } catch (\OutOfBoundsException $error) {
            return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Pratinjau tidak ditemukan.']]);
        } catch (\Throwable $error) {
            return $this->response->setStatusCode(409)->setJSON(['error' => ['code' => 'CONFLICT', 'message' => 'Data berubah sejak pratinjau; impor dibatalkan.']]);
        }
    }
}
