<?php

declare(strict_types=1);

namespace Bond\Domain\Model;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
}
