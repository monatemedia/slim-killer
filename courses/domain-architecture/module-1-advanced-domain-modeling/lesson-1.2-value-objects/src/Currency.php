<?php

declare(strict_types=1);

namespace Bond\ValueObject;

/**
 * Currency — a backed enum that makes a magic-string currency code impossible.
 *
 * In the anemic Bond flow, currency does not exist at all: `bond_amount` is a bare
 * float with an *implied* "rands" meaning living only in a developer's head. The moment
 * you pair an amount with a Currency enum, "what money is this?" becomes a type, not a comment.
 */
enum Currency: string
{
    case ZAR = 'ZAR';
    case USD = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            Currency::ZAR => 'R',
            Currency::USD => '$',
        };
    }
}
