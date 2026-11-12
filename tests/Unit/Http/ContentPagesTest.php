<?php

declare(strict_types=1);

use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\HttpClient;
use SlimKiller\Support\AppConfig;
use Tests\Fakes\FakeGitHub;

/** A signed-in panel reading the fixture site. */
function contentPanel(): array
{
    $site = dirname(__DIR__, 2) . '/Fixtures/site';
    [$browser, $container, $clock] = installedPanel([
        AppConfig::class => new AppConfig(contentDir: $site . '/content', schemaDir: $site . '/schemas'),
    ]);
    enrol($browser, $container, $clock);

    return [$browser, $container, $clock];
}

/**
 * A signed-in panel on a scratch copy of the fixture site, saving to a fake GitHub that
 * holds the same content files, so saves never touch the fixtures.
 *
 * @return array{0: Tests\Fakes\Browser, 1: FakeGitHub, 2: string}
 */
function savingPanel(): array
{
    $fixtures = dirname(__DIR__, 2) . '/Fixtures/site';
    $copy = temporaryFolder();
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtures . '/content', FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($fixtures . '/content/')));
        if (! is_dir(dirname($copy . '/' . $relative))) {
            mkdir(dirname($copy . '/' . $relative), recursive: true);
        }
        copy($file->getPathname(), $copy . '/' . $relative);
        $files['content/' . $relative] = (string) file_get_contents($file->getPathname());
    }
    $github = new FakeGitHub($files);
    [$browser, $container, $clock] = installedPanel([
        AppConfig::class => new AppConfig(contentDir: $copy, schemaDir: $fixtures . '/schemas'),
        HttpClient::class => $github,
        GitHubSettings::class => new GitHubSettings('owner/site', 'main', 'secret'),
    ]);
    enrol($browser, $container, $clock);

    return [$browser, $github, $copy];
}

/** The oryx form as the browser sends it, with $changes on top. */
function oryxForm(array $changes = []): array
{
    return array_replace_recursive([
        'f' => [
            'title' => 'The Oryx', 'description' => "Namibia's national animal.", 'heroImage' => 'oryx.jpg',
            'gallery' => [['src' => 'oryx-1.jpg', 'alt' => 'An oryx at a waterhole'], ['src' => 'oryx-2.jpg', 'alt' => '']],
        ],
        'body' => "## In the desert\r\n\r\nThe oryx can go for _weeks_ without water.",
    ], $changes);
}

it('keeps content pages for signed-in people', function (string $path): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);

    expect($browser->get($path)->getHeaderLine('Location'))->toBe('/cp/sign-in');
})->with(['/cp/content', '/cp/content/articles', '/cp/content/articles/oryx']);

it('lists the collections, then a collection\'s pages', function (): void {
    [$browser] = contentPanel();

    expect($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('href="/cp/content"');

    $browser->get('/cp/content');
    expect($browser->body())->toContain('Articles')->toContain('2 pages')->toContain('Pages')->not->toContain('Products');

    $browser->get('/cp/content/articles');
    expect($browser->body())->toContain('href="/cp/content/articles/oryx"')->toContain('Deadvlei')->not->toContain('Template');
});

it('shows a page\'s form, built from the schema and filled with its values', function (): void {
    [$browser] = contentPanel();

    $response = $browser->get('/cp/content/articles/oryx');
    $body = $browser->body();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain('Page title')
        ->toContain('value="The Oryx"')
        ->toContain('One or two sentences.')
        ->toContain('value="oryx-2.jpg"')
        ->toContain('The oryx can go for _weeks_ without water.')
        ->toContain('name="f[gallery][1][src]"')
        ->toContain('maxlength="20"')
        ->toContain('Web address: <a href="/articles/oryx/" target="_blank" rel="noopener"><code>/articles/oryx/</code></a>')
        ->not->toContain('style=');

    $browser->get('/cp/content/articles/deadvlei');
    expect($browser->body())->toContain('Deadvlei')->not->toContain('Web address');
});

it('says a page is not there, rather than guessing', function (string $path): void {
    [$browser] = contentPanel();

    expect($browser->get($path)->getStatusCode())->toBe(404);
})->with(['/cp/content/nope', '/cp/content/articles/nope', '/cp/content/articles/_template', '/cp/content/products']);

it('adds each file\'s change time to its address, so browsers do not keep an old copy', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content');

    expect($browser->body())->toMatch('#href="/cp/assets/cp\\.css\\?v=\\d+"#')
        ->toMatch('#src="/cp/assets/cp\\.js\\?v=\\d+"#');
});

