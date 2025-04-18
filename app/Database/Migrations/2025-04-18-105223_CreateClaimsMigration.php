<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClaimsMigration extends Migration
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
            'claimant_user_id' => [ // Foreign key to users table (Shield)
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'claim_type_id' => [ // Foreign key to claim_types table
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
            'incident_date' => [
                'type' => 'DATE',
            ],
            'description' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '50', // e.g., Pending, Under Review, Pending Approval, Approved, Denied
                'default'    => 'Pending',
            ],
            'assigned_reviewer_id' => [ // Foreign key to users table (Reviewer/Maker)
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true, // Can be unassigned initially
            ],
            'submitted_for_approval_at' => [ // When reviewer submitted for approval
                'type' => 'DATETIME',
                'null' => true,
            ],
            'final_action_user_id' => [ // Foreign key to users table (Checker)
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'final_action_at' => [ // When checker approved/denied
                'type' => 'DATETIME',
                'null' => true,
            ],
            'denial_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'settlement_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2', // Example: Up to 9,999,999.99
                'null'       => true,
            ],
            'settlement_date' => [
                'type' => 'DATE',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [ // For soft deletes
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('claimant_user_id');
        $this->forge->addKey('claim_type_id');
        $this->forge->addKey('assigned_reviewer_id');
        $this->forge->addKey('final_action_user_id');
        $this->forge->createTable('claims');
    }

    public function down()
    {
        $this->forge->dropTable('claims');
    }
}
