<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * One email, ready to send: always a plain-text body, and an HTML one too when it was
 * written from the email layout (LetterWriter). The same word as in slim-killer-forms.
 */
final readonly class Letter
{
    public function __construct(
        public string $to,
        public string $from,
        public string $subject,
        public string $text,
        public string $html = '',
    ) {}
}
