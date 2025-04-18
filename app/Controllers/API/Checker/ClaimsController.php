<?php

namespace App\Controllers\API\Checker; // Correct Namespace

use App\Models\ClaimModel;
use App\Models\ClaimDocumentModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Models\UserModel as UserModel;

// Optional for permissions

class ClaimsController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = ClaimModel::class;
    protected $format    = 'json';
    protected ?ClaimDocumentModel $docModel = null;
    protected ?UserModel $userModel = null; // For validating reviewer exists

    // Status Constants for logic clarity
    private const STATUS_PENDING = 'Pending';
    private const STATUS_UNDER_REVIEW = 'Under Review';
    private const STATUS_PENDING_APPROVAL = 'Pending Approval';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_DENIED = 'Denied';

    public function __construct()
    {
        $this->docModel = new ClaimDocumentModel();
        // Assuming Shield's UserModel is located at App\Models\UserModel
        // Adjust namespace if you placed it differently or extended it elsewhere
        $this->userModel = new UserModel();
        helper(['filesystem']);
    }

    /**
     * Returns a list of claims accessible to the checker.
     * Allows filtering by status via query parameter (e.g., /api/checker/claims?status=Pending)
     * GET /api/checker/claims
     *
     * @return ResponseInterface
     */
    public function index()
    {
        try {
            // Base query with necessary joins for display
            $query = $this->model
                ->select('claims.*, ct.name as claim_type_name, uc.username as claimant_name, ur.username as reviewer_name')
                ->join('claim_types ct', 'ct.id = claims.claim_type_id', 'left')
                ->join('users uc', 'uc.id = claims.claimant_user_id', 'left')
                ->join('users ur', 'ur.id = claims.assigned_reviewer_id', 'left');

            // Apply optional status filter from query string
            $statusFilter = $this->request->getGet('status');
            if ($statusFilter && is_string($statusFilter)) {
                // Allow multiple statuses separated by comma potentially
                $statuses = explode(',', $statusFilter);
                $validStatuses = array_map('trim', $statuses); // Trim whitespace
                // Basic validation (add more strict checks if needed)
                if (!empty($validStatuses)) {
                    $query->whereIn('claims.status', $validStatuses);
                }
            }

            // Add default ordering
            $query->orderBy('claims.created_at', 'DESC');

            $claims = $query->findAll();

            return $this->respond([
                'message' => 'Claims retrieved successfully.',
                'data'    => $claims
            ]);

        } catch (\Throwable $e) {
            log_message('error', '[API Checker Claims Index] Error: ' . $e->getMessage());
            return $this->failServerError('Could not retrieve claims.');
        }
    }

    /**
     * Shows the details of any specific claim, including documents.
     * GET /api/checker/claims/{id}
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function show($id = null)
    {
//        $checkerId = auth()->id(); // Get ID for logging/potential future use
//        if ($checkerId === null) {
//            return $this->failUnauthorized('Authentication required.');
//        }
        if ($id === null || !is_numeric($id)) {
            return $this->failValidationErrors('Valid Claim ID is required.');
        }

        // Optional permission check
        // if (! auth()->user()->can('claims.view.all')) {
        //     return $this->failForbidden(lang('Auth.notEnoughPrivilege'));
        // }

        try {
            // Checkers can view any claim, fetch with all details needed
            $claim = $this->model
                ->select('claims.*, ct.name as claim_type_name, uc.username as claimant_name, ur.username as reviewer_name')
                ->join('claim_types ct', 'ct.id = claims.claim_type_id', 'left')
                ->join('users uc', 'uc.id = claims.claimant_user_id', 'left')
                ->join('users ur', 'ur.id = claims.assigned_reviewer_id', 'left')
                ->find($id); // Find by ID

            if ($claim === null) {
                return $this->failNotFound('Claim not found.');
            }

            // Fetch associated documents (both claimant and reviewer)
            $documents = $this->docModel->where('claim_id', $id)->orderBy('is_review_document', 'ASC')->orderBy('created_at', 'ASC')->findAll();
            $claim['documents'] = $documents ?? [];

            return $this->respond([
                'message' => 'Claim details retrieved successfully.',
                'data'    => $claim
            ]);

        } catch (\Throwable $e) {
            log_message('error', "[API Checker Claims Show] Error fetching claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('Could not retrieve claim details.');
        }
    }

    /**
     * Assigns a 'Pending' claim to a specified reviewer.
     * PATCH /api/checker/claims/{id}/assign
     * Expects JSON: { "reviewer_id": <user_id> }
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function assignClaim($id = null)
    {
//        $checkerId = auth()->id();
//        if ($checkerId === null) return $this->failUnauthorized('Authentication required.');
        if ($id === null || !is_numeric($id)) return $this->failValidationErrors('Valid Claim ID required.');

        // Permission Check
        // if (! auth()->user()->can('claims.assign')) {
        //     return $this->failForbidden('You do not have permission to assign claims.');
        // }

        // --- Validate Input ---
        $json = $this->request->getJSON(true);
        $reviewerId = $json['reviewer_id'] ?? null;

        if ($reviewerId === null || !is_numeric($reviewerId)) {
            return $this->failValidationErrors(['reviewer_id' => 'A valid reviewer ID is required.']);
        }

        try {
            // --- Authorization & Status Checks ---
            // 1. Fetch the claim
            $claim = $this->model->find($id);
            if ($claim === null) {
                return $this->failNotFound('Claim not found.');
            }
            // 2. Ensure it's currently 'Pending'
            if ($claim['status'] !== self::STATUS_PENDING) {
                return $this->failValidationErrors(['status' => 'Claim must be "Pending" to be assigned. Current status: ' . $claim['status']]);
            }
            // 3. Ensure reviewer exists and has 'reviewer' role (important!)
            $reviewerUser = $this->userModel->find($reviewerId);
            if ($reviewerUser === null || !$reviewerUser->inGroup('reviewer')) { // Shield's inGroup() method
                return $this->respond('Invalid reviewer ID or user is not a reviewer.', 400);
            }

            // --- Perform Update ---
            $updateData = [
                'assigned_reviewer_id' => (int) $reviewerId,
                'status'               => self::STATUS_UNDER_REVIEW, // Assigning moves to Under Review
            ];

            if ($this->model->update($id, $updateData) === false) {
                log_message('error', "[API Checker Assign Claim] Model update failed for claim ID {$id}: " . json_encode($this->model->errors()));
                return $this->failValidationErrors($this->model->errors());
            }

            // --- Success Response ---
            $updatedClaim = $this->model->find($id); // Fetch updated record
            return $this->respondUpdated([
                'message' => "Claim {$id} assigned to reviewer {$reviewerId} successfully.",
                'data'    => $updatedClaim
            ], 'Claim assigned');

        } catch (\Throwable $e) {
            log_message('error', "[API Checker Assign Claim] Exception for claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('An unexpected error occurred while assigning the claim.');
        }
    }

    /**
     * Approves a claim currently in 'Pending Approval' status.
     * PATCH /api/checker/claims/{id}/approve
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function approveClaim($id = null, $checkerId = null)
    {
//        $checkerId = auth()->id();
//        if ($checkerId === null) return $this->failUnauthorized('Authentication required.');
        if ($id === null || !is_numeric($id)) return $this->failValidationErrors('Valid Claim ID required.');

        // Permission Check
        // if (! auth()->user()->can('claims.approve')) {
        //     return $this->failForbidden('You do not have permission to approve claims.');
        // }

        try {
            // --- Authorization & Status Checks ---
            $claim = $this->model->find($id);
            if ($claim === null) return $this->failNotFound('Claim not found.');
            if ($claim['status'] !== self::STATUS_PENDING_APPROVAL) {
                return $this->failValidationErrors(['status' => 'Claim must be "Pending Approval" to be approved. Current status: ' . $claim['status']]);
            }

            // --- Perform Update ---
            $updateData = [
                'status'               => self::STATUS_APPROVED,
                'final_action_user_id' => $checkerId,
                'final_action_at'      => Time::now()->toDateTimeString(),
                'denial_reason'        => null // Clear denial reason if previously denied then reconsidered?
            ];

            if ($this->model->update($id, $updateData) === false) {
                log_message('error', "[API Checker Approve Claim] Model update failed for claim ID {$id}: " . json_encode($this->model->errors()));
                return $this->failValidationErrors($this->model->errors());
            }

            // --- Success Response ---
            $updatedClaim = $this->model->find($id);
            return $this->respondUpdated([
                'message' => "Claim {$id} approved successfully.",
                'data'    => $updatedClaim
            ], 'Claim approved');

        } catch (\Throwable $e) {
            log_message('error', "[API Checker Approve Claim] Exception for claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('An unexpected error occurred while approving the claim.');
        }
    }

    /**
     * Denies a claim currently in 'Pending Approval' status.
     * PATCH /api/checker/claims/{id}/deny
     * Expects optional JSON: { "denial_reason": "..." }
     *
     * @param int|string|null $id Claim ID
     * @return ResponseInterface
     */
    public function denyClaim($id = null)
    {
        $checkerId = auth()->id();
        if ($checkerId === null) return $this->failUnauthorized('Authentication required.');
        if ($id === null || !is_numeric($id)) return $this->failValidationErrors('Valid Claim ID required.');

        // Permission Check
        // if (! auth()->user()->can('claims.approve')) { // Often same permission as approve
        //     return $this->failForbidden('You do not have permission to deny claims.');
        // }

        // Get denial reason from JSON payload (optional)
        $json = $this->request->getJSON(true);
        $denialReason = isset($json['denial_reason']) && is_string($json['denial_reason'])
            ? trim($json['denial_reason'])
            : null;

        // You might want to make denial_reason mandatory here:
        // if (empty($denialReason)) {
        //     return $this->failValidationErrors(['denial_reason' => 'A reason is required for denial.']);
        // }

        try {
            // --- Authorization & Status Checks ---
            $claim = $this->model->find($id);
            if ($claim === null) return $this->failNotFound('Claim not found.');
            if ($claim['status'] !== self::STATUS_PENDING_APPROVAL) {
                return $this->failValidationErrors(['status' => 'Claim must be "Pending Approval" to be denied. Current status: ' . $claim['status']]);
            }

            // --- Perform Update ---
            $updateData = [
                'status'               => self::STATUS_DENIED,
                'final_action_user_id' => $checkerId,
                'final_action_at'      => Time::now()->toDateTimeString(),
                'denial_reason'        => $denialReason, // Store the reason
            ];

            if ($this->model->update($id, $updateData) === false) {
                log_message('error', "[API Checker Deny Claim] Model update failed for claim ID {$id}: " . json_encode($this->model->errors()));
                return $this->failValidationErrors($this->model->errors());
            }

            // --- Success Response ---
            $updatedClaim = $this->model->find($id);
            return $this->respondUpdated([
                'message' => "Claim {$id} denied successfully.",
                'data'    => $updatedClaim
            ], 'Claim denied');

        } catch (\Throwable $e) {
            log_message('error', "[API Checker Deny Claim] Exception for claim ID {$id}: {$e->getMessage()}");
            return $this->failServerError('An unexpected error occurred while denying the claim.');
        }
    }

    // --- Forbidden Methods for Checker ---
    // Checkers generally don't create claims directly, nor use generic update/delete via API
    public function create() { return $this->failForbidden('Checkers cannot create claims via this endpoint.'); }
    public function update($id = null) { return $this->failForbidden('Generic update not permitted. Use specific actions (assign/approve/deny).'); }
    public function delete($id = null) { return $this->failForbidden('Deleting claims is not permitted via API.'); }

}