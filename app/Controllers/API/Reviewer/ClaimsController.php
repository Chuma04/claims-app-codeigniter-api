<?php

namespace App\Controllers\API\Reviewer; // Correct Namespace

use App\Models\ClaimModel;
use App\Models\ClaimDocumentModel; // Needed for adding reviewer documents
use CodeIgniter\Files\File;        // For file validation/moving
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;         // For timestamping
use CodeIgniter\Shield\Authorization\AuthorizationException; // Optional for fine-grained permissions

class ClaimsController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = ClaimModel::class;
    protected $format    = 'json';
    protected ?ClaimDocumentModel $docModel = null;

    // Status constants for clarity
    private const STATUS_UNDER_REVIEW = 'Under Review';
    private const STATUS_PENDING = 'Pending'; // May see claims just assigned
    private const STATUS_SUBMITTABLE = 'Under Review'; // Only allow submit from this state
    private const STATUS_AFTER_SUBMISSION = 'Pending Approval'; // Next status after review

    // File upload constants (mirror frontend validation)
    private const MAX_REVIEWER_FILES = 3;
    private const MAX_FILE_SIZE_KB = 5120; // 5MB
    private const ALLOWED_FILE_EXT = 'png,jpg,jpeg,pdf,doc,docx';

    public function __construct()
    {
        $this->docModel = new ClaimDocumentModel();
        helper(['filesystem']); // Ensure filesystem helper is loaded
    }

    /**
     * Returns a list of claims ASSIGNED to the currently authenticated reviewer.
     * Primarily shows claims needing action.
     * GET /api/reviewer/claims
     *
     * @return ResponseInterface
     */
    public function index()
    {
//        $reviewerId = auth('tokens')->id();
//        if ($reviewerId === null) {
//            return $this->failUnauthorized('Authentication required.');
//        }

        try {
            // Fetch claims assigned to this reviewer, including relevant joins
            $claims = $this->model
                ->select('claims.*, ct.name as claim_type_name, uc.username as claimant_name')
                ->join('claim_types ct', 'ct.id = claims.claim_type_id', 'left')
                ->join('users uc', 'uc.id = claims.claimant_user_id', 'left')
//                ->where('claims.assigned_reviewer_id', user_id())
                ->whereIn('claims.status', [self::STATUS_UNDER_REVIEW, self::STATUS_PENDING]) // Focus on actionable statuses
                ->orderBy('claims.status', 'ASC')
                ->orderBy('claims.created_at', 'ASC')
                ->findAll();

            return $this->respond([
                'message' => 'Assigned claims retrieved successfully.',
                'data'    => $claims
            ]);

        } catch (\Throwable $e) {
            log_message('error', '[API Reviewer Claims Index] Error for reviewer ID ' . user_id() . ': ' . $e->getMessage());
            return $this->failServerError('Could not retrieve assigned claims.');
        }
    }

    /**
     * Shows the details of a specific claim, including documents,
     * *only* if it is assigned to the authenticated reviewer.
     * GET /api/reviewer/claims/{id}
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function show($id = null)
    {
        $reviewerId = auth()->id();
        if ($reviewerId === null) {
            return $this->failUnauthorized('Authentication required.');
        }
        if ($id === null || !is_numeric($id)) {
            return $this->failValidationErrors('Valid Claim ID is required.');
        }

        // Optional permission check
        // if (! auth()->user()->can('claims.view.assigned')) {
        //     return $this->failForbidden(lang('Auth.notEnoughPrivilege'));
        // }

        try {
            // Verify claim exists AND is assigned to this reviewer
            $claim = $this->model
                ->select('claims.*, ct.name as claim_type_name, uc.username as claimant_name')
                ->join('claim_types ct', 'ct.id = claims.claim_type_id', 'left')
                ->join('users uc', 'uc.id = claims.claimant_user_id', 'left')
                ->where('claims.id', $id)
                ->where('claims.assigned_reviewer_id', $reviewerId) // CRITICAL assignment check
                ->first();

            if ($claim === null) {
                return $this->failNotFound('Claim not found or not assigned to you.');
            }

            // Fetch associated documents (claimant and reviewer)
            $documents = $this->docModel->where('claim_id', $id)->orderBy('created_at', 'ASC')->findAll();
            $claim['documents'] = $documents ?? []; // Add documents to the response

            return $this->respond([
                'message' => 'Claim details retrieved successfully.',
                'data'    => $claim
            ]);

        } catch (\Throwable $e) {
            log_message('error', "[API Reviewer Claims Show] Error fetching claim ID {$id} for reviewer {$reviewerId}: {$e->getMessage()}");
            return $this->failServerError('Could not retrieve claim details.');
        }
    }

    /**
     * Updates a claim's status to 'Pending Approval', saves reviewer notes,
     * and stores reviewer-uploaded documents. Expects multipart/form-data.
     * PATCH /api/reviewer/claims/{id}/submit-for-approval
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function submitForApproval($id = null)
    {
        $reviewerId = auth()->id();
        if ($reviewerId === null) return $this->failUnauthorized('Authentication required.');
        if ($id === null || !is_numeric($id)) return $this->failValidationErrors('Valid Claim ID required.');

        // Optional permission check
        if (! auth()->user()->can('claims.review')) {
            return $this->failForbidden('You do not have permission to submit claims for approval.');
        }

        // --- 1. Define Validation Rules for Reviewer Input ---
        $validationRules = [
            // Expect notes from getPost()
            'reviewer_notes' => 'required|string|max_length[5000]',
            // Expect files from getFiles()
            'reviewer_documents' => [ // Key matches frontend FormData append
                'label' => 'Reviewer Documents',
                'rules' => 'max_files[reviewer_documents,' . self::MAX_REVIEWER_FILES . ']'
                    . '|max_size[reviewer_documents,' . self::MAX_FILE_SIZE_KB . ']'
                    . '|ext_in[reviewer_documents,' . self::ALLOWED_FILE_EXT . ']',
                // Note: 'uploaded' check is not needed here as files are optional
            ]
        ];

        // --- 2. Validate Request Data ---
        if (! $this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // --- 3. Process Validated Data ---
        $reviewerNotes = $this->request->getPost('reviewer_notes');
        // Use [] default to handle case where no files are uploaded at all
        $uploadedFiles = $this->request->getFiles()['reviewer_documents'] ?? [];
        // Ensure it's always an array
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = ($uploadedFiles instanceof \CodeIgniter\HTTP\Files\UploadedFile && $uploadedFiles->isValid()) ? [$uploadedFiles] : [];
        }


        // --- 4. Database Transaction ---
        $db = db_connect();
        $db->transStart();

        $errorSavingDocs = null; // Flag for document saving errors within transaction

        try {
            // --- 5. Authorization & Status Checks ---
            $claim = $this->model
                ->where('id', $id)
                ->where('assigned_reviewer_id', $reviewerId)
                ->first();

            if ($claim === null) {
                // No need to proceed or rollback, fail early
                return $this->failNotFound('Claim not found or not assigned to you.');
            }
            if ($claim['status'] !== self::STATUS_SUBMITTABLE) {
                // Request is invalid based on current state
                return $this->failValidationErrors(['status' => 'Claim must be "' . self::STATUS_SUBMITTABLE . '" to submit for approval. Current status: ' . $claim['status']]);
            }

            // --- 6. Prepare Claim Update Data ---
            $updateData = [
                'status'                      => self::STATUS_AFTER_SUBMISSION,
                'submitted_for_approval_at' => Time::now()->toDateTimeString(),
                'reviewer_notes'              => $reviewerNotes, // Save the notes
            ];

            // --- 7. Update Claim Record ---
            if ($this->model->update($id, $updateData) === false) {
                // Should be rare if initial checks pass, but handle model validation errors
                log_message('error', "[API Reviewer Submit] Model update failed for claim ID {$id}: " . json_encode($this->model->errors()));
                // Setting this flag ensures transaction fails even if file handling doesn't run/error
                $errorSavingDocs = "Failed to update claim status or notes.";
            } else {
                // --- 8. Process and Store Uploaded Reviewer Files ---
                if (!empty($uploadedFiles) && $errorSavingDocs === null) { // Only proceed if claim update worked
                    $uploadPath = WRITEPATH . 'uploads/reviewer_docs/' . date('Y/m'); // Distinct path maybe?
                    if (! is_dir($uploadPath)) {
                        mkdir($uploadPath, 0775, true);
                    }

                    foreach ($uploadedFiles as $file) {
                        // Need this check again inside the loop
                        if ($file instanceof \CodeIgniter\HTTP\Files\UploadedFile && $file->isValid() && !$file->hasMoved()) {
                            $originalName = $file->getClientName();
                            $newName = $file->getRandomName();
                            $fileSize = $file->getSize();
                            $fileMime = $file->getMimeType();

                            if ($file->move($uploadPath, $newName)) {
                                $docData = [
                                    'claim_id'            => $id, // Link to current claim
                                    'uploaded_by_user_id' => $reviewerId, // Reviewer uploading
                                    'original_filename'   => $originalName,
                                    'stored_filename'     => $newName,
                                    'file_path'           => str_replace(WRITEPATH . 'uploads/', '', $uploadPath), // Relative path
                                    'file_size'           => $fileSize,
                                    'mime_type'           => $fileMime,
                                    'is_review_document'  => true, // <-- SET REVIEW FLAG
                                ];

                                if ($this->docModel->insert($docData) === false) {
                                    log_message('error', "[API Reviewer Submit] Doc insert failed for claim {$id}, reviewer {$reviewerId}, file {$originalName}: ".json_encode($this->docModel->errors()));
                                    $errorSavingDocs = "Failed to save metadata for {$originalName}.";
                                    // Attempt to cleanup file
                                    try { @unlink($uploadPath . DIRECTORY_SEPARATOR . $newName); } catch (\Throwable $ex) {}
                                    break; // Exit file processing loop
                                }
                            } else {
                                log_message('error', "[API Reviewer Submit] File move failed for claim ID {$id}, reviewer {$reviewerId}, file {$originalName}: {$file->getErrorString()}");
                                $errorSavingDocs = "Could not store uploaded file {$originalName}.";
                                break; // Exit file processing loop
                            }
                        }
                    } // End foreach
                } // End if !empty($uploadedFiles)
            } // End if claim update succeeded

            // --- 9. Complete Transaction ---
            $db->transComplete();

            // --- 10. Check Transaction Status ---
            if ($db->transStatus() === false || $errorSavingDocs !== null) {
                log_message('error', "[API Reviewer Submit Approval] Transaction failed for claim ID {$id}. Error: " . ($errorSavingDocs ?? 'DB Transaction Failure'));
                // Rollback happened automatically
                return $this->failServerError($errorSavingDocs ?? 'Failed to submit claim for approval due to a processing error.');
            }

            // --- 11. Success ---
            $updatedClaim = $this->model->find($id); // Fetch the fully updated record
            // Fetch updated documents as well if needed for immediate UI update
            $documents = $this->docModel->where('claim_id', $id)->orderBy('created_at', 'ASC')->findAll();
            $updatedClaim['documents'] = $documents ?? [];

            return $this->respondUpdated([
                'message' => 'Claim submitted for final approval.',
                'data'    => $updatedClaim
            ], 'Claim submitted for approval');

        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', "[API Reviewer Submit Approval] Exception for claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('An unexpected error occurred while submitting for approval.');
        }
    } // End submitForApproval()


    // --- Methods Forbidden for Reviewers in this Context ---

    public function create()
    {
        return $this->failForbidden('Reviewers cannot create new claims via this endpoint.');
    }

    public function update($id = null)
    {
        return $this->failForbidden('General claim update not permitted for reviewers. Use specific actions.');
    }

    public function delete($id = null)
    {
        return $this->failForbidden('Deleting claims is not permitted for reviewers.');
    }
}