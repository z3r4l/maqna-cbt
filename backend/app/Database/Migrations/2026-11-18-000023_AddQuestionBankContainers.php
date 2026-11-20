<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQuestionBankContainers extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'school_id' => $ref,
            'subject_id' => $ref,
            'exam_type_id' => $ref + ['null' => true],
            'author_user_id' => $ref,
            'name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'default_key' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'archived_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['school_id', 'subject_id', 'archived_at']);
        $this->forge->addKey('author_user_id');
        $this->forge->addUniqueKey('default_key');
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('exam_type_id', 'exam_types', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_banks', true);

        $this->forge->addField([
            'bank_id' => $ref,
            'class_group_id' => $ref,
        ]);
        $this->forge->addKey(['bank_id', 'class_group_id'], true);
        $this->forge->addForeignKey('bank_id', 'question_banks', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_bank_classes', true);

        $this->forge->addColumn('questions', ['bank_id' => $ref + ['null' => true]]);
        $this->db->query('ALTER TABLE questions ADD INDEX idx_questions_bank_id (bank_id), ADD CONSTRAINT fk_questions_bank FOREIGN KEY (bank_id) REFERENCES question_banks(id)');

        $now = gmdate('Y-m-d H:i:s');
        $owners = $this->db->table('questions')->select('school_id, subject_id, author_user_id')
            ->groupBy('school_id, subject_id, author_user_id')->get()->getResultArray();
        foreach ($owners as $owner) {
            $key = $owner['school_id'] . ':' . $owner['author_user_id'] . ':' . $owner['subject_id'];
            $this->db->table('question_banks')->insert($owner + [
                'name' => 'Soal belum dikelompokkan', 'default_key' => $key,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->table('questions')->where($owner)->where('bank_id', null)
                ->update(['bank_id' => (int) $this->db->insertID()]);
        }
    }

    public function down(): void
    {
        // Additive migration: existing questions and bank assignments are retained.
    }
}
