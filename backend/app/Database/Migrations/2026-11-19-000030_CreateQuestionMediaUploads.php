<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuestionMediaUploads extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'token' => ['type' => 'VARCHAR', 'constraint' => 64],
            'school_id' => $ref,
            'actor_user_id' => $ref,
            'stored_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'size_bytes' => $ref,
            'expires_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('token', true);
        $this->forge->addKey(['school_id', 'actor_user_id', 'expires_at']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_media_uploads', true);
        $this->forge->addColumn('question_assets', ['question_version_id' => $ref + ['null' => true]]);
        $this->db->query('ALTER TABLE question_assets ADD INDEX idx_question_assets_version (question_version_id), ADD CONSTRAINT fk_question_assets_version FOREIGN KEY (question_version_id) REFERENCES question_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        throw new \RuntimeException('Temporary question media records may still reference uploaded files.');
    }
}
