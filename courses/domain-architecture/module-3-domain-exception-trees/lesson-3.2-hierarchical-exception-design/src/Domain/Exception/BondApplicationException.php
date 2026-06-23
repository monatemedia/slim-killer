<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

use Bond\Shared\Exception\DomainException;

/**
 * BondApplicationException — the BOUNDED-CONTEXT NODE.
 *
 * Every failure of a bond-application business rule extends this. It sits between the
 * shared root and the concrete leaves, which gives the edge a choice of altitude:
 *
 *   catch (ApplicantHasInsufficientIncomeException) → handle one specific rule
 *   catch (BondApplicationException)                → handle ANY bond-context rule
 *   catch (DomainException)                         → handle ANY business rule, app-wide
 *
 * It also defines the contract every bond leaf must satisfy: a `context()` of SAFE,
 * client-facing fields. The method is abstract here, so a leaf cannot exist without
 * declaring exactly what it is allowed to tell the outside world.
 */
abstract class BondApplicationException extends DomainException
{
    /**
     * Safe, structured, client-facing context — scalars only.
     * NEVER stack traces, SQL, file paths, or other internals.
     *
     * @return array<string, scalar>
     */
    abstract public function context(): array;
}
