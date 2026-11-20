<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Models\UserModel;

final class PeopleService
{
    private const TYPES = ['students' => ['table' => 'students', 'role' => 'student'], 'teachers' => ['table' => 'teachers', 'role' => 'teacher']];

    public function __construct(private BaseConnection $db)
    {
    }

    private function type(string $kind): array
    {
        return self::TYPES[$kind] ?? throw new \InvalidArgumentException('Unknown person type');
    }

    public function list(string $kind, int $schoolId, int $page, int $perPage, string $search, string $loginStatus = 'all'): array
    {
        $table = $this->type($kind)['table'];
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $builder = $this->db->table($table . ' p')
            ->select('p.*, u.username, u.last_active, up.display_name, up.active AS account_active, ai.secret AS email')
            ->join('users u', 'u.id=p.user_id')
            ->join('user_profiles up', 'up.user_id=p.user_id')
            ->join('auth_identities ai', "ai.user_id=p.user_id AND ai.type='email_password'", 'left')
            ->where('p.school_id', $schoolId);
        if ($kind === 'students') {
            $builder->select('(SELECT cg.name FROM student_class_memberships m JOIN academic_years ay ON ay.id=m.academic_year_id AND ay.school_id=p.school_id JOIN class_groups cg ON cg.id=m.class_group_id AND cg.school_id=p.school_id WHERE m.student_id=p.id AND m.school_id=p.school_id AND m.active=1 AND ay.active=1 ORDER BY m.id DESC LIMIT 1) AS class_name', false);
            $builder->select('(EXISTS(SELECT 1 FROM auth_logins al WHERE al.user_id=p.user_id AND al.success=1)) AS has_logged_in', false);
            if ($loginStatus === 'yes') $builder->where('EXISTS(SELECT 1 FROM auth_logins al WHERE al.user_id=p.user_id AND al.success=1)', null, false);
            if ($loginStatus === 'no') $builder->where('NOT EXISTS(SELECT 1 FROM auth_logins al WHERE al.user_id=p.user_id AND al.success=1)', null, false);
        }
        if ($search !== '') {
            $search = mb_substr($search, 0, 80);
            $builder->groupStart()->like('u.username', $search)->orLike('up.display_name', $search);
            if ($kind === 'students') $builder->orLike('p.nis', $search);
            $builder->groupEnd();
        }
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('p.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        foreach ($rows as &$row) {
            if (is_string($row['email']) && str_ends_with($row['email'], '@internal.maqna.invalid')) $row['email'] = null;
        }
        return ['rows' => $rows, 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }

    public function find(string $kind, int $schoolId, int $id): ?array
    {
        $table = $this->type($kind)['table'];
        $row = $this->db->table($table)->where('id', $id)->where('school_id', $schoolId)->get()->getRowArray();
        if ($row === null) return null;
        $user = (new UserModel())->find($row['user_id']);
        $profile = $this->db->table('user_profiles')->where('user_id', $row['user_id'])->get()->getRowArray();
        $row['username'] = $user->username;
        $row['display_name'] = $profile['display_name'];
        $row['account_active'] = (int) $profile['active'];
        $row['email'] = $user->email && ! str_ends_with($user->email, '@internal.maqna.invalid') ? $user->email : null;
        return $row;
    }

    public function create(string $kind, int $schoolId, array $input): array
    {
        $type = $this->type($kind);
        $allowed = ['username', 'email', 'displayName', 'nis', 'nisn', 'employeeNo'];
        if (array_diff(array_keys($input), $allowed) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $name = trim((string) ($input['displayName'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if (! preg_match('/\A[a-z0-9.]{3,30}\z/', $username)) throw new \DomainException('Nama pengguna 3–30 huruf, angka, atau titik.');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new \DomainException('Nama tampilan tidak valid.');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \DomainException('Email tidak valid.');
        if ($kind === 'teachers' && $email === '') throw new \DomainException('Email guru wajib diisi.');
        $identifier = $kind === 'students' ? trim((string) ($input['nis'] ?? '')) : trim((string) ($input['employeeNo'] ?? ''));
        if ($kind === 'students' && (mb_strlen($identifier) < 2 || mb_strlen($identifier) > 40)) throw new \DomainException('NIS tidak valid.');
        if ($kind === 'teachers' && mb_strlen($identifier) > 40) throw new \DomainException('Nomor pegawai terlalu panjang.');
        $nisn = $kind === 'students' ? trim((string) ($input['nisn'] ?? '')) : '';
        if (mb_strlen($nisn) > 40) throw new \DomainException('NISN terlalu panjang.');
        $model = new UserModel();
        if ($model->where('username', $username)->first() !== null) throw new \RuntimeException('Nama pengguna sudah digunakan.');
        if ($email === '') $email = 'u-' . bin2hex(random_bytes(12)) . '@internal.maqna.invalid';
        $temporary = bin2hex(random_bytes(12));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $user = $model->createNewUser(['username' => $username, 'email' => $email, 'password' => $temporary]);
            $userId = (int) $model->insert($user);
            $saved = $model->find($userId);
            $saved->addGroup($type['role']);
            $this->db->table('user_profiles')->insert(['user_id' => $userId, 'school_id' => $schoolId, 'display_name' => $name, 'active' => 1, 'must_change_password' => 1]);
            $person = ['user_id' => $userId, 'school_id' => $schoolId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now];
            if ($kind === 'students') $person += ['nis' => $identifier, 'nisn' => $nisn === '' ? null : $nisn];
            else $person += ['employee_no' => $identifier === '' ? null : $identifier];
            $this->db->table($type['table'])->insert($person);
            $id = (int) $this->db->insertID();
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return ['person' => $this->find($kind, $schoolId, $id), 'temporaryPassword' => $temporary];
    }

    public function update(string $kind, int $schoolId, int $id, array $input): array
    {
        $type = $this->type($kind);
        $person = $this->find($kind, $schoolId, $id);
        if ($person === null) throw new \OutOfBoundsException('Data tidak ditemukan.');
        $allowed = $kind === 'students' ? ['displayName', 'nis', 'nisn', 'status'] : ['displayName', 'employeeNo', 'status'];
        if ($input === [] || array_diff(array_keys($input), $allowed) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $profile = [];
        $fields = [];
        if (isset($input['displayName'])) {
            $name = trim((string) $input['displayName']);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new \DomainException('Nama tampilan tidak valid.');
            $profile['display_name'] = $name;
        }
        if (isset($input['status'])) {
            if (! in_array($input['status'], ['active', 'inactive'], true)) throw new \DomainException('Status tidak valid.');
            $fields['status'] = $input['status'];
            $profile['active'] = $input['status'] === 'active' ? 1 : 0;
        }
        foreach (['nis', 'nisn', 'employeeNo'] as $key) {
            if (! array_key_exists($key, $input)) continue;
            $value = trim((string) $input[$key]);
            if (mb_strlen($value) > 40 || ($key === 'nis' && mb_strlen($value) < 2)) throw new \DomainException("{$key} tidak valid.");
            $column = $key === 'employeeNo' ? 'employee_no' : $key;
            $fields[$column] = $value === '' ? null : $value;
        }
        $this->db->transBegin();
        try {
            if ($fields !== []) $this->db->table($type['table'])->where('id', $id)->where('school_id', $schoolId)->update($fields + ['updated_at' => gmdate('Y-m-d H:i:s')]);
            if ($profile !== []) $this->db->table('user_profiles')->where('user_id', $person['user_id'])->update($profile);
            if (! $this->db->transStatus()) throw new \RuntimeException('Penyimpanan gagal.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->find($kind, $schoolId, $id);
    }
}
