<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Content\PageAddresses;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\Session\Session;

/**
 * /cp/me?path=/about/: asked by the website bar, which only loads in a browser that
 * signed in. Says whether that is still true (a session can end while the browser's
 * marker stays), who it is, and where to edit the page at that address.
 *
 * Not behind SignedIn, which would answer with a redirect: someone signed out gets
 * {"signedIn": false}, and the bar takes its marker away. Nothing here changes
 * anything, and nothing is said about anyone else.
 */
final class MeAction
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PageAddresses $addresses,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = Session::of($request)->userId();
        $user = $userId === null ? null : $this->users->byId($userId);

        if ($user === null) {
            return $this->json($response, ['signedIn' => false]);
        }

        $base = ControlPanel::BASE_PATH;
        $entry = $this->addresses->entryAt(Form::query($request, 'path'));

        return $this->json($response, [
            'signedIn' => true,
            'name' => $user->name,
            'bar' => $user->websiteBar->value,
            'edit' => $entry === null ? null : $base . '/content/' . rawurlencode($entry['collection']) . '/' . rawurlencode($entry['slug']),
            'dashboard' => $base . '/',
            'visits' => $base . '/visits',
            'preferences' => $base . '/preferences',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
