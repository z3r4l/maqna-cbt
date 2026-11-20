<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddExamBankSchedules extends Migration
{
    public function up(): void
    {
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addColumn('exams', [
            'source_bank_id' => $ref + ['null' => true],
            'display_question_count' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'archived_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->db->query('ALTER TABLE exams ADD INDEX idx_exams_source_bank (source_bank_id), ADD INDEX idx_exams_archived_at (archived_at)');
        $this->db->query('ALTER TABLE exams ADD CONSTRAINT fk_exams_source_bank FOREIGN KEY (source_bank_id) REFERENCES question_banks(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE exams DROP FOREIGN KEY fk_exams_source_bank');
        $this->db->query('ALTER TABLE exams DROP INDEX idx_exams_source_bank, DROP INDEX idx_exams_archived_at');
        $this->forge->dropColumn('exams', ['source_bank_id', 'display_question_count', 'archived_at']);
    }
}
