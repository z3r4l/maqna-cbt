<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\BulkExamStatus;
use CodeIgniter\HTTP\ResponseInterface;

final class BulkExamStatusController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'exams.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id];
    }

    public function preview(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['ids', 'target']) !== []) return $this->error('VALIDATION', 'Kolom tidak diizinkan.', 422);
        try {
            $result = (new BulkExamStatus(db_connect()))->preview($context[0], $context[1], $input['ids'] ?? [], (string) ($input['target'] ?? ''));
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\DomainException $error) { return $this->error('VALIDATION', $error->getMessage(), 422); }
    }

    public function commit(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['confirmed', 'target']) !== []) return $this->error('VALIDATION', 'Kolom tidak diizinkan.', 422);
        try {
            $result = (new BulkExamStatus(db_connect()))->commit($context[0], $context[1], (int) $id, (string) ($input['target'] ?? ''), $input);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\OutOfBoundsException $error) { return $this->error('NOT_FOUND', $error->getMessage(), 404); }
        catch (\DomainException $error) { return $this->error('CONFLICT', $error->getMessage(), 409); }
    }
}
