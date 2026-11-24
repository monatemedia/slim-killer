<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

use SlimKiller\Support\Clock;

/**
 * On a developer's own computer (APP_ENV=local), email is written to storage/mail as .eml
 * files instead of sent: nobody gets a test email by mistake, and each one opens in an
 * email app to see how it looks.
 */
final class SavedMailer implements Mailer
{
    public function __construct(
        private readonly string $folder,
        private readonly Clock $clock,
    ) {}

    public function send(Letter $letter): bool
    {
        if (! is_dir($this->folder) && ! @mkdir($this->folder, 0755, true) && ! is_dir($this->folder)) {
            return false;
        }
        $message = MimeMessage::of($letter);
        $now = $this->clock->now();
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($letter->subject)), '-');
        $file = $this->folder . '/' . $now->format('Y-m-d-His') . '-' . substr($slug !== '' ? $slug : 'email', 0, 60) . '.eml';
        $lines = [
            'Date: ' . $now->format(DATE_RFC2822),
            'To: ' . $letter->to,
            'Subject: ' . MimeMessage::subject($letter->subject),
            ...$message->headers,
            '',
            $message->body,
        ];

        return file_put_contents($file, implode("\r\n", $lines)) !== false;
    }

    public function lastProblem(): string
    {
        return '';
    }
}
