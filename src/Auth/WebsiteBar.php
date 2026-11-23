<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Whether someone sees the website bar on the website while signed in: a small button
 * that opens to "Edit this page", the control panel and Visits. Chosen on the
 * Preferences page. Either way, a signed-in browser is not counted as a visitor.
 */
enum WebsiteBar: string
{
    case Show = 'show';
    case Hide = 'hide';
}
