<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Publishing\Progress;

/** /cp/publishing: how the newest save is getting on, as JSON, for the progress bar. */
final class PublishingAction
{
    public function __construct(
        private readonly Progress $progress,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode($this->progress->report(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
