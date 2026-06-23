<?php

declare(strict_types=1);

namespace Bond\Domain\ValueObject;

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
