<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExamAccessCode extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exams', ['access_code_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]]);
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'exam_id' => $ref, 'student_id' => $ref,
            'failed_count' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'window_started_at' => ['type' => 'DATETIME'], 'locked_until' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'student_id']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_code_attempts', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('exam_code_attempts', true);
        $this->forge->dropColumn('exams', 'access_code_hash');
    }
}
