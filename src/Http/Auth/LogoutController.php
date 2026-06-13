<?php

namespace App\Http\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogoutController {
    public function __invoke(Request $request, Response $response): Response {
        session_destroy();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}