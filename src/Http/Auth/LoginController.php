<?php

namespace App\Http\Auth;

use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LoginController {
    public function __invoke(Request $request, Response $response): Response {
        $params = $request->getParsedBody();
        $user = DB::table('users')->where('username', $params['username'])->first();

        if ($user && password_verify($params['password'], $user->password)) {
            $_SESSION['user_id'] = $user->id;
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        return $response->withHeader('Location', '/login?error=invalid')->withStatus(302);
    }
}