<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Database\Migrator;
use SlimKiller\Photos\PhotoLibrary;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Paths;

/*
 * The control panel for the browser tests: the real app, on a copy of the fixture site,
 * served by PHP's built-in server (tests/Browser/server.php) for the length of the run.
 * Saving is not set up (no GitHub), so nothing leaves the machine.
 */

const BROWSER_USER = 'browser@example.com';

/** The panel's container for one state folder (its storage and its copy of the site). */
function browserContainer(string $state): ContainerInterface
{
    $builder = new ContainerBuilder();
    $builder->addDefinitions(dirname(__DIR__, 2) . '/config/container.php');
    $builder->addDefinitions([
        Paths::class => new Paths($state),
        AppConfig::class => new AppConfig(
            name: 'Browser tests',
            contentDir: $state . '/site/content',
            schemaDir: dirname(__DIR__) . '/Fixtures/site/schemas',
        ),
    ]);

    return $builder->build();
}

/**
 * The address of a panel page, signed in, for visit(): the test server signs the browser
 * in, then sends it on to $path. Starts the server the first time.
 */
function signedIn(string $path): string
{
    return browserPanel() . '/cp/__test/sign-in?next=' . rawurlencode($path);
}

/** Starts the test server once per run, and returns its address. */
function browserPanel(): string
{
    static $address = null;
    if ($address !== null) {
        return $address;
    }

    $state = temporaryFolder();
    prepareBrowserState($state);

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $root = dirname(__DIR__, 2);
    $log = $state . '/server.log';
    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', __DIR__ . '/server.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
        $pipes,
        $root,
        ['SK_BROWSER_STATE' => $state] + getenv(),
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start the test server.');
    }
    register_shutdown_function(static function () use ($process): void {
        proc_terminate($process);
    });

    // Wait for it to listen. The first tries are expected to fail, so their warnings are
    // ignored here rather than reported as test warnings.
    set_error_handler(static fn(): bool => true);
    try {
        for ($try = 0; $try < 100; $try++) {
            $connection = fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if (is_resource($connection)) {
                fclose($connection);

                return $address = 'http://127.0.0.1:' . $port;
            }
            usleep(100_000);
        }
    } finally {
        restore_error_handler();
    }

    throw new RuntimeException('The test server did not start. See ' . $log);
}

/**
 * Picks a file in a file input, as a person choosing a photo does: the file is put on the
 * input and its change event fires. Done in the page, because Playwright's server will not
 * read files from this machine for a client it treats as remote.
 */
function pickFile(mixed $page, string $selector, string $path, string $type = 'image/jpeg'): mixed
{
    $page->script(sprintf(
        "(() => { const bytes = Uint8Array.from(atob(%s), (c) => c.charCodeAt(0));
            const input = document.querySelector(%s);
            const files = new DataTransfer();
            files.items.add(new File([bytes], %s, { type: %s }));
            input.files = files.files;
            input.dispatchEvent(new Event('change', { bubbles: true })); })()",
        json_encode(base64_encode((string) file_get_contents($path))),
        json_encode($selector),
        json_encode(basename($path)),
        json_encode($type),
    ));

    return $page;
}

/** A copy of the fixture site, previews for its photos, a database and one person. */
function prepareBrowserState(string $state): void
{
    $fixtures = dirname(__DIR__) . '/Fixtures/site/content';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtures, FilesystemIterator::SKIP_DOTS)) as $file) {
        $target = $state . '/site/content/' . substr($file->getPathname(), strlen($fixtures) + 1);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        copy($file->getPathname(), $target);
    }

    // Previews as a build would make them, so "Choose a photo" has something to show.
    mkdir($state . '/site/previews', 0755, true);
    $previews = [];
    foreach (['oryx.jpg' => [200, 120, 40], 'oryx-1.jpg' => [40, 120, 200], 'oryx-2.jpg' => [120, 200, 40], 'deadvlei.jpg' => [200, 60, 60]] as $photo => $colour) {
        $image = imagecreatetruecolor(300, 200);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$colour));
        $name = PhotoLibrary::previewName($photo);
        imagejpeg($image, $state . '/site/previews/' . $name);
        $previews[$photo] = ['preview' => $name, 'width' => 300, 'height' => 200];
    }
    file_put_contents($state . '/site/previews.json', json_encode($previews));

    $container = browserContainer($state);
    $container->get(Migrator::class)->migrate();
    $container->get(UserRepository::class)->create(EmailAddress::from(BROWSER_USER), 'Browser', Role::Admin);
}
