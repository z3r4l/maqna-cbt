<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProctorEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'school_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'attempt_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['attempt_id', 'created_at']);
        $this->forge->addKey(['school_id', 'created_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('attempt_id', 'attempts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('proctor_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('proctor_events', true);
    }
}
