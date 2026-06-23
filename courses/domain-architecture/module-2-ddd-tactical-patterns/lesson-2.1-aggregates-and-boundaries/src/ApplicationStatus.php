<?php

declare(strict_types=1);

namespace Bond\Model;

/** The lifecycle states of the aggregate (guards taught in Lesson 1.1). */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
}
