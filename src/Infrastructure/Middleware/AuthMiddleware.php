<?php
declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AuthMiddleware {
    // Inject the abstract PSR-7 Response Factory via pure DI 
    // This removes the concrete hardcoded "new Response()" implementation!
    public function __construct(
        private ResponseFactory $responseFactory
    ) {}

    /**
     * Process an incoming server request intercept loop.
     */
    public function __invoke(Request $request, Handler $handler): Response {
        if (session_status() === PHP_SESSION_NONE) { 
            session_start(); 
        }

        if (!isset($_SESSION['user_id'])) {
            // Create a clean, interface-compliant response out of thin air safely
            return $this->responseFactory->createResponse()
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }
        
        return $handler->handle($request);
    }
}