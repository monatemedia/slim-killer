<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Auth;

use App\Infrastructure\Commands\CommandInterface;
use Psr\Container\ContainerInterface;

class CreateAdminCommand implements CommandInterface {

    public function __construct(
        private ContainerInterface $container
    ) {}

    public function getName(): string {
        return 'auth:create-admin';
    }

    public function getDescription(): string {
        return 'Seed a new administrative user into the database';
    }

    public function handle(array $argv): void {
        $username = $argv[2] ?? 'edward';
        $passwordInput = $argv[3] ?? 'your_secure_password';
        
        $passwordHash = password_hash($passwordInput, PASSWORD_DEFAULT);

        try {
            // Extract Pixie directly from the compiled application container
            /** @var \Pixie\QueryBuilder\QueryBuilderHandler $db */
            $db = $this->container->get('db');

            $db->table('users')->insert([
                'username'   => $username,
                'password'   => $passwordHash,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            echo "\e[32m✅ Admin user created successfully:\e[0m $username\n";
        } catch (\Exception $e) {
            echo "\e[31m[ERROR] Failed to seed admin user:\e[0m " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}