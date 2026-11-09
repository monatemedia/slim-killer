<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Http\ControlPanel\View;

/**
 * /cp/ before anyone can log in. It says only that the panel is being set up: the
 * host report is on the setup page, behind the link the doctor prints.
 *
 * Once the first user exists (the install branch), this becomes the login page.
 */
final class HomeAction
{
    public function __construct(
        private readonly View $view,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'cp/waiting.twig', ['link_refused' => false]);
    }
}
