<?php

declare(strict_types=1);

namespace Bond\Model;

/** The lifecycle states a BondApplication can occupy (see Lesson 1.1 for the guards). */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
}
