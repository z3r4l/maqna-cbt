<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\QuestionVersionService;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionVersionController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionVersionService(db_connect())];
    }

    private function respond(callable $callback, int $status = 200): ResponseInterface
    {
        try { return $this->response->setStatusCode($status)->setJSON(['data' => $callback(), 'meta' => []]); }
        catch (\OutOfBoundsException $error) { return $this->error('NOT_FOUND', $error->getMessage(), 404); }
        catch (\DomainException $error) { return $this->error('VALIDATION', $error->getMessage(), 422); }
    }

    public function index(): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        $questionId = $this->request->getGet('questionId');
        if ($questionId !== null && (! ctype_digit((string) $questionId) || (int) $questionId < 1)) return $this->error('VALIDATION', 'ID soal tidak valid.', 422);
        return $this->respond(fn () => $context[3]->list($context[0], $context[1], $context[2], $questionId === null ? null : (int) $questionId));
    }

    public function create(string $questionId): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        return $this->respond(fn () => $context[3]->create($context[0], (int) $questionId, $context[1], $context[2]), 201);
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        return $this->respond(fn () => $context[3]->find($context[0], (int) $id, $context[1], $context[2]));
    }

    public function edit(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        return $this->respond(fn () => $context[3]->edit($context[0], (int) $id, $context[1], $this->request->getJSON(true) ?? []));
    }

    public function submit(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        return $this->respond(fn () => $context[3]->submit($context[0], (int) $id, $context[1]));
    }

    public function decide(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        if (! $context[2]) return $this->error('FORBIDDEN', 'Hanya peninjau sekolah yang dapat memutuskan revisi.', 403);
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['approve', 'note']) !== [] || ! is_bool($input['approve'] ?? null) || ! is_string($input['note'] ?? '') || mb_strlen($input['note'] ?? '') > 5000) return $this->error('VALIDATION', 'Keputusan tidak valid.', 422);
        return $this->respond(fn () => $context[3]->decide($context[0], (int) $id, $context[1], $input['approve'], trim(strip_tags($input['note'] ?? ''))));
    }

    public function archive(string $id): ResponseInterface
    {
        $context = $this->context(); if ($context instanceof ResponseInterface) return $context;
        return $this->respond(fn () => $context[3]->archive($context[0], (int) $id, $context[1], $context[2]));
    }
}
