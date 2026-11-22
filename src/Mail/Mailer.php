<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/** Sends one email. The real one uses PHP's mail(); tests use a fake. */
interface Mailer
{
    /** True when the host accepted the email. */
    public function send(Letter $letter): bool;
}
