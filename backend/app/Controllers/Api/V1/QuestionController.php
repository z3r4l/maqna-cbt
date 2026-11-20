<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\QuestionBank;
use App\Services\AuditTrail;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionController extends BaseController
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
        return [(int) $profile['school_id'], (int) $user->id, $admin, new QuestionBank(db_connect())];
    }

    private function owned(array $context, int $id): array|ResponseInterface
    {
        [$school, $user, $admin, $bank] = $context;
        $row = $bank->find($school, $id);
        if ($row === null || (! $admin && (int) $row['author_user_id'] !== $user)) return $this->error('NOT_FOUND', 'Soal tidak ditemukan.', 404);
        return $row;
    }

    public function index(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $bank] = $context;
        $subject = $this->request->getGet('subjectId');
        if ($subject !== null && (! ctype_digit((string) $subject) || (int) $subject < 1)) return $this->error('VALIDATION', 'subjectId tidak valid.', 422);
        $bankId = $this->request->getGet('bankId');
        if ($bankId !== null && (! ctype_digit((string) $bankId) || (int) $bankId < 1)) return $this->error('VALIDATION', 'bankId tidak valid.', 422);
        if ($bankId !== null && (new \App\Services\QuestionBankContainer(db_connect()))->find($school, (int) $bankId, $admin ? null : $user) === null) return $this->error('NOT_FOUND', 'Bank soal tidak ditemukan.', 404);
        $result = $bank->list($school, $admin ? null : $user, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20), trim((string) $this->request->getGet('search')), $subject === null ? null : (int) $subject, $bankId === null ? null : (int) $bankId);
        return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total']]]);
    }

    public function masters(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        [$school, $user, $admin, $bank] = $context;
        $subjects = db_connect()->table('subjects')->select('id, code, name')->where('school_id', $school)->where('active', 1)->orderBy('name')->get()->getResultArray();
        if (! $admin) $subjects = array_values(array_filter($subjects, static fn ($subject) => $bank->teacherCanUseSubject($school, $user, (int) $subject['id'])));
        $ids = array_column($subjects, 'id');
        $categories = $ids === [] ? [] : db_connect()->table('question_categories')->select('id, subject_id, name')->where('school_id', $school)->where('active', 1)->whereIn('subject_id', $ids)->get()->getResultArray();
        $categoryIds = array_column($categories, 'id');
        $topics = $categoryIds === [] ? [] : db_connect()->table('question_topics')->select('id, category_id, name')->where('school_id', $school)->where('active', 1)->whereIn('category_id', $categoryIds)->get()->getResultArray();
        return $this->response->setJSON(['data' => ['subjects' => $subjects, 'categories' => $categories, 'topics' => $topics], 'meta' => []]);
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $row = $this->owned($context, (int) $id);
        if ($row instanceof ResponseInterface) return $row;
        return $this->response->setJSON(['data' => $context[3]->full($row), 'meta' => []]);
    }

    public function create(): ResponseInterface
    {
        return $this->save(null);
    }

    public function update(string $id): ResponseInterface
    {
        return $this->save((int) $id);
    }

    private function save(?int $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        if ($id !== null && $this->owned($context, $id) instanceof ResponseInterface) return $this->error('NOT_FOUND', 'Soal tidak ditemukan.', 404);
        [$school, $user, $admin, $bank] = $context;
        try {
            $row = $bank->save($school, $user, $this->request->getJSON(true) ?? [], $id, $admin);
            (new AuditTrail(db_connect()))->record($school, $user, $id === null ? 'questions.create' : 'questions.update', 'question', (int) $row['id'], ['status' => $row['status']]);
            return $this->response->setStatusCode($id === null ? 201 : 200)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Soal tidak ditemukan.', 404);
        }
    }

    public function archive(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $row = $this->owned($context, (int) $id);
        if ($row instanceof ResponseInterface) return $row;
        $context[3]->archive($context[0], (int) $id);
        (new AuditTrail(db_connect()))->record($context[0], $context[1], 'questions.archive', 'question', (int) $id);
        return $this->response->setJSON(['data' => ['archived' => true], 'meta' => []]);
    }

    public function upload(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $row = $this->owned($context, (int) $id);
        if ($row instanceof ResponseInterface) return $row;
        if ($row['status'] !== 'draft') return $this->error('CONFLICT', 'Media hanya dapat ditambah pada draf.', 409);
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 5 * 1024 * 1024) return $this->error('VALIDATION', 'Gambar wajib diisi dan maksimal 5 MB.', 422);
        $tmp = $file->getTempName();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $image = @getimagesize($tmp);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime]) || $image === false || $image['mime'] !== $mime) return $this->error('VALIDATION', 'Hanya PNG, JPEG, dan WebP yang valid.', 422);
        $directory = WRITEPATH . 'uploads/questions';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) return $this->error('STORAGE', 'Penyimpanan gagal.', 500);
        $stored = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
        $file->move($directory, $stored);
        $name = mb_substr(basename($file->getClientName()), 0, 180);
        $now = gmdate('Y-m-d H:i:s');
        db_connect()->table('question_assets')->insert(['school_id' => $context[0], 'question_id' => $row['id'], 'stored_name' => $stored, 'original_name' => $name, 'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'created_at' => $now, 'updated_at' => $now]);
        $asset = db_connect()->table('question_assets')->select('id, original_name, mime_type, size_bytes')->where('id', db_connect()->insertID())->get()->getRowArray();
        return $this->response->setStatusCode(201)->setJSON(['data' => $asset, 'meta' => []]);
    }

    public function asset(string $id): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $asset = db_connect()->table('question_assets')->where('id', (int) $id)->where('school_id', $context[0])->where('archived_at', null)->get()->getRowArray();
        if ($asset === null || $this->owned($context, (int) $asset['question_id']) instanceof ResponseInterface) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        $path = WRITEPATH . 'uploads/questions/' . $asset['stored_name'];
        if (! is_file($path)) return $this->error('NOT_FOUND', 'Media tidak ditemukan.', 404);
        return $this->response->setHeader('Content-Type', $asset['mime_type'])->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline')->setBody(file_get_contents($path));
    }
}
