<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResults extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $dates = ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'exam_id' => $ref, 'student_id' => $ref, 'attempt_id' => $ref,
            'earned_points' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'max_points' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'score' => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'passed' => ['type' => 'TINYINT', 'constraint' => 1],
            'correct_count' => $ref, 'question_count' => $ref,
            'pending_review' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'graded_at' => ['type' => 'DATETIME'],
            'released_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('attempt_id');
        $this->forge->addKey(['school_id', 'exam_id', 'released_at']);
        $this->forge->addKey(['student_id', 'exam_id']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('attempt_id', 'attempts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attempt_results', true);

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'exam_id' => $ref, 'released_by_user_id' => $ref,
            'released_at' => ['type' => 'DATETIME'], 'result_count' => $ref,
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('exam_id');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('released_by_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('result_release_batches', true);

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'actor_user_id' => $ref,
            'action' => ['type' => 'VARCHAR', 'constraint' => 80],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'target_id' => $ref, 'metadata_json' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'created_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('audit_events', true);
    }

    public function down(): void
    {
        foreach (['audit_events', 'result_release_batches', 'attempt_results'] as $table) $this->forge->dropTable($table, true);
    }
}
