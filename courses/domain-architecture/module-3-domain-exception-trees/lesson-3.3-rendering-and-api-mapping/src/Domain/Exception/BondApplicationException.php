<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

use Bond\Shared\Exception\DomainException;

/** Bounded-context node (Lesson 3.2): every bond leaf carries safe client-facing context. */
abstract class BondApplicationException extends DomainException
{
    /** @return array<string, scalar> Safe fields only — never traces, SQL, or paths. */
    abstract public function context(): array;
}
