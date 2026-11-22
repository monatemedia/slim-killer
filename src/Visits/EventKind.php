<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

/** What the visit counter counted. */
enum EventKind: string
{
    /** A page was opened. */
    case Page = 'page';

    /** A counted button or link was tapped: WhatsApp, email, phone, or one marked data-count. */
    case Tap = 'tap';

    /** A form was sent (the site's contact form fires a "form-sent" event). */
    case Form = 'form';
}
