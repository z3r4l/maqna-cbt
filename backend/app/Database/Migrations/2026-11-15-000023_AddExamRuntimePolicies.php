<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExamRuntimePolicies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exams', [
            'token_mode' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'static'],
            'require_all_answers' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
    }

    public function down(): void
    {
        throw new \RuntimeException('Kebijakan ujian tidak boleh dihapus oleh rollback otomatis.');
    }
}
