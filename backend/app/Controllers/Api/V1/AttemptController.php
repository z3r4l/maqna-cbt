<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AttemptService;
use CodeIgniter\HTTP\ResponseInterface;

class AttemptController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('student') || ! \App\Services\PermissionPolicy::allows($user, 'attempts.take')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $student = db_connect()->table('students')->where('school_id', $profile['school_id'])->where('user_id', $user->id)->where('status', 'active')->get()->getRowArray();
        if ($student === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $student['id'], new AttemptService(db_connect())];
    }

    private function device(): string
    {
        $header = (string) $this->request->getHeaderLine('X-Attempt-Device');
        return $header !== '' && preg_match('/^[A-Za-z0-9_-]{20,128}$/', $header) ? $header : 'session:' . session_id();
    }

    public function start(string $examId): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            $input = $this->request->getJSON(true) ?? [];
            if (array_diff(array_keys($input), ['accessCode']) !== [] || ! is_string($input['accessCode'] ?? '')) return $this->error('VALIDATION', 'Kode akses tidak valid.', 422);
            return $this->response->setJSON(['data' => $context[2]->start($context[0], $context[1], (int) $examId, $this->device(), $input['accessCode'] ?? ''), 'meta' => []]);
        } catch (\OverflowException $error) {
            return $this->error('RATE_LIMITED', $error->getMessage(), 429);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $attempt = $context[2]->find($context[0], $context[1], (int) $id);
        if ($attempt === null) return $this->error('NOT_FOUND', 'Percobaan tidak ditemukan.', 404);
        if (! $context[2]->deviceAllowed($attempt, $this->device())) return $this->error('CONFLICT', 'Percobaan aktif pada perangkat lain.', 409);
        return $this->response->setJSON(['data' => $context[2]->detail($context[2]->expireOne($attempt)), 'meta' => []]);
    }

    public function saveAnswer(string $id, string $itemId): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            $result = $context[2]->saveAnswer($context[0], $context[1], (int) $id, (int) $itemId, $this->device(), $this->request->getJSON(true) ?? []);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\InvalidArgumentException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }

    public function submit(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        try {
            $result = $context[2]->submit($context[0], $context[1], (int) $id, $this->device());
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }

    public function asset(string $id, string $assetId): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $attempt = $context[2]->find($context[0], $context[1], (int) $id);
        if ($attempt === null) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        if ($attempt['status'] !== 'active' || $attempt['deadline_at'] <= gmdate('Y-m-d H:i:s')) return $this->error('FORBIDDEN', 'Media ujian tidak tersedia.', 403);
        $snapshots = db_connect()->table('exam_items ei')->select('ei.snapshot_json')->join('attempt_items ai', 'ai.exam_item_id=ei.id')
            ->where('ai.attempt_id', (int) $id)->where('ei.exam_id', $attempt['exam_id'])->get()->getResultArray();
        $included = false;
        foreach ($snapshots as $row) {
            $snapshot = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($snapshot['assets'] ?? [] as $asset) if ((int) ($asset['id'] ?? 0) === (int) $assetId) $included = true;
        }
        $asset = $included ? db_connect()->table('question_assets')->select('stored_name, mime_type')
            ->where('id', (int) $assetId)->where('school_id', $context[0])->where('archived_at', null)->get()->getRowArray() : null;
        if ($asset === null) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        $path = WRITEPATH . 'uploads/questions/' . $asset['stored_name'];
        if (! is_file($path)) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        return $this->response->setHeader('Content-Type', $asset['mime_type'])->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->setHeader('Content-Disposition', 'inline')->setBody(file_get_contents($path));
    }
}
