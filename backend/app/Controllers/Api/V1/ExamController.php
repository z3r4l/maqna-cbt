<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ExamService;
use App\Services\AuditTrail;
use CodeIgniter\HTTP\ResponseInterface;

class ExamController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function staff(bool $publish = false): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $admin = $user->inGroup('superadmin', 'school_admin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, $publish ? 'exams.publish' : 'exams.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [(int) $profile['school_id'], (int) $user->id, $admin, new ExamService(db_connect())];
    }

    private function owned(array $context, int $id): array|ResponseInterface
    {
        [$school, $user, $admin, $service] = $context;
        $exam = $service->find($school, $id);
        if ($exam === null || ! $service->isAllowed($school, $user, $admin, $exam)) return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        return $exam;
    }

    public function index(): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $service] = $context;
        $subjectId = (string) ($this->request->getGet('subjectId') ?? '');
        $classId = (string) ($this->request->getGet('classGroupId') ?? '');
        foreach (['subjectId' => $subjectId, 'classGroupId' => $classId] as $key => $value) {
            if ($value !== '' && (! ctype_digit($value) || (int) $value < 1)) return $this->error('VALIDATION', "$key tidak valid.", 422);
        }
        try {
            $result = $service->list($school, $admin ? null : $user, (int) ($this->request->getGet('page') ?? 1), (int) ($this->request->getGet('perPage') ?? 20),
                trim((string) $this->request->getGet('search')), $subjectId, $classId, (string) ($this->request->getGet('status') ?? 'all'));
            return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total'], 'counts' => $result['counts'], 'serverNow' => $result['serverNow']]]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        }
    }

    public function masters(): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin] = $context;
        $subjects = db_connect()->table('subjects')->select('id, name')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        $semesters = db_connect()->table('semesters')->select('id, name, academic_year_id')->where('school_id', $school)->where('active', 1)->get()->getResultArray();
        $classes = db_connect()->table('class_groups')->select('id, name, academic_year_id')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        $examTypes = db_connect()->table('exam_types')->select('id, code, name')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        if (! $admin) {
            $assignments = db_connect()->table('teaching_assignments ta')->select('ta.subject_id, ta.semester_id, ta.class_group_id')
                ->join('teachers t', 't.id=ta.teacher_id')->where('ta.school_id', $school)->where('t.user_id', $user)->where('ta.active', 1)->get()->getResultArray();
            $subjects = array_values(array_filter($subjects, static fn ($row) => in_array($row['id'], array_column($assignments, 'subject_id'))));
            $semesters = array_values(array_filter($semesters, static fn ($row) => in_array($row['id'], array_column($assignments, 'semester_id'))));
            $classes = array_values(array_filter($classes, static fn ($row) => in_array($row['id'], array_column($assignments, 'class_group_id'))));
        }
        return $this->response->setJSON(['data' => ['subjects' => $subjects, 'semesters' => $semesters, 'classes' => $classes, 'examTypes' => $examTypes], 'meta' => []]);
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->owned($context, (int) $id);
        return $exam instanceof ResponseInterface ? $exam : $this->response->setJSON(['data' => $context[3]->detail($exam), 'meta' => []]);
    }

    public function create(): ResponseInterface
    {
        return $this->save(null);
    }

    public function createFromBank(): ResponseInterface
    {
        return $this->saveFromBank(null);
    }

    public function updateFromBank(string $id): ResponseInterface
    {
        return $this->saveFromBank((int) $id);
    }

    private function saveFromBank(?int $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $service] = $context;
        try {
            $row = $service->saveFromBank($school, $user, $admin, $this->request->getJSON(true) ?? [], $id);
            $audit = new AuditTrail(db_connect());
            $audit->record($school, $user, $id === null ? 'exams.create_from_bank' : 'exams.update_from_bank', 'exam', (int) $row['id'], ['bank_id' => (int) $row['source_bank_id'], 'status' => $row['status']]);
            if ($row['status'] === 'published') $audit->record($school, $user, 'exams.publish', 'exam', (int) $row['id'], ['participants' => $row['participantCount']]);
            return $this->response->setStatusCode($id === null ? 201 : 200)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Bank soal atau ujian tidak ditemukan.', 404);
        }
    }

    public function schedule(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $service] = $context;
        try {
            $row = $service->updatePublishedSchedule($school, $user, $admin, (int) $id, $this->request->getJSON(true) ?? []);
            (new AuditTrail(db_connect()))->record($school, $user, 'exams.schedule.update', 'exam', (int) $id);
            return $this->response->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        }
    }

    public function archive(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        try {
            $context[3]->archiveDraft($context[0], $context[1], $context[2], (int) $id);
            (new AuditTrail(db_connect()))->record($context[0], $context[1], 'exams.archive_draft', 'exam', (int) $id);
            return $this->response->setJSON(['data' => ['archived' => true], 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        }
    }

    public function update(string $id): ResponseInterface
    {
        return $this->save((int) $id);
    }

    private function save(?int $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        if ($id !== null && $this->owned($context, $id) instanceof ResponseInterface) return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        [$school, $user, $admin, $service] = $context;
        try {
            $row = $service->save($school, $user, $admin, $this->request->getJSON(true) ?? [], $id);
            (new AuditTrail(db_connect()))->record($school, $user, $id === null ? 'exams.create' : 'exams.update', 'exam', (int) $row['id']);
            return $this->response->setStatusCode($id === null ? 201 : 200)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        }
    }

    public function roster(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->owned($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        if (! $context[2] && ! $context[3]->canTeachAll($context[0], $context[1], $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        return $this->response->setJSON(['data' => $context[3]->rosterPreview($context[0], (int) $id), 'meta' => []]);
    }

    public function previewQuestions(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->owned($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        if (! $context[2] && ! $context[3]->canTeachAll($context[0], $context[1], $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        $db = db_connect(); $rows = [];
        if ($exam['status'] === 'published') {
            $items = $db->table('exam_items')->select('id, snapshot_json')->where('exam_id', (int) $id)->orderBy('sort_order')->get()->getResultArray();
            foreach ($items as $position => $item) {
                $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                $snapshot['matchingChoices'] = $snapshot['matching_choices'] ?? [];
                $rows[] = $this->safePreviewItem($snapshot, (int) $item['id'], $position + 1);
            }
        } else {
            $query = $db->table('questions q')->select('q.id, q.body, q.question_type')->where('q.school_id', $context[0])->where('q.status', 'published')->where('q.deleted_at', null);
            if (! empty($exam['source_bank_id'])) $query->where('q.bank_id', (int) $exam['source_bank_id']);
            else $query->join('exam_questions eq', 'eq.question_id=q.id')->where('eq.exam_id', (int) $id)->orderBy('eq.sort_order');
            if (! empty($exam['source_bank_id'])) $query->orderBy('q.id');
            foreach ($query->get()->getResultArray() as $position => $item) {
                $options = $db->table('question_options')->select('id, body, match_choice_id, match_body')->where('question_id', $item['id'])->orderBy('sort_order')->get()->getResultArray();
                $choices = $db->table('question_matching_choices')->select('id, body')->where('question_id', $item['id'])->orderBy('sort_order')->get()->getResultArray();
                if ($item['question_type'] === 'matching' && $choices === []) {
                    foreach ($options as &$option) { $choices[] = ['id' => (int) $option['id'], 'body' => (string) ($option['match_body'] ?? '')]; }
                    unset($option);
                }
                $assets = $db->table('question_assets')->select('id, original_name')->where('question_id', $item['id'])->where('archived_at', null)->get()->getResultArray();
                $rows[] = $this->safePreviewItem(['body' => $item['body'], 'type' => $item['question_type'], 'options' => $options, 'matchingChoices' => $choices, 'assets' => $assets], (int) $item['id'], $position + 1);
            }
        }
        $eligible = count($rows); $limit = $exam['display_question_count'] === null ? $eligible : max(0, (int) $exam['display_question_count']);
        if (! empty($exam['shuffle_questions'])) shuffle($rows);
        $rows = array_slice($rows, 0, $limit);
        if (! empty($exam['shuffle_options'])) foreach ($rows as &$row) { shuffle($row['options']); shuffle($row['choices']); } unset($row);
        $response = $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['data' => ['title' => $exam['title'], 'items' => array_values($rows), 'eligibleQuestionCount' => $eligible, 'displayQuestionCount' => min($limit, $eligible), 'shuffleQuestions' => (bool) $exam['shuffle_questions'], 'shuffleOptions' => (bool) $exam['shuffle_options']], 'meta' => []]);
        return $response;
    }

    private function safePreviewItem(array $item, int $id, int $order): array
    {
        return ['id' => $id, 'order' => $order, 'body' => (string) ($item['body'] ?? ''), 'type' => (string) ($item['type'] ?? ''),
            'options' => array_map(static fn ($option) => ['id' => (int) ($option['id'] ?? 0), 'body' => (string) ($option['body'] ?? '')], $item['options'] ?? []),
            'choices' => array_map(static fn ($choice) => ['id' => (int) ($choice['id'] ?? 0), 'body' => (string) ($choice['body'] ?? '')], $item['matchingChoices'] ?? $item['choices'] ?? []),
            'assets' => array_map(static fn ($asset) => ['id' => (int) $asset['id'], 'original_name' => (string) $asset['original_name']], $item['assets'] ?? [])];
    }

    public function previewAsset(string $id, string $assetId): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->owned($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        if (! $context[2] && ! $context[3]->canTeachAll($context[0], $context[1], $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        $db = db_connect(); $assetId = (int) $assetId; $included = false; $archivedAllowed = false;
        if ($exam['status'] === 'published') {
            foreach ($db->table('exam_items')->select('snapshot_json')->where('exam_id', (int) $id)->get()->getResultArray() as $item) {
                $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($snapshot['assets'] ?? [] as $asset) if ((int) ($asset['id'] ?? 0) === $assetId) { $included = true; $archivedAllowed = true; }
            }
        } else {
            $query = $db->table('question_assets qa')->join('questions q', 'q.id=qa.question_id')->where('qa.id', $assetId)->where('qa.school_id', $context[0])->where('qa.archived_at', null)->where('q.status', 'published')->where('q.deleted_at', null);
            if (! empty($exam['source_bank_id'])) $query->where('q.bank_id', (int) $exam['source_bank_id']);
            else $query->join('exam_questions eq', 'eq.question_id=q.id')->where('eq.exam_id', (int) $id);
            $included = $query->countAllResults() > 0;
        }
        $asset = $included ? $db->table('question_assets')->select('stored_name, mime_type, archived_at')->where('id', $assetId)->where('school_id', $context[0])->get()->getRowArray() : null;
        if ($asset !== null && $asset['archived_at'] !== null && ! $archivedAllowed) $asset = null;
        if ($asset === null) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        $path = WRITEPATH . 'uploads/questions/' . $asset['stored_name'];
        if (! is_file($path)) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        return $this->response->setHeader('Content-Type', $asset['mime_type'])->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")->setHeader('Cache-Control', 'private, no-store')->setHeader('Content-Disposition', 'inline')->setBody(file_get_contents($path));
    }

    public function assignments(string $id): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->owned($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        if (! $context[2] && ! $context[3]->canTeachAll($context[0], $context[1], $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        try {
            return $this->response->setJSON(['data' => $context[3]->assignmentRoster($context[0], (int) $id), 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        }
    }

    public function assignParticipant(string $id, string $studentId): ResponseInterface
    {
        $context = $this->staff();
        if ($context instanceof ResponseInterface) return $context;
        if (! $context[2]) return $this->error('FORBIDDEN', 'Hanya admin sekolah dapat mengatur penempatan.', 403);
        try {
            $row = $context[3]->assignParticipant($context[0], (int) $id, (int) $studentId, $this->request->getJSON(true) ?? []);
            (new AuditTrail(db_connect()))->record($context[0], $context[1], 'exams.participant.assign', 'exam_assignment', (int) $row['id'], ['exam_id' => (int) $id, 'student_id' => (int) $studentId, 'exam_room_id' => $row['exam_room_id'], 'participant_number' => $row['participant_number']]);
            return $this->response->setJSON(['data' => $row, 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\LogicException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        }
    }

    public function publish(string $id): ResponseInterface
    {
        $context = $this->staff(true);
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $service] = $context;
        try {
            $row = $service->publish($school, $user, $admin, (int) $id);
            (new AuditTrail(db_connect()))->record($school, $user, 'exams.publish', 'exam', (int) $id, ['participants' => $row['participantCount']]);
            return $this->response->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        }
    }

    public function studentIndex(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('student') || ! \App\Services\PermissionPolicy::allows($user, 'attempts.take')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $student = db_connect()->table('students')->where('user_id', $user->id)->where('school_id', $profile['school_id'])->where('status', 'active')->get()->getRowArray();
        if ($student === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return $this->response->setJSON(['data' => (new ExamService(db_connect()))->studentList((int) $profile['school_id'], (int) $student['id']), 'meta' => []]);
    }
}