it('checks a posted page against the schema and shows each problem beside its field', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    $response = $browser->post('/cp/content/articles/oryx', [
        'f' => [
            'title' => 'A title much longer than twenty characters',
            'description' => '',
            'heroImage' => 'oryx.jpg',
        ],
        'body' => 'Text',
    ]);
    $body = $browser->body();

    expect($response->getStatusCode())->toBe(422)
        ->and($body)->toContain('2 things need changing')
        ->toContain('Page title can have up to 20 characters. This has 42.')
        ->toContain('Description for search results is needed.')
        ->toContain('href="#f-description"')
        ->toContain('value="A title much longer than twenty characters"');
});

it('says so when saving is not set up, and keeps the form', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    $response = $browser->post('/cp/content/articles/oryx', [
        'f' => ['title' => 'The Gemsbok', 'description' => 'Namibia\'s national animal.', 'heroImage' => 'oryx.jpg'],
        'body' => 'Text',
    ]);

    expect($response->getStatusCode())->toBe(502)
        ->and($browser->body())->toContain('Saving is not set up for this website yet')
        ->toContain('value="The Gemsbok"');
});

it('says so when a posted page changes nothing', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    $browser->post('/cp/content/articles/oryx', [
        'f' => [
            'title' => 'The Oryx', 'description' => "Namibia's national animal.", 'heroImage' => 'oryx.jpg',
            'gallery' => [['src' => 'oryx-1.jpg', 'alt' => 'An oryx at a waterhole'], ['src' => 'oryx-2.jpg', 'alt' => '']],
        ],
        'body' => "## In the desert\r\n\r\nThe oryx can go for _weeks_ without water.",
    ]);

    expect($browser->body())->toContain('Nothing has changed');
});

it('refuses a posted page without the form\'s token', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    expect($browser->post('/cp/content/articles/oryx', ['f' => [], '_csrf' => 'wrong'])->getStatusCode())->toBe(403);
});

it('adds, moves and removes list items without saving, keeping what was typed', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');
    $form = [
        'f' => [
            'title' => 'Changed title', 'description' => 'Namibia\'s national animal.', 'heroImage' => 'oryx.jpg',
            'gallery' => [['src' => 'oryx-1.jpg', 'alt' => 'First'], ['src' => 'oryx-2.jpg', 'alt' => 'Second']],
        ],
        'body' => 'Text',
    ];

    $added = $browser->post('/cp/content/articles/oryx', $form + ['list_action' => 'add:gallery']);
    expect($added->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('name="f[gallery][2][src]"')
        ->toContain('value="Changed title"')
        ->toContain('data-focus')
        ->toContain('This change is in the form only.')
        ->toContain('>Discard changes</a>')
        ->not->toContain('Ready to save');

    $browser->post('/cp/content/articles/oryx', $form + ['list_action' => 'up:gallery.1']);
    expect(strpos($browser->body(), 'value="Second"'))->toBeLessThan(strpos($browser->body(), 'value="First"'));

    $browser->post('/cp/content/articles/oryx', $form + ['list_action' => 'remove:gallery.0']);
    expect($browser->body())->not->toContain('value="First"')->toContain('value="Second"');
});

it('saves a changed page to GitHub and the working copy, then shows it saved', function (): void {
    [$browser, $github, $copy] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    $before = $github->head;

    $response = $browser->post('/cp/content/articles/oryx', oryxForm(['f' => ['title' => 'The Gemsbok']]));

    expect($response->getStatusCode())->toBe(303)
        ->and($response->getHeaderLine('Location'))->toBe('/cp/content/articles/oryx')
        ->and($github->head)->not->toBe($before)
        ->and($github->file('content/articles/oryx.md'))->toContain('title: "The Gemsbok"')
        ->and($github->commits[$github->head]['message'])->toContain('articles/oryx.md')
        ->and((string) file_get_contents($copy . '/articles/oryx.md'))->toBe($github->file('content/articles/oryx.md'));

    $browser->get('/cp/content/articles/oryx');
    expect($browser->body())->toContain('Saved.')->toContain('value="The Gemsbok"');

    $browser->get('/cp/content/articles/oryx');
    expect($browser->body())->not->toContain('Saved.');
});

