<?php
declare(strict_types=1);

use Pixie\Connection;
use Slim\Views\Twig;
use Psr\Container\ContainerInterface;
use Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Slim Killer Service Definitions
 * * This file returns a raw definitions array directly to the application 
 * bootstrappers, ensuring autowiring maps cleanly across HTTP and CLI context layers.
 */
return [
// 1. Initialize Pixie Connection via Environment Definitions Matrix
    Connection::class => function () {
        $dbConfig = require __DIR__ . '/database.php';
        $activeDriver = $dbConfig['default'];
        $settings = $dbConfig['connections'][$activeDriver];
        
        // Pass the driver identifier and its config array to satisfy Pixie
        return new Connection($activeDriver, $settings);
    },

    // 2. Safely capture the global Database query wrapper alias and typehint ContainerInterface
    QueryBuilderHandler::class => function (ContainerInterface $container) {
        $connection = $container->get(Connection::class);
        return new QueryBuilderHandler($connection);
    },

    'db' => function (ContainerInterface $container) {
        return $container->get(QueryBuilderHandler::class);
    },

    // 3. Initialize the Decoupled Twig Template Engine Layer
    Twig::class => function () {
        $views = __DIR__ . '/../resources/views';
        $cache = __DIR__ . '/../storage/cache/views';

        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }

        return Twig::create($views, [
            'cache' => $cache,
            'debug' => true,
        ]);
    },
];