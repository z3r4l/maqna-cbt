<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttempts extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $dates = ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'exam_id' => $ref, 'student_id' => $ref,
            'attempt_number' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
            'started_at' => ['type' => 'DATETIME'], 'deadline_at' => ['type' => 'DATETIME'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'student_id', 'attempt_number']);
        $this->forge->addKey(['school_id', 'student_id', 'status']);
        $this->forge->addKey(['status', 'deadline_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attempts', true);

        $this->forge->addField([
            'id' => $id, 'attempt_id' => $ref, 'exam_item_id' => $ref,
            'display_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'option_order_json' => ['type' => 'TEXT'],
            'selected_option_id' => $ref + ['null' => true],
            'marked' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'saved_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['attempt_id', 'exam_item_id']);
        $this->forge->addUniqueKey(['attempt_id', 'display_order']);
        $this->forge->addForeignKey('attempt_id', 'attempts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_item_id', 'exam_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attempt_items', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('attempt_items', true);
        $this->forge->dropTable('attempts', true);
    }
}
