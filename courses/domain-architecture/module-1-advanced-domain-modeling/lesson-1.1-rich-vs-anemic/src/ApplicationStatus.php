<?php

declare(strict_types=1);

namespace Bond\Model;

/**
 * ApplicationStatus — the legal states a Bond Application can occupy.
 *
 * In the anemic flow, status is a magic string ('draft', 'submitted', ...), so
 * 'aproved' is just as "valid" as 'approved'. A backed enum makes the set of states
 * closed: a BondApplication can only ever hold one of THESE four cases.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved  => 'Approved',
            self::Declined  => 'Declined',
        };
    }
}
