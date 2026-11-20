<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ExamService;
use App\Services\ResultService;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ResultController extends BaseController
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

    private function staff(string $permission): array|ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, $permission)) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [$user, $school, $admin];
    }

    private function authorizedExam(array $context, int $id): array|ResponseInterface
    {
        [$user, $school, $admin] = $context;
        $service = new ExamService(db_connect());
        $exam = $service->find($school, $id);
        if ($exam === null) return $this->error('NOT_FOUND', 'Ujian tidak ditemukan.', 404);
        if (! $admin && ((int) $exam['author_user_id'] !== (int) $user->id || ! $service->canTeachAll($school, (int) $user->id, $exam))) return $this->error('FORBIDDEN', 'Ujian di luar penugasan.', 403);
        return $exam;
    }

    private function student(): array|ResponseInterface
    {
        $context = $this->principal();
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school] = $context;
        if (! $user->inGroup('student')) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $student = db_connect()->table('students')->where('school_id', $school)->where('user_id', $user->id)->where('status', 'active')->get()->getRowArray();
        return $student === null ? $this->error('FORBIDDEN', 'Akses ditolak.', 403) : [$school, (int) $student['id']];
    }

    public function release(string $id): ResponseInterface
    {
        $context = $this->staff('results.release');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        try {
            $row = (new ResultService(db_connect()))->release($context[1], (int) $id, (int) $context[0]->id);
            return $this->response->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        }
    }

    public function history(): ResponseInterface
    {
        $context = $this->student();
        if ($context instanceof ResponseInterface) return $context;
        return $this->response->setJSON(['data' => (new ResultService(db_connect()))->history($context[0], $context[1]), 'meta' => []]);
    }

    public function studentResult(string $attemptId): ResponseInterface
    {
        $context = $this->student();
        if ($context instanceof ResponseInterface) return $context;
        try {
            return $this->response->setJSON(['data' => (new ResultService(db_connect()))->studentResult($context[0], $context[1], (int) $attemptId), 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        }
    }

    public function answerReview(string $attemptId): ResponseInterface
    {
        $context = $this->student();
        if ($context instanceof ResponseInterface) return $context;
        try {
            return $this->response->setJSON(['data' => (new ResultService(db_connect()))->answerReview($context[0], $context[1], (int) $attemptId), 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('FORBIDDEN', $error->getMessage(), 403);
        }
    }

    public function reports(): ResponseInterface
    {
        $context = $this->staff('reports.view');
        if ($context instanceof ResponseInterface) return $context;
        [$user, $school, $admin] = $context;
        $query = db_connect()->table('exams e')->select('e.id, e.title, e.status, e.start_at, e.end_at, e.subject_id, e.semester_id, s.name AS subject_name, se.name AS semester_name, se.academic_year_id')
            ->join('subjects s', 's.id=e.subject_id')->join('semesters se', 'se.id=e.semester_id')->where('e.school_id', $school)->where('e.status', 'published');
        if (! $admin) $query->where('e.author_user_id', $user->id);
        foreach (['academicYearId' => 'se.academic_year_id', 'semesterId' => 'e.semester_id', 'subjectId' => 'e.subject_id', 'examId' => 'e.id'] as $param => $column) {
            $value = $this->request->getGet($param);
            if ($value !== null) {
                if (! ctype_digit((string) $value) || (int) $value < 1) return $this->error('VALIDATION', $param . ' tidak valid.', 422);
                $query->where($column, (int) $value);
            }
        }
        $class = $this->request->getGet('classGroupId');
        if ($class !== null) {
            if (! ctype_digit((string) $class) || (int) $class < 1) return $this->error('VALIDATION', 'classGroupId tidak valid.', 422);
            $query->join('exam_classes ec', 'ec.exam_id=e.id')->where('ec.class_group_id', (int) $class);
        }
        $rows = $query->orderBy('e.id', 'DESC')->limit(100)->get()->getResultArray();
        if (! $admin) {
            $service = new ExamService(db_connect());
            $rows = array_values(array_filter($rows, static function ($row) use ($service, $school, $user) {
                $exam = $service->find($school, (int) $row['id']);
                return $exam !== null && $service->canTeachAll($school, (int) $user->id, $exam);
            }));
        }
        return $this->response->setJSON(['data' => $rows, 'meta' => ['total' => count($rows)]]);
    }

    public function report(string $id): ResponseInterface
    {
        $context = $this->staff('reports.view');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        $class = $this->request->getGet('classGroupId');
        if ($class !== null && (! ctype_digit((string) $class) || (int) $class < 1)) return $this->error('VALIDATION', 'classGroupId tidak valid.', 422);
        $detail = (new ResultService(db_connect()))->report($context[1], (int) $id, $class === null ? null : (int) $class);
        return $this->response->setJSON(['data' => $detail, 'meta' => []]);
    }

    public function essays(string $id): ResponseInterface
    {
        $context = $this->staff('reports.view');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        $rows = db_connect()->table('attempt_items ai')->select('ai.id, ai.attempt_id, ai.essay_text, ai.essay_points, ai.essay_feedback, a.status, ei.weight, ei.snapshot_json, up.display_name')
            ->join('attempts a', 'a.id=ai.attempt_id')->join('exam_items ei', 'ei.id=ai.exam_item_id')
            ->join('students s', 's.id=a.student_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->where('a.school_id', $context[1])->where('a.exam_id', (int) $id)->whereIn('a.status', ['submitted', 'expired'])
            ->orderBy('ai.id')->get()->getResultArray();
        $data = [];
        foreach ($rows as $row) {
            $snapshot = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['type'] ?? 'mcq') !== 'essay') continue;
            $data[] = ['id' => (int) $row['id'], 'attemptId' => (int) $row['attempt_id'], 'studentName' => $row['display_name'],
                'question' => $snapshot['body'], 'rubric' => $snapshot['rubric'], 'answer' => $row['essay_text'], 'maxPoints' => (float) $row['weight'],
                'points' => $row['essay_points'] === null ? null : (float) $row['essay_points'], 'feedback' => $row['essay_feedback']];
        }
        return $this->response->setJSON(['data' => $data, 'meta' => []]);
    }

    public function scoreEssay(string $id, string $itemId): ResponseInterface
    {
        $context = $this->staff('results.release');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['points', 'feedback']) !== [] || ! isset($input['points']) || ! is_numeric($input['points']) || ! is_string($input['feedback'] ?? '') || mb_strlen($input['feedback'] ?? '') > 5000) return $this->error('VALIDATION', 'Poin atau umpan balik tidak valid.', 422);
        try {
            $result = (new ResultService(db_connect()))->scoreEssay($context[1], (int) $id, (int) $itemId, (int) $context[0]->id, (float) $input['points'], trim(strip_tags($input['feedback'] ?? '')));
            return $this->response->setJSON(['data' => ['score' => (float) $result['score'], 'pendingReview' => (bool) $result['pending_review']], 'meta' => []]);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', $error->getMessage(), 404);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        }
    }

    public function export(string $id, string $format): ResponseInterface
    {
        $context = $this->staff('reports.view');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        if (! in_array($format, ['xlsx', 'pdf'], true)) return $this->error('NOT_FOUND', 'Format tidak tersedia.', 404);
        $class = $this->request->getGet('classGroupId');
        if ($class !== null && (! ctype_digit((string) $class) || (int) $class < 1)) return $this->error('VALIDATION', 'Rombel tidak valid.', 422);
        $report = (new ResultService(db_connect()))->report($context[1], (int) $id, $class === null ? null : (int) $class);
        $headers = ['Nama siswa', 'Username', 'Rombel', 'Status', 'Nilai', 'Lulus', 'Dirilis'];
        $rows = array_map(static fn ($row) => [$row['display_name'], $row['username'], $row['class_name'], $row['attempt_status'] ?? 'belum mulai',
            $row['score'] === null ? '' : (string) $row['score'], $row['passed'] === null ? '' : ((int) $row['passed'] === 1 ? 'Ya' : 'Tidak'), $row['released_at'] ?? ''], $report['rows']);
        if ($format === 'xlsx') {
            if (! extension_loaded('zip')) {
                $body = (new \App\Services\XlsxFallbackWriter())->write($headers, $rows);
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            } else {
            $sheet = new Spreadsheet();
            $tab = $sheet->getActiveSheet();
            $tab->setTitle('Hasil ujian');
            foreach (array_merge([$headers], $rows) as $index => $cells) {
                foreach ($cells as $column => $cell) {
                    $safe = (string) $cell;
                    if (preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $safe)) $safe = "'" . $safe;
                    $tab->setCellValueExplicit([$column + 1, $index + 1], $safe, DataType::TYPE_STRING);
                }
            }
            $path = tempnam(WRITEPATH . 'cache', 'maqna-export-');
            try {
                (new Xlsx($sheet))->save($path);
                $body = file_get_contents($path);
            } finally {
                $sheet->disconnectWorksheets();
                if (is_file($path)) unlink($path);
            }
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
        } else {
            $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #777;padding:5px;text-align:left}</style></head><body><h1>Hasil ujian: ' . $escape($report['exam']['title']) . '</h1><table><thead><tr>';
            foreach ($headers as $header) $html .= '<th>' . $escape($header) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) { $html .= '<tr>'; foreach ($row as $cell) $html .= '<td>' . $escape($cell) . '</td>'; $html .= '</tr>'; }
            $html .= '</tbody></table></body></html>';
            $options = new Options(); $options->set('isRemoteEnabled', false); $options->set('isPhpEnabled', false);
            $pdf = new Dompdf($options); $pdf->loadHtml($html); $pdf->setPaper('A4', 'landscape'); $pdf->render();
            $body = $pdf->output();
            $mime = 'application/pdf';
        }
        db_connect()->table('audit_events')->insert(['school_id' => $context[1], 'actor_user_id' => $context[0]->id,
            'action' => 'reports.export', 'target_type' => 'exam', 'target_id' => (int) $id,
            'metadata_json' => json_encode(['format' => $format, 'classGroupId' => $class]), 'created_at' => gmdate('Y-m-d H:i:s')]);
        return $this->response->setHeader('Content-Type', $mime)->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-results-' . (int) $id . '.' . $format . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setBody($body);
    }

    public function exportAnswers(string $id): ResponseInterface
    {
        $context = $this->staff('reports.view');
        if ($context instanceof ResponseInterface) return $context;
        $exam = $this->authorizedExam($context, (int) $id);
        if ($exam instanceof ResponseInterface) return $exam;
        $class = $this->request->getGet('classGroupId');
        if ($class !== null && (! ctype_digit((string) $class) || (int) $class < 1)) return $this->error('VALIDATION', 'Rombel tidak valid.', 422);
        $db = db_connect();
        $query = $db->table('attempt_items ai')->select('a.id AS attempt_id, a.status AS attempt_status, up.display_name, cg.name AS class_name, ai.display_order, ai.selected_option_id, ai.answer_json, ai.essay_text, ai.essay_points, ai.essay_feedback, ei.snapshot_json, ei.weight, ar.score')
            ->join('attempts a', 'a.id=ai.attempt_id')->join('exam_assignments ea', 'ea.exam_id=a.exam_id AND ea.student_id=a.student_id')->join('students s', 's.id=a.student_id')
            ->join('user_profiles up', 'up.user_id=s.user_id')->join('class_groups cg', 'cg.id=ea.class_group_id')->join('exam_items ei', 'ei.id=ai.exam_item_id')
            ->join('attempt_results ar', 'ar.attempt_id=a.id', 'left')->where('a.school_id', $context[1])->where('a.exam_id', (int) $id);
        if ($class !== null) $query->where('ea.class_group_id', (int) $class);
        $items = $query->orderBy('up.display_name')->orderBy('a.id')->orderBy('ai.display_order')->limit(20000)->get()->getResultArray();
        $headers = ['Nama siswa', 'Rombel', 'Percobaan', 'Status', 'Nomor soal', 'Tipe', 'Pertanyaan', 'Jawaban siswa', 'Poin esai', 'Bobot soal', 'Umpan balik', 'Nilai total'];
        $rows = [];
        foreach ($items as $item) {
            $snapshot = json_decode($item['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $options = []; $matchOptions = [];
            foreach ($snapshot['options'] ?? [] as $option) {
                $options[(string) $option['id']] = strip_tags((string) $option['body']);
            }
            $choices = $snapshot['matching_choices'] ?? [];
            if ($choices === [] && ($snapshot['type'] ?? '') === 'matching') foreach ($snapshot['options'] ?? [] as $option) $choices[] = ['id' => $option['id'], 'body' => $option['match_body'] ?? ''];
            foreach ($choices as $choice) $matchOptions[(string) $choice['id']] = strip_tags((string) $choice['body']);
            if ($item['essay_text'] !== null) $answer = $item['essay_text'];
            elseif ($item['answer_json'] !== null) {
                $structured = json_decode($item['answer_json'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($structured) && array_is_list($structured)) $answer = implode('; ', array_map(static fn ($option) => $options[(string) $option] ?? (string) $option, $structured));
                elseif (is_array($structured)) $answer = implode('; ', array_map(static fn ($key, $value) => ($options[(string) $key] ?? (string) $key) . ': ' . (is_bool($value) ? ($value ? 'Benar' : 'Salah') : ($matchOptions[(string) $value] ?? (string) $value)), array_keys($structured), $structured));
                else $answer = '';
            } else $answer = $item['selected_option_id'] === null ? '' : ($options[(string) $item['selected_option_id']] ?? '');
            $rows[] = [$item['display_name'], $item['class_name'], $item['attempt_id'], $item['attempt_status'], $item['display_order'], $snapshot['type'] ?? 'mcq', strip_tags((string) $snapshot['body']), $answer,
                $item['essay_points'] ?? '', $item['weight'], $item['essay_feedback'] ?? '', $item['score'] ?? ''];
        }
        if (! extension_loaded('zip')) $body = (new \App\Services\XlsxFallbackWriter())->write($headers, $rows);
        else {
            $sheet = new Spreadsheet();
            try {
                $tab = $sheet->getActiveSheet();
                $tab->setTitle('Jawaban siswa');
                foreach (array_merge([$headers], $rows) as $rowIndex => $cells) foreach ($cells as $column => $cell) {
                    $safe = (string) $cell;
                    if (preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $safe)) $safe = "'" . $safe;
                    $tab->setCellValueExplicit([$column + 1, $rowIndex + 1], $safe, DataType::TYPE_STRING);
                }
                $path = tempnam(WRITEPATH . 'cache', 'maqna-answers-');
                try { (new Xlsx($sheet))->save($path); $body = file_get_contents($path); }
                finally { if (is_file($path)) unlink($path); }
            } finally { $sheet->disconnectWorksheets(); }
        }
        (new \App\Services\AuditTrail($db))->record($context[1], (int) $context[0]->id, 'reports.answers.export', 'exam', (int) $id, ['classGroupId' => $class]);
        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-answers-' . (int) $id . '.xlsx"')->setHeader('Cache-Control', 'no-store')->setHeader('X-Content-Type-Options', 'nosniff')->setBody($body);
    }
}
