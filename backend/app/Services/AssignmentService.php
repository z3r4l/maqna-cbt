<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AssignmentService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function scoped(string $table, int $id, int $school): array
    {
        $row = $this->db->table($table)->where('id', $id)->where('school_id', $school)->get()->getRowArray();
        if ($row === null) throw new \DomainException('Relasi di luar sekolah atau tidak ditemukan.');
        return $row;
    }

    public function createTeaching(int $school, array $input): array
    {
        if (array_diff(array_keys($input), ['teacherId', 'subjectId', 'classGroupId', 'semesterId']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        foreach (['teacherId', 'subjectId', 'classGroupId', 'semesterId'] as $key) {
            if (! is_numeric($input[$key] ?? null) || (int) $input[$key] < 1) throw new \DomainException("{$key} wajib diisi.");
        }
        $teacher = $this->scoped('teachers', (int) $input['teacherId'], $school);
        $subject = $this->scoped('subjects', (int) $input['subjectId'], $school);
        $class = $this->scoped('class_groups', (int) $input['classGroupId'], $school);
        $semester = $this->scoped('semesters', (int) $input['semesterId'], $school);
        if ($teacher['status'] !== 'active' || (int) $subject['active'] !== 1 || (int) $class['active'] !== 1 || (int) $semester['active'] !== 1) throw new \DomainException('Guru, mapel, rombel, dan semester harus aktif.');
        if ((int) $class['academic_year_id'] !== (int) $semester['academic_year_id']) throw new \DomainException('Semester dan rombel harus satu tahun ajaran.');
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('teaching_assignments')->insert([
            'school_id' => $school, 'teacher_id' => $teacher['id'], 'subject_id' => $subject['id'],
            'class_group_id' => $class['id'], 'semester_id' => $semester['id'], 'active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return $this->db->table('teaching_assignments')->where('id', $this->db->insertID())->get()->getRowArray();
    }

    public function listTeaching(int $school, int $page, int $perPage, ?int $teacherId = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $builder = $this->db->table('teaching_assignments ta')
            ->select('ta.*, u.username AS teacher_username, up.display_name AS teacher_name, s.name AS subject_name, cg.name AS class_name, se.name AS semester_name')
            ->join('teachers t', 't.id=ta.teacher_id')->join('users u', 'u.id=t.user_id')
            ->join('user_profiles up', 'up.user_id=t.user_id')
            ->join('subjects s', 's.id=ta.subject_id')->join('class_groups cg', 'cg.id=ta.class_group_id')
            ->join('semesters se', 'se.id=ta.semester_id')
            ->where('ta.school_id', $school);
        if ($teacherId !== null) $builder->where('ta.teacher_id', $teacherId)->where('ta.active', 1);
        $total = $builder->countAllResults(false);
        return ['rows' => $builder->orderBy('ta.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray(), 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }

    public function setTeachingActive(int $school, int $id, bool $active): ?array
    {
        $row = $this->db->table('teaching_assignments')->where('school_id', $school)->where('id', $id)->get()->getRowArray();
        if ($row === null) return null;
        $this->db->table('teaching_assignments')->where('id', $id)->update(['active' => $active ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return $this->db->table('teaching_assignments')->where('id', $id)->get()->getRowArray();
    }

    public function moveStudent(int $school, int $studentId, int $classId): array
    {
        $class = $this->scoped('class_groups', $classId, $school);
        $year = $this->scoped('academic_years', (int) $class['academic_year_id'], $school);
        if ((int) $class['active'] !== 1 || (int) $year['active'] !== 1) throw new \DomainException('Rombel harus berada di tahun ajaran aktif.');
        $this->db->transBegin();
        try {
            $locked = $this->db->query('SELECT id, status FROM students WHERE id = ? AND school_id = ? FOR UPDATE', [$studentId, $school])->getRowArray();
            if ($locked === null || $locked['status'] !== 'active') throw new \DomainException('Siswa tidak aktif atau di luar sekolah.');
            $old = $this->db->table('student_class_memberships')->where('student_id', $studentId)->where('academic_year_id', $year['id'])->where('active', 1)->get()->getRowArray();
            if ($old !== null && (int) $old['class_group_id'] === $classId) {
                $this->db->transCommit();
                return $old;
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('student_class_memberships')->where('student_id', $studentId)->where('academic_year_id', $year['id'])->where('active', 1)->update(['active' => 0, 'left_at' => $now, 'updated_at' => $now]);
            $this->db->table('student_class_memberships')->insert(['school_id' => $school, 'student_id' => $studentId, 'academic_year_id' => $year['id'], 'class_group_id' => $classId, 'active' => 1, 'joined_at' => $now, 'left_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            $row = $this->db->table('student_class_memberships')->where('id', $this->db->insertID())->get()->getRowArray();
            if (! $this->db->transStatus()) throw new \RuntimeException('Pemindahan kelas gagal.');
            $this->db->transCommit();
            return $row;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function listMemberships(int $school, ?int $studentId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $builder = $this->db->table('student_class_memberships m')
            ->select('m.*, cg.name AS class_name, ay.label AS academic_year, u.username, up.display_name AS student_name')
            ->join('class_groups cg', 'cg.id=m.class_group_id')->join('academic_years ay', 'ay.id=m.academic_year_id')
            ->join('students s', 's.id=m.student_id')->join('users u', 'u.id=s.user_id')
            ->join('user_profiles up', 'up.user_id=s.user_id')->where('m.school_id', $school);
        if ($studentId !== null) $builder->where('m.student_id', $studentId);
        $total = $builder->countAllResults(false);
        return ['rows' => $builder->orderBy('m.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray(), 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }

    public function teacherRoster(int $school, int $teacherId, int $classId): array
    {
        $allowed = $this->db->table('teaching_assignments ta')
            ->join('class_groups cg', 'cg.id=ta.class_group_id')
            ->join('academic_years ay', 'ay.id=cg.academic_year_id')
            ->join('semesters se', 'se.id=ta.semester_id')
            ->where('ta.school_id', $school)->where('ta.teacher_id', $teacherId)
            ->where('ta.class_group_id', $classId)->where('ta.active', 1)
            ->where('cg.active', 1)->where('ay.active', 1)->where('se.active', 1)->countAllResults();
        if ($allowed < 1) throw new \OutOfBoundsException('Rombel tidak diampu.');
        return $this->db->table('student_class_memberships m')
            ->select('s.id AS student_id, s.nis, up.display_name, u.username')
            ->join('students s', 's.id=m.student_id')->join('users u', 'u.id=s.user_id')->join('user_profiles up', 'up.user_id=s.user_id')
            ->join('academic_years ay', 'ay.id=m.academic_year_id')
            ->where('m.school_id', $school)->where('m.class_group_id', $classId)->where('m.active', 1)
            ->where('ay.active', 1)->where('s.status', 'active')->orderBy('up.display_name')->get()->getResultArray();
    }
}
