<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserPermissionRules extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'school_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 80],
            'denied' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['school_id', 'user_id', 'permission']);
        $this->forge->createTable('user_permission_rules', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Aturan izin tidak boleh dihapus oleh rollback otomatis.');
    }
}
