<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExamPackages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exams', ['package_mode' => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'single']]);
        $this->forge->addColumn('attempts', ['package_code' => ['type' => 'VARCHAR', 'constraint' => 1, 'default' => 'A']]);
        $id = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $ref = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true];
        $this->forge->addField([
            'id' => $id, 'exam_id' => $ref, 'code' => ['type' => 'VARCHAR', 'constraint' => 1],
            'item_order_json' => ['type' => 'TEXT'], 'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'code']);
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_packages', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('exam_packages', true);
        $this->forge->dropColumn('attempts', 'package_code');
        $this->forge->dropColumn('exams', 'package_mode');
    }
}
