<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;

/**
 * Sends through the website's own mailbox (SMTP_HOST, for example Titan), signed in as
 * SMTP_USER. The mail provider DKIM-signs it for the website's domain, so it passes
 * DMARC, where the host's mail() can't.
 *
 * The conversation: EHLO, STARTTLS when the port isn't 465, AUTH LOGIN, MAIL FROM, RCPT
 * TO, DATA, QUIT. The password goes only into AUTH, and never into a message.
 */
final class SmtpMailer implements Mailer
{
    public const int TIMEOUT_SECONDS = 15;

    private string $problem = '';

    public function __construct(
        private readonly SmtpConnection $connection,
        private readonly AppConfig $config,
        private readonly Clock $clock,
    ) {}

    public function send(Letter $letter): bool
    {
        $this->problem = '';
        try {
            $this->signIn();
            $this->expect('MAIL FROM:<' . $letter->from . '>', 250);
            $this->expect('RCPT TO:<' . $letter->to . '>', 250, 251);
            $this->expect('DATA', 354);
            foreach ($this->lines($letter) as $line) {
                $this->connection->write(str_starts_with($line, '.') ? '.' . $line : $line);
            }
            $this->expect('.', 250);
            $this->quit();

            return true;
        } catch (SmtpFailed $failed) {
            $this->problem = $failed->getMessage();
            $this->connection->close();

            return false;
        }
    }

    public function lastProblem(): string
    {
        return $this->problem;
    }

    /** Connects and signs in, then says goodbye: what hammer doctor uses. Empty when it worked. */
    public function check(): string
    {
        try {
            $this->signIn();
            $this->quit();

            return '';
        } catch (SmtpFailed $failed) {
            $this->connection->close();

            return $failed->getMessage();
        }
    }

    private function signIn(): void
    {
        $host = $this->config->smtpHost;
        $port = $this->config->smtpPort;
        $this->connection->open($host, $port, self::TIMEOUT_SECONDS);
        $this->reply(220);
        $this->expect('EHLO ' . $this->helo(), 250);
        if ($port !== 465) {
            $this->expect('STARTTLS', 220);
            $this->connection->startTls();
            $this->expect('EHLO ' . $this->helo(), 250);
        }
        $this->expect('AUTH LOGIN', 334);
        $this->expect(base64_encode($this->config->smtpUser), 334);
        $this->connection->write(base64_encode($this->config->smtpPassword));
        $reply = $this->connection->read();
        if ($reply['code'] !== 235) {
            throw new SmtpFailed("{$host} did not accept the sign-in for {$this->config->smtpUser} ({$reply['code']} {$reply['text']}). Check SMTP_USER and SMTP_PASSWORD.");
        }
    }

    private function quit(): void
    {
        try {
            $this->connection->write('QUIT');
            $this->connection->read();
        } catch (SmtpFailed) {
            // Already sent: a server that hangs up on QUIT changes nothing.
        }
        $this->connection->close();
    }

    /** @return list<string> the email as it goes after DATA, a line at a time */
    private function lines(Letter $letter): array
    {
        $message = MimeMessage::of($letter);
        $domain = $this->domain($letter->from);
        $headers = [
            'Date: ' . $this->clock->now()->format(DATE_RFC2822),
            'To: ' . $letter->to,
            'Subject: ' . MimeMessage::subject($letter->subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            ...$message->headers,
        ];
        $body = preg_split('/\r\n|\n|\r/', rtrim($message->body, "\r\n")) ?: [];

        return [...$headers, '', ...$body];
    }

    private function helo(): string
    {
        return $this->domain($this->config->mailFrom !== '' ? $this->config->mailFrom : $this->config->smtpUser);
    }

    private function domain(string $address): string
    {
        $at = strrpos($address, '@');

        return $at === false ? 'localhost' : substr($address, $at + 1);
    }

    /** Sends a line and checks the reply code. */
    private function expect(string $line, int ...$codes): void
    {
        $this->connection->write($line);
        $this->reply(...$codes);
    }

    private function reply(int ...$codes): void
    {
        $reply = $this->connection->read();
        if (! in_array($reply['code'], $codes, true)) {
            throw new SmtpFailed("{$this->config->smtpHost} said {$reply['code']} {$reply['text']}");
        }
    }
}
