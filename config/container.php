<?php

declare(strict_types=1);

use function DI\add;
use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use SlimKiller\Console\Application;
use SlimKiller\Console\Commands\CronHeartbeatCommand;
use SlimKiller\Console\Commands\DeployFetchCommand;
use SlimKiller\Console\Commands\DeployRollbackCommand;
use SlimKiller\Console\Commands\DoctorCommand;
use SlimKiller\Console\Commands\InstallCommand;
use SlimKiller\Console\Commands\MigrateCommand;
use SlimKiller\Console\Commands\UserCreateCommand;
use SlimKiller\Console\Commands\UserResetCommand;
use SlimKiller\Content\ContentLocation;
use SlimKiller\Database\Database;
use SlimKiller\Database\Migrator;
use SlimKiller\Doctor\BinaryFinder;
use SlimKiller\Doctor\Checks\CronCheck;
use SlimKiller\Doctor\Checks\DisabledFunctionsCheck;
use SlimKiller\Doctor\Checks\ExtensionsCheck;
use SlimKiller\Doctor\Checks\GitHubReachableCheck;
use SlimKiller\Doctor\Checks\ImageSupportCheck;
use SlimKiller\Doctor\Checks\LimitsCheck;
use SlimKiller\Doctor\Checks\PhpBinaryCheck;
use SlimKiller\Doctor\Checks\PhpVersionCheck;
use SlimKiller\Doctor\Checks\StorageCheck;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\Environment\RuntimeEnvironment;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Http\CurlHttpClient;
use SlimKiller\Http\HttpClient;
use SlimKiller\Publishing\Releases;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;
use SlimKiller\Support\EnvFile;
use SlimKiller\Support\Paths;
use SlimKiller\Support\ShutdownAfterResponse;
use SlimKiller\Support\SystemClock;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/*
 * Core container definitions. A client site can extend or override any of these in
 * config/site.php, for example by adding its own doctor checks:
 *
 *     'doctor.php_checks' => DI\add([DI\get(MyCheck::class)]),
 */
return [
    // --- Support -----------------------------------------------------------------
    Paths::class => create()->constructor(dirname(__DIR__)),
    Clock::class => get(SystemClock::class),
    HttpClient::class => get(CurlHttpClient::class),
    Environment::class => get(RuntimeEnvironment::class),
    AppConfig::class => factory(static fn(): AppConfig => AppConfig::fromEnvironment($_ENV)),
    EnvFile::class => create()->constructor(dirname(__DIR__) . '/.env'),

    // --- Database ----------------------------------------------------------------
    // One SQLite file in storage, outside the web root. Opened only when needed.
    PDO::class => factory(static fn(Paths $paths): PDO => Database::open($paths->storage(Database::FILE))),
    Migrator::class => autowire()->constructorParameter('directory', dirname(__DIR__) . '/database/migrations'),

    // --- Content -----------------------------------------------------------------
    // The working copy the panel reads from: in storage on a client's host, or wherever
    // CONTENT_DIR and SCHEMA_DIR point for local development.
    ContentLocation::class => factory(static fn(AppConfig $config, Paths $paths): ContentLocation => ContentLocation::from($config, $paths)),

    GitHubSettings::class => factory(static fn(AppConfig $config): GitHubSettings => new GitHubSettings(
        $config->githubRepository,
        $config->githubBranch,
        $config->githubToken,
        $config->githubContentFolder,
    )),

    // --- Publishing --------------------------------------------------------------
    Releases::class => factory(static fn(AppConfig $config, Paths $paths): Releases => Releases::from($config, $paths)),
    AfterResponse::class => get(ShutdownAfterResponse::class),

    // --- Console -----------------------------------------------------------------
    // The commands hammer knows about. Sites add their own with DI\add([...]).
    'console.commands' => add([
        get(InstallCommand::class),
        get(DoctorCommand::class),
        get(MigrateCommand::class),
        get(UserCreateCommand::class),
        get(UserResetCommand::class),
        get(CronHeartbeatCommand::class),
        get(DeployFetchCommand::class),
        get(DeployRollbackCommand::class),
    ]),
    Application::class => autowire()->constructorParameter('commands', get('console.commands')),

    // --- Doctor ------------------------------------------------------------------
    BinaryFinder::class => autowire()->constructorParameter('patterns', [
        '/opt/alt/php*/usr/bin/php',
        '/opt/cpanel/ea-php*/root/usr/bin/php',
        '/usr/local/bin/php*',
        '/usr/bin/php*',
    ]),

    // Checks that run against a PHP runtime: the command line, and the website.
    'doctor.php_checks' => add([
        get(PhpVersionCheck::class),
        get(ExtensionsCheck::class),
        get(DisabledFunctionsCheck::class),
        get(LimitsCheck::class),
        get(ImageSupportCheck::class),
    ]),

    // Checks about the server itself. These run once, from the command line.
    'doctor.host_checks' => add([
        get(PhpBinaryCheck::class),
        get(StorageCheck::class),
        get(GitHubReachableCheck::class),
        get(CronCheck::class),
    ]),

    Doctor::class => autowire()
        ->constructorParameter('phpChecks', get('doctor.php_checks'))
        ->constructorParameter('hostChecks', get('doctor.host_checks')),

    // --- Control panel -----------------------------------------------------------
    ResponseFactoryInterface::class => get(ResponseFactory::class),

    // Templates ship with core. Compiled templates are cached in storage; auto_reload
    // recompiles a template when its file changes, so editing one needs no cache clear.
    Twig::class => factory(static function (Paths $paths, AppConfig $config): Twig {
        $twig = new Twig(new FilesystemLoader(dirname(__DIR__) . '/templates'), [
            'cache' => $paths->storage('cache/twig'),
            'auto_reload' => true,
            'strict_variables' => true,
        ]);
        $twig->addGlobal('base', ControlPanel::BASE_PATH);
        $twig->addGlobal('site_name', $config->name);

        // asset('cp.css') gives the file's address with its change time on the end,
        // so a browser fetches the file again after it changes, not an old copy.
        $assets = dirname(__DIR__) . '/public/cp/assets';
        $twig->addFunction(new TwigFunction('asset', static function (string $file) use ($assets): string {
            $changed = @filemtime($assets . '/' . $file);

            return ControlPanel::BASE_PATH . '/assets/' . $file . ($changed === false ? '' : '?v=' . $changed);
        }));

        return $twig;
    }),
];
