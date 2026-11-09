<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Http\ControlPanel\View;

/** /cp/content/{collection}: the entries in one collection. */
final class EntriesAction
{
    public function __construct(
        private readonly View $view,
        private readonly ContentRepository $content,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $collection = $this->content->collection($args['collection'] ?? '');
        if ($collection === null) {
            throw new HttpNotFoundException($request);
        }

        $entries = [];
        foreach ($this->content->entries($collection) as $entry) {
            $entries[] = ['slug' => $entry->slug, 'title' => $entry->title];
        }

        return $this->view->render($response, 'cp/content/entries.twig', [
            'collection' => ['name' => $collection->name, 'label' => $collection->label],
            'entries' => $entries,
        ]);
    }
}
