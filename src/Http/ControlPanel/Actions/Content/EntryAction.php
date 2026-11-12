<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Content\Collection;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Content\ContentWriter;
use SlimKiller\Content\Entry;
use SlimKiller\Content\Form\FieldFactory;
use SlimKiller\Content\Form\FormReader;
use SlimKiller\Content\Form\ListChange;
use SlimKiller\GitHub\ContentCommitter;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Publishing\PublishLog;
use SlimKiller\Support\Clock;

/**
 * /cp/content/{collection}/{slug}: one entry's form, built from the collection's schema.
 *
 * Posting it checks the values against the schema. The list buttons (add, remove, move)
 * change the form only. Save writes the changed fields into the file and commits it to
 * GitHub with the version the client opened, so a newer save by someone else is never
 * overwritten. After a save the browser is sent back to the page (so a reload does not
 * save twice) and the working copy holds the new file.
 */
final class EntryAction
{
    private const string NOTICE = '_content_notice';

    public function __construct(
        private readonly View $view,
        private readonly ContentRepository $content,
        private readonly FieldFactory $fields,
        private readonly FormReader $reader,
        private readonly ContentWriter $writer,
        private readonly ContentCommitter $committer,
        private readonly AuditLog $audit,
        private readonly PublishLog $publishLog,
        private readonly Clock $clock,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $collection = $this->content->collection($args['collection'] ?? '');
        $entry = $collection === null ? null : $this->content->entry($collection, $args['slug'] ?? '');
        if ($collection === null || $entry === null) {
            throw new HttpNotFoundException($request);
        }
        $session = Session::of($request);

        if ($request->getMethod() !== 'POST') {
            $notice = $session->string(self::NOTICE);
            $session->remove(self::NOTICE);

            return $this->page($response, $session, $collection, $entry, ['notice' => $notice]);
        }

        $posted = $request->getParsedBody();
        $posted = is_array($posted) ? $posted : [];
        $fields = $this->fields->fields($collection->schema);
        $submission = $this->reader->read($fields, $posted['f'] ?? []);
        $body = is_string($posted['body'] ?? null) ? $posted['body'] : null;
        $version = is_string($posted['version'] ?? null) ? $posted['version'] : $entry->version;
        $form = ['values' => $submission->values, 'body' => $body ?? $entry->body, 'version' => $version];

        $change = ListChange::from($posted['list_action'] ?? null);
        if ($change !== null) {
            [$form['values'], $focus] = $change->apply($submission->values);

            return $this->page($response, $session, $collection, $entry, $form + [
                'focus' => $focus,
                'notice' => 'This change is in the form only. Press Save to keep it, or Discard changes.',
            ]);
        }
        if (! $submission->isValid()) {
            return $this->page($response, $session, $collection, $entry, $form + ['errors' => $submission->errors], 422);
        }

        $raw = (string) $this->content->raw($collection, $entry->slug);
        $saved = $this->writer->write($raw, $fields, $submission->values, $body);
        if ($saved === $raw) {
            return $this->page($response, $session, $collection, $entry, $form + ['notice' => 'Nothing has changed, so there was nothing to save.']);
        }

        $user = SignedIn::user($request);
        $result = $this->committer->save(
            $collection->name . '/' . $entry->slug . '.md',
            $saved,
            $version,
            sprintf('Save "%s" (%s/%s.md) in the control panel', $entry->title, $collection->name, $entry->slug),
            ['name' => $user->name, 'email' => $user->email],
        );
        if ($result->isConflict() && $result->current !== null) {
            // Someone saved first: keep the newer version in the working copy, so
            // Discard changes shows it and the next save starts from it.
            $this->content->write($collection, $entry->slug, $result->current);
        }
        if (! $result->isSaved()) {
            return $this->page($response, $session, $collection, $entry, $form + ['problem' => $result->message], $result->isConflict() ? 409 : 502);
        }

        $this->content->write($collection, $entry->slug, $saved);
        $this->audit->record('save', $user->id, Form::ip($request));
        if ($result->commit !== null) {
            $this->publishLog->saved($result->commit, $this->clock->now());
        }
        $session->set(self::NOTICE, 'Saved. Your website shows the change once it has been published.');

        return Form::redirect($response, '/content/' . $collection->name . '/' . $entry->slug);
    }

    /** @param array<string, mixed> $form */
    private function page(ResponseInterface $response, Session $session, Collection $collection, Entry $entry, array $form, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/content/entry.twig', [
            'collection' => ['name' => $collection->name, 'label' => $collection->label],
            'entry' => [
                'slug' => $entry->slug,
                'title' => $entry->title,
                'body' => $form['body'] ?? $entry->body,
                'hasBody' => trim($entry->body) !== '',
                'version' => $form['version'] ?? $entry->version,
            ],
            'fields' => $this->fields->fields($collection->schema),
            'values' => $form['values'] ?? $entry->data,
            'errors' => $form['errors'] ?? [],
            'notice' => $form['notice'] ?? null,
            'problem' => $form['problem'] ?? null,
            'focus' => $form['focus'] ?? null,
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
