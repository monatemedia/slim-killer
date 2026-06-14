<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Application;

use Pixie\QueryBuilder\QueryBuilderHandler;

class ApplicationRepository {

    public function __construct(
        private QueryBuilderHandler $db
    ) {}

    /**
     * Store a new structural financing application record.
     */
    public function create(array $data): int {
        return (int) $this->db->table('applications')->insert([
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'province'    => $data['province'] ?? null,
            'city'        => $data['city'] ?? null,
            'bond_amount' => (float) $data['bond_amount'],
            'message'     => $data['message'] ?? null,
        ]);
    }

    public function all(): array {
        return $this->db->table('applications')->get() ?: [];
    }
}