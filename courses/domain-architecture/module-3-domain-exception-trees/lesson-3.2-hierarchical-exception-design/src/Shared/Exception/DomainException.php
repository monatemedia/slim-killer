<?php

declare(strict_types=1);

namespace Bond\Shared\Exception;

use RuntimeException;

/**
 * DomainException — the ABSTRACT ROOT of the whole exception tree.
 *
 * It lives in Shared (not in the Bond context) because it is the universal "a business
 * rule said no" type that EVERY bounded context's exceptions ultimately extend. An edge
 * handler that catches DomainException catches a business failure from anywhere in the
 * application — bond, subscriptions, billing — and knows it is safe to translate to a
 * client response (never a 500).
 *
 * It extends \RuntimeException, so it is also a \Throwable and a \Exception.
 */
abstract class DomainException extends RuntimeException
{
}
