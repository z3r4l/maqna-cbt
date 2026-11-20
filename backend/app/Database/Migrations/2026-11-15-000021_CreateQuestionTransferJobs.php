<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuestionTransferJobs extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'school_id' => $ref, 'actor_user_id' => $ref,
            'status' => ['type' => 'VARCHAR', 'constraint' => 16],
            'payload_json' => ['type' => 'LONGTEXT'],
            'row_count' => $ref,
            'created_at' => ['type' => 'DATETIME'],
            'committed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'actor_user_id', 'status']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_transfer_jobs', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Pratinjau impor soal tidak boleh dihapus oleh rollback otomatis.');
    }
}
