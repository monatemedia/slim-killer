<?php

declare(strict_types=1);

use SlimKiller\Http\ControlPanel\ControlPanel;

require dirname(__DIR__) . '/vendor/autoload.php';

// The panel's hints name hammer commands with the PHP cron uses, not the website's.
SlimKiller\Support\Hammer::usePhpFromHeartbeat(dirname(__DIR__) . '/storage/' . SlimKiller\Cron\Heartbeat::FILE);

/** @var Psr\Container\ContainerInterface $container */
$container = require __DIR__ . '/container.php';

ControlPanel::create($container)->run();
