<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Photos\PhotoLibrary;
use SlimKiller\Photos\StagedPhotos;

/**
 * /cp/photos/{name}: a photo's small preview, for signed-in people. Previews live outside
 * the web root (beside the working copy, or staged), so the panel hands them out itself.
 */
final class PhotoPreviewAction
{
    public function __construct(
        private readonly PhotoLibrary $library,
        private readonly StagedPhotos $staged,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $name = $args['name'] ?? '';
        $path = $this->staged->previewPath(SignedIn::user($request)->id, $name) ?? $this->library->previewPath($name);
        if ($path === null) {
            throw new HttpNotFoundException($request);
        }
        $response->getBody()->write((string) file_get_contents($path));

        return $response
            ->withHeader('Content-Type', str_ends_with($name, '.webp') ? 'image/webp' : 'image/jpeg')
            ->withHeader('Cache-Control', 'private, max-age=86400');
    }
}
