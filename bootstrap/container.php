<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

/*
 * The composition root: the only place that builds the container. Everything else
 * receives its dependencies through constructors (BUILD_PHILOSOPHY.md, Rule 1).
 *
 * Definitions load in order, and later files override earlier ones, so a client site
 * can change a binding without touching core.
 */
return (static function (): ContainerInterface {
    // .env holds this site's settings (written by `hammer install`). Missing is fine.
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);
    $builder->addDefinitions(dirname(__DIR__) . '/config/container.php');

    $siteDefinitions = dirname(__DIR__) . '/config/site.php';
    if (is_file($siteDefinitions)) {
        $builder->addDefinitions($siteDefinitions);
    }

    return $builder->build();
})();
