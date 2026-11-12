<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions\Content;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Content\Collection;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Content\ContentWriter;
use SlimKiller\Content\Entry;
use SlimKiller\Content\Form\FieldFactory;
use SlimKiller\Content\Form\FormReader;
use SlimKiller\Content\Form\ListChange;
use SlimKiller\Content\PageAddresses;
use SlimKiller\GitHub\ContentCommitter;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Photos\PhotoLibrary;
use SlimKiller\Photos\PhotoProblem;
use SlimKiller\Photos\PhotoProcessor;
use SlimKiller\Photos\StagedPhotos;
use SlimKiller\Publishing\PublishLog;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * /cp/content/{collection}/{slug}: one entry's form, built from the collection's schema.
 *
 * Posting it checks the values against the schema. The list buttons (add, remove, move)
 * change the form only. Save writes the changed fields into the file and commits it to
 * GitHub with the version the client opened, so a newer save by someone else is never
 * overwritten. After a save the browser is sent back to the page (so a reload does not
 * save twice) and the working copy holds the new file.
 *
 * A photo chosen in an image field (upload[<path>]) is checked and re-encoded on any
 * post, kept on the host under a clean name, and put in the field; it goes to GitHub with
 * the page, in the same commit, when the person presses Save.
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
        private readonly PageAddresses $addresses,
        private readonly AppConfig $config,
        private readonly PhotoProcessor $photos,
        private readonly StagedPhotos $staged,
        private readonly PhotoLibrary $library,
        private readonly Paths $paths,
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
        $person = SignedIn::user($request);
        $user = $person->id;

        if ($request->getMethod() !== 'POST') {
            $notice = $session->string(self::NOTICE);
            $session->remove(self::NOTICE);

            return $this->page($response, $session, $user, $collection, $entry, ['notice' => $notice]);
        }

        $posted = $request->getParsedBody();
        $posted = is_array($posted) ? $posted : [];
        $uploadErrors = $this->stageUploads($request, $user, $posted);
        $fields = $this->fields->fields($collection->schema);
        $submission = $this->reader->read($fields, $posted['f'] ?? []);
        $body = is_string($posted['body'] ?? null) ? $posted['body'] : null;
        $version = is_string($posted['version'] ?? null) ? $posted['version'] : $entry->version;
        $form = ['values' => $submission->values, 'body' => $body ?? $entry->body, 'version' => $version];

        if ($uploadErrors !== []) {
            return $this->page($response, $session, $user, $collection, $entry, $form + ['errors' => $uploadErrors + $submission->errors], 422);
        }

        $change = ListChange::from($posted['list_action'] ?? null);
        if ($change !== null) {
            [$form['values'], $focus] = $change->apply($submission->values);

            return $this->page($response, $session, $user, $collection, $entry, $form + [
                'focus' => $focus,
                'notice' => 'This change is in the form only. Press Save to keep it, or Discard changes.',
            ]);
        }
        if (! $submission->isValid()) {
            return $this->page($response, $session, $user, $collection, $entry, $form + ['errors' => $submission->errors], 422);
        }

        $raw = (string) $this->content->raw($collection, $entry->slug);
        $saved = $this->writer->write($raw, $fields, $submission->values, $body);
        if ($saved === $raw) {
            return $this->page($response, $session, $user, $collection, $entry, $form + ['notice' => 'Nothing has changed, so there was nothing to save.']);
        }

        $photos = $this->stagedIn($user, $submission->values);
        $result = $this->committer->save(
            $collection->name . '/' . $entry->slug . '.md',
            $saved,
            $version,
            sprintf('Save "%s" (%s/%s.md) in the control panel', $entry->title, $collection->name, $entry->slug)
                . ($photos === [] ? '' : ', with ' . (count($photos) === 1 ? 'a new photo' : count($photos) . ' new photos')),
            ['name' => $person->name, 'email' => $person->email],
            $photos,
        );
        if ($result->isConflict() && $result->current !== null) {
            // Someone saved first: keep the newer version in the working copy, so
            // Discard changes shows it and the next save starts from it.
            $this->content->write($collection, $entry->slug, $result->current);
        }
        if (! $result->isSaved()) {
            return $this->page($response, $session, $user, $collection, $entry, $form + ['problem' => $result->message], $result->isConflict() ? 409 : 502);
        }

        $this->content->write($collection, $entry->slug, $saved);
        foreach (array_keys($photos) as $path) {
            $this->staged->saved($user, basename($path));
        }
        $this->audit->record('save', $user, Form::ip($request));
        if ($result->commit !== null) {
            $this->publishLog->saved($result->commit, $this->clock->now());
        }
        $session->set(self::NOTICE, 'Saved. Your website shows the change once it has been published.');

        return Form::redirect($response, '/content/' . $collection->name . '/' . $entry->slug);
    }

    /**
     * Checks and keeps every photo chosen in the form, and puts its file name in its field.
     *
     * @param array<mixed> $posted
     * @return array<string, string> problems, by the field's path
     */
    private function stageUploads(ServerRequestInterface $request, int $user, array &$posted): array
    {
        $uploads = $request->getUploadedFiles()['upload'] ?? [];
        $errors = [];
        foreach (is_array($uploads) ? $uploads : [] as $path => $upload) {
            if (! is_string($path) || preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $path) !== 1
                || ! $upload instanceof UploadedFileInterface || $upload->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $temporary = $this->paths->storage('tmp/upload-' . bin2hex(random_bytes(8)));
            try {
                if ($upload->getError() !== UPLOAD_ERR_OK) {
                    throw new PhotoProblem(in_array($upload->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? 'That photo is too big to upload. Use a copy of at most 20 MB.'
                        : 'The photo did not arrive. Choose it again.');
                }
                if (! is_dir(dirname($temporary))) {
                    @mkdir(dirname($temporary), 0755, true);
                }
                $upload->moveTo($temporary);
                $file = $this->staged->stage($user, $this->photos->process($temporary), (string) $upload->getClientFilename());
                $posted['f'] = self::setAt(is_array($posted['f'] ?? null) ? $posted['f'] : [], explode('.', $path), $file);
            } catch (PhotoProblem $problem) {
                $errors[$path] = $problem->getMessage();
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }

        return $errors;
    }

    /**
     * The staged photos a page's values use, ready to commit with it.
     *
     * @param array<mixed> $values
     * @return array<string, string> path inside content/ => bytes
     */
    private function stagedIn(int $user, array $values): array
    {
        $photos = [];
        array_walk_recursive($values, function (mixed $value) use ($user, &$photos): void {
            if (is_string($value) && $this->staged->has($user, $value)) {
                $photos['images/' . $value] = (string) $this->staged->bytes($user, $value);
            }
        });

        return $photos;
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $keys
     * @return array<mixed>
     */
    private static function setAt(array $data, array $keys, string $value): array
    {
        $key = array_shift($keys);
        if ($key === null) {
            return $data;
        }
        $data[$key] = $keys === [] ? $value : self::setAt(is_array($data[$key] ?? null) ? $data[$key] : [], $keys, $value);

        return $data;
    }

    /** @param array<string, mixed> $form */
    private function page(ResponseInterface $response, Session $session, int $user, Collection $collection, Entry $entry, array $form, int $status = 200): ResponseInterface
    {
        $address = $this->addresses->of($collection->name, $entry->slug);
        // Photos staged by this person first, so a new upload is easy to find again.
        $photos = $this->staged->all($user) + $this->library->all();

        return $this->view->render($response, 'cp/content/entry.twig', [
            'collection' => ['name' => $collection->name, 'label' => $collection->label],
            'entry' => [
                'slug' => $entry->slug,
                'title' => $entry->title,
                'body' => $form['body'] ?? $entry->body,
                'hasBody' => trim($entry->body) !== '',
                'version' => $form['version'] ?? $entry->version,
                'address' => $address,
                'link' => $address === null ? null : $this->config->link($address),
            ],
            'fields' => $this->fields->fields($collection->schema),
            'values' => $form['values'] ?? $entry->data,
            'errors' => $form['errors'] ?? [],
            'notice' => $form['notice'] ?? null,
            'problem' => $form['problem'] ?? null,
            'focus' => $form['focus'] ?? null,
            'csrf' => $session->csrfToken(),
            'photos' => $photos,
        ], $status);
    }
}
