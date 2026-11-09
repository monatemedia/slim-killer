<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\Session\Session;

/** Signs out: a POST, with the form token, so another website cannot sign you out. */
final class SignOutAction
{
    public function __construct(
        private readonly AuditLog $audit,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        if ($session->userId() !== null) {
            $this->audit->record('sign_out', $session->userId(), Form::ip($request));
        }
        $session->destroy();

        return Form::redirect($response, '/sign-in');
    }
}
