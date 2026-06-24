<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

/** Leaf that maps to 404 — a requested application does not exist. */
final class ApplicationNotFoundException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
    ) {
        parent::__construct('No bond application exists with that reference.');
    }

    #[\Override]
    public function context(): array
    {
        return ['application_id' => $this->applicationId];
    }
}
