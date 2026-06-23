<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

use RuntimeException;

/**
 * DomainException — the abstract base that marks a failure as a BUSINESS failure.
 *
 * Its whole job in this lesson is to be a *type the edge can catch*: `catch (DomainException)`
 * means "a business rule said no", as opposed to "the machinery broke". In Lesson 3.2 this
 * grows into a full tree (DomainException -> BondApplicationException -> leaves). For now it
 * is the dividing line between the two failure categories.
 */
abstract class DomainException extends RuntimeException
{
}
