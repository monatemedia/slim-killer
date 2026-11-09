<?php

declare(strict_types=1);

namespace SlimKiller\Setup;

use RuntimeException;

/**
 * The setup link could not be written to the storage folder, so there is no link to
 * hand out. The doctor reports it; the storage check says how to fix it.
 */
final class SetupLinkNotSaved extends RuntimeException
{
    public function __construct(public readonly string $path)
    {
        parent::__construct("Could not write the setup link to {$path}.");
    }
}
