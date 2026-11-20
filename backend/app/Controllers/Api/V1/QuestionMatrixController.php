<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\ExamService;
use App\Services\QuestionAnalyticsService;
use App\Services\XlsxFallbackWriter;
use CodeIgniter\HTTP\ResponseInterface;

final class QuestionMatrixController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function context(int $id): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'reports.view')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $school = (int) $profile['school_id'];
        $service = new ExamService($db);
        $exam = $service->find($school, $id);
        if ($exam === null) return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        if (! $admin && ((int) $exam['author_user_id'] !== (int) $user->id || ! $service->canTeachAll($school, (int) $user->id, $exam))) return $this->error('FORBIDDEN', 'Ujian di luar penugasan.', 403);
        return [$db, $school, (int) $user->id];
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context((int) $id);
        if ($context instanceof ResponseInterface) return $context;
        $matrix = (new QuestionAnalyticsService($context[0]))->matrix($context[1], (int) $id);
        return $this->response->setJSON(['data' => $matrix, 'meta' => []]);
    }

    public function export(string $id): ResponseInterface
    {
        $context = $this->context((int) $id);
        if ($context instanceof ResponseInterface) return $context;
        $matrix = (new QuestionAnalyticsService($context[0]))->matrix($context[1], (int) $id);
        $headers = ['Siswa', 'Rombel', 'Percobaan', 'Nilai'];
        foreach ($matrix['questions'] as $question) $headers[] = 'Soal ' . $question['order'];
        $rows = array_map(static fn ($row) => array_merge([$row['name'], $row['className'], $row['attemptId'], $row['score']], $row['answers']), $matrix['rows']);
        $body = (new XlsxFallbackWriter())->write($headers, $rows);
        (new AuditTrail($context[0]))->record($context[1], $context[2], 'analytics.matrix.export', 'exam', (int) $id);
        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-matrix-' . (int) $id . '.xlsx"')->setHeader('Cache-Control', 'no-store')->setHeader('X-Content-Type-Options', 'nosniff')->setBody($body);
    }
}
