<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEssay extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('attempt_results', ['graded_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addColumn('questions', [
            'question_type' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'mcq', 'after' => 'body'],
            'rubric' => ['type' => 'TEXT', 'null' => true, 'after' => 'explanation'],
        ]);
        $this->forge->addColumn('attempt_items', [
            'essay_text' => ['type' => 'TEXT', 'null' => true, 'after' => 'selected_option_id'],
            'essay_points' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true, 'after' => 'essay_text'],
            'essay_feedback' => ['type' => 'TEXT', 'null' => true, 'after' => 'essay_points'],
            'essay_graded_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'essay_feedback'],
            'essay_graded_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'essay_graded_by'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('attempt_items', ['essay_text', 'essay_points', 'essay_feedback', 'essay_graded_by', 'essay_graded_at']);
        $this->forge->dropColumn('questions', ['question_type', 'rubric']);
    }
}
