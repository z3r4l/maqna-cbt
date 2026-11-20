<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\PeopleService;
use App\Services\AuditTrail;
use App\Services\BulkPeopleService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Exceptions\ValidationException;

class PeopleController extends BaseController
{
    private function context(string $kind): int|ResponseInterface
    {
        if (! in_array($kind, ['students', 'teachers'], true)) return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'users.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        return $profile === null ? $this->error('FORBIDDEN', 'Akses ditolak.', 403) : (int) $profile['school_id'];
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    public function index(string $kind): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        $loginStatus = (string) ($this->request->getGet('loginStatus') ?? 'all');
        if (! in_array($loginStatus, ['all', 'yes', 'no'], true)) return $this->error('VALIDATION', 'Filter status login tidak valid.', 422);
        $result = (new PeopleService(db_connect()))->list($kind, $school, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20), trim((string) $this->request->getGet('search')), $loginStatus);
        return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total']]]);
    }

    public function show(string $kind, string $id): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        $row = (new PeopleService(db_connect()))->find($kind, $school, (int) $id);
        if ($row === null) return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        return $this->response->setJSON(['data' => $row, 'meta' => []]);
    }

    public function create(string $kind): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        try {
            $result = (new PeopleService(db_connect()))->create($kind, $school, $this->request->getJSON(true) ?? []);
            (new AuditTrail(db_connect()))->record($school, (int) auth('session')->user()->id, 'people.create', $kind, (int) $result['person']['id']);
            return $this->response->setStatusCode(201)->setJSON(['data' => $result, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\RuntimeException|DatabaseException|ValidationException $error) {
            return $this->error('CONFLICT', 'Nama pengguna, email, atau nomor identitas sudah digunakan.', 409);
        }
    }

    public function update(string $kind, string $id): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        try {
            $result = (new PeopleService(db_connect()))->update($kind, $school, (int) $id, $this->request->getJSON(true) ?? []);
            (new AuditTrail(db_connect()))->record($school, (int) auth('session')->user()->id, 'people.update', $kind, (int) $id);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        } catch (DatabaseException $error) {
            return $this->error('CONFLICT', 'Nomor identitas sudah digunakan.', 409);
        }
    }

    public function studentSessions(string $id): ResponseInterface
    {
        $school = $this->context('students');
        if ($school instanceof ResponseInterface) return $school;
        $student = db_connect()->table('students')->where('id', (int) $id)->where('school_id', $school)->get()->getRowArray();
        if ($student === null) return $this->error('NOT_FOUND', 'Siswa tidak ditemukan.', 404);
        $rows = db_connect()->table('student_sessions')->select('id, created_at, last_seen_at, revoked_at')
            ->where('school_id', $school)->where('student_id', (int) $id)->orderBy('last_seen_at', 'DESC')->limit(100)->get()->getResultArray();
        return $this->response->setJSON(['data' => $rows, 'meta' => []]);
    }

    public function revokeStudentSession(string $id, string $sessionId): ResponseInterface
    {
        $school = $this->context('students');
        if ($school instanceof ResponseInterface) return $school;
        $input = $this->request->getJSON(true) ?? [];
        $reason = trim((string) ($input['reason'] ?? ''));
        if (array_diff(array_keys($input), ['reason', 'confirmed']) !== [] || ($input['confirmed'] ?? false) !== true || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            return $this->error('VALIDATION', 'Konfirmasi dan alasan 10-500 karakter wajib diisi.', 422);
        }
        $student = db_connect()->table('students')->where('id', (int) $id)->where('school_id', $school)->get()->getRowArray();
        if ($student === null) return $this->error('NOT_FOUND', 'Siswa tidak ditemukan.', 404);
        $db = db_connect();
        $db->transBegin();
        try {
            $record = $db->query('SELECT * FROM student_sessions WHERE id = ? AND school_id = ? AND student_id = ? FOR UPDATE', [(int) $sessionId, $school, (int) $id])->getRowArray();
            if ($record === null) {
                $db->transRollback();
                return $this->error('NOT_FOUND', 'Sesi tidak ditemukan.', 404);
            }
            if ($record['revoked_at'] !== null) {
                $db->transRollback();
                return $this->error('CONFLICT', 'Sesi sudah dicabut.', 409);
            }
            $db->table('student_sessions')->where('id', (int) $sessionId)->update([
                'revoked_at' => gmdate('Y-m-d H:i:s'),
                'revoked_by_user_id' => (int) auth('session')->user()->id,
                'revoke_reason' => $reason,
            ]);
            (new AuditTrail($db))->record($school, (int) auth('session')->user()->id, 'student.session.revoke', 'student_session', (int) $sessionId, ['student_id' => (int) $id, 'reason' => $reason]);
            if (! $db->transStatus()) throw new \RuntimeException('Pencabutan sesi gagal.');
            $db->transCommit();
            return $this->response->setJSON(['data' => ['revoked' => true], 'meta' => []]);
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function bulkPreview(string $kind): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        try {
            $input = $this->request->getJSON(true) ?? [];
            if (array_diff(array_keys($input), ['ids']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
            if (! is_array($input['ids'] ?? null)) throw new \DomainException('Daftar ID wajib diisi.');
            $result = (new BulkPeopleService(db_connect()))->preview($school, (int) auth('session')->user()->id, $kind, $input['ids'] ?? []);
            return $this->response->setStatusCode(201)->setJSON(['data' => $result, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        }
    }

    public function bulkCommit(string $kind, string $id): ResponseInterface
    {
        $school = $this->context($kind);
        if ($school instanceof ResponseInterface) return $school;
        try {
            $result = (new BulkPeopleService(db_connect()))->commit($school, (int) auth('session')->user()->id, $kind, (int) $id, $this->request->getJSON(true) ?? []);
            return $this->response->setJSON(['data' => $result, 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }
}
