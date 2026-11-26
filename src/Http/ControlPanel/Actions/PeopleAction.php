<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\InvalidEmailAddress;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserAlreadyExists;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\ControlPanel\WelcomeLinkSender;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\Ago;
use SlimKiller\Support\Clock;

/**
 * /cp/people: everyone who can use the control panel, and adding someone new. The
 * new person gets a welcome link by email (and it is shown once, to copy) to choose
 * their own password and set up two-factor, as with `hammer user:create`.
 *
 * Administrators can add either role; site owners can add site owners.
 */
final class PeopleAction
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly WelcomeLinks $welcomeLinks,
        private readonly WelcomeLinkSender $sender,
        private readonly AuditLog $audit,
        private readonly Clock $clock,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $me = SignedIn::user($request);
        $session = Session::of($request);

        if ($request->getMethod() !== 'POST') {
            return $this->page($response, $session, $me, ['name' => '', 'email' => '', 'role' => Role::Owner->value], null, Form::query($request, 'removed'));
        }

        $fields = [
            'name' => trim(Form::field($request, 'name')),
            'email' => trim(Form::field($request, 'email')),
            'role' => Form::field($request, 'role'),
        ];
        $role = Role::tryFrom($fields['role']) ?? Role::Owner;

        $problem = match (true) {
            $fields['name'] === '' => 'Type the person\'s name.',
            ! $me->canAdd($role) => 'Only an administrator can add an administrator.',
            default => null,
        };
        $person = null;
        if ($problem === null) {
            try {
                $person = $this->users->create(EmailAddress::from($fields['email']), $fields['name'], $role);
            } catch (InvalidEmailAddress) {
                $problem = $fields['email'] === '' ? 'Type the person\'s email address.' : 'That isn\'t an email address.';
            } catch (UserAlreadyExists) {
                $problem = 'Someone here already has that email address.';
            }
        }

        if ($person === null) {
            return $this->page($response, $session, $me, $fields, $problem ?? 'That didn\'t work.', '', 422);
        }

        $this->audit->record('user_created', $person->id, Form::ip($request));

        return $this->sender->send($response, $person, $me, reset: false);
    }

    /** @param array{name: string, email: string, role: string} $fields */
    private function page(ResponseInterface $response, Session $session, User $me, array $fields, ?string $error, string $removed, int $status = 200): ResponseInterface
    {
        $lastSignIns = $this->users->lastSignIns();
        $now = $this->clock->now()->getTimestamp();
        $people = array_map(fn(User $person): array => [
            'id' => $person->id,
            'name' => $person->name,
            'email' => $person->email,
            'role' => $person->role->label(),
            'me' => $person->id === $me->id,
            'can_manage' => $me->canManage($person),
            'state' => match (true) {
                $person->hasTwoFactor() => isset($lastSignIns[$person->id]) ? 'Last signed in ' . Ago::seconds($now - $lastSignIns[$person->id]) . ' ago' : 'Set up',
                $this->welcomeLinks->isOpenFor($person->id) => 'Welcome link sent, not opened yet',
                default => 'Welcome link expired: reset to send a new one',
            },
        ], $this->users->all());

        return $this->view->render($response, 'cp/people.twig', [
            'people' => $people,
            'fields' => $fields,
            'error' => $error,
            'removed' => $removed,
            'can_add_admin' => $me->canAdd(Role::Admin),
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
