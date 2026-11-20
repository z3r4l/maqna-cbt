<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExamMinutes extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true], 'school_id' => $ref, 'exam_id' => $ref,
            'notes' => ['type' => 'TEXT'], 'signer_one' => ['type' => 'VARCHAR', 'constraint' => 160],
            'signer_two' => ['type' => 'VARCHAR', 'constraint' => 160],
            'recorded_at' => ['type' => 'DATETIME'], 'recorded_by' => $ref,
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('exam_id');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_minutes', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Berita acara tidak boleh dihapus oleh rollback otomatis.');
    }
}
