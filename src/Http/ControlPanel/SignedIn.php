<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\Session\Session;

/**
 * Guards the pages only signed-in people may see. Anyone else goes to the sign-in page.
 * The signed-in user is put on the request, for the action to use.
 */
final class SignedIn implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ResponseFactoryInterface $responses,
    ) {}

    public static function user(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(User::class);
        if (! $user instanceof User) {
            throw new LogicException('No signed-in user on this request. Is the route behind SignedIn?');
        }

        return $user;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = Session::of($request)->userId();
        $user = $userId === null ? null : $this->users->byId($userId);

        if ($user === null) {
            return Form::redirect($this->responses->createResponse(), '/sign-in');
        }

        return $handler->handle($request->withAttribute(User::class, $user));
    }
}
