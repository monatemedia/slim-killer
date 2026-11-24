<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/** Sends one email: through SMTP when SMTP_HOST is set, otherwise PHP's mail(). Tests use a fake. */
interface Mailer
{
    /** True when the mail server accepted the email. */
    public function send(Letter $letter): bool;

    /** Why the last email wasn't sent, in a line; empty when it was, or when nothing says. Never a password. */
    public function lastProblem(): string;
}
