<?php

declare(strict_types=1);

use SlimKiller\Publishing\Releases;
use SlimKiller\Publishing\WebRoot;
use SlimKiller\Support\Paths;

/** @return array{0: WebRoot, 1: string, 2: string, 3: Releases} the setup, the project, the web root and its releases */
function webRootSetup(string $webRoot = 'public_html'): array
{
    $project = temporaryFolder();
    mkdir($project . '/public/cp/assets', 0755, true);
    file_put_contents($project . '/public/cp/index.php', '<?php // the real front controller');
    file_put_contents($project . '/public/cp/assets/cp.css', 'body{}');
    file_put_contents($project . '/public/cp/assets/logo.svg', '<svg/>');
    $web = $webRoot === '' ? '' : temporaryFolder();
    $paths = new Paths($project);

    $releases = new Releases($web, $paths->storage('releases.json'));

    return [new WebRoot($releases, $paths), $project, $web, $releases];
}

it('routes /cp to the panel and everything else to the live release', function (): void {
    [$webRoot, , $web] = webRootSetup();

    $webRoot->install();

    $htaccess = (string) file_get_contents($web . '/.htaccess');
    expect($htaccess)->toContain(WebRoot::MARKER)
        ->toContain('RewriteRule ^cp(/.*)?$ cp/index.php [L]')
        ->toContain('RewriteRule ^(.*)$ _site/$1 [L]')
        ->toContain('RewriteRule ^\.well-known/ - [L]')
        ->toContain('Options -Indexes')
        ->toContain('ErrorDocument 404 /404.html');
});

it('loads the panel from the project folder, outside the web root', function (): void {
    [$webRoot, $project, $web] = webRootSetup();

    $webRoot->install();

    expect((string) file_get_contents($web . '/cp/index.php'))
        ->toContain('require ' . var_export($project . '/public/cp/index.php', true) . ';')
        ->and(file_get_contents($web . '/cp/assets/cp.css'))->toBe('body{}')
        ->and($web . '/cp/assets/logo.svg')->toBeFile();
});

it('keeps an .htaccess it did not write, and rewrites its own when run again', function (): void {
    [$webRoot, , $web] = webRootSetup();
    file_put_contents($web . '/.htaccess', '# the host\'s own rules');

    $first = $webRoot->install();
    $second = $webRoot->install();

    expect(file_get_contents($web . '/.htaccess.before-slim-killer'))->toBe('# the host\'s own rules')
        ->and($first[0])->toContain('Kept the existing .htaccess')
        ->and($second[0])->toStartWith('Wrote .htaccess')
        ->and(glob($web . '/.htaccess.before-slim-killer*'))->toHaveCount(1);
});

it('says what is missing when WEB_ROOT is not set', function (): void {
    [$webRoot] = webRootSetup('');

    expect(fn() => $webRoot->install())->toThrow(RuntimeException::class, 'WEB_ROOT in .env is empty');
});

it('puts the live release\'s 404 page back when run again', function (): void {
    [$webRoot, , $web, $releases] = webRootSetup();
    $folder = temporaryFolder();
    file_put_contents($folder . '/index.html', 'Home');
    file_put_contents($folder . '/404.html', 'This Trail Doesn\'t Exist');
    $release = new SlimKiller\Publishing\Release('abc123def456', str_repeat('a', 40), str_repeat('b', 40), '', '');
    $releases->add($release, $folder);
    $releases->switchTo($release);
    unlink($web . '/404.html');

    $done = $webRoot->install();

    expect(file_get_contents($web . '/404.html'))->toBe('This Trail Doesn\'t Exist')
        ->and(end($done))->toBe('Brought the live release\'s rules and 404 page up to date.')
        ->and($webRoot->hasLiveRelease())->toBeTrue();
});

it('answers missing pages with the live 404 page, or a plain one before the first fetch', function (): void {
    [$webRoot, , $web] = webRootSetup();
    $webRoot->install();
    $script = $web . '/cp/not-found.php';
    $run = static fn(): string => (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script));

    expect($webRoot->hasLiveRelease())->toBeFalse()
        ->and($run())->toContain('<h1>Page not found</h1>');

    file_put_contents($web . '/404.html', 'This Trail Doesn\'t Exist');
    expect($run())->toBe('This Trail Doesn\'t Exist');
});
