<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttemptMatchingChoiceOrder extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('attempt_items', [
            'matching_choice_order_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'option_order_json'],
        ]);
    }

    public function down(): void
    {
        throw new \RuntimeException('Stable matching order is persisted with attempts and cannot be rolled back automatically.');
    }
}
