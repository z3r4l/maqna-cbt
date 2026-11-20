<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AttemptService;
use App\Services\ExamService;
use App\Services\ProctorService;
use App\Services\QuestionAnalyticsService;
use App\Services\AuditTrail;
use CodeIgniter\HTTP\ResponseInterface;

class ProctorController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function principal(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [$user, (int) $profile['school_id']];
    }

    public function heartbeat(string $id): ResponseInterface
    {
        $context = $this->principal(); if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('student') || ! \App\Services\PermissionPolicy::allows($user, 'attempts.take')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $student = db_connect()->table('students')->where('school_id', $school)->where('user_id', $user->id)->where('status', 'active')->get()->getRowArray();
        if ($student === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['event']) !== [] || (isset($input['event']) && ! is_string($input['event']))) return $this->error('VALIDATION', 'Peristiwa tidak valid.', 422);
        $device = (string) $this->request->getHeaderLine('X-Attempt-Device');
        if ($device === '' || ! preg_match('/^[A-Za-z0-9_-]{20,128}$/', $device)) $device = 'session:' . session_id();
        try {
            $result = (new ProctorService(db_connect()))->heartbeat($school, (int) $student['id'], (int) $id, $device, $input['event'] ?? null);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\InvalidArgumentException $error) { return $this->error('VALIDATION', $error->getMessage(), 422); }
        catch (\OutOfBoundsException $error) { return $this->error('NOT_FOUND', $error->getMessage(), 404); }
        catch (\DomainException $error) { return $this->error('CONFLICT', $error->getMessage(), 409); }
    }

    private function authorizedExam(string $id, string $permission): array|ResponseInterface
    {
        $context = $this->principal(); if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        $admin = $user->inGroup('school_admin', 'superadmin');
        $proctor = $user->inGroup('proctor');
        if ((! $admin && ! $user->inGroup('teacher') && ! $proctor) || ! \App\Services\PermissionPolicy::allows($user, $permission)) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $examService = new ExamService(db_connect());
        $exam = $examService->find($school, (int) $id);
        if ($exam === null) return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        if (! $admin && $proctor) {
            $assigned = db_connect()->table('exam_proctors')->where('school_id', $school)->where('exam_id', (int) $id)->where('user_id', $user->id)->where('active', 1)->countAllResults();
            if ($assigned < 1) return $this->error('FORBIDDEN', 'Ujian di luar penugasan.', 403);
        } elseif (! $admin && ((int) $exam['author_user_id'] !== (int) $user->id || ! $examService->canTeachAll($school, (int) $user->id, $exam))) return $this->error('FORBIDDEN', 'Ujian di luar penugasan.', 403);
        return [$school, $exam];
    }

    public function roster(string $id): ResponseInterface
    {
        $context = $this->authorizedExam($id, 'monitor.view'); if ($context instanceof ResponseInterface) return $context;
        $loginStatus = (string) ($this->request->getGet('loginStatus') ?? 'all');
        if (! in_array($loginStatus, ['all', 'yes', 'no'], true)) return $this->error('VALIDATION', 'Filter status login tidak valid.', 422);
        return $this->response->setJSON(['data' => (new ProctorService(db_connect()))->roster($context[0], (int) $id, $loginStatus), 'meta' => []]);
    }

    public function revokeSession(string $id, string $studentId, string $sessionId): ResponseInterface
    {
        $context = $this->authorizedExam($id, 'monitor.view'); if ($context instanceof ResponseInterface) return $context;
        $user = auth('session')->user();
        if (! $user->inGroup('proctor', 'school_admin', 'superadmin')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $input = $this->request->getJSON(true) ?? [];
        $reason = trim((string) ($input['reason'] ?? ''));
        if (array_diff(array_keys($input), ['confirmed', 'reason']) !== [] || ($input['confirmed'] ?? false) !== true || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) return $this->error('VALIDATION', 'Konfirmasi dan alasan 10-500 karakter wajib diisi.', 422);
        $db = db_connect();
        $assigned = $db->table('exam_assignments')->where('school_id', $context[0])->where('exam_id', (int) $id)->where('student_id', (int) $studentId)->countAllResults();
        if ($assigned < 1) return $this->error('NOT_FOUND', 'Peserta tidak ditugaskan.', 404);
        $record = $db->table('student_sessions')->where('id', (int) $sessionId)->where('school_id', $context[0])->where('student_id', (int) $studentId)->where('revoked_at', null)->get()->getRowArray();
        if ($record === null) return $this->error('NOT_FOUND', 'Sesi aktif tidak ditemukan.', 404);
        $db->transBegin();
        try {
            $db->table('student_sessions')->where('id', (int) $sessionId)->where('revoked_at', null)->update(['revoked_at' => gmdate('Y-m-d H:i:s'), 'revoked_by_user_id' => $user->id, 'revoke_reason' => $reason]);
            if ($db->affectedRows() !== 1) throw new \DomainException('Sesi berubah. Muat ulang.');
            (new AuditTrail($db))->record($context[0], (int) $user->id, 'student.session.revoke', 'student_session', (int) $sessionId, ['exam_id' => (int) $id, 'student_id' => (int) $studentId, 'reason' => $reason]);
            $db->transCommit();
            return $this->response->setJSON(['data' => ['revoked' => true], 'meta' => []]);
        } catch (\DomainException $error) {
            $db->transRollback();
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }

    public function analytics(string $id): ResponseInterface
    {
        $context = $this->authorizedExam($id, 'reports.view'); if ($context instanceof ResponseInterface) return $context;
        return $this->response->setJSON(['data' => (new QuestionAnalyticsService(db_connect()))->forExam($context[0], (int) $id), 'meta' => []]);
    }
}
