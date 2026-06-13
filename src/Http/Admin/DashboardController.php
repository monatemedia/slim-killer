<?php

namespace App\Http\Admin;

use Jenssegers\Blade\Blade;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController {
    protected $blade;

    // The DI container automatically injects Blade here!
    public function __construct(Blade $blade) {
        $this->blade = $blade;
    }

    /**
     * Handle the incoming GET /admin request.
     */
    public function __invoke(Request $request, Response $response): Response {
        // Fetch leads from the database using Capsule DB
        $leads = DB::table('applications')->orderBy('created_at', 'desc')->get();
        
        // Render the admin.dashboard blade view template
        $html = $this->blade->make('admin.dashboard', ['leads' => $leads])->render();
        
        $response->getBody()->write($html);
        return $response;
    }
}