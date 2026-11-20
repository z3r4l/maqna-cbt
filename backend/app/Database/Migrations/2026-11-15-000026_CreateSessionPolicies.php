<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSessionPolicies extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField(['id' => $ref + ['auto_increment' => true], 'school_id' => $ref,
            'role' => ['type' => 'VARCHAR', 'constraint' => 20], 'max_sessions' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['school_id', 'role']);
        $this->forge->createTable('school_session_policies', true);
        $this->forge->addField(['id' => $ref + ['auto_increment' => true], 'school_id' => $ref, 'user_id' => $ref,
            'role' => ['type' => 'VARCHAR', 'constraint' => 20], 'session_key_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME'], 'last_seen_at' => ['type' => 'DATETIME'],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true], 'revoke_reason' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('session_key_hash');
        $this->forge->addKey(['school_id', 'user_id', 'revoked_at']);
        $this->forge->createTable('account_sessions', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Riwayat kebijakan sesi tidak boleh dihapus oleh rollback otomatis.');
    }
}
