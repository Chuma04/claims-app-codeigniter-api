<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReviewFlagToClaimDocumentsMigration extends Migration
{
    public function up()
    {
        $this->forge->addColumn('claim_documents', [
            'is_review_document' => [
                'type'       => 'BOOLEAN',
                'null'       => false,
                'default'    => false,
                'after'      => 'mime_type',
            ],
        ]);

         $this->forge->addKey('is_review_document');
    }

    public function down()
    {
        $this->forge->dropColumn('claim_documents', 'is_review_document');
    }
}
