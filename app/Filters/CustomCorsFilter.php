<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomCorsFilter implements FilterInterface
{
    /**
     * Handles Cross-Origin Resource Sharing preflight requests and adds CORS headers to responses.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return object|void|null The response object or void if execution should continue
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowedOrigins = ['http://localhost:5173'];
        $origin = $request->getHeaderLine('Origin');

        // Default headers
        $headers = [
            'Access-Control-Allow-Origin'      => in_array($origin, $allowedOrigins) ? $origin : 'null',
            'Access-Control-Allow-Headers'     => 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization',
            'Access-Control-Allow-Methods'     => 'GET, POST, OPTIONS, PUT, DELETE, PATCH',
            'Access-Control-Allow-Credentials' => 'false',
            'Access-Control-Max-Age'           => '86400',
        ];

        // Handle the preflight OPTIONS request
        if (strtolower($request->getMethod()) === 'options') {
            $response = service('response');
            foreach ($headers as $key => $value) {
                $response->setHeader($key, $value);
            }

            // Set status code to 200 OK or 204 No Content
            $response->setStatusCode(ResponseInterface::HTTP_OK);
            return $response;
        }
    }

    /**
     * Adds CORS headers to the actual response after the controller runs.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // List of allowed origins (should match 'before' method)
        $allowedOrigins = ['http://localhost:5173']; // Adjust as needed
        $origin = $request->getHeaderLine('Origin');

        // Set basic CORS headers for the actual response
        // Crucially, set Allow-Origin again for the actual response
        if (in_array($origin, $allowedOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Credentials', 'true'); // Match 'before' if needed
        } else {
            $response->setHeader('Access-Control-Allow-Origin', 'null');
        }


        // Note: Other headers like Allow-Methods/Headers are generally only needed
        // for the preflight response handled in the 'before' method.
        // You might need Access-Control-Expose-Headers here if your frontend
        // needs to read custom headers from the response.
        // $response->setHeader('Access-Control-Expose-Headers', 'Content-Length, X-My-Custom-Header');
    }
}