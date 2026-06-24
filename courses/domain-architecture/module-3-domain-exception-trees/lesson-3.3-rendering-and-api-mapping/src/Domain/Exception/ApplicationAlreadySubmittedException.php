<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

final class ApplicationAlreadySubmittedException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $currentStatus,
    ) {
        parent::__construct('This application has already been submitted and can no longer be changed.');
    }

    #[\Override]
    public function context(): array
    {
        return [
            'application_id' => $this->applicationId,
            'current_status' => $this->currentStatus,
        ];
    }
}
