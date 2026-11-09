<?php

declare(strict_types=1);

use SlimKiller\Http\ControlPanel\ControlPanel;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Psr\Container\ContainerInterface $container */
$container = require __DIR__ . '/container.php';

ControlPanel::create($container)->run();
