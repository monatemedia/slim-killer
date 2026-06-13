<?php
declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

class ApplicationRepository
{
    public function create(array $data): bool
    {
        // Eloquent 'Query Builder' style
        return DB::table('applications')->insert([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'],
            'amount'     => $data['bond_amount'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}