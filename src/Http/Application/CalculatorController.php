<?php
declare(strict_types=1);

namespace App\Http\Application;

use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CalculatorController {
    public function __construct(
        private Twig $view
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response {
        return $this->view->render($response, 'calculator.twig', [
            'title' => 'Mortgage Calculator | Manage'
        ]);
    }
}