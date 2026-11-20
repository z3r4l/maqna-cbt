<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ArchiveQuestionAssets extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('question_assets', ['archived_at' => ['type' => 'DATETIME', 'null' => true]]);
    }

    public function down(): void
    {
        throw new \RuntimeException('Riwayat media soal tidak boleh dihapus oleh rollback otomatis.');
    }
}
