<?php
declare(strict_types=1);

/**
 * Slim Killer Database Matrix Configuration
 */
return [
    // Read the active driver environment token, default to sqlite
    'default' => getenv('DB_DRIVER') ?: 'sqlite',

    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => getenv('DB_DATABASE') ?: __DIR__ . '/../database/database.sqlite',
            'prefix'   => getenv('DB_PREFIX') ?: '',
        ],

        'mysql' => [
            'driver'   => 'mysql',
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'slim_killer',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
            'prefix'   => getenv('DB_PREFIX') ?: '',
        ],

        'mariadb' => [
            'driver'   => 'mysql', // Uses native mysql driver context
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'slim_killer',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
            'prefix'   => getenv('DB_PREFIX') ?: '',
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'slim_killer',
            'username' => getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset'  => 'utf8',
            'prefix'   => getenv('DB_PREFIX') ?: '',
        ],
    ],
];