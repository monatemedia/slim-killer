<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Content\Form\FieldFactory;
use SlimKiller\Content\Form\FormReader;
use SlimKiller\Content\Form\ListChange;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * /cp/content/{collection}/{slug}: one entry's form, built from the collection's schema
 * and filled with the entry's values. Posting it checks the values against the schema
 * and shows any problems beside their fields. The list buttons (add, remove, move)
 * post the form too: the change is applied and the form shown again, unsaved.
 * Writing to GitHub comes next.
 */
final class EntryAction
{
    public function __construct(
        private readonly View $view,
        private readonly ContentRepository $content,
        private readonly FieldFactory $fields,
        private readonly FormReader $reader,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $collection = $this->content->collection($args['collection'] ?? '');
        $entry = $collection === null ? null : $this->content->entry($collection, $args['slug'] ?? '');
        if ($collection === null || $entry === null) {
            throw new HttpNotFoundException($request);
        }

        $fields = $this->fields->fields($collection->schema);
        $values = $entry->data;
        $body = $entry->body;
        $errors = [];
        $notice = null;
        $focus = null;
        $status = 200;

        if ($request->getMethod() === 'POST') {
            $posted = $request->getParsedBody();
            $posted = is_array($posted) ? $posted : [];
            $submission = $this->reader->read($fields, $posted['f'] ?? []);
            $values = $submission->values;
            $body = is_string($posted['body'] ?? null) ? str_replace("\r\n", "\n", $posted['body']) : $body;
            $change = ListChange::from($posted['list_action'] ?? null);
            if ($change !== null) {
                [$values, $focus] = $change->apply($values);
                $notice = 'This change is in the form only. Press Save to keep it, or Discard changes.';
            } elseif ($submission->isValid()) {
                $notice = 'Everything is filled in correctly. Saving it to your website arrives in the next step.';
            } else {
                $errors = $submission->errors;
                $status = 422;
            }
        }

        return $this->view->render($response, 'cp/content/entry.twig', [
            'collection' => ['name' => $collection->name, 'label' => $collection->label],
            'entry' => [
                'slug' => $entry->slug,
                'title' => $entry->title,
                'body' => $body,
                'hasBody' => trim($entry->body) !== '',
                'version' => $entry->version,
            ],
            'fields' => $fields,
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'focus' => $focus,
            'csrf' => Session::of($request)->csrfToken(),
        ], $status);
    }
}
