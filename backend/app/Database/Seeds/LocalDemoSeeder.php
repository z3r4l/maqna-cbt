<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Models\UserModel;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new \RuntimeException('Local fixtures may not run in production.');
        }
        $model = new UserModel();
        foreach ([
            ['admin_demo', 'admin@example.invalid', 'Admin Contoh', 'school_admin'],
            ['teacher_demo', 'teacher@example.invalid', 'Guru Contoh', 'teacher'],
            ['student_demo', 'student@example.invalid', 'Siswa Contoh', 'student'],
            ['noemail_demo', 'noemail_demo@internal.maqna.invalid', 'Siswa Tanpa Email', 'student'],
        ] as [$username, $email, $name, $role]) {
            $saved = $model->where('username', $username)->first();
            if ($saved === null) {
                $user = $model->createNewUser(['username' => $username, 'email' => $email, 'password' => 'FixtureOnly123!']);
                $id = $model->insert($user);
                $saved = $model->find($id);
            }
            if (! $saved->inGroup($role)) {
                $saved->addGroup($role);
            }
            if (! $this->db->table('user_profiles')->where('user_id', $saved->id)->countAllResults()) {
                $this->db->table('user_profiles')->insert([
                    'user_id' => $saved->id,
                    'school_id' => 1,
                    'display_name' => $name,
                    'active' => 1,
                    'must_change_password' => 0,
                ]);
            }
        }
    }
}
