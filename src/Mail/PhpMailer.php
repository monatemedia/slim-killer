<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * Sends with PHP's mail(), as the contact form does: shared hosts deliver it through
 * their own mail server. The sender must be a mailbox on the website's own domain
 * (MAIL_FROM in .env), or the host may refuse it or it may land in spam.
 *
 * A letter with an HTML body goes as both parts (multipart/alternative), plain text
 * first, so each email app shows the best one it can.
 */
final class PhpMailer implements Mailer
{
    public function send(Letter $letter): bool
    {
        $headers = ['From: ' . $letter->from, 'MIME-Version: 1.0', 'X-Mailer: Slim Killer'];

        if ($letter->html === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($letter->text));
        } else {
            $boundary = 'slim-killer-' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = implode("\r\n", [
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($letter->text)),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($letter->html)),
                '--' . $boundary . '--',
                '',
            ]);
        }

        return mail($letter->to, '=?UTF-8?B?' . base64_encode($letter->subject) . '?=', $body, implode("\r\n", $headers), '-f' . $letter->from);
    }
}
