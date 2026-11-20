<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStructuredQuestionAnswers extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('questions', ['question_type' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'mcq']]);
        $this->forge->addColumn('question_options', ['match_body' => ['type' => 'TEXT', 'null' => true, 'after' => 'body']]);
        $this->forge->addColumn('attempt_items', ['answer_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'essay_text']]);
    }

    public function down(): void
    {
        throw new \RuntimeException('Jawaban terstruktur tidak boleh dihapus oleh rollback otomatis.');
    }
}
