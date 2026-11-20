<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExamAdministrationMasters extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $dates = ['created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']];

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref,
            'code' => ['type' => 'VARCHAR', 'constraint' => 24],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['school_id', 'code']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_types', true);

        $this->forge->addField([
            'id' => $id, 'school_id' => $ref, 'academic_year_id' => $ref,
            'code' => ['type' => 'VARCHAR', 'constraint' => 24],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'capacity' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 40],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $dates);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['school_id', 'academic_year_id', 'code']);
        $this->forge->addForeignKey('school_id', 'schools', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_rooms', true);

        $this->forge->addColumn('exams', ['exam_type_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true]]);
        $this->forge->addColumn('exam_assignments', [
            'exam_room_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'participant_number' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ]);
        $this->db->query('ALTER TABLE exams ADD CONSTRAINT fk_exams_exam_type FOREIGN KEY (exam_type_id) REFERENCES exam_types(id)');
        $this->db->query('ALTER TABLE exam_assignments ADD CONSTRAINT fk_assignments_room FOREIGN KEY (exam_room_id) REFERENCES exam_rooms(id)');
        $this->db->query('CREATE UNIQUE INDEX uq_exam_participant_number ON exam_assignments (exam_id, participant_number)');

        $now = gmdate('Y-m-d H:i:s');
        $labels = [
            'PH' => 'Penilaian Harian', 'PTS' => 'Penilaian Tengah Semester',
            'PAS' => 'Penilaian Akhir Semester', 'PAT' => 'Penilaian Akhir Tahun',
            'US' => 'Ujian Sekolah', 'TO' => 'Try Out',
        ];
        foreach ($this->db->table('schools')->select('id')->get()->getResultArray() as $school) {
            foreach ($labels as $code => $name) {
                $this->db->table('exam_types')->insert([
                    'school_id' => $school['id'], 'code' => $code, 'name' => $name,
                    'active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Riwayat ujian dan nomor peserta tidak boleh dihapus oleh rollback otomatis.');
    }
}
