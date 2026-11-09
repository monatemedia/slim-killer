<?php

declare(strict_types=1);

use function DI\add;
use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

use SlimKiller\Console\Application;
use SlimKiller\Console\Commands\CronHeartbeatCommand;
use SlimKiller\Console\Commands\DoctorCommand;
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
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Http\CurlHttpClient;
use SlimKiller\Http\HttpClient;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;
use SlimKiller\Support\SystemClock;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

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

    // --- Console -----------------------------------------------------------------
    // The commands hammer knows about. Sites add their own with DI\add([...]).
    'console.commands' => add([
        get(DoctorCommand::class),
        get(CronHeartbeatCommand::class),
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
    // Templates ship with core. Compiled templates are cached in storage; auto_reload
    // recompiles a template when its file changes, so editing one needs no cache clear.
    Twig::class => factory(static function (Paths $paths): Twig {
        $twig = new Twig(new FilesystemLoader(dirname(__DIR__) . '/templates'), [
            'cache' => $paths->storage('cache/twig'),
            'auto_reload' => true,
            'strict_variables' => true,
        ]);
        $twig->addGlobal('base', ControlPanel::BASE_PATH);

        return $twig;
    }),
];
