<?php

declare(strict_types=1);

use SlimKiller\Content\ContentLocation;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\Response;
use SlimKiller\Publishing\Fetcher;
use SlimKiller\Publishing\Releases;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\FrozenClock;

/** A zip the way GitHub sends one: everything inside an "owner-repo-sha/" folder. */
function githubZip(array $files): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addEmptyDir('owner-site-abc1234');
    foreach ($files as $name => $contents) {
        $zip->addFromString('owner-site-abc1234/' . $name, $contents);
    }
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

/** The files of one site build, saved from $commit, with the panel's folder. */
function siteBuild(string $commit, string $title = 'Home'): array
{
    return [
        'index.html' => "<h1>{$title}</h1>",
        '404.html' => "Not found: {$title}",
        'destinations/namibia/index.html' => 'Namibia',
        '_slim-killer/build.json' => json_encode(['commit' => $commit, 'run' => 1, 'builtAt' => '2026-11-11T00:00:00Z']),
        '_slim-killer/schemas/articles.schema.json' => '{"title":"' . $title . '"}',
        '_slim-killer/content/articles/oryx.md' => "---\ntitle: \"{$title}\"\n---\n",
    ];
}

/**
 * A host with an empty web root, and a fake GitHub whose dist branch is on $github->dist.
 *
 * @return array{0: Fetcher, 1: Releases, 2: object, 3: string, 4: FakeHttpClient}
 */
function publishingHost(bool $developmentFolders = false): array
{
    $root = temporaryFolder();
    mkdir($root . '/public_html');
    $paths = new Paths($root);
    $github = (object) ['dist' => null, 'zips' => [], 'status' => 200];

    $http = new FakeHttpClient(static function (string $url) use ($github): Response {
        if ($github->status !== 200) {
            return new Response($github->status, '');
        }
        if (str_ends_with($url, '/git/ref/heads/dist')) {
            return $github->dist === null
                ? new Response(404, '{"message":"Not Found"}')
                : new Response(200, json_encode(['object' => ['sha' => $github->dist]]));
        }
        if (preg_match('#/zipball/([0-9a-f]{40})$#', $url, $match) === 1 && isset($github->zips[$match[1]])) {
            return new Response(200, $github->zips[$match[1]]);
        }

        return new Response(404, '');
    });
    $releases = new Releases($root . '/public_html', $paths->storage('releases.json'));
    $location = $developmentFolders
        ? new ContentLocation($root . '/dev/content', $root . '/dev/schemas')
        : new ContentLocation($paths->storage('site/content'), $paths->storage('site/schemas'));
    $fetcher = new Fetcher(
        $http,
        new GitHubSettings('owner/site', 'main', 'secret'),
        $releases,
        $location,
        $paths,
        FrozenClock::at('2026-11-11 02:00:00'),
    );

    return [$fetcher, $releases, $github, $root, $http];
}

/** Puts a new build on the fake dist branch and returns its commit. */
function newBuild(object $github, string $savedCommit, string $title = 'Home', ?array $files = null): string
{
    $build = sha1($savedCommit . $title . count($github->zips));
    $github->zips[$build] = githubZip($files ?? siteBuild($savedCommit, $title));
    $github->dist = $build;

    return $build;
}

it('fetches the newest build, makes it live, and keeps the panel files out of the website', function (): void {
    [$fetcher, $releases, $github, $root] = publishingHost();
    $build = newBuild($github, str_repeat('d', 40));

    $result = $fetcher->fetch();

    $id = substr($build, 0, 12);
    $release = $root . '/public_html/_site/' . $id;
    expect($result->outcome)->toBe('switched')
        ->and($result->message)->toContain('Live: the build of ddddddd')
        ->and(file_get_contents($release . '/index.html'))->toBe('<h1>Home</h1>')
        ->and(file_get_contents($release . '/destinations/namibia/index.html'))->toBe('Namibia')
        ->and($release . '/_slim-killer')->not->toBeDirectory()
        ->and($releases->live()?->id)->toBe($id)
        ->and($releases->live()?->commit)->toBe(str_repeat('d', 40));

    $htaccess = (string) file_get_contents($root . '/public_html/_site/.htaccess');
    expect($htaccess)->toContain("RewriteRule ^(.*)$ {$id}/\$1 [L]")
        ->toContain("ErrorDocument 404 /_site/{$id}/404.html")
        ->toContain('RewriteRule ^(.*)$ /cp/not-found.php [L]')
        ->toContain('/' . $id . '/$1/index.html !-f')
        ->toContain('Options -Indexes')
        ->toContain('RewriteCond %{THE_REQUEST} \\s/_site/ [NC]');
});

