<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WebsiteBar;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * /cp/preferences: each person's own choices. For now, whether they see the website
 * bar on the website. Saving sends them back here, so the page that follows tells
 * this browser the new choice at once.
 */
final class PreferencesAction
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = SignedIn::user($request);
        $session = Session::of($request);

        if ($request->getMethod() === 'POST') {
            $bar = WebsiteBar::tryFrom(Form::field($request, 'website_bar')) ?? $user->websiteBar;
            $this->users->setWebsiteBar($user->id, $bar);

            return Form::redirect($response, '/preferences?saved=1');
        }

        return $this->view->render($response, 'cp/preferences.twig', [
            'website_bar' => $user->websiteBar->value,
            'saved' => Form::query($request, 'saved') === '1',
            'csrf' => $session->csrfToken(),
        ]);
    }
}
