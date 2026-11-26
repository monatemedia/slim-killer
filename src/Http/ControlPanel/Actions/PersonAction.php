<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\ControlPanel\WelcomeLinkSender;
use SlimKiller\Http\Session\Session;

/**
 * /cp/people/{id}/reset and /cp/people/{id}/remove: first a page that asks, then the
 * change, for someone this person may manage (User::canManage).
 *
 * Reset is `hammer user:reset` from the panel: password, two-factor, recovery codes and
 * browsers cleared, signed out everywhere, and a new welcome link sent. Remove deletes
 * the account; the audit log keeps what they did.
 */
final class PersonAction
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly WelcomeLinkSender $sender,
        private readonly AuditLog $audit,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $me = SignedIn::user($request);
        $task = ($args['task'] ?? '') === 'remove' ? 'remove' : 'reset';
        $person = $this->users->byId((int) ($args['id'] ?? 0));

        if ($person === null || ! $me->canManage($person)) {
            return $this->view->render($response, 'cp/message.twig', [
                'title' => 'You can\'t change this account',
                'message' => $person !== null && $person->id === $me->id
                    ? 'Your own password is on Preferences. To reset your whole account, ask another administrator.'
                    : 'Site owners can change site owners\' accounts; only an administrator can change an administrator\'s.',
                'link' => ['path' => '/people', 'label' => 'Back to People'],
            ], 403);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->view->render($response, 'cp/person-confirm.twig', [
                'task' => $task,
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'csrf' => Session::of($request)->csrfToken(),
            ]);
        }

        $ip = Form::ip($request);
        if ($task === 'remove') {
            $this->users->delete($person->id);
            $this->audit->record('user_removed', $person->id, $ip);

            return Form::redirect($response, '/people?removed=' . rawurlencode($person->name));
        }

        $this->users->resetCredentials($person->id);
        $this->audit->record('user_reset', $person->id, $ip);

        return $this->sender->send($response, $person, $me, reset: true);
    }
}
