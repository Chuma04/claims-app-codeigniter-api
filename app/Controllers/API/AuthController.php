<?php

namespace App\Controllers\API;

use App\Models\OutputModels\UserOutputModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Authentication\Authenticators\Tokens; // Need this? Possibly just auth() service.
use CodeIgniter\Shield\Exceptions\ValidationException;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use Exception;

class AuthController extends ResourceController
{
    protected UserModel $userModel;

    /**
     * Authenticate user and return JWT.
     *
     * @return ResponseInterface
     */
    public function login()
    {
        if(auth()->loggedIn()) {
            $this->logout();
        }

        $rules = [
            'email' => [
                'label' => 'Login ID',
                'rules' => 'required|is_not_unique[auth_identities.secret]|valid_email',
                'errors' => [
                    'required' => 'Email address is required',
                    'valid_email' => 'Email address is not valid',
                    'is_not_unique' => 'This user does not exist'
                ]
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required',
                'errors' => [
                    'required' => 'Password is required'
                ]
            ]
        ];

        $this->validator = Services::validation();
        $this->validator->setRules($rules);

        if (!$this->validator->withRequest($this->request)->run()){
            $error = implode(" | ", $this->validator->getErrors());
            return $this->respond([
                'success' => false,
                'message' => $error,
                'data' => null
            ], 400, "Validation error");
        }
        $this->userModel = new UserModel();
        $user = $this->userModel->findByCredentials(['email' => $this->request->getVar('email')]);
        try {
            if($user->getGroups()[0] == 'user' && !$user->active) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Your account has not yet been activated',
                    'data' => null
                ], 400, "Account not activated");
            }

            log_message('info', 'User login attempt: ' . $user->email . ' - ' . $this->request->getVar('password'));
            if (!($result = auth()->check(['email' => $user->email, 'password' => $this->request->getVar('password')]))->isOK()) {
                log_message('error', 'User login failed because: ' . $result->reason());
                $response = [
                    'success' => false,
                    'message' => 'Invalid password',
                    'data' => null
                ];

                return $this->respond($response, 401, "Invalid password");
            }
            else {
                $token = $user->generateAccessToken(env('auth.jwt.secretKey'));
                $authToken = $token->raw_token;
                log_message('info' , 'User login successful. Token' . $authToken);

                // get the access token that has just been created from the auth_identities table
                $user = $this->userModel->where('id', $user->id)->first();
                $db = \Config\Database::connect();
                $builder = $db->table('auth_identities');
                $builder->where('user_id', $user->id);
                $builder->where('type', 'access_token');
                // get the latest token
                $builder->orderBy('created_at', 'DESC');
                $builder->limit(1);
                $query = $builder->get();
                $token = $query->getRow();
                $authToken = $token->secret;
                log_message('info' , 'User login successful. Token' . $authToken);

                $userRole = $user->getGroups()[0];
                $responseData = [
                    'token' => $authToken,
                    'user' => [
                        'id'       => $user->id,
                        'name'     => ucfirst($user->username), // Use username, or add a 'name' field to your users table/entity
                        'email'    => $user->email,
                        'role'     => $userRole,
                    ],
                ];

                return $this->respondCreated($responseData, 'Login successful');
            }
        }
        catch (Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500, "Something went wrong while logging in the user");
        }
    }

    /**
     * Placeholder for logout if needed for stateless tokens
     * Often logout is handled purely client-side for JWT by deleting the token.
     * If using refresh tokens or server-side token invalidation, this would be needed.
     *
     * @return ResponseInterface
     */
     public function logout() : ResponseInterface
     {
         auth('tokens')->logout();
         return $this->respondDeleted(['message' => 'Logged out successfully']);
     }
}