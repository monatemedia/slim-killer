<?php

declare(strict_types=1);

namespace Bond\Domain\ValueObject;

/** Backed enum from Module 1. */
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
