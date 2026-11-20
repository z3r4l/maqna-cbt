<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\QuestionPrintService;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionPrintController extends BaseController
{
    public function download(string $format): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'questions.manage')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        if (! in_array($format, ['pdf', 'docx'], true)) return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Format tidak dikenal.']]);
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $subject = $this->request->getGet('subjectId');
        $variant = $this->request->getGet('variant') ?? 'questions';
        if ($subject !== null && (! ctype_digit((string) $subject) || (int) $subject < 1) || ! in_array($variant, ['questions', 'keys'], true)) return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Filter tidak valid.']]);
        $service = new QuestionPrintService($db);
        try {
            $rows = $service->collect((int) $profile['school_id'], (int) $user->id, $admin, $subject === null ? null : (int) $subject);
            $content = $format === 'pdf' ? $service->pdf($rows, $variant === 'keys') : $service->docx($rows, $variant === 'keys');
            $mime = $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            return $this->response->setHeader('Content-Type', $mime)->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-' . $variant . '.' . $format . '"')->setHeader('Cache-Control', 'no-store')->setBody($content);
        } catch (\DomainException $error) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => $error->getMessage()]]);
        } catch (\OutOfBoundsException $error) {
            return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Soal terbit tidak ditemukan.']]);
        }
    }
}
