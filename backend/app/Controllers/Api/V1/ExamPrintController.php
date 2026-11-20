<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ExamService;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExamPrintController extends BaseController
{
    private function documentContext(string $id): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        $admin = $user->inGroup('school_admin', 'superadmin');
        if ((! $admin && ! $user->inGroup('teacher')) || ! \App\Services\PermissionPolicy::allows($user, 'exams.manage')) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $school = (int) $profile['school_id'];
        $service = new ExamService($db);
        $exam = $service->find($school, (int) $id);
        if ($exam === null || $exam['status'] !== 'published' || ! $service->isAllowed($school, (int) $user->id, $admin, $exam)) return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Ujian terbit tidak ditemukan.']]);
        if (! $admin && ! $service->canTeachAll($school, (int) $user->id, $exam)) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Rombel di luar penugasan.']]);
        return [$db, $school, $exam];
    }

    private function rosterQuery(int $school, int $examId): array|ResponseInterface
    {
        $classId = $this->request->getGet('classGroupId');
        $roomId = $this->request->getGet('examRoomId');
        foreach ([$classId, $roomId] as $filter) if ($filter !== null && (! ctype_digit((string) $filter) || (int) $filter < 1)) return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Filter tidak valid.']]);
        $query = db_connect()->table('exam_assignments ea')->select('ea.student_id, ea.participant_number, s.nis, up.display_name, cg.name AS class_name, er.name AS room_name')
            ->join('students s', 's.id=ea.student_id')->join('user_profiles up', 'up.user_id=s.user_id')->join('class_groups cg', 'cg.id=ea.class_group_id')->join('exam_rooms er', 'er.id=ea.exam_room_id', 'left')
            ->where('ea.school_id', $school)->where('ea.exam_id', $examId)->orderBy('ea.participant_number')->orderBy('up.display_name');
        if ($classId !== null) $query->where('ea.class_group_id', (int) $classId);
        if ($roomId !== null) $query->where('ea.exam_room_id', (int) $roomId);
        return $query->get()->getResultArray();
    }

    private function pdf(string $html, string $filename): ResponseInterface
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper([0, 0, 612, 936]);
        $pdf->render();
        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setHeader('Cache-Control', 'no-store')->setBody($pdf->output());
    }

    public function attendance(string $id): ResponseInterface
    {
        $context = $this->documentContext($id);
        if ($context instanceof ResponseInterface) return $context;
        [$db, $school, $exam] = $context;
        $rows = $this->rosterQuery($school, (int) $id);
        if ($rows instanceof ResponseInterface) return $rows;
        $blank = $this->request->getGet('blank') === '1';
        $name = $db->table('schools')->select('name')->where('id', $school)->get()->getRowArray()['name'];
        $esc = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:8.5in 13in;margin:.55in}body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#183b38}h1{text-align:center;font-size:16pt}table{width:100%;border-collapse:collapse;margin-top:18px}td,th{border:1px solid #56716d;padding:7px}th{background:#e9f2ef}.sign{margin-top:36px;text-align:right}</style></head><body><h1>Daftar hadir ujian</h1><p><strong>' . $esc($name) . '</strong><br>' . $esc($exam['title']) . '<br>Jadwal: ' . $esc($exam['start_at']) . ' UTC</p><table><thead><tr><th>No.</th><th>Nomor peserta</th><th>Nama</th><th>Rombel</th><th>Ruang</th><th>Tanda tangan</th></tr></thead><tbody>';
        foreach ($rows as $index => $row) $html .= '<tr><td>' . ($index + 1) . '</td><td>' . ($blank ? '&nbsp;' : $esc($row['participant_number'])) . '</td><td>' . ($blank ? '&nbsp;' : $esc($row['display_name'])) . '</td><td>' . $esc($row['class_name']) . '</td><td>' . $esc($row['room_name']) . '</td><td>&nbsp;</td></tr>';
        $html .= '</tbody></table><div class="sign">Pengawas ujian,<br><br><br><br>(........................................)</div></body></html>';
        return $this->pdf($html, 'maqna-cbt-attendance-' . (int) $id . '.pdf');
    }

    public function minutes(string $id): ResponseInterface
    {
        $context = $this->documentContext($id);
        if ($context instanceof ResponseInterface) return $context;
        [$db, $school, $exam] = $context;
        $rows = $this->rosterQuery($school, (int) $id);
        if ($rows instanceof ResponseInterface) return $rows;
        $blank = $this->request->getGet('blank') === '1';
        $name = $db->table('schools')->select('name')->where('id', $school)->get()->getRowArray()['name'];
        $ids = array_column($rows, 'student_id');
        $present = 0;
        if (! $blank && $ids !== []) $present = $db->table('attempts')->select('student_id')->where('school_id', $school)->where('exam_id', (int) $id)->whereIn('student_id', $ids)->groupBy('student_id')->countAllResults();
        $minutes = $blank ? null : $db->table('exam_minutes')->where('school_id', $school)->where('exam_id', (int) $id)->get()->getRowArray();
        $esc = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $notes = $minutes === null ? '' : $esc($minutes['notes']);
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:8.5in 13in;margin:.65in}body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#183b38}h1{text-align:center;font-size:16pt}.line{border-bottom:1px solid #56716d;min-height:27px;margin-top:15px}.sign{display:inline-block;width:45%;vertical-align:top;margin-top:45px}</style></head><body><h1>Berita acara ujian</h1><p><strong>' . $esc($name) . '</strong></p><p>Ujian: ' . $esc($exam['title']) . '<br>Mulai: ' . $esc($exam['start_at']) . ' UTC<br>Selesai: ' . $esc($exam['end_at']) . ' UTC</p><p>Peserta terdaftar: ' . count($rows) . '<br>Peserta hadir: ' . ($blank ? '........' : $present) . '<br>Peserta tidak hadir: ' . ($blank ? '........' : count($rows) - $present) . '</p><h2>Catatan pelaksanaan</h2><p>' . nl2br($notes) . '</p><div class="line"></div><div class="line"></div><div class="sign">Pengawas 1,<br><br><br>(' . $esc($minutes['signer_one'] ?? '................................') . ')</div><div class="sign">Pengawas 2,<br><br><br>(' . $esc($minutes['signer_two'] ?? '................................') . ')</div></body></html>';
        return $this->pdf($html, 'maqna-cbt-minutes-' . (int) $id . '.pdf');
    }

    public function cards(string $id): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, 'exams.manage')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        }
        $db = db_connect();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        $school = (int) $profile['school_id'];
        $exam = (new ExamService($db))->find($school, (int) $id);
        if ($exam === null || $exam['status'] !== 'published') return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Ujian terbit tidak ditemukan.']]);
        $classId = $this->request->getGet('classGroupId');
        $roomId = $this->request->getGet('examRoomId');
        foreach ([$classId, $roomId] as $filter) {
            if ($filter !== null && (! ctype_digit((string) $filter) || (int) $filter < 1)) {
                return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Filter tidak valid.']]);
            }
        }
        $query = $db->table('exam_assignments ea')
            ->select('ea.participant_number, s.nis, up.display_name, cg.name AS class_name, er.name AS room_name')
            ->join('students s', 's.id=ea.student_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('class_groups cg', 'cg.id=ea.class_group_id')
            ->join('exam_rooms er', 'er.id=ea.exam_room_id', 'left')
            ->where('ea.school_id', $school)->where('ea.exam_id', (int) $id)->orderBy('ea.participant_number')->orderBy('up.display_name');
        if ($classId !== null) $query->where('ea.class_group_id', (int) $classId);
        if ($roomId !== null) $query->where('ea.exam_room_id', (int) $roomId);
        $rows = $query->get()->getResultArray();
        if ($rows === []) return $this->response->setStatusCode(404)->setJSON(['error' => ['code' => 'NOT_FOUND', 'message' => 'Tidak ada peserta sesuai filter.']]);
        $schoolName = $db->table('schools')->select('name')->where('id', $school)->get()->getRowArray()['name'];
        $logo = $db->table('school_assets')->where('school_id', $school)->where('kind', 'logo')->get()->getRowArray();
        $logoHtml = $logo === null ? '' : '<img src="data:' . $logo['mime_type'] . ';base64,' . base64_encode($logo['content']) . '" style="float:right;max-width:.5in;max-height:.5in" alt="">';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:8.5in 13in;margin:.55in}body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#183b38}.card{display:inline-block;vertical-align:top;width:45%;height:2.55in;box-sizing:border-box;border:1px solid #658e88;border-radius:8px;margin:0 2% .18in 0;padding:.18in;page-break-inside:avoid}.school{font-size:11pt;font-weight:bold;border-bottom:1px solid #9ab7b1;padding-bottom:.1in}.title{font-size:9pt;color:#45615d;margin:.1in 0}.number{font-size:16pt;font-weight:bold;margin:.1in 0}.name{font-size:12pt;font-weight:bold}.meta{margin-top:.1in;font-size:9pt}</style></head><body>';
        foreach ($rows as $row) {
            $html .= '<div class="card"><div class="school">' . $logoHtml . $escape($schoolName) . '</div><div class="title">' . $escape($exam['title']) . '</div>'
                . '<div class="number">' . $escape($row['participant_number'] ?: 'Nomor belum diatur') . '</div>'
                . '<div class="name">' . $escape($row['display_name']) . '</div>'
                . '<div class="meta">NIS: ' . $escape($row['nis']) . '<br>Rombel: ' . $escape($row['class_name']) . '<br>Ruang: ' . $escape($row['room_name'] ?: 'Belum diatur') . '</div></div>';
        }
        $html .= '</body></html>';
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper([0, 0, 612, 936]);
        $pdf->render();
        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="maqna-cbt-cards-' . (int) $id . '.pdf"')
            ->setHeader('Cache-Control', 'no-store')->setBody($pdf->output());
    }
}
