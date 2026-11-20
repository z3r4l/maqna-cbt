<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExams extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $dates = ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'subject_id' => $ref, 'semester_id' => $ref,
            'author_user_id' => $ref, 'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'instructions' => ['type' => 'TEXT', 'null' => true],
            'start_at' => ['type' => 'DATETIME'], 'end_at' => ['type' => 'DATETIME'],
            'duration_minutes' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'passing_grade' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 70],
            'max_attempts' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'result_policy' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'first'],
            'shuffle_questions' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'shuffle_options' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'status', 'start_at', 'end_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('semester_id', 'semesters', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exams', true);

        $this->forge->addField(['id' => $id, 'exam_id' => $ref, 'class_group_id' => $ref] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'class_group_id']);
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_classes', true);

        $this->forge->addField(['id' => $id, 'exam_id' => $ref, 'question_id' => $ref, 'sort_order' => $ref] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'question_id']);
        $this->forge->addUniqueKey(['exam_id', 'sort_order']);
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_questions', true);

        $this->forge->addField([
            'id' => $id, 'exam_id' => $ref, 'source_question_id' => $ref, 'sort_order' => $ref,
            'snapshot_json' => ['type' => 'LONGTEXT'], 'weight' => ['type' => 'DECIMAL', 'constraint' => '8,2'],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'sort_order']);
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('source_question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_items', true);

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'exam_id' => $ref, 'student_id' => $ref, 'class_group_id' => $ref,
            'assigned_at' => ['type' => 'DATETIME'],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'student_id']);
        $this->forge->addKey(['school_id', 'student_id']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_assignments', true);
    }

    public function down(): void
    {
        foreach (['exam_assignments', 'exam_items', 'exam_questions', 'exam_classes', 'exams'] as $table) $this->forge->dropTable($table, true);
    }
}
