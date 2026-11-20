<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentSessions extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'school_id' => $ref,
            'student_id' => $ref,
            'session_key_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME'],
            'last_seen_at' => ['type' => 'DATETIME'],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'revoke_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('session_key_hash');
        $this->forge->addKey(['school_id', 'student_id', 'revoked_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('student_sessions', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Riwayat revoke sesi tidak boleh dihapus oleh rollback otomatis.');
    }
}
