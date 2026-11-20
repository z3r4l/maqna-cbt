<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchoolAssets extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField(['id' => $ref + ['auto_increment' => true], 'school_id' => $ref,
            'kind' => ['type' => 'VARCHAR', 'constraint' => 16], 'mime_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'content' => ['type' => 'LONGBLOB'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['school_id', 'kind']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('school_assets', true);
    }

    public function down(): void
    {
        throw new \RuntimeException('Aset sekolah tidak boleh dihapus oleh rollback otomatis.');
    }
}
