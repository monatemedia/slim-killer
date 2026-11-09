<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Content\Form\FieldFactory;
use SlimKiller\Http\ControlPanel\View;

/**
 * /cp/content/{collection}/{slug}: one entry's form, built from the collection's schema
 * and filled with the entry's values. Read-only until saving is built.
 */
final class EntryAction
{
    public function __construct(
        private readonly View $view,
        private readonly ContentRepository $content,
        private readonly FieldFactory $fields,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $collection = $this->content->collection($args['collection'] ?? '');
        $entry = $collection === null ? null : $this->content->entry($collection, $args['slug'] ?? '');
        if ($collection === null || $entry === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->view->render($response, 'cp/content/entry.twig', [
            'collection' => ['name' => $collection->name, 'label' => $collection->label],
            'entry' => ['slug' => $entry->slug, 'title' => $entry->title, 'body' => $entry->body],
            'fields' => $this->fields->fields($collection->schema),
            'values' => $entry->data,
        ]);
    }
}
