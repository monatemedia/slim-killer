<?php

declare(strict_types=1);

namespace Tests\Fakes;

use SlimKiller\Mail\SmtpConnection;
use SlimKiller\Mail\SmtpFailed;

/**
 * A mail server that answers from a script and keeps every line it was sent. Each reply
 * in the script is "code text", or a list of lines for a reply over several lines.
 */
final class FakeSmtpConnection implements SmtpConnection
{
    /** @var list<string> */
    public array $written = [];

    public bool $tls = false;

    public bool $closed = false;

    public string $opened = '';

    /** @param list<string|list<string>> $replies */
    public function __construct(
        private array $replies,
        private readonly bool $reachable = true,
    ) {}

    /** A server that accepts everything, on port 465. */
    public static function accepting(): self
    {
        return new self([
            '220 smtp.example.com ESMTP',
            ['250-smtp.example.com', '250-AUTH LOGIN PLAIN', '250 8BITMIME'],
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 Authentication successful',
            '250 OK',
            '250 OK',
            '354 End data with <CR><LF>.<CR><LF>',
            '250 OK queued',
            '221 Bye',
        ]);
    }

    public function open(string $host, int $port, int $timeoutSeconds): void
    {
        if (! $this->reachable) {
            throw new SmtpFailed("Could not reach {$host} on port {$port}: Connection refused.");
        }
        $this->opened = $host . ':' . $port;
    }

    public function startTls(): void
    {
        $this->tls = true;
    }

    public function write(string $line): void
    {
        $this->written[] = $line;
    }

    public function read(): array
    {
        $reply = array_shift($this->replies) ?? throw new SmtpFailed('The mail server stopped answering.');
        $lines = is_array($reply) ? $reply : [$reply];
        $last = (string) end($lines);

        return ['code' => (int) substr($last, 0, 3), 'text' => implode(' ', array_map(static fn(string $line): string => substr($line, 4), $lines))];
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
