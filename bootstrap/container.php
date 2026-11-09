<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

/*
 * The composition root: the only place that builds the container. Everything else
 * receives its dependencies through constructors (BUILD_PHILOSOPHY.md, Rule 1).
 *
 * Definitions load in order, and later files override earlier ones, so a client site
 * can change a binding without touching core.
 */
return (static function (): ContainerInterface {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);
    $builder->addDefinitions(dirname(__DIR__) . '/config/container.php');

    $siteDefinitions = dirname(__DIR__) . '/config/site.php';
    if (is_file($siteDefinitions)) {
        $builder->addDefinitions($siteDefinitions);
    }

    return $builder->build();
})();
