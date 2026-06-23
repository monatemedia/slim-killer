<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Exception;

use RuntimeException;
use Throwable;

/**
 * PersistenceException — an INFRASTRUCTURE failure (a database problem).
 *
 * Note what it is NOT: it does not extend the domain's DomainException. That is
 * deliberate — a dropped connection is not a business rule, so it must never be routed
 * as one. It wraps the original driver exception (PDOException) as $previous so the full
 * technical detail is available for SERVER-SIDE logging, while the domain and the edge
 * only ever see this clean, framework-level type.
 */
final class PersistenceException extends RuntimeException
{
    public function __construct(string $message = 'A persistence error occurred.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
