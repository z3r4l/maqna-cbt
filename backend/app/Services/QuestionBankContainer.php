<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionBankContainer
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function find(int $school, int $id, ?int $author): ?array
    {
        $query = $this->db->table('question_banks')->where('school_id', $school)->where('id', $id)->where('archived_at', null);
        if ($author !== null) $query->where('author_user_id', $author);
        return $query->get()->getRowArray();
    }

    public function detail(array $bank): array
    {
        if (! array_key_exists('subject_name', $bank)) {
            $subject = $this->db->table('subjects')->select('name')->where('id', $bank['subject_id'])->get()->getRowArray();
            $bank['subject_name'] = $subject['name'] ?? '';
        }
        if (! array_key_exists('exam_type_name', $bank)) {
            $type = $bank['exam_type_id'] ? $this->db->table('exam_types')->select('name, code')->where('id', $bank['exam_type_id'])->get()->getRowArray() : null;
            $bank['exam_type_name'] = $type['name'] ?? null;
            $bank['exam_type_code'] = $type['code'] ?? null;
        }
        $bank['classes'] = $this->db->table('question_bank_classes bc')
            ->select('cg.id, cg.name, cg.code, cg.academic_year_id')->join('class_groups cg', 'cg.id=bc.class_group_id')
            ->where('bc.bank_id', $bank['id'])->orderBy('cg.name')->get()->getResultArray();
        $bank['question_count'] = $this->db->table('questions')->where('bank_id', $bank['id'])->where('deleted_at', null)->countAllResults();
        $bank['published_question_count'] = $this->db->table('questions')->where('bank_id', $bank['id'])->where('status', 'published')->where('deleted_at', null)->countAllResults();
        $bank['published_total_points'] = (float) ($this->db->table('questions')->selectSum('weight', 'total')->where('bank_id', $bank['id'])->where('status', 'published')->where('deleted_at', null)->get()->getRowArray()['total'] ?? 0);
        $types = $this->db->table('questions')->select('question_type, COUNT(*) AS total')
            ->where('bank_id', $bank['id'])->where('deleted_at', null)->groupBy('question_type')->get()->getResultArray();
        $bank['type_counts'] = array_column($types, 'total', 'question_type');
        return $bank;
    }

    public function list(int $school, ?int $author, int $page, int $perPage, string $search, ?int $subject, ?int $class): array
    {
        $page = max(1, $page); $perPage = max(1, min(100, $perPage));
        $query = $this->db->table('question_banks b')->select('b.*, s.name AS subject_name, et.name AS exam_type_name, et.code AS exam_type_code, up.display_name AS author_name')
            ->join('subjects s', 's.id=b.subject_id')->join('exam_types et', 'et.id=b.exam_type_id', 'left')->join('user_profiles up', 'up.user_id=b.author_user_id', 'left')
            ->where('b.school_id', $school)->where('b.archived_at', null);
        if ($author !== null) $query->where('b.author_user_id', $author);
        if ($subject !== null) $query->where('b.subject_id', $subject);
        if ($class !== null) $query->whereIn('b.id', static function ($builder) use ($class) {
            return $builder->select('bank_id')->from('question_bank_classes')->where('class_group_id', $class);
        });
        if ($search !== '') $query->like('b.name', mb_substr($search, 0, 80));
        $total = $query->countAllResults(false);
        $rows = $query->orderBy('b.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['rows' => array_map(fn ($row) => $this->detail($row), $rows), 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function save(int $school, int $user, bool $admin, array $input, ?int $id): array
    {
        if (array_diff(array_keys($input), ['name', 'subjectId', 'examTypeId', 'classGroupIds']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $old = $id === null ? null : $this->find($school, $id, $admin ? null : $user);
        if ($id !== null && $old === null) throw new \OutOfBoundsException('Bank soal tidak ditemukan.');
        $name = trim(strip_tags((string) ($input['name'] ?? $old['name'] ?? '')));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) throw new \DomainException('Nama bank soal harus 3 sampai 160 karakter.');
        $subjectId = (int) ($input['subjectId'] ?? $old['subject_id'] ?? 0);
        $subject = $this->db->table('subjects')->where('id', $subjectId)->where('school_id', $school)->where('active', 1)->get()->getRowArray();
        if ($subject === null || (! $admin && ! (new QuestionBank($this->db))->teacherCanUseSubject($school, $user, $subjectId))) throw new \DomainException('Mata pelajaran di luar penugasan.');
        if ($old !== null && $subjectId !== (int) $old['subject_id'] && $this->db->table('questions')->where('bank_id', $id)->countAllResults() > 0) throw new \DomainException('Mata pelajaran bank berisi soal tidak dapat diganti.');
        $examTypeId = (int) ($input['examTypeId'] ?? $old['exam_type_id'] ?? 0);
        if ($examTypeId && ! $this->db->table('exam_types')->where('id', $examTypeId)->where('school_id', $school)->where('active', 1)->countAllResults()) throw new \DomainException('Jenis ujian tidak valid.');
        if (! $examTypeId && ($old === null || $old['default_key'] === null)) throw new \DomainException('Jenis ujian wajib dipilih.');
        $ids = $input['classGroupIds'] ?? ($old === null ? [] : array_column($this->detail($old)['classes'], 'id'));
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > 50 || count($ids) < 1) throw new \DomainException('Pilih sedikitnya satu kelas, maksimal 50.');
        $ids = array_map(static fn ($value) => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $ids);
        if (in_array(false, $ids, true) || count(array_unique($ids)) !== count($ids)) throw new \DomainException('Pilihan kelas tidak valid.');
        foreach ($ids as $classId) {
            $class = $this->db->table('class_groups cg')->join('academic_years ay', 'ay.id=cg.academic_year_id')
                ->where('cg.id', $classId)->where('cg.school_id', $school)->where('cg.active', 1)->where('ay.active', 1)->get()->getRowArray();
            if ($class === null) throw new \DomainException('Kelas tidak aktif atau di luar sekolah.');
            if (! $admin) {
                $assigned = $this->db->table('teaching_assignments ta')->join('teachers t', 't.id=ta.teacher_id')
                    ->join('semesters se', 'se.id=ta.semester_id')->where('ta.school_id', $school)
                    ->where('t.user_id', $user)->where('ta.subject_id', $subjectId)->where('ta.class_group_id', $classId)
                    ->where('ta.active', 1)->where('se.active', 1)->countAllResults();
                if (! $assigned) throw new \DomainException('Kelas di luar penugasan guru.');
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if ($id === null) {
                $this->db->table('question_banks')->insert(['school_id' => $school, 'subject_id' => $subjectId,
                    'exam_type_id' => $examTypeId, 'author_user_id' => $user, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
                $id = (int) $this->db->insertID();
            } else {
                $this->db->table('question_banks')->where('id', $id)->update(['subject_id' => $subjectId,
                    'exam_type_id' => $examTypeId ?: null, 'name' => $name, 'updated_at' => $now]);
                $this->db->table('question_bank_classes')->where('bank_id', $id)->delete();
            }
            foreach ($ids as $classId) $this->db->table('question_bank_classes')->insert(['bank_id' => $id, 'class_group_id' => $classId]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan bank soal gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback(); throw $error;
        }
        return $this->detail($this->find($school, $id, $admin ? null : $user));
    }

    public function defaultFor(int $school, int $user, int $subject): int
    {
        $key = "$school:$user:$subject";
        $existing = $this->db->table('question_banks')->where('default_key', $key)->get()->getRowArray();
        if ($existing !== null) return (int) $existing['id'];
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('question_banks')->insert(['school_id' => $school, 'subject_id' => $subject,
            'author_user_id' => $user, 'name' => 'Soal belum dikelompokkan', 'default_key' => $key,
            'created_at' => $now, 'updated_at' => $now]);
        return (int) $this->db->insertID();
    }

    public function archive(int $school, int $id, ?int $author): void
    {
        $query = $this->db->table('question_banks')->where('school_id', $school)->where('id', $id);
        if ($author !== null) $query->where('author_user_id', $author);
        $query->update(['archived_at' => gmdate('Y-m-d H:i:s')]);
    }
}
