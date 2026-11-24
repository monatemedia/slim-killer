<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * Sends with PHP's mail(), as the contact form does: shared hosts deliver it through
 * their own mail server. The sender must be a mailbox on the website's own domain
 * (MAIL_FROM in .env), or the host may refuse it or it may land in spam.
 *
 * Hosts relay it under their own name, so it isn't DKIM-signed for the website's domain
 * and fails DMARC alignment. SMTP (SmtpMailer) fixes that, when SMTP_HOST is set.
 */
final class PhpMailer implements Mailer
{
    public function send(Letter $letter): bool
    {
        $message = MimeMessage::of($letter);

        return mail($letter->to, MimeMessage::subject($letter->subject), $message->body, implode("\r\n", $message->headers), '-f' . $letter->from);
    }

    public function lastProblem(): string
    {
        return '';
    }
}
