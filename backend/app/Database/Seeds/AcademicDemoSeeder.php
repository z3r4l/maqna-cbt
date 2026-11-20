<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AcademicDemoSeeder extends Seeder
{
    private function ensure(string $table, array $where, array $attributes): int
    {
        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if ($row !== null) return (int) $row['id'];
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table($table)->insert($where + $attributes + ['created_at' => $now, 'updated_at' => $now]);
        return (int) $this->db->insertID();
    }

    public function run(): void
    {
        if (ENVIRONMENT === 'production') throw new \RuntimeException('Demo fixtures may not run in production.');
        $this->call(LocalDemoSeeder::class);
        $db = $this->db;
        $db->transStart();
        $school = $this->ensure('schools', ['code' => 'MAQNA-DEMO'], ['name' => 'Madrasah Contoh', 'timezone' => 'Asia/Jakarta', 'status' => 'active']);
        foreach (['admin_demo', 'teacher_demo', 'student_demo', 'noemail_demo'] as $username) {
            $user = $db->table('users')->where('username', $username)->get()->getRowArray();
            $db->table('user_profiles')->where('user_id', $user['id'])->update(['school_id' => $school]);
        }
        $year = $this->ensure('academic_years', ['school_id' => $school, 'label' => '2026/2027'], ['start_on' => '2026-07-01', 'end_on' => '2027-06-30', 'active' => 1]);
        $ganjil = $this->ensure('semesters', ['school_id' => $school, 'academic_year_id' => $year, 'name' => 'Ganjil'], ['start_on' => '2026-07-01', 'end_on' => '2026-12-31', 'active' => 1]);
        $this->ensure('semesters', ['school_id' => $school, 'academic_year_id' => $year, 'name' => 'Genap'], ['start_on' => '2027-01-01', 'end_on' => '2027-06-30', 'active' => 0]);
        $vii = $this->ensure('grade_levels', ['school_id' => $school, 'code' => 'VII'], ['name' => 'Kelas VII', 'sort_order' => 7, 'active' => 1]);
        $viii = $this->ensure('grade_levels', ['school_id' => $school, 'code' => 'VIII'], ['name' => 'Kelas VIII', 'sort_order' => 8, 'active' => 1]);
        $viiA = $this->ensure('class_groups', ['school_id' => $school, 'academic_year_id' => $year, 'code' => 'VII-A'], ['grade_level_id' => $vii, 'name' => 'VII A', 'active' => 1]);
        $viiB = $this->ensure('class_groups', ['school_id' => $school, 'academic_year_id' => $year, 'code' => 'VII-B'], ['grade_level_id' => $vii, 'name' => 'VII B', 'active' => 1]);
        $this->ensure('class_groups', ['school_id' => $school, 'academic_year_id' => $year, 'code' => 'VIII-A'], ['grade_level_id' => $viii, 'name' => 'VIII A', 'active' => 1]);
        $math = $this->ensure('subjects', ['school_id' => $school, 'code' => 'MAT'], ['name' => 'Matematika', 'active' => 1]);
        $this->ensure('subjects', ['school_id' => $school, 'code' => 'BIN'], ['name' => 'Bahasa Indonesia', 'active' => 1]);
        $teacherUser = $db->table('users')->where('username', 'teacher_demo')->get()->getRowArray();
        $teacher = $this->ensure('teachers', ['school_id' => $school, 'user_id' => $teacherUser['id']], ['employee_no' => 'EMP-DEMO-01', 'status' => 'active']);
        $this->ensure('teaching_assignments', ['school_id' => $school, 'teacher_id' => $teacher, 'semester_id' => $ganjil, 'class_group_id' => $viiA, 'subject_id' => $math], ['active' => 1]);
        foreach ([['student_demo', 'NIS-DEMO-01', $viiA], ['noemail_demo', 'NIS-DEMO-02', $viiB]] as [$username, $nis, $class]) {
            $user = $db->table('users')->where('username', $username)->get()->getRowArray();
            $student = $this->ensure('students', ['school_id' => $school, 'user_id' => $user['id']], ['nis' => $nis, 'nisn' => null, 'status' => 'active']);
            $this->ensure('student_class_memberships', ['school_id' => $school, 'student_id' => $student, 'academic_year_id' => $year, 'class_group_id' => $class], ['active' => 1, 'joined_at' => gmdate('Y-m-d H:i:s'), 'left_at' => null]);
        }
        $db->transComplete();
        if (! $db->transStatus()) throw new \RuntimeException('Academic fixtures failed.');
    }
}
