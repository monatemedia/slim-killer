<?php
declare(strict_types=1);

namespace App\Http\Application;

use App\Actions\SubmitApplicationAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProcessApplyController {
    public function __construct(
        private SubmitApplicationAction $action
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response {
        $data = $request->getParsedBody();
        $success = $this->action->execute($data);

        if ($success) {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            // Aligned with the input attribute: name="first_name"
            $_SESSION['flash_name'] = $data['first_name'] ?? 'Client';
            
            return $response->withHeader('Location', '/success')->withStatus(302);
        }

        return $response->withHeader('Location', '/apply?error=1')->withStatus(302);
    }
}