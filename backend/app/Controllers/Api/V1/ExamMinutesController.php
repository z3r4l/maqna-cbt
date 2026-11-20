<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuditTrail;
use App\Services\ExamService;
use CodeIgniter\HTTP\ResponseInterface;

final class ExamMinutesController extends BaseController
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
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'exams.manage')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $school = (int) $profile['school_id'];
        $service = new ExamService($db);
        $exam = $service->find($school, $id);
        if ($exam === null || $exam['status'] !== 'published' || ! $service->isAllowed($school, (int) $user->id, $admin, $exam)) return $this->error('NOT_FOUND', 'Ujian terbit tidak ditemukan.', 404);
        if (! $admin && ! $service->canTeachAll($school, (int) $user->id, $exam)) return $this->error('FORBIDDEN', 'Rombel di luar penugasan.', 403);
        return [$db, $school, (int) $user->id];
    }

    public function show(string $id): ResponseInterface
    {
        $context = $this->context((int) $id);
        if ($context instanceof ResponseInterface) return $context;
        $row = $context[0]->table('exam_minutes')->where('school_id', $context[1])->where('exam_id', (int) $id)->get()->getRowArray();
        return $this->response->setJSON(['data' => $row, 'meta' => []]);
    }

    public function save(string $id): ResponseInterface
    {
        $context = $this->context((int) $id);
        if ($context instanceof ResponseInterface) return $context;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['notes', 'signerOne', 'signerTwo']) !== []) return $this->error('VALIDATION', 'Kolom tidak diizinkan.', 422);
        foreach (['notes', 'signerOne', 'signerTwo'] as $key) if (! isset($input[$key]) || ! is_string($input[$key])) return $this->error('VALIDATION', 'Semua kolom wajib berupa teks.', 422);
        $notes = trim(strip_tags($input['notes']));
        $signerOne = trim(strip_tags($input['signerOne']));
        $signerTwo = trim(strip_tags($input['signerTwo']));
        if (mb_strlen($notes) > 5000 || mb_strlen($signerOne) > 160 || mb_strlen($signerTwo) > 160) return $this->error('VALIDATION', 'Teks berita acara terlalu panjang.', 422);
        [$db, $school, $actor] = $context;
        $now = gmdate('Y-m-d H:i:s');
        $row = ['school_id' => $school, 'exam_id' => (int) $id, 'notes' => $notes, 'signer_one' => $signerOne, 'signer_two' => $signerTwo, 'recorded_at' => $now, 'recorded_by' => $actor, 'updated_at' => $now];
        $existing = $db->table('exam_minutes')->where('exam_id', (int) $id)->get()->getRowArray();
        $db->transBegin();
        try {
            if ($existing === null) $db->table('exam_minutes')->insert($row + ['created_at' => $now]);
            else $db->table('exam_minutes')->where('id', $existing['id'])->update($row);
            (new AuditTrail($db))->record($school, $actor, 'exams.minutes.save', 'exam', (int) $id);
            if (! $db->transStatus()) throw new \RuntimeException('Berita acara gagal disimpan.');
            $db->transCommit();
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
        return $this->show($id);
    }
}
