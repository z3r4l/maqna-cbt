<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAnswerReview extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exams', ['review_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('exams', 'review_enabled');
    }
}
