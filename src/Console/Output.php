<?php

declare(strict_types=1);

namespace SlimKiller\Console;

/**
 * Writes to a stream (STDOUT in real use, php://memory in tests), with optional colour.
 */
final class Output
{
    private const array COLOURS = [
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'red' => "\033[31m",
        'cyan' => "\033[36m",
        'grey' => "\033[90m",
        'bold' => "\033[1m",
        // Monate Media orange (#f99314), for the logo.
        'orange' => "\033[38;2;249;147;20m",
    ];

    private const string RESET = "\033[0m";

    /** @var resource */
    private $stream;

    private bool $colour;

    /** @param resource $stream */
    public function __construct($stream, ?bool $colour = null)
    {
        $this->stream = $stream;
        $this->colour = $colour ?? (function_exists('stream_isatty') && @stream_isatty($stream) && getenv('NO_COLOR') === false);
    }

    public function line(string $text = '', ?string $colour = null): void
    {
        fwrite($this->stream, $this->paint($text, $colour) . PHP_EOL);
    }

    public function paint(string $text, ?string $colour): string
    {
        if ($colour === null || ! $this->colour || ! isset(self::COLOURS[$colour])) {
            return $text;
        }

        return self::COLOURS[$colour] . $text . self::RESET;
    }
}
