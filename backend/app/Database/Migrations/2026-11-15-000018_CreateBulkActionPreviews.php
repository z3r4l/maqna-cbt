<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBulkActionPreviews extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'school_id' => $ref,
            'actor_user_id' => $ref,
            'kind' => ['type' => 'VARCHAR', 'constraint' => 20],
            'ids_json' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
            'committed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'actor_user_id', 'status']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('bulk_action_previews', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Riwayat pratinjau aksi massal tidak boleh dihapus oleh rollback otomatis.');
    }
}
