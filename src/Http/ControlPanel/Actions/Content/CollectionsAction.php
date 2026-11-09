<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Http\ControlPanel\View;

/** /cp/content: the collections this site has. */
final class CollectionsAction
{
    public function __construct(
        private readonly View $view,
        private readonly ContentRepository $content,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $collections = [];
        foreach ($this->content->collections() as $collection) {
            $collections[] = [
                'name' => $collection->name,
                'label' => $collection->label,
                'count' => count($this->content->entries($collection)),
            ];
        }

        return $this->view->render($response, 'cp/content/collections.twig', ['collections' => $collections]);
    }
}
