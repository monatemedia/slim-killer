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
 * ProblemDetailsMapper — the boundary translation layer.
 *
 * This is the ONE place that knows how a thrown exception becomes an HTTP response. It
 * does three jobs and nothing else:
 *   1. Map the exception TYPE to an HTTP STATUS (the single match table below).
 *   2. Build a SAFE RFC 7807 body — domain messages + context() are safe to show;
 *      anything else gets a generic body + a log reference and no internals.
 *   3. Reveal internals ONLY when $debug is true (APP_DEBUG), and even then only for
 *      server errors.
 *
 * Adding a new domain error is a one-line change here (or zero lines, if it falls under
 * BondApplicationException -> 422 already). The domain stays free of HTTP concerns.
 */
final class ProblemDetailsMapper
{
    public static function map(Throwable $e, bool $debug = false): ProblemDetails
    {
        // ── The single type → status table ───────────────────────────────
        $status = match (true) {
            $e instanceof ApplicationNotFoundException        => 404,
            $e instanceof ApplicationAlreadySubmittedException => 409,
            $e instanceof BondApplicationException            => 422, // income, over-leverage, future leaves
            $e instanceof DomainException                     => 422, // business failures from other contexts
            default                                            => 500, // infrastructure / unexpected
        };

        return $e instanceof DomainException
            ? self::domainProblem($e, $status)
            : self::serverProblem($e, $debug);
    }

    private static function domainProblem(DomainException $e, int $status): ProblemDetails
    {
        $body = [
            'type'   => '/problems/' . self::slug($e),
            'title'  => self::titleFor($status),
            'status' => $status,
            'detail' => $e->getMessage(), // domain messages are written to be safe to show
        ];

        // Bond leaves expose vetted, structured context; include it as an extension member.
        if ($e instanceof BondApplicationException) {
            $body['context'] = $e->context();
        }

        return new ProblemDetails($status, $body);
    }

    private static function serverProblem(Throwable $e, bool $debug): ProblemDetails
    {
        // A stable reference the client can quote and you can grep your logs for.
        $reference = 'err_' . substr(hash('sha256', $e->getMessage() . $e->getFile() . $e->getLine()), 0, 8);

        $body = [
            'type'      => '/problems/internal-error',
            'title'     => 'Something went wrong',
            'status'    => 500,
            'reference' => $reference,
        ];

        // ONLY with APP_DEBUG=true. Never expose this in production.
        if ($debug) {
            $body['debug'] = [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ];
        }

        return new ProblemDetails(500, $body);
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            404     => 'Bond application not found',
            409     => 'Bond application conflict',
            422     => 'Bond application rejected',
            default => 'Request could not be processed',
        };
    }

    /** ApplicantHasInsufficientIncomeException -> "applicant-has-insufficient-income". */
    private static function slug(Throwable $e): string
    {
        $short = (new ReflectionClass($e))->getShortName();
        $short = preg_replace('/Exception$/', '', $short);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }
}
