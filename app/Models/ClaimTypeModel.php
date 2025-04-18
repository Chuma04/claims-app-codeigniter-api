<?php

namespace App\Models;

use CodeIgniter\Model;

class ClaimTypeModel extends Model
{
    protected $table            = 'claim_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'description'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    // protected $deletedField  = 'deleted_at'; // Not using soft deletes

    // Validation (basic rules, more specific in controllers/requests)
    protected $validationRules      = [
        'name' => 'required|max_length[100]|is_unique[claim_types.name,id,{id}]',
        'description' => 'max_length[255]',
    ];
    protected $validationMessages   = [
        'name' => [
            'is_unique' => 'This claim type name already exists.',
        ],
    ];
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
}