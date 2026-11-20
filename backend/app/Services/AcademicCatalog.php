<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AcademicCatalog
{
    private const RESOURCES = [
        'academic-years' => ['table' => 'academic_years', 'fields' => ['label', 'start_on', 'end_on', 'active'], 'required' => ['label', 'start_on', 'end_on'], 'search' => ['label'], 'permission' => 'academic.manage'],
        'semesters' => ['table' => 'semesters', 'fields' => ['academic_year_id', 'name', 'start_on', 'end_on', 'active'], 'required' => ['academic_year_id', 'name', 'start_on', 'end_on'], 'search' => ['name'], 'permission' => 'academic.manage'],
        'grade-levels' => ['table' => 'grade_levels', 'fields' => ['code', 'name', 'sort_order', 'active'], 'required' => ['code', 'name', 'sort_order'], 'search' => ['code', 'name'], 'permission' => 'academic.manage'],
        'class-groups' => ['table' => 'class_groups', 'fields' => ['academic_year_id', 'grade_level_id', 'code', 'name', 'active'], 'required' => ['academic_year_id', 'grade_level_id', 'code', 'name'], 'search' => ['code', 'name'], 'permission' => 'academic.manage'],
        'subjects' => ['table' => 'subjects', 'fields' => ['code', 'name', 'active'], 'required' => ['code', 'name'], 'search' => ['code', 'name'], 'permission' => 'subjects.manage'],
        'question-categories' => ['table' => 'question_categories', 'fields' => ['subject_id', 'name', 'active'], 'required' => ['subject_id', 'name'], 'search' => ['name'], 'permission' => 'subjects.manage'],
        'question-topics' => ['table' => 'question_topics', 'fields' => ['category_id', 'name', 'active'], 'required' => ['category_id', 'name'], 'search' => ['name'], 'permission' => 'subjects.manage'],
        'exam-types' => ['table' => 'exam_types', 'fields' => ['code', 'name', 'active'], 'required' => ['code', 'name'], 'search' => ['code', 'name'], 'permission' => 'academic.manage'],
        'exam-rooms' => ['table' => 'exam_rooms', 'fields' => ['academic_year_id', 'code', 'name', 'capacity', 'active'], 'required' => ['academic_year_id', 'code', 'name', 'capacity'], 'search' => ['code', 'name'], 'permission' => 'academic.manage'],
    ];

    private const REFERENCES = [
        'academic_year_id' => 'academic_years',
        'grade_level_id' => 'grade_levels',
        'subject_id' => 'subjects',
        'category_id' => 'question_categories',
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    public static function spec(string $resource): ?array
    {
        return self::RESOURCES[$resource] ?? null;
    }

    public function list(string $resource, int $schoolId, int $page, int $perPage, string $search): array
    {
        $spec = self::RESOURCES[$resource] ?? throw new \InvalidArgumentException('Unknown resource');
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $builder = $this->db->table($spec['table'])->where('school_id', $schoolId);
        if ($search !== '') {
            $builder->groupStart();
            foreach ($spec['search'] as $field) $builder->orLike($field, mb_substr($search, 0, 80));
            $builder->groupEnd();
        }
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['rows' => $rows, 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }

    public function find(string $resource, int $schoolId, int $id): ?array
    {
        $spec = self::RESOURCES[$resource] ?? throw new \InvalidArgumentException('Unknown resource');
        return $this->db->table($spec['table'])->where('school_id', $schoolId)->where('id', $id)->get()->getRowArray();
    }

    public function save(string $resource, int $schoolId, array $input, ?int $id = null): array
    {
        $spec = self::RESOURCES[$resource] ?? throw new \InvalidArgumentException('Unknown resource');
        if (array_diff(array_keys($input), $spec['fields']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $existing = $id === null ? null : $this->find($resource, $schoolId, $id);
        if ($id !== null && $existing === null) throw new \OutOfBoundsException('Data tidak ditemukan.');
        $values = $existing === null ? $input : array_merge($existing, $input);
        foreach ($spec['required'] as $field) {
            if (! isset($values[$field]) || trim((string) $values[$field]) === '') throw new \DomainException("{$field} wajib diisi.");
        }
        foreach ($input as $field => $value) {
            if ($field === 'active') {
                if (! in_array($value, [0, 1, true, false, '0', '1'], true)) throw new \DomainException('Nilai active tidak valid.');
                $input[$field] = (int) $value;
            } elseif ($field === 'capacity') {
                if (! is_numeric($value) || (int) $value < 1 || (int) $value > 1000) throw new \DomainException('Kapasitas ruang harus 1-1000.');
                $input[$field] = (int) $value;
            } elseif (str_ends_with($field, '_id') || $field === 'sort_order') {
                if (! is_numeric($value) || (int) $value < 1) throw new \DomainException("{$field} tidak valid.");
                $input[$field] = (int) $value;
            } elseif (in_array($field, ['start_on', 'end_on'], true)) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                if ($date === false || $date->format('Y-m-d') !== $value) throw new \DomainException('Tanggal harus YYYY-MM-DD.');
            } elseif (! is_string($value) || mb_strlen(trim($value)) < 1 || mb_strlen($value) > 160) {
                throw new \DomainException("{$field} tidak valid.");
            } else {
                $input[$field] = trim($value);
            }
        }
        $values = array_merge($values, $input);
        if ($existing !== null && in_array($resource, ['academic-years', 'semesters'], true)
            && (int) $existing['active'] === 1 && array_key_exists('active', $input) && (int) $input['active'] === 0) {
            throw new \DomainException('Aktifkan pengganti sebelum menonaktifkan periode berjalan.');
        }
        if (isset($values['start_on'], $values['end_on']) && $values['start_on'] > $values['end_on']) throw new \DomainException('Tanggal akhir sebelum tanggal mulai.');
        foreach (self::REFERENCES as $field => $table) {
            if (! isset($values[$field])) continue;
            $reference = $this->db->table($table)->where('id', (int) $values[$field])->where('school_id', $schoolId)->get()->getRowArray();
            if ($reference === null) throw new \DomainException("{$field} di luar sekolah.");
        }
        if ($resource === 'class-groups') {
            $year = $this->db->table('academic_years')->where('id', $values['academic_year_id'])->get()->getRowArray();
            if ($year === null) throw new \DomainException('Tahun ajaran tidak ditemukan.');
        }
        if ($resource === 'semesters' && (int) ($values['active'] ?? 0) === 1) {
            $year = $this->db->table('academic_years')->where('id', $values['academic_year_id'])->get()->getRowArray();
            if ($year === null || (int) $year['active'] !== 1) throw new \DomainException('Semester aktif harus berada pada tahun ajaran aktif.');
        }
        if ($resource === 'semesters') {
            $year = $this->db->table('academic_years')->where('id', $values['academic_year_id'])->get()->getRowArray();
            if ($values['start_on'] < $year['start_on'] || $values['end_on'] > $year['end_on']) throw new \DomainException('Tanggal semester harus berada dalam tahun ajaran.');
        }

        if (in_array($resource, ['exam-types', 'exam-rooms'], true)) {
            if (mb_strlen((string) $values['code']) > 24 || mb_strlen((string) $values['name']) > 120) {
                throw new \DomainException('Kode atau nama terlalu panjang.');
            }
            $duplicate = $this->db->table($spec['table'])->where('school_id', $schoolId)->where('code', $values['code']);
            if ($resource === 'exam-rooms') $duplicate->where('academic_year_id', $values['academic_year_id']);
            if ($id !== null) $duplicate->where('id !=', $id);
            if ($duplicate->countAllResults() > 0) throw new \LogicException('Kode sudah digunakan.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        if (($resource === 'academic-years' || $resource === 'semesters') && (int) ($values['active'] ?? 0) === 1) {
            $this->db->table($spec['table'])->where('school_id', $schoolId)->update(['active' => 0, 'updated_at' => $now]);
            if ($resource === 'academic-years') $this->db->table('semesters')->where('school_id', $schoolId)->update(['active' => 0, 'updated_at' => $now]);
        }
        if ($id === null) {
            $defaultActive = in_array($resource, ['academic-years', 'semesters'], true) ? 0 : 1;
            $this->db->table($spec['table'])->insert($input + ['school_id' => $schoolId, 'active' => $defaultActive, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table($spec['table'])->where('id', $id)->where('school_id', $schoolId)->update($input + ['updated_at' => $now]);
        }
        if (! $this->db->transStatus()) {
            $this->db->transRollback();
            throw new \RuntimeException('Penyimpanan gagal.');
        }
        $this->db->transCommit();
        return $this->find($resource, $schoolId, $id);
    }
}
