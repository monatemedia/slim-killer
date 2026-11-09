<?php

declare(strict_types=1);

use SlimKiller\Console\Application;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Psr\Container\ContainerInterface $container */
$container = require __DIR__ . '/container.php';

$application = $container->get(Application::class);
assert($application instanceof Application);

$argv = array_values(array_filter(
    is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [],
    'is_string',
));

return $application->run(Input::fromArgv($argv), new Output(STDOUT));
