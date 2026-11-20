<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExamProctors extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'school_id' => $ref,
            'exam_id' => $ref,
            'user_id' => $ref,
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'user_id']);
        $this->forge->addKey(['school_id', 'user_id', 'active']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_proctors', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Penugasan pengawas tidak boleh dihapus oleh rollback otomatis.');
    }
}
