<?php

declare(strict_types=1);

namespace Bond\Http;

use Bond\Domain\Exception\DomainException;
use Throwable;

/**
 * ErrorResponder — the EDGE. It decides what the outside world sees, based purely on the
 * CATEGORY of the failure:
 *
 *   - DomainException (business)        -> 422 with a safe, specific message.
 *   - anything else (infrastructure /   -> 500 generic with a log reference; the detail is
 *     unexpected bugs)                     logged server-side, never sent to the client.
 *
 * This is the only layer allowed to read an exception's message for the client, and it
 * only does so for DomainExceptions — which are, by design, safe to show.
 */
final class ErrorResponder
{
    /** @return array{0:int,1:array<string,mixed>} [status, body] */
    public static function render(Throwable $e): array
    {
        if ($e instanceof DomainException) {
            return [422, [
                'title'  => 'Bond application rejected',
                'detail' => $e->getMessage(),
            ]];
        }

        // Infrastructure or unexpected: never leak. Produce a reference instead.
        $reference = 'err_' . substr(hash('sha256', $e->getMessage() . $e->getFile() . $e->getLine()), 0, 6);
        // Production: log the FULL $e here (including ->getPrevious()) under $reference.

        return [500, [
            'title'     => 'Something went wrong',
            'reference' => $reference,
        ]];
    }
}
