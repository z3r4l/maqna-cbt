<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuestionBank extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $dates = ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];
        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'subject_id' => $ref,
            'topic_id' => $ref + ['null' => true], 'author_user_id' => $ref,
            'body' => ['type' => 'TEXT'], 'explanation' => ['type' => 'TEXT', 'null' => true],
            'weight' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'subject_id', 'status']);
        $this->forge->addKey('author_user_id');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('topic_id', 'question_topics', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('questions', true);

        $this->forge->addField([
            'id' => $id, 'question_id' => $ref, 'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'body' => ['type' => 'TEXT'], 'is_correct' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['question_id', 'sort_order']);
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_options', true);

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'question_id' => $ref,
            'stored_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'size_bytes' => $ref,
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'question_id']);
        $this->forge->addUniqueKey('stored_name');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_assets', true);
    }

    public function down(): void
    {
        foreach (['question_assets', 'question_options', 'questions'] as $table) $this->forge->dropTable($table, true);
    }
}
