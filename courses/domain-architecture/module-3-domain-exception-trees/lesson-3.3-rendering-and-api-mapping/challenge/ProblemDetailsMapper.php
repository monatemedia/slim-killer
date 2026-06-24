<?php

declare(strict_types=1);

namespace Bond\Http\Problem;

use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\ApplicationNotFoundException;
use Bond\Domain\Exception\BondApplicationException;
use Bond\Shared\Exception\DomainException;
use ReflectionClass;
use Throwable;

/**
 * 🏗️  CODE CHALLENGE — Build the boundary translation layer
 * -------------------------------------------------------------------------
 * Implement map(): the single place that turns any thrown exception into an RFC 7807
 * ProblemDetails. This is the engine the whole module has been building toward.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. Type → status, via one match:
 *        ApplicationNotFoundException        -> 404
 *        ApplicationAlreadySubmittedException -> 409
 *        any other BondApplicationException  -> 422
 *        any other DomainException           -> 422
 *        anything else                       -> 500
 *   2. DOMAIN failures: body has type ('/problems/<kebab-slug>'), title, status, and
 *      detail = the exception message. If it is a BondApplicationException, also include
 *      'context' => $e->context().
 *   3. SERVER failures (500): body has type, title 'Something went wrong', status 500,
 *      and a 'reference'. It must NOT contain 'detail' or 'context'. Include a 'debug'
 *      member ONLY when $debug is true.
 *
 * TIP: the slug() helper below already turns the class name into a kebab type. Build the
 *      body array yourself and wrap it in `new ProblemDetails($status, $body)`.
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/ProblemDetailsMapper.php
 */
final class ProblemDetailsMapper
{
    public static function map(Throwable $e, bool $debug = false): ProblemDetails
    {
        // TODO: implement requirements 1–3 and return a ProblemDetails.
        throw new \RuntimeException('TODO: implement ProblemDetailsMapper::map()');
    }

    /** ApplicantHasInsufficientIncomeException -> "applicant-has-insufficient-income". */
    private static function slug(Throwable $e): string
    {
        $short = (new ReflectionClass($e))->getShortName();
        $short = preg_replace('/Exception$/', '', $short);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }
}
