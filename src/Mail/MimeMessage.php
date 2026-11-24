<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * A letter as the lines an email is made of: its headers and its body. Plain text, or
 * plain text and HTML together (multipart/alternative, plain text first, so each email
 * app shows the best one it can). Base64 throughout, so any language and any line
 * length is safe.
 *
 * Shared by both ways of sending: PHP's mail() adds To and Subject itself, SMTP sends
 * them as headers too.
 */
final readonly class MimeMessage
{
    /** @param list<string> $headers */
    private function __construct(
        public array $headers,
        public string $body,
    ) {}

    public static function of(Letter $letter): self
    {
        $headers = ['From: ' . $letter->from, 'MIME-Version: 1.0', 'X-Mailer: Slim Killer'];

        if ($letter->html === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';

            return new self($headers, chunk_split(base64_encode($letter->text)));
        }

        $boundary = 'slim-killer-' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        return new self($headers, implode("\r\n", [
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
        ]));
    }

    /** A subject in any language, as a header can carry it. */
    public static function subject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
}
