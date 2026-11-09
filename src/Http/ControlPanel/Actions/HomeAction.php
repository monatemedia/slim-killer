<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * /cp/: the dashboard when signed in. Before anyone exists, it says only that the
 * panel is being set up; after that, it sends visitors to the sign-in page.
 */
final class HomeAction
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly RecoveryCodes $recoveryCodes,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        $userId = $session->userId();
        $user = $userId === null ? null : $this->users->byId($userId);

        if ($user !== null) {
            return $this->view->render($response, 'cp/dashboard.twig', [
                'name' => $user->name,
                'role' => $user->role->label(),
                'codes_left' => $this->recoveryCodes->remaining($user->id),
                'csrf' => $session->csrfToken(),
            ]);
        }

        if ($this->users->any()) {
            return Form::redirect($response, '/sign-in');
        }

        return $this->view->render($response, 'cp/waiting.twig', ['link_refused' => false]);
    }
}
