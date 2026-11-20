<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQuestionMatchingChoices extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $ref + ['auto_increment' => true],
            'question_id' => $ref,
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'body' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['question_id', 'sort_order']);
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('question_matching_choices', true);

        $this->forge->addColumn('question_options', ['match_choice_id' => $ref + ['null' => true]]);
        $questions = $this->db->table('questions')->where('question_type', 'matching')->get()->getResultArray();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($questions as $question) {
            $options = $this->db->table('question_options')->where('question_id', $question['id'])->orderBy('sort_order')->get()->getResultArray();
            foreach ($options as $option) {
                if (trim((string) ($option['match_body'] ?? '')) === '') continue;
                $this->db->table('question_matching_choices')->insert([
                    'question_id' => $question['id'],
                    'sort_order' => $option['sort_order'],
                    'body' => $option['match_body'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->db->table('question_options')->where('id', $option['id'])->update([
                    'match_choice_id' => (int) $this->db->insertID(),
                ]);
            }
        }
        $this->db->query('ALTER TABLE question_options ADD CONSTRAINT fk_question_options_match_choice FOREIGN KEY (match_choice_id) REFERENCES question_matching_choices(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        throw new \RuntimeException('Question matching choices retain migrated question data and cannot be rolled back automatically.');
    }
}
