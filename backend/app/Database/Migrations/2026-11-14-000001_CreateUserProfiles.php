<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'school_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('user_id', true);
        $this->forge->addKey('school_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('user_profiles', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_profiles', true);
    }
}
