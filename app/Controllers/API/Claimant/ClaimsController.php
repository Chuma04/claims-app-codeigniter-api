<?php

namespace App\Controllers\API\Claimant;

use App\Models\ClaimDocumentModel;
use App\Models\ClaimModel;
use App\Models\ClaimTypeModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

// Added
// Added
// Added
// For permission errors

class ClaimsController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = ClaimModel::class;
    protected $format    = 'json';
    protected ?ClaimTypeModel $claimTypeModel = null;     // Property for model instance
    protected ?ClaimDocumentModel $docModel = null;       // Property for model instance

    public function __construct()
    {
        // Instantiate models in constructor for repeated use
        $this->claimTypeModel = new ClaimTypeModel();
        $this->docModel = new ClaimDocumentModel();

        // Load filesystem helper if not autoloaded
        helper('filesystem');
    }


    // --- index() --- remains the same as before (fetching claimant's claims) ---
    public function index(int $claimantUserId = null)
    {
        // ... (keep existing index method) ...
//        $claimantUserId = auth()->id();
//        if ($claimantUserId === null) {
//            return $this->failUnauthorized('Authentication required.');
//        }

        try {
            $claims = $this->model
                ->select('claims.*, claim_types.name as claim_type_name') // Select type name
                ->join('claim_types', 'claim_types.id = claims.claim_type_id', 'left') // Join tables
                ->where('claims.claimant_user_id', $claimantUserId)
                ->orderBy('claims.created_at', 'DESC')
                ->findAll();

            return $this->respond([
                'message' => 'Claims retrieved successfully.',
                'data' => $claims,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[API Claims Index] Error: ' . $e->getMessage());
            return $this->failServerError('Could not retrieve claims.');
        }
    }

    // --- show($id) --- remains the same as before (fetching one claim) ---
    public function show($id = null, $claimantUserId = null)
    {
//        // ... (keep existing show method, consider adding join here too if needed) ...
//        $claimantUserId = auth()->id();
//        if ($claimantUserId === null) return $this->failUnauthorized('Authentication required.');
//        if ($id === null || ! is_numeric($id)) return $this->failValidationErrors('Valid Claim ID required.');

        try {
            $claim = $this->model
                ->select('claims.*, claim_types.name as claim_type_name') // Also get type name
                ->join('claim_types', 'claim_types.id = claims.claim_type_id', 'left')
                ->where('claims.claimant_user_id', $claimantUserId)
                ->find($id);

            if ($claim === null) {
                return $this->failNotFound('Claim not found or access denied.');
            }

            // Optionally Fetch Associated Documents Here
            $documents = $this->docModel->where('claim_id', $id)->findAll();
            $claim['documents'] = $documents ?? []; // Add documents to response

            return $this->respond([ 'message' => 'Claim details retrieved.', 'data' => $claim ]);
        } catch (\Throwable $e) {
            log_message('error', "[API Claims Show] Error fetching claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('Could not retrieve claim details.');
        }
    }

    /**
     * Create a new claim including file uploads.
     * Expects multipart/form-data request.
     *
     * @return ResponseInterface
     */
    public function create()
    {
//        $claimantUserId = auth()->id();
//        if ($claimantUserId === null) {
//            return $this->failUnauthorized('Authentication required.');
//        }
//
//        // Permission Check (Optional but recommended)
//        if (! auth()->user()->can('claims.submit')) {
//            return $this->failForbidden(lang('Auth.notEnoughPrivilege'));
//        }

        $validationRules = [
            'claimType'    => 'required|string',
            'incident_date'=> 'required|valid_date',
            'description'  => 'required|string|max_length[5000]', // Increased max length maybe?
            'user_id'    => 'required|integer|is_not_unique[users.id]',
            'documents' => [
                'label' => 'Supporting Documents',
                'rules' => 'uploaded[documents]|max_size[documents,5120]|ext_in[documents,png,jpg,jpeg,pdf,doc,docx]'
            ]
        ];

        if (! $this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $claimTypeStr   = $this->request->getPost('claimType');
        $incidentDate = $this->request->getPost('incident_date');
        $description  = $this->request->getPost('description');
        $uploadedFiles= $this->request->getFiles()['documents'] ?? []; // Get uploaded file objects

        // Ensure we always have an array, even if only one file uploaded
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = ($uploadedFiles instanceof UploadedFile && $uploadedFiles->isValid()) ? [$uploadedFiles] : [];
        }

        $type = $this->claimTypeModel->where('name', $claimTypeStr)->first();
        log_message('debug', '[API Claims Create] Claim Type: ' . $claimTypeStr);
        if (!$type) {
            return $this->failValidationErrors(['claimType' => 'Invalid Claim Type selected.']);
        }
        $claimTypeId = $type['id'];

        $claimData = [
            'claimant_user_id' => $this->request->getVar('user_id'),
            'claim_type_id'    => $claimTypeId,
            'incident_date'    => $incidentDate,
            'description'      => $description,
            'status'           => 'Pending',
        ];

        $db = db_connect();
        $db->transStart();

        $insertedClaimId = false;
        $movedFilesMeta = []; // To store details of successfully moved files
        $errorMovingFile = null; // Flag for file move/db errors

        try {
            $insertedClaimId = $this->model->insert($claimData, true);
            if ($insertedClaimId === false) {
                log_message('error', '[API Claims Create] ClaimModel insertion failed: ' . json_encode($this->model->errors()));
                $errorMovingFile = 'Claim data insertion failed.';
            } else {
                if (!empty($uploadedFiles)) {
                    $uploadPath = WRITEPATH . 'uploads/claims/' . time();
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0775, true);
                    }

                    foreach ($uploadedFiles as $file) {
                        if ($file instanceof UploadedFile && $file->isValid() && !$file->hasMoved()) {
                            $originalName = $file->getClientName();
                            $newName = $file->getRandomName();
                            $fileSize = $file->getSize();
                            $fileMime = $file->getMimeType();

                            if ($file->move($uploadPath, $newName)) {
                                // File moved successfully, prepare DB record
                                $docData = [
                                    'claim_id'            => $insertedClaimId,
                                    'uploaded_by_user_id' => $this->request->getVar('user_id'),
                                    'original_filename'   => $originalName,
                                    'stored_filename'     => $newName, // Stored name
                                    'file_path'           => str_replace(WRITEPATH . 'uploads/', '', $uploadPath), // Store relative path
                                    'file_size'           => $fileSize,
                                    'mime_type'           => $fileMime,
                                ];

                                // Insert document record
                                if ($this->docModel->insert($docData) === false) {
                                    log_message('error', "[API Claims Create] ClaimDocumentModel insertion failed for claim ID {$insertedClaimId}, file {$originalName}: " . json_encode($this->docModel->errors()));
                                    $errorMovingFile = "Failed to save document metadata for {$originalName}."; // Set flag
                                    // Attempt to delete the file we just moved, as the DB record failed
                                    try { @unlink($uploadPath . DIRECTORY_SEPARATOR . $newName); } catch (\Throwable $ex) {}
                                    break; // Stop processing more files
                                } else {
                                    // Optionally collect metadata of successfully saved files
                                    $movedFilesMeta[] = $docData;
                                }
                            } else {
                                log_message('error', "[API Claims Create] File move failed for claim ID {$insertedClaimId}, file {$originalName}: " . $file->getErrorString() . '(' . $file->getError() . ')');
                                $errorMovingFile = "Could not store uploaded file {$originalName}."; // Set flag
                                break; // Stop processing more files
                            }
                        }
                    } // End foreach file loop
                }
            } // End if $insertedClaimId check

            $db->transComplete();

            if ($db->transStatus() === false || $errorMovingFile !== null) {
                log_message('error', "[API Claims Create] Transaction failed for potential claim ID {$insertedClaimId}. File Error: {$errorMovingFile}");
                return $this->failServerError($errorMovingFile ?? 'Claim submission failed due to a database error.');
            } else {
                // --- 11. Success - Fetch and Return Data ---
                $newClaim = $this->model->find($insertedClaimId); // Fetch the full claim data
                // Optionally add documents that were saved:
                if ($newClaim) $newClaim['documents'] = $movedFilesMeta;

                return $this->respondCreated([
                    'message' => 'Claim submitted successfully.',
                    'data' => $newClaim
                ], 'Claim created');
            }

        } catch (\Throwable $e) { // Catch broader exceptions including DB/FS issues
            $db->transRollback(); // Ensure rollback on any other exception
            log_message('error', "[API Claims Create] Exception during transaction for potential claim ID {$insertedClaimId}: " . $e->getMessage());
            return $this->failServerError('An unexpected error occurred during claim submission.');
        }
    } // End create()


    // --- update() --- remains forbidden for claimants ---
    public function update($id = null)
    {
        return $this->failForbidden('Updating claims directly is not permitted.');
    }

    // --- delete() --- remains forbidden for claimants ---
    public function delete($id = null)
    {
        return $this->failForbidden('Deleting claims is not permitted.');
    }
}