it('does not save over a newer version, keeps what the client typed, and fetches the newer version', function (): void {
    [$browser, $github, $copy] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    $github->commitDirectly('content/articles/oryx.md', "---\ntitle: \"Someone else's title\"\n---\n");
    $head = $github->head;

    $response = $browser->post('/cp/content/articles/oryx', oryxForm(['f' => ['title' => 'The Gemsbok']]));

    expect($response->getStatusCode())->toBe(409)
        ->and($browser->body())->toContain('Someone saved this page after you opened it')
        ->toContain('value="The Gemsbok"')
        ->and($github->head)->toBe($head)
        ->and((string) file_get_contents($copy . '/articles/oryx.md'))->toBe($github->file('content/articles/oryx.md'));

    $browser->get('/cp/content/articles/oryx');
    // The page escapes the apostrophe.
    expect($browser->body())->toContain('value="Someone else&#039;s title"');
});

it('writes nothing to GitHub when nothing changed', function (): void {
    [$browser, $github] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    $head = $github->head;

    $browser->post('/cp/content/articles/oryx', oryxForm());

    expect($browser->body())->toContain('Nothing has changed')->and($github->head)->toBe($head);
});

it('keeps the form and explains it when GitHub cannot be reached', function (): void {
    [$browser, $github] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    $github->failWith = 0;

    $response = $browser->post('/cp/content/articles/oryx', oryxForm(['f' => ['title' => 'The Gemsbok']]));

    expect($response->getStatusCode())->toBe(502)
        ->and($browser->body())->toContain('Your changes are not saved yet')
        ->toContain('could not reach GitHub')
        ->toContain('value="The Gemsbok"');
});

it('takes a photo into an image field, and saves it with the page in one commit', function (): void {
    [$browser, $github, $copy] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    expect($browser->body())->toContain('enctype="multipart/form-data"')
        ->toContain('name="upload[heroImage]"')
        ->toContain('name="upload[gallery.1.src]"');
    $before = $github->head;

    // Choosing a photo and pressing a list button keeps the photo, without saving.
    $browser->post('/cp/content/articles/oryx', oryxForm(['list_action' => 'add:gallery']), [
        'upload' => ['heroImage' => uploaded(jpegPhoto(3000, 2000), 'Oryx at Sossusvlei.jpg')],
    ]);
    expect($github->head)->toBe($before)
        ->and($browser->body())->toContain('value="oryx-at-sossusvlei.jpg"')
        ->toContain('/cp/photos/' . substr(sha1('oryx-at-sossusvlei.jpg'), 0, 16) . '.jpg');

    $response = $browser->post('/cp/content/articles/oryx', oryxForm(['f' => ['heroImage' => 'oryx-at-sossusvlei.jpg']]));

    $photo = $github->file('content/images/oryx-at-sossusvlei.jpg');
    expect($response->getStatusCode())->toBe(303)
        ->and($github->commits[$github->head]['parents'])->toBe([$before])
        ->and($github->commits[$github->head]['message'])->toContain('with a new photo')
        ->and($github->file('content/articles/oryx.md'))->toContain('heroImage: "oryx-at-sossusvlei.jpg"')
        ->and($photo)->not->toBeNull()
        ->and(getimagesizefromstring((string) $photo)[0])->toBe(2400);

    // The preview now comes from the library, before any build has made one.
    $preview = $browser->get('/cp/photos/' . substr(sha1('oryx-at-sossusvlei.jpg'), 0, 16) . '.jpg');
    expect($preview->getStatusCode())->toBe(200)
        ->and($preview->getHeaderLine('Content-Type'))->toBe('image/jpeg');
});

it('explains a file that is not a photo, beside its field, and keeps the rest of the form', function (): void {
    [$browser, $github] = savingPanel();
    $browser->get('/cp/content/articles/oryx');
    $before = $github->head;

    $response = $browser->post('/cp/content/articles/oryx', oryxForm(['f' => ['title' => 'The Gemsbok']]), [
        'upload' => ['gallery.0.src' => uploaded(photoFile('<?php echo 1;', 'jpg'), 'shell.jpg')],
    ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($browser->body())->toContain('not a photo the website can use')
        ->toContain('value="The Gemsbok"')
        ->and($github->head)->toBe($before);
});

it('hands out photo previews only to signed-in people, and only real ones', function (): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);
    expect($browser->get('/cp/photos/0123456789abcdef.jpg')->getHeaderLine('Location'))->toBe('/cp/sign-in');

    [$browser] = contentPanel();
    expect($browser->get('/cp/photos/0123456789abcdef.jpg')->getStatusCode())->toBe(404)
        ->and($browser->get('/cp/photos/..%2F..%2F.env')->getStatusCode())->toBe(404);
});
