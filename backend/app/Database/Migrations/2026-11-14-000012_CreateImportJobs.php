<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateImportJobs extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'actor_user_id' => $ref,
            'kind' => ['type' => 'VARCHAR', 'constraint' => 12], 'mode' => ['type' => 'VARCHAR', 'constraint' => 12],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'preview'],
            'row_count' => $ref, 'ready_count' => $ref, 'skip_count' => $ref, 'error_count' => $ref,
            'created_at' => ['type' => 'DATETIME'], 'committed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'actor_user_id', 'status']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('import_jobs', true);

        $this->forge->addField([
            'id' => $id, 'job_id' => $ref,
            'row_number' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12],
            'payload_json' => ['type' => 'TEXT'], 'error_message' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['job_id', 'row_number']);
        $this->forge->addForeignKey('job_id', 'import_jobs', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('import_rows', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('import_rows', true);
        $this->forge->dropTable('import_jobs', true);
    }
}
