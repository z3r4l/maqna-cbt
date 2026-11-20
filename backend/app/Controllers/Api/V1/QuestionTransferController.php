<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\QuestionTransferService;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionTransferController extends BaseController
{
    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionTransferService(db_connect())];
    }

    public function preview(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            $raw = $this->request->getBody();
            if (strlen($raw) > 2 * 1024 * 1024) throw new \DomainException('JSON maksimal 2 MB.');
            $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) throw new \DomainException('JSON tidak valid.');
            return $this->response->setStatusCode(201)->setJSON(['data' => $context[3]->preview($context[0], $context[1], $context[2], $payload)]);
        } catch (\JsonException|\DomainException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Pratinjau JSON gagal: ' . $error->getMessage()]]);
        } catch (\Throwable $error) {
            return $this->response->setStatusCode(500)->setJSON(['error' => ['code' => 'INTERNAL', 'message' => 'Pratinjau JSON gagal.']]);
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
            return $this->response->setStatusCode(409)->setJSON(['error' => ['code' => 'CONFLICT', 'message' => 'Data berubah atau tidak valid sejak pratinjau; impor dibatalkan.']]);
        }
    }

    public function export(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            return $this->response->setHeader('Content-Type', 'application/json; charset=utf-8')
                ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-questions.json"')
                ->setBody(json_encode($context[3]->export($context[0], $context[1], $context[2]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (\DomainException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => $error->getMessage()]]);
        }
    }
}
