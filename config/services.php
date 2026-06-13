<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Pixie\Connection;
use Slim\Views\Twig;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // 1. Initialize Pixie Connection Parameters Explicitly
    Connection::class => function () {
        $config = [
            'driver'   => 'sqlite',
            'database' => __DIR__ . '/../database/database.sqlite',
            'prefix'   => '',
        ];
        
        // Pass arguments directly to satisfy Pixie's constructor requirements
        return new Connection('sqlite', $config);
    },

    // 2. Safely capture the global Database query wrapper alias 'db'
    'db' => function ($c) {
        $connection = $c->get(Connection::class);
        return new \Pixie\QueryBuilder\QueryBuilderHandler($connection);
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
]);

return $containerBuilder->build();