<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClaimDocumentsMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'primary'       => true,
            ],
            'claim_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'uploaded_by_user_id' => [ // Foreign key to users table
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'original_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'stored_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'unique'     => true,
            ],
            'file_path' => [ // Relative path within uploads directory (e.g., 'claims/2024/03/')
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'file_size' => [ // In bytes
                'type'       => 'INT',
                'constraint' => 11, // Max file size ~2GB
                'unsigned'   => true,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            // No soft delete needed here typically
        ]);
        $this->forge->addPrimaryKey('id'); // Primary Key
        $this->forge->addKey('claim_id'); // Delete documents if claim is deleted
        $this->forge->addKey('uploaded_by_user_id'); // Keep doc record but remove user link if user deleted
        $this->forge->createTable('claim_documents');
    }

    public function down()
    {
        $this->forge->dropTable('claim_documents');
    }
}
