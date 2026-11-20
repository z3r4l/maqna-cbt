<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuestionVersions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('questions', ['version_no' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1]]);
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'question_id' => $ref, 'version_no' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'author_user_id' => $ref, 'reviewer_user_id' => $ref + ['null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16], 'snapshot_json' => ['type' => 'LONGTEXT'],
            'review_note' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true], 'decided_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['question_id', 'version_no']);
        $this->forge->addKey(['school_id', 'status']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('reviewer_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_versions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('question_versions', true);
        $this->forge->dropColumn('questions', 'version_no');
    }
}
