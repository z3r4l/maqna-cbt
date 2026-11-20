<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAcademicTables extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function ref(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
    }

    private function text(int $size = 120): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $size];
    }

    private function dates(): array
    {
        return ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];
    }

    private function base(array $fields): void
    {
        $this->forge->addField(['id' => $this->id(), 'school_id' => $this->ref()] + $fields + $this->dates());
        $this->forge->addKey('id', true);
        $this->forge->addKey('school_id');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
    }

    public function up(): void
    {
        $this->forge->addField(['id' => $this->id(), 'name' => $this->text(160), 'code' => $this->text(32), 'timezone' => $this->text(64), 'status' => $this->text(16)] + $this->dates());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('schools', true);

        $this->base(['label' => $this->text(32), 'start_on' => ['type' => 'DATE'], 'end_on' => ['type' => 'DATE'], 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]]);
        $this->forge->addUniqueKey(['school_id', 'label']);
        $this->forge->createTable('academic_years', true);

        $this->base(['academic_year_id' => $this->ref(), 'name' => $this->text(20), 'start_on' => ['type' => 'DATE'], 'end_on' => ['type' => 'DATE'], 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]]);
        $this->forge->addUniqueKey(['academic_year_id', 'name']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('semesters', true);

        $this->base(['code' => $this->text(12), 'name' => $this->text(80), 'sort_order' => ['type' => 'INT', 'constraint' => 11], 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['school_id', 'code']);
        $this->forge->createTable('grade_levels', true);

        $this->base(['academic_year_id' => $this->ref(), 'grade_level_id' => $this->ref(), 'code' => $this->text(20), 'name' => $this->text(80), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['academic_year_id', 'code']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('grade_level_id', 'grade_levels', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('class_groups', true);

        $this->base(['code' => $this->text(24), 'name' => $this->text(120), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['school_id', 'code']);
        $this->forge->createTable('subjects', true);

        $this->base(['user_id' => $this->ref(), 'nis' => $this->text(40), 'nisn' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'status' => $this->text(16)]);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey(['school_id', 'nis']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('students', true);

        $this->base(['user_id' => $this->ref(), 'employee_no' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'status' => $this->text(16)]);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey(['school_id', 'employee_no']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('teachers', true);

        $this->base(['student_id' => $this->ref(), 'academic_year_id' => $this->ref(), 'class_group_id' => $this->ref(), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'joined_at' => ['type' => 'DATETIME'], 'left_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey(['student_id', 'academic_year_id', 'active']);
        $this->forge->addForeignKey('student_id', 'students', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('student_class_memberships', true);

        $this->base(['teacher_id' => $this->ref(), 'semester_id' => $this->ref(), 'class_group_id' => $this->ref(), 'subject_id' => $this->ref(), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['teacher_id', 'semester_id', 'class_group_id', 'subject_id']);
        $this->forge->addForeignKey('teacher_id', 'teachers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('semester_id', 'semesters', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('teaching_assignments', true);

        $this->base(['subject_id' => $this->ref(), 'name' => $this->text(100), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['school_id', 'subject_id', 'name']);
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_categories', true);

        $this->base(['category_id' => $this->ref(), 'name' => $this->text(100), 'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        $this->forge->addUniqueKey(['school_id', 'category_id', 'name']);
        $this->forge->addForeignKey('category_id', 'question_categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_topics', true);
    }

    public function down(): void
    {
        foreach (['question_topics', 'question_categories', 'teaching_assignments', 'student_class_memberships', 'teachers', 'students', 'subjects', 'class_groups', 'grade_levels', 'semesters', 'academic_years', 'schools'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
