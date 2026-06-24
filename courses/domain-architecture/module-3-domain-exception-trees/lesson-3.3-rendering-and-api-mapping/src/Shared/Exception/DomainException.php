<?php

declare(strict_types=1);

namespace Bond\Shared\Exception;

use RuntimeException;

/** Abstract root of the domain exception tree (Lesson 3.2), shared across contexts. */
abstract class DomainException extends RuntimeException
{
}
