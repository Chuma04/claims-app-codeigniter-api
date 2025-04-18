<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReviewerNotesToClaimsMigration extends Migration
{
    public function up()
    {
        $this->forge->addColumn('claims', [
            'reviewer_notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'submitted_for_approval_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('claims', 'reviewer_notes');
    }
}
