<?php
declare(strict_types=1);

namespace App\Http\Application;

use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SuccessController {
    public function __construct(
        private Twig $view
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $name = $_SESSION['flash_name'] ?? 'Client';
        unset($_SESSION['flash_name']);

        return $this->view->render($response, 'pages/success.twig', [
            'name' => $name
        ]);
    }
}