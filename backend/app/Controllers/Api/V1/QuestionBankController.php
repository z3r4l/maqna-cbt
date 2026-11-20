<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\PermissionPolicy;
use App\Services\QuestionBankContainer;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionBankController extends BaseController
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
        if ((! $admin && ! $user->inGroup('teacher')) || ! PermissionPolicy::allows($user, 'questions.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionBankContainer(db_connect())];
    }

    private function owned(array $context, int $id): array|ResponseInterface
    {
        $bank = $context[3]->find($context[0], $id, $context[2] ? null : $context[1]);
        return $bank ?? $this->error('NOT_FOUND', 'Bank soal tidak ditemukan.', 404);
    }

    public function index(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        foreach (['subjectId', 'classGroupId'] as $field) {
            $value = $this->request->getGet($field);
            if ($value !== null && (! ctype_digit((string) $value) || (int) $value < 1)) return $this->error('VALIDATION', "$field tidak valid.", 422);
        }
        $result = $context[3]->list($context[0], $context[2] ? null : $context[1],
            (int) ($this->request->getGet('page') ?? 1), (int) ($this->request->getGet('perPage') ?? 20),
            trim((string) $this->request->getGet('search')),
            $this->request->getGet('subjectId') === null ? null : (int) $this->request->getGet('subjectId'),
            $this->request->getGet('classGroupId') === null ? null : (int) $this->request->getGet('classGroupId'));
        return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total']]]);
    }

    public function masters(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin] = $context;
        $db = db_connect();
        $subjects = $db->table('subjects')->select('id, code, name')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        $classes = $db->table('class_groups cg')->select('cg.id, cg.code, cg.name')
            ->join('academic_years ay', 'ay.id=cg.academic_year_id')->where('cg.school_id', $school)
            ->where('cg.active', 1)->where('ay.active', 1)->orderBy('cg.name')->get()->getResultArray();
        if (! $admin) {
            $assignments = $db->table('teaching_assignments ta')->select('ta.subject_id, ta.class_group_id')
                ->join('teachers t', 't.id=ta.teacher_id')->join('semesters se', 'se.id=ta.semester_id')
                ->join('academic_years ay', 'ay.id=se.academic_year_id')
                ->where('ta.school_id', $school)->where('t.user_id', $user)->where('ta.active', 1)
                ->where('se.active', 1)->where('ay.active', 1)->get()->getResultArray();
            $subjects = array_values(array_filter($subjects, static fn ($row) => in_array($row['id'], array_column($assignments, 'subject_id'))));
            $classes = array_values(array_filter($classes, static fn ($row) => in_array($row['id'], array_column($assignments, 'class_group_id'))));
        }
        $types = $db->table('exam_types')->select('id, code, name')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        return $this->response->setJSON(['data' => ['subjects' => $subjects, 'classes' => $classes, 'examTypes' => $types], 'meta' => []]);
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $bank = $this->owned($context, (int) $id);
        if ($bank instanceof ResponseInterface) return $bank;
        return $this->response->setJSON(['data' => $context[3]->detail($bank), 'meta' => []]);
    }

    public function create(): ResponseInterface { return $this->save(null); }
    public function update(string $id): ResponseInterface { return $this->save((int) $id); }

    private function save(?int $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        if ($id !== null && $this->owned($context, $id) instanceof ResponseInterface) return $this->error('NOT_FOUND', 'Bank soal tidak ditemukan.', 404);
        try {
            $bank = $context[3]->save($context[0], $context[1], $context[2], $this->request->getJSON(true) ?? [], $id);
            (new AuditTrail(db_connect()))->record($context[0], $context[1], $id === null ? 'question_banks.create' : 'question_banks.update', 'question_bank', (int) $bank['id']);
            return $this->response->setStatusCode($id === null ? 201 : 200)->setJSON(['data' => $bank, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Bank soal tidak ditemukan.', 404);
        }
    }

    public function archive(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $bank = $this->owned($context, (int) $id);
        if ($bank instanceof ResponseInterface) return $bank;
        if ($bank['default_key'] !== null) return $this->error('CONFLICT', 'Wadah soal bawaan tidak dapat diarsipkan.', 409);
        $context[3]->archive($context[0], (int) $id, $context[2] ? null : $context[1]);
        (new AuditTrail(db_connect()))->record($context[0], $context[1], 'question_banks.archive', 'question_bank', (int) $id);
        return $this->response->setJSON(['data' => ['archived' => true], 'meta' => []]);
    }
}
