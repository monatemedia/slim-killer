<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseInterface;
use Twig\Environment as Twig;

/**
 * Renders a Twig template into a response. Templates live in templates/, and escape
 * everything by default.
 */
final class View
{
    public function __construct(
        private readonly Twig $twig,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(ResponseInterface $response, string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $response->getBody()->write($this->twig->render($template, $data));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
