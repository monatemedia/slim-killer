<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

/**
 * A real connection to a mail server, through PHP's own streams: no program to run and
 * no library. The server's certificate is checked, as PHP does by default.
 */
final class SocketSmtpConnection implements SmtpConnection
{
    /** @var resource|null */
    private $stream = null;

    public function open(string $host, int $port, int $timeoutSeconds): void
    {
        $address = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true]]);
        $stream = @stream_socket_client($address, $number, $message, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        if ($stream === false) {
            throw new SmtpFailed("Could not reach {$host} on port {$port}: " . ($message !== '' ? $message : 'no answer') . '.');
        }
        stream_set_timeout($stream, $timeoutSeconds);
        $this->stream = $stream;
    }

    public function startTls(): void
    {
        if (@stream_socket_enable_crypto($this->stream(), true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new SmtpFailed('The mail server\'s encryption could not be started (STARTTLS).');
        }
    }

    public function write(string $line): void
    {
        if (@fwrite($this->stream(), $line . "\r\n") === false) {
            throw new SmtpFailed('The connection to the mail server was lost.');
        }
    }

    public function read(): array
    {
        $text = [];
        while (true) {
            $line = @fgets($this->stream(), 1024);
            if ($line === false) {
                throw new SmtpFailed('The mail server stopped answering.');
            }
            $text[] = trim(substr($line, 4));
            if (strlen($line) < 4 || $line[3] !== '-') {
                return ['code' => (int) substr($line, 0, 3), 'text' => implode(' ', $text)];
            }
        }
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    /** @return resource */
    private function stream()
    {
        if ($this->stream === null) {
            throw new SmtpFailed('Not connected to the mail server.');
        }

        return $this->stream;
    }
}
