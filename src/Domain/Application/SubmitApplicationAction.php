<?php
declare(strict_types=1);

namespace App\Actions;

use App\Repositories\ApplicationRepository;

class SubmitApplicationAction
{
    public function __construct(
        private ApplicationRepository $repository
    ) {}

    public function execute(array $data): bool
    {
        // 1. You could add logic here to send an email notification
        // 2. You could add logic to log this to a 3rd party API
        
        // 3. Save to our local SQLite database
        return $this->repository->create($data);
    }
}