<?php

namespace App\Http\Auth;

use Jenssegers\Blade\Blade;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ShowLoginController {
    protected $blade;

    public function __construct(Blade $blade) {
        $this->blade = $blade;
    }

    public function __invoke(Request $request, Response $response): Response {
        $html = $this->blade->make('admin.login')->render();
        $response->getBody()->write($html);
        return $response;
    }
}