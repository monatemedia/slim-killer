<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Small helpers the control panel's actions share. */
final class Form
{
    /** A posted field as a string; missing or not a string becomes ''. */
    public static function field(ServerRequestInterface $request, string $name): string
    {
        $body = $request->getParsedBody();
        $value = is_array($body) ? ($body[$name] ?? '') : '';

        return is_string($value) ? $value : '';
    }

    public static function query(ServerRequestInterface $request, string $name): string
    {
        $value = $request->getQueryParams()[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    public static function ip(ServerRequestInterface $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($ip) ? $ip : '';
    }

    /** See other: after a POST, the browser loads the next page with GET. */
    public static function redirect(ResponseInterface $response, string $path): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Location', ControlPanel::BASE_PATH . $path);
    }

    public static function minutes(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
    }
}
