<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use SlimKiller\Alerts\Alerts;
use SlimKiller\Auth\BreachedPasswords;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Database\Database;
use SlimKiller\Database\Migrator;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\Environment\SnapshotEnvironment;
use SlimKiller\Doctor\Requirements;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\AppEnv;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;
use SlimKiller\Visits\SiteTime;
use Tests\Fakes\Browser;
use Tests\Fakes\FakeBreachedPasswords;
use Tests\Fakes\FakeMailer;
use Tests\Fakes\SteppingClock;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

/*
 * A healthy PHP environment for tests. Override only what a test is about:
 *
 *     environment(['php_version' => '8.3.36'])
 */
function environment(array $overrides = [], string $label = 'Command line'): SnapshotEnvironment
{
    $defaults = [
        'php_version' => '8.4.27',
        'sapi' => 'cli',
        'php_binary' => '/opt/alt/php84/usr/bin/php',
        'extensions' => [...array_keys(Requirements::REQUIRED_EXTENSIONS), ...array_keys(Requirements::RECOMMENDED_EXTENSIONS)],
        'ini' => [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
            'upload_max_filesize' => '128M',
            'post_max_size' => '128M',
        ],
        'disabled_functions' => [],
        'jpeg_write' => true,
        'webp_read' => false,
        'avif_read' => false,
    ];

    return SnapshotEnvironment::fromProbe(array_replace($defaults, $overrides), $label);
}

/**
 * A fresh, empty temporary folder, removed again at the end of the test run.
 *
 * Windows will not delete a file that is still open, and a test's SQLite connection
 * can outlive the test inside a container's reference cycles. So the clean-up first
 * collects those cycles, which closes the connections, and if a file is still locked
 * it is left for the system's temp clean-up rather than failing loudly.
 */
function temporaryFolder(): string
{
    $folder = sys_get_temp_dir() . '/slim-killer-test-' . bin2hex(random_bytes(6));
    mkdir($folder, 0755, true);
    register_shutdown_function(static function () use ($folder): void {
        if (! is_dir($folder)) {
            return;
        }
        gc_collect_cycles();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($folder);
    });

    return $folder;
}

/** @return list<string> */
function statuses(array $results): array
{
    return array_map(static fn($result) => $result->status->value, $results);
}

/** A fresh in-memory database with every migration run. */
function migratedDatabase(): PDO
{
    $pdo = Database::open(':memory:');
    (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))->migrate();

    return $pdo;
}

// --- A control panel to drive like a browser (shared by the Http tests) -----------------

const PASSWORD = 'correct horse battery staple';

/** @return array{0: Browser, 1: ContainerInterface, 2: SteppingClock} */
function installedPanel(array $definitions = []): array
{
    $clock = new SteppingClock('2026-11-09 12:00:00');
    $builder = new ContainerBuilder();
    $builder->addDefinitions(dirname(__DIR__) . '/config/container.php');
    $builder->addDefinitions([
        Paths::class => new Paths(temporaryFolder()),
        Clock::class => $clock,
        Environment::class => environment(['sapi' => 'litespeed'], 'Website'),
        // Never ask Have I Been Pwned from a test; a test about it passes its own.
        BreachedPasswords::class => new FakeBreachedPasswords(0),
        ...$definitions,
    ]);
    $container = $builder->build();
    $container->get(Migrator::class)->migrate();

    return [new Browser(ControlPanel::create($container, showErrorDetails: true, logErrors: false)), $container, $clock];
}

function welcomeLink(ContainerInterface $container, string $email = 'edward@example.com'): string
{
    $user = $container->get(UserRepository::class)->create(EmailAddress::from($email), 'Edward', Role::Admin);

    return WelcomeLinks::pathFor($container->get(WelcomeLinks::class)->issue($user->id));
}

/** Takes a new user from the welcome link to signed in. Returns the secret and recovery codes. */
function enrol(Browser $browser, ContainerInterface $container, SteppingClock $clock): array
{
    $browser->get(welcomeLink($container));
    $browser->get('/cp/welcome');
    $browser->post('/cp/welcome', ['password' => PASSWORD, 'password_confirmation' => PASSWORD]);
    $browser->get('/cp/two-factor/setup');
    preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $browser->body(), $match);
    $secret = str_replace(' ', '', $match[1] ?? '');
    $browser->post('/cp/two-factor/setup', ['code' => (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp())]);
    preg_match_all('#<li><code>([a-z0-9-]{11})</code></li>#', $browser->body(), $codes);
    $browser->get('/cp/');

    return [$secret, $codes[1]];
}

require __DIR__ . '/photos.php';

// Browser tests (tests/Browser, composer test:browser) run in WebKit, Safari's engine,
// since the panel is tried on an iPhone. Run another with: pest --browser chrome
pest()->browser()->inSafari();
require __DIR__ . '/Browser/panel.php';

/**
 * Alerts for a test, with a mailer that keeps what it sends. By default they go to
 * owner@example.com (VISIT_REPORT_TO) from website@example.com.
 *
 * @return array{0: Alerts, 1: FakeMailer}
 */
function testAlerts(Paths $paths, ?AppConfig $config = null, bool $accepts = true, ?SlimKiller\Mail\Mailer $fallback = null): array
{
    $config ??= new AppConfig(name: 'Acme Tours', timezone: 'Africa/Johannesburg', mailFrom: 'website@example.com', visitReportTo: 'owner@example.com');
    $twig = new Twig(new FilesystemLoader(dirname(__DIR__) . '/templates'), ['strict_variables' => true, 'autoescape' => 'name']);
    $twig->addGlobal('site_name', $config->name);
    $mailer = new FakeMailer($accepts);

    return [new Alerts($paths, new LetterWriter($twig, $config), $mailer, $config, SiteTime::named($config->timezone), $fallback), $mailer];
}

/** Settings for a test copy (APP_ENV=staging): the doctor's warnings stay warnings. */
function stagingConfig(): AppConfig
{
    return new AppConfig(env: AppEnv::Staging);
}
