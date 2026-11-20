<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttemptMutations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('attempts', ['device_token_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true]]);
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'attempt_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'attempt_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'mutation_id' => ['type' => 'CHAR', 'constraint' => 36],
            'response_json' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['attempt_id', 'mutation_id']);
        $this->forge->addKey('attempt_item_id');
        $this->forge->addForeignKey('attempt_id', 'attempts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('attempt_item_id', 'attempt_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attempt_mutations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('attempt_mutations', true);
        $this->forge->dropColumn('attempts', 'device_token_hash');
    }
}
