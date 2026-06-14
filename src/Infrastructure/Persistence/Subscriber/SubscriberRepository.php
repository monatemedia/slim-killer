<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Subscriber;

use Pixie\QueryBuilder\QueryBuilderHandler;

class SubscriberRepository {

    public function __construct(
        private QueryBuilderHandler $db
    ) {}

    /**
     * Check if an email address is already registered in the subscription list.
     */
    public function exists(string $email): bool {
        $record = $this->db->table('subscribers')
            ->where('email', '=', $email)
            ->first();

        return !empty($record);
    }

    /**
     * Store a unique subscriber record if they don't already exist.
     */
    public function subscribe(string $email): int {
        if ($this->exists($email)) {
            return 0;
        }

        return (int) $this->db->table('subscribers')->insert([
            'email' => $email
        ]);
    }

    public function all(): array {
        return $this->db->table('subscribers')->get() ?: [];
    }
}