it('refreshes the working copy from the build, so the forms match the live site', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    newBuild($github, str_repeat('a', 40), 'First');
    $fetcher->fetch();
    newBuild($github, str_repeat('b', 40), 'Second');

    $result = $fetcher->fetch();

    expect($result->message)->toContain('The working copy was refreshed.')
        ->and(file_get_contents($root . '/storage/site/content/articles/oryx.md'))->toContain('Second')
        ->and(file_get_contents($root . '/storage/site/schemas/articles.schema.json'))->toContain('Second')
        ->and(glob($root . '/storage/site/*.old-*'))->toBe([]);
});

it('leaves a development working copy alone', function (): void {
    [$fetcher, , $github, $root] = publishingHost(developmentFolders: true);
    newBuild($github, str_repeat('a', 40));

    $result = $fetcher->fetch();

    expect($result->outcome)->toBe('switched')
        ->and($result->message)->toContain('left alone')
        ->and($root . '/storage/site/content')->not->toBeDirectory();
});

it('downloads nothing when the newest build is already here', function (): void {
    [$fetcher, , $github, , $http] = publishingHost();
    newBuild($github, str_repeat('a', 40));
    $fetcher->fetch();
    $http->requested = [];

    $result = $fetcher->fetch();

    expect($result->outcome)->toBe('unchanged')
        ->and($result->message)->toContain('Live: the build of aaaaaaa')
        ->and($http->requested)->toHaveCount(1);
});

it('keeps the newest three releases, and never removes the live one', function (): void {
    [$fetcher, $releases, $github, $root] = publishingHost();
    $builds = [];
    foreach (['a', 'b', 'c', 'd'] as $letter) {
        $builds[] = newBuild($github, str_repeat($letter, 40));
        $fetcher->fetch();
    }

    $folders = array_map('basename', glob($root . '/public_html/_site/*', GLOB_ONLYDIR) ?: []);
    sort($folders);
    $expected = array_map(static fn(string $build): string => substr($build, 0, 12), array_slice($builds, 1));
    sort($expected);
    expect($folders)->toBe($expected)
        ->and(array_map(static fn($release) => $release->id, $releases->all()))->toBe(array_reverse(array_map(static fn(string $build): string => substr($build, 0, 12), array_slice($builds, 1))));
});

it('goes back to the previous release, and the next fetch does not undo that', function (): void {
    [$fetcher, $releases, $github] = publishingHost();
    $first = newBuild($github, str_repeat('a', 40), 'First');
    $fetcher->fetch();
    newBuild($github, str_repeat('b', 40), 'Second');
    $fetcher->fetch();

    $result = $fetcher->rollback();

    expect($result->outcome)->toBe('switched')
        ->and($result->message)->toContain('Live: the build of aaaaaaa')
        ->and($releases->live()?->build)->toBe($first)
        ->and($fetcher->fetch()->outcome)->toBe('unchanged')
        ->and($releases->live()?->build)->toBe($first);

    newBuild($github, str_repeat('c', 40), 'Third');
    expect($fetcher->fetch()->outcome)->toBe('switched')
        ->and($releases->live()?->commit)->toBe(str_repeat('c', 40));
});

it('says when there is nothing to go back to', function (): void {
    [$fetcher, , $github] = publishingHost();
    newBuild($github, str_repeat('a', 40));
    $fetcher->fetch();

    expect($fetcher->rollback()->message)->toContain('no earlier release');
});

it('refuses a build it cannot use, and leaves the website as it was', function (array $files, string $message): void {
    [$fetcher, $releases, $github, $root] = publishingHost();
    $good = newBuild($github, str_repeat('a', 40));
    $fetcher->fetch();
    newBuild($github, str_repeat('b', 40), files: $files);

    $result = $fetcher->fetch();

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toContain($message)
        ->and($result->message)->toContain('The website is unchanged.')
        ->and($releases->live()?->build)->toBe($good)
        ->and($root . '/public_html/_site/.incoming')->not->toBeDirectory()
        ->and(glob($root . '/public_html/_site/*', GLOB_ONLYDIR))->toHaveCount(1);
})->with([
    'no home page' => [['_slim-killer/build.json' => '{"commit":"x"}'], 'no home page'],
    'no build record' => [['index.html' => 'Home'], 'no _slim-killer/build.json'],
    'a name reaching outside' => [['index.html' => 'Home', '../../evil.php' => '<?php'], 'not allowed'],
]);

it('says why when GitHub cannot be asked', function (int $status, string $message): void {
    [$fetcher, , $github] = publishingHost();
    $github->status = $status;

    $result = $fetcher->fetch();

    expect($result->isFailed())->toBeTrue()->and($result->message)->toContain($message);
})->with([
    'no connection' => [0, 'Could not reach GitHub'],
    'expired key' => [401, 'GitHub refused GITHUB_TOKEN'],
    'GitHub down' => [503, 'GitHub is having trouble'],
]);

it('says when there is no build yet', function (): void {
    [$fetcher] = publishingHost();

    expect($fetcher->fetch()->message)->toContain('GitHub has no build yet');
});

it('does nothing while another fetch is running', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    newBuild($github, str_repeat('a', 40));
    @mkdir($root . '/storage', 0755, true);
    $lock = fopen($root . '/storage/fetch.lock', 'c');
    flock($lock, LOCK_EX);

    $result = $fetcher->fetch();

    flock($lock, LOCK_UN);
    fclose($lock);
    expect($result->outcome)->toBe('busy')
        ->and($root . '/public_html/_site')->not->toBeDirectory();
});

it('explains what is missing before publishing is set up', function (): void {
    $root = temporaryFolder();
    $paths = new Paths($root);
    $fetcher = new Fetcher(
        FakeHttpClient::always(new Response(200, '')),
        new GitHubSettings('owner/site', 'main', 'secret'),
        new Releases('', $paths->storage('releases.json')),
        new ContentLocation('', ''),
        $paths,
        FrozenClock::at('2026-11-11'),
    );

    expect($fetcher->fetch()->message)->toContain('WEB_ROOT in .env is empty');
});

it('puts the live release\'s 404 page in the web root, and keeps it in step with switches', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    newBuild($github, str_repeat('a', 40), 'First');
    $fetcher->fetch();
    expect(file_get_contents($root . '/public_html/404.html'))->toBe('Not found: First');

    newBuild($github, str_repeat('b', 40), 'Second');
    $fetcher->fetch();
    expect(file_get_contents($root . '/public_html/404.html'))->toBe('Not found: Second');

    $fetcher->rollback();
    expect(file_get_contents($root . '/public_html/404.html'))->toBe('Not found: First');
});

it('writes the build\'s redirects as single 301s, and a rollback brings back the old ones', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    $htaccess = fn(): string => (string) file_get_contents($root . '/public_html/_site/.htaccess');
    newBuild($github, str_repeat('a', 40), 'First', siteBuild(str_repeat('a', 40), 'First') + [
        '_slim-killer/redirects.json' => json_encode([
            ['from' => '/discover', 'to' => '/destinations/'],
            ['from' => '/old-page/', 'to' => 'https://example.com/new/'],
            ['from' => '/bad path', 'to' => '/destinations/'],
            ['from' => '/sneaky', 'to' => '/x [R=302]'],
            ['from' => '/', 'to' => '/destinations/'],
        ]),
    ]);
    $fetcher->fetch();

    expect($htaccess())->toContain('RewriteRule ^discover/?$ /destinations/ [R=301,L]')
        ->toContain('RewriteRule ^old\\-page/?$ https://example.com/new/ [R=301,L]')
        ->not->toContain('bad path')
        ->not->toContain('sneaky')
        ->and(substr_count($htaccess(), '[R=301,L]'))->toBe(3); // two redirects, and the trailing slash rule

    newBuild($github, str_repeat('b', 40), 'Second');
    $fetcher->fetch();
    expect($htaccess())->not->toContain('discover');

    $fetcher->rollback();
    expect($htaccess())->toContain('RewriteRule ^discover/?$ /destinations/ [R=301,L]');
});

it('keeps the build\'s page addresses beside the working copy, and drops them when a build has none', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    newBuild($github, str_repeat('a', 40), 'First', siteBuild(str_repeat('a', 40), 'First') + [
        '_slim-killer/addresses.json' => '{"destinations/namibia": "/destinations/namibia/"}',
    ]);
    $fetcher->fetch();
    expect(file_get_contents($root . '/storage/site/addresses.json'))->toContain('/destinations/namibia/');

    newBuild($github, str_repeat('b', 40), 'Second');
    $fetcher->fetch();
    expect($root . '/storage/site/addresses.json')->not->toBeFile();
});

it('keeps the build\'s photo previews beside the working copy', function (): void {
    [$fetcher, , $github, $root] = publishingHost();
    newBuild($github, str_repeat('a', 40), 'First', siteBuild(str_repeat('a', 40), 'First') + [
        '_slim-killer/previews.json' => '{"oryx.jpg": {"preview": "0123456789abcdef.webp", "width": 10, "height": 10}}',
        '_slim-killer/previews/0123456789abcdef.webp' => 'webp bytes',
    ]);
    $fetcher->fetch();

    expect(file_get_contents($root . '/storage/site/previews/0123456789abcdef.webp'))->toBe('webp bytes')
        ->and($root . '/storage/site/previews.json')->toBeFile()
        ->and($root . '/public_html/_site/' . substr($github->dist, 0, 12) . '/_slim-killer')->not->toBeDirectory();
});
