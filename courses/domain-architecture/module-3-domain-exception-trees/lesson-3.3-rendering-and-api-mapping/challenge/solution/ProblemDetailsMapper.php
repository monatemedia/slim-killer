<?php

declare(strict_types=1);

namespace Bond\Http\Problem;

use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\ApplicationNotFoundException;
use Bond\Domain\Exception\BondApplicationException;
use Bond\Shared\Exception\DomainException;
use ReflectionClass;
use Throwable;

/** ✅ REFERENCE SOLUTION — the boundary translation layer. */
final class ProblemDetailsMapper
{
    public static function map(Throwable $e, bool $debug = false): ProblemDetails
    {
        $status = match (true) {
            $e instanceof ApplicationNotFoundException         => 404,
            $e instanceof ApplicationAlreadySubmittedException => 409,
            $e instanceof BondApplicationException             => 422,
            $e instanceof DomainException                      => 422,
            default                                            => 500,
        };

        if ($e instanceof DomainException) {
            $body = [
                'type'   => '/problems/' . self::slug($e),
                'title'  => self::titleFor($status),
                'status' => $status,
                'detail' => $e->getMessage(),
            ];
            if ($e instanceof BondApplicationException) {
                $body['context'] = $e->context();
            }

            return new ProblemDetails($status, $body);
        }

        $reference = 'err_' . substr(hash('sha256', $e->getMessage() . $e->getFile() . $e->getLine()), 0, 8);
        $body = [
            'type'      => '/problems/internal-error',
            'title'     => 'Something went wrong',
            'status'    => 500,
            'reference' => $reference,
        ];
        if ($debug) {
            $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
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

    private static function slug(Throwable $e): string
    {
        $short = (new ReflectionClass($e))->getShortName();
        $short = preg_replace('/Exception$/', '', $short);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }
}
