<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * One conversation with a mail server, a line at a time. The real one is a socket
 * (SocketSmtpConnection); tests use a scripted fake.
 */
interface SmtpConnection
{
    /**
     * Connects, encrypted: at once on port 465, or with STARTTLS on any other port,
     * which the mailer asks for with startTls().
     *
     * @throws SmtpFailed
     */
    public function open(string $host, int $port, int $timeoutSeconds): void;

    /** @throws SmtpFailed */
    public function startTls(): void;

    /** @throws SmtpFailed */
    public function write(string $line): void;

    /**
     * The server's next reply, which may run over several lines ("250-…" then "250 …").
     *
     * @return array{code: int, text: string}
     *
     * @throws SmtpFailed
     */
    public function read(): array;

    public function close(): void;
}
