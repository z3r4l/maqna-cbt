<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AssignmentService;
use App\Services\AuditTrail;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;

class AssignmentController extends BaseController
{
    private function principal(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [$user, (int) $profile['school_id']];
    }

    private function admin(): array|ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user] = $context;
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'teaching.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return $context;
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function listResponse(array $result): ResponseInterface
    {
        return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total']]]);
    }

    public function teaching(): ResponseInterface
    {
        $context = $this->admin();
        if ($context instanceof ResponseInterface) return $context;
        [, $school] = $context;
        return $this->listResponse((new AssignmentService(db_connect()))->listTeaching($school, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20)));
    }

    public function createTeaching(): ResponseInterface
    {
        $context = $this->admin();
        if ($context instanceof ResponseInterface) return $context;
        [, $school] = $context;
        try {
            $row = (new AssignmentService(db_connect()))->createTeaching($school, $this->request->getJSON(true) ?? []);
            (new AuditTrail(db_connect()))->record($school, (int) $context[0]->id, 'teaching.create', 'teaching_assignment', (int) $row['id']);
            return $this->response->setStatusCode(201)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (DatabaseException $error) {
            return $this->error('CONFLICT', 'Penugasan guru sudah ada.', 409);
        }
    }

    public function toggleTeaching(string $id): ResponseInterface
    {
        $context = $this->admin();
        if ($context instanceof ResponseInterface) return $context;
        [, $school] = $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_keys($input) !== ['active'] || ! in_array($input['active'], [true, false, 0, 1], true)) return $this->error('VALIDATION', 'Status aktif wajib diisi.', 422);
        $row = (new AssignmentService(db_connect()))->setTeachingActive($school, (int) $id, (bool) $input['active']);
        if ($row !== null) (new AuditTrail(db_connect()))->record($school, (int) $context[0]->id, 'teaching.toggle', 'teaching_assignment', (int) $id, ['active' => (bool) $input['active']]);
        return $row === null ? $this->error('NOT_FOUND', 'Penugasan tidak ditemukan.', 404) : $this->response->setJSON(['data' => $row, 'meta' => []]);
    }

    public function memberships(): ResponseInterface
    {
        $context = $this->admin();
        if ($context instanceof ResponseInterface) return $context;
        [, $school] = $context;
        $studentId = $this->request->getGet('studentId');
        if ($studentId !== null && (! ctype_digit((string) $studentId) || (int) $studentId < 1)) return $this->error('VALIDATION', 'studentId tidak valid.', 422);
        return $this->listResponse((new AssignmentService(db_connect()))->listMemberships($school, $studentId === null ? null : (int) $studentId, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20)));
    }

    public function moveStudent(): ResponseInterface
    {
        $context = $this->admin();
        if ($context instanceof ResponseInterface) return $context;
        [, $school] = $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['studentId', 'classGroupId']) !== [] || ! is_numeric($input['studentId'] ?? null) || ! is_numeric($input['classGroupId'] ?? null)) return $this->error('VALIDATION', 'Siswa dan rombel wajib diisi.', 422);
        try {
            $row = (new AssignmentService(db_connect()))->moveStudent($school, (int) $input['studentId'], (int) $input['classGroupId']);
            (new AuditTrail(db_connect()))->record($school, (int) $context[0]->id, 'membership.move', 'student', (int) $input['studentId'], ['classGroupId' => (int) $input['classGroupId']]);
            return $this->response->setStatusCode(201)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        }
    }

    public function myTeaching(): ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('teacher')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $teacher = db_connect()->table('teachers')->where('user_id', $user->id)->where('school_id', $school)->where('status', 'active')->get()->getRowArray();
        if ($teacher === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return $this->listResponse((new AssignmentService(db_connect()))->listTeaching($school, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20), (int) $teacher['id']));
    }

    public function roster(string $classId): ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('teacher')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $teacher = db_connect()->table('teachers')->where('user_id', $user->id)->where('school_id', $school)->where('status', 'active')->get()->getRowArray();
        if ($teacher === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        try {
            return $this->response->setJSON(['data' => (new AssignmentService(db_connect()))->teacherRoster($school, (int) $teacher['id'], (int) $classId), 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('FORBIDDEN', 'Rombel tidak diampu.', 403);
        }
    }

    public function myClass(): ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('student')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $student = db_connect()->table('students')->where('user_id', $user->id)->where('school_id', $school)->where('status', 'active')->get()->getRowArray();
        if ($student === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $row = db_connect()->table('student_class_memberships m')->select('m.id, m.student_id, m.class_group_id, cg.name AS class_name, ay.label AS academic_year')
            ->join('class_groups cg', 'cg.id=m.class_group_id')->join('academic_years ay', 'ay.id=m.academic_year_id')
            ->where('m.student_id', $student['id'])->where('m.school_id', $school)->where('m.active', 1)->where('ay.active', 1)->get()->getRowArray();
        return $this->response->setJSON(['data' => $row, 'meta' => []]);
    }
}
