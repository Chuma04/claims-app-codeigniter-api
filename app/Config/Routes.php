<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// --- API Routes ---
$routes->group('api', [
    'namespace' => 'App\Controllers\API'
], static function (RouteCollection $routes) {

    // --- Authentication
    $routes->group('auth', ['namespace' => 'App\Controllers\API'], static function (RouteCollection $routes) {
        $routes->options('login', function () {
            return $this->response->setStatusCode(204);
        });
        $routes->post('login', 'AuthController::login');
        $routes->delete('logout', 'AuthController::logout', ['filter' => 'auth:tokens']);
    });

    // --- Claimant Routes ---
    $routes->group('claimant', [
        'namespace' => 'App\Controllers\API\Claimant',
    ], static function (RouteCollection $routes) {
        $routes->group('claims', static function (RouteCollection $routes) {
            $routes->options('/', function () {
                return $this->response->setStatusCode(204);
            });
            $routes->options('(:num)', function () {
                return $this->response->setStatusCode(204);
            });
            $routes->get('(:num)', 'ClaimsController::index/$1');
            $routes->post('/', 'ClaimsController::create');
            $routes->get('(:num)/(:num)', 'ClaimsController::show/$1/$2');
            $routes->options('(:num)/(:num)', function () {
                return $this->response->setStatusCode(204);
            });
        });

    });

    // --- Reviewer Routes (Maker) ---
    $routes->group('reviewer', [
        'namespace' => 'App\Controllers\API\Reviewer',
//        'filter'    => 'apiauth'
    ], static function (RouteCollection $routes) {
        $routes->group('claims', static function (RouteCollection $routes) {
            $routes->options('/', function () {
                return $this->response->setStatusCode(204);
            });
            $routes->get('/', 'ClaimsController::index');
            $routes->get('(:num)', 'ClaimsController::show/$1');
            $routes->options('(:num)', function () {
                return $this->response->setStatusCode(204);
            });
            $routes->options('(:num)/submit-for-approval', function () {
                return $this->response->setStatusCode(204);
            });
            $routes->patch('(:num)/submit-for-approval', 'ClaimsController::submitForApproval/$1');
        });
    });

    // --- Checker Routes ---
    $routes->group('checker', [
        'namespace' => 'App\Controllers\API\Checker',
    ], static function (RouteCollection $routes) {

        $routes->group('claims', static function (RouteCollection $routes) {
            $routes->get('/', 'ClaimsController::index');
            $routes->get('(:num)', 'ClaimsController::show/$1');
            $routes->patch('(:num)/assign', 'ClaimsController::assignClaim/$1');
            $routes->patch('(:num)/approve/(:num)', 'ClaimsController::approveClaim/$1/$2');
            $routes->patch('(:num)/deny', 'ClaimsController::denyClaim/$1');
        });

        // User management
        $routes->group('users', static function (RouteCollection $routes) {
            $routes->get('/', 'UsersController::index');
        });
    });

});

service('auth')->routes($routes);

// Optional: Add a fallback route for API 404s if needed (as before)
// $routes->set404Override(...)