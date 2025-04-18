<?php

namespace App\Models;

use CodeIgniter\Model;

class ClaimModel extends Model
{
    protected $table            = 'claims';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array'; // Or 'App\Entities\Claim'
    protected $useSoftDeletes   = true;   // Use soft deletes
    protected $protectFields    = true;
    protected $allowedFields    = [
        'claimant_user_id',
        'claim_type_id',
        'incident_date',
        'description',
        'status',
        'assigned_reviewer_id',
        'submitted_for_approval_at',
        'final_action_user_id',
        'final_action_at',
        'denial_reason',
        'settlement_amount',
        'settlement_date',
        // 'deleted_at' // Handled by useSoftDeletes
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime'; // Keep 'datetime' or use 'int' if storing timestamps as integers
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at'; // Define the soft delete field

    // Validation (add more specific rules as needed)
    protected $validationRules      = [
        'claimant_user_id' => 'required|is_natural_no_zero',
        'claim_type_id'    => 'required|is_natural_no_zero',
        'incident_date'    => 'required|valid_date',
        'description'      => 'required',
        'status'           => 'required|max_length[50]|in_list[Pending,Under Review,Pending Approval,Approved,Denied]', // Validate status values
        'assigned_reviewer_id' => 'permit_empty|is_natural_no_zero', // Allow null/empty
        'final_action_user_id' => 'permit_empty|is_natural_no_zero',
        'denial_reason'         => 'permit_empty',
        'settlement_amount'     => 'permit_empty|decimal',
        'settlement_date'       => 'permit_empty|valid_date',
    ];
    protected $validationMessages   = []; // Add custom messages if needed
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    // Consider adding relationships later for easier data fetching:
    // protected $returnType = 'App\Entities\Claim'; // Switch if using entities
    // public function claimType() { return $this->belongsTo(ClaimTypeModel::class, 'claim_type_id'); }
    // public function claimant() { return $this->belongsTo(UserModel::class, 'claimant_user_id'); } // Needs Shield's UserModel
    // public function reviewer() { return $this->belongsTo(UserModel::class, 'assigned_reviewer_id'); }